<?php

namespace Tests\Feature;

use App\Enums\ConfirmationStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirm / Not Confirm / Duplicate, tracked separately from the order status.
 *
 * It is a label on the order and nothing more: no report aggregate, no revenue
 * rule and no delivery rule reads it.
 */
class ConfirmationStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = User::factory()->admin()->create();
        $this->manager = User::factory()->create(['role' => \App\Enums\UserRole::Manager]);
    }

    private function order(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => Customer::factory(),
        ], $overrides));
    }

    /** An order is real unless someone marks it otherwise. */
    public function test_a_new_order_starts_as_confirmed(): void
    {
        $this->assertSame(ConfirmationStatus::Confirmed, $this->order()->confirmation_status);
    }

    public function test_the_three_choices_are_the_ones_asked_for(): void
    {
        $labels = array_map(fn (ConfirmationStatus $c) => $c->label(), ConfirmationStatus::cases());

        $this->assertSame(['Confirm', 'Not Confirm', 'Duplicate'], $labels);
    }

    public function test_it_can_be_changed_in_one_click(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('orders.confirmation', $order), ['confirmation_status' => 'duplicate'])
            ->assertRedirect();

        $this->assertSame(ConfirmationStatus::Duplicate, $order->fresh()->confirmation_status);
    }

    public function test_an_invalid_value_is_refused(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('orders.confirmation', $order), ['confirmation_status' => 'maybe'])
            ->assertSessionHasErrors('confirmation_status');

        $this->assertSame(ConfirmationStatus::Confirmed, $order->fresh()->confirmation_status);
    }

    public function test_the_change_is_written_to_the_audit_log(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('orders.confirmation', $order), ['confirmation_status' => 'confirmed']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'order.confirmation_changed']);
    }

    public function test_a_sales_person_cannot_change_it(): void
    {
        $order  = $this->order();
        $seller = User::factory()->salesPerson()->create();

        $this->actingAs($seller)
            ->patch(route('orders.confirmation', $order), ['confirmation_status' => 'duplicate'])
            ->assertForbidden();

        $this->assertSame(ConfirmationStatus::Confirmed, $order->fresh()->confirmation_status);
    }

    public function test_the_list_can_be_filtered_by_it(): void
    {
        $this->order(['confirmation_status' => ConfirmationStatus::Duplicate]);
        $this->order(['confirmation_status' => ConfirmationStatus::Confirmed]);
        $this->order(['confirmation_status' => ConfirmationStatus::Confirmed]);

        $orders = $this->actingAs($this->admin)
            ->get(route('orders.index', ['confirmation_status' => 'confirmed']))
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(2, $orders->items());
    }

    public function test_the_order_list_no_longer_carries_a_confirmation_column(): void
    {
        $this->order();

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('>Confirmation<', $html);
        // The filter stays: it is how a Duplicate gets found again.
        $this->assertStringContainsString('name="confirmation_status"', $html);
    }

    public function test_it_is_set_from_the_order_detail_page(): void
    {
        $order = $this->order();

        $html = $this->actingAs($this->admin)->get(route('orders.show', $order))->assertOk()->getContent();

        $this->assertStringContainsString('name="confirmation_status"', $html);
        $this->assertStringContainsString(route('orders.confirmation', $order), $html);
    }

    public function test_the_reports_can_filter_on_it(): void
    {
        $this->order(['confirmation_status' => ConfirmationStatus::Duplicate, 'order_created_at' => today()]);
        $this->order(['confirmation_status' => ConfirmationStatus::Confirmed, 'order_created_at' => today()]);

        $orders = $this->actingAs($this->admin)
            ->get(route('reports.sales', ['range' => 'this_month', 'confirmation_status' => 'duplicate']))
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(1, $orders->items());
    }

    public function test_both_report_tables_and_exports_show_it(): void
    {
        $this->order(['confirmation_status' => ConfirmationStatus::Duplicate, 'order_created_at' => today()]);

        foreach (['reports.sales', 'reports.deliveries'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route, ['range' => 'this_month']))->assertOk()->getContent();
            $this->assertStringContainsString('>Confirmation<', $html, "{$route} has no Confirmation column.");
            $this->assertStringContainsString('name="confirmation_status"', $html, "{$route} has no Confirmation filter.");
        }

        foreach (['sales', 'deliveries'] as $report) {
            $csv = $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'range' => 'this_month', 'format' => 'csv']))
                ->assertOk()
                ->streamedContent();

            $this->assertStringContainsString('Confirmation', $csv, "The {$report} export has no Confirmation column.");
        }
    }

    /** It is a label, not a rule: revenue and the order status are untouched. */
    public function test_marking_an_order_duplicate_changes_nothing_else(): void
    {
        $order  = $this->order(['order_status' => OrderStatus::Delivered, 'grand_total' => 5000]);
        $before = $order->grand_total;

        $this->actingAs($this->admin)
            ->patch(route('orders.confirmation', $order), ['confirmation_status' => 'duplicate']);

        $order->refresh();

        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertSame($before, $order->grand_total);
    }
}
