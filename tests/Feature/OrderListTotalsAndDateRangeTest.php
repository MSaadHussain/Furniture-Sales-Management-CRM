<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\DateRangeService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things that belong together only because they were reported together:
 * the KPI bar on the order list, and the date presets the reports offer.
 */
class OrderListTotalsAndDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = User::factory()->admin()->create();
        $this->seller = User::factory()->salesPerson()->create(['name' => 'Mujeeb']);
    }

    private function order(?int $count, ?Customer $customer = null, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id'      => $customer?->id ?? Customer::factory(),
            'sales_person_id'  => $this->seller->id,
            'number_of_orders' => $count,
            'order_status'     => OrderStatus::Delivered,
        ], $overrides));
    }

    /* ---------------------------------------------------------------------
     | Order list KPI bar
     |--------------------------------------------------------------------- */

    public function test_the_list_totals_the_entered_counter(): void
    {
        $this->order(2);
        $this->order(5);
        $this->order(null);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(7, (int) $totals->no_of_orders);
        $this->assertSame(3, (int) $totals->orders);
    }

    public function test_the_list_counts_distinct_customers_not_orders(): void
    {
        $repeat = Customer::factory()->create();

        $this->order(1, $repeat);
        $this->order(1, $repeat);   // same customer, second order
        $this->order(1);            // someone else

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(3, (int) $totals->orders);
        $this->assertSame(2, (int) $totals->customers);
    }

    public function test_filtering_by_a_seller_narrows_both_figures_to_theirs(): void
    {
        $other = User::factory()->salesPerson()->create(['name' => 'Shamrez']);

        $this->order(3);
        $this->order(4);
        $this->order(99, null, ['sales_person_id' => $other->id]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sales_person_id' => $this->seller->id]))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(7, (int) $totals->no_of_orders);
        $this->assertSame(2, (int) $totals->customers);
    }

    public function test_cancelled_orders_stay_out_of_the_kpi_bar(): void
    {
        $this->order(6);
        $this->order(100, null, ['order_status' => OrderStatus::Cancelled]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(6, (int) $totals->no_of_orders);
        $this->assertSame(1, (int) $totals->customers);
    }

    /* ---------------------------------------------------------------------
     | Date presets
     |--------------------------------------------------------------------- */

    /**
     * The preset buttons were hand-written in the component and their keys had
     * drifted from the service's, so Last 7 days / 3 months / 6 months silently
     * fell back to This month.
     */
    public function test_every_preset_link_on_the_page_uses_a_key_the_service_accepts(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('reports.sales'))
            ->assertOk()
            ->getContent();

        preg_match_all('/[?&]range=([a-z0-9_]+)/i', $html, $matches);

        $offered = array_unique($matches[1]);
        $known   = array_keys(DateRangeService::presets());

        $this->assertNotEmpty($offered, 'The page offered no date presets at all.');

        foreach ($offered as $key) {
            $this->assertContains($key, $known, "The page links to range={$key}, which the service does not know.");
        }

        // The three that were broken must actually be on offer.
        foreach (['last_7', 'last_3', 'last_6'] as $key) {
            $this->assertContains($key, $offered, "The {$key} preset is missing from the page.");
        }
    }

    public static function presetProvider(): array
    {
        return [
            'last 7 days'   => ['last_7', 6,   'Last 7 days'],
            'last 3 months' => ['last_3', 89,  'Last 3 months'],
            'last 6 months' => ['last_6', 180, 'Last 6 months'],
        ];
    }

    #[DataProvider('presetProvider')]
    public function test_the_preset_actually_moves_the_window(string $key, int $minDaysBack, string $label): void
    {
        $range = $this->actingAs($this->admin)
            ->get(route('reports.sales', ['range' => $key]))
            ->assertOk()
            ->viewData('range');

        $this->assertSame($label, $range['label']);
        $this->assertSame($key, $range['preset']);
        $this->assertTrue(
            $range['from']->lte(today()->subDays($minDaysBack)),
            "{$label} should reach at least {$minDaysBack} days back, got {$range['from']->toDateString()}."
        );
    }

    public function test_an_unknown_range_still_falls_back_to_the_default(): void
    {
        $range = $this->actingAs($this->admin)
            ->get(route('reports.sales', ['range' => 'last_7_days']))
            ->assertOk()
            ->viewData('range');

        $this->assertSame(DateRangeService::DEFAULT, $range['preset']);
    }
}
