<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\OrderController;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clickable column sorting on the orders list.
 */
class OrderSortingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function order(string $customer, float $total, string $created, ?string $seller = null): Order
    {
        return Order::factory()->create([
            'customer_id'      => Customer::factory()->create(['name' => $customer])->id,
            'sales_person_id'  => $seller ? User::factory()->salesPerson()->create(['name' => $seller])->id : null,
            'grand_total'      => $total,
            'order_created_at' => $created,
        ]);
    }

    /** The customer names in the order the page rendered them. */
    private function renderedOrder(string $html, array $needles): array
    {
        $found = [];
        foreach ($needles as $needle) {
            $pos = strpos($html, $needle);
            if ($pos !== false) {
                $found[$needle] = $pos;
            }
        }
        asort($found);

        return array_keys($found);
    }

    public function test_the_list_defaults_to_newest_first(): void
    {
        $this->order('Alpha', 1000, now()->subDays(2)->toDateTimeString());
        $this->order('Bravo', 2000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertSame(['Bravo', 'Alpha'], $this->renderedOrder($html, ['Alpha', 'Bravo']));
    }

    public function test_total_can_be_sorted_ascending_and_descending(): void
    {
        $this->order('Cheap', 1000, now()->toDateTimeString());
        $this->order('Pricey', 90000, now()->toDateTimeString());

        $asc = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'total', 'direction' => 'asc']))
            ->assertOk()->getContent();
        $this->assertSame(['Cheap', 'Pricey'], $this->renderedOrder($asc, ['Cheap', 'Pricey']));

        $desc = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'total', 'direction' => 'desc']))
            ->assertOk()->getContent();
        $this->assertSame(['Pricey', 'Cheap'], $this->renderedOrder($desc, ['Cheap', 'Pricey']));
    }

    public function test_customer_name_sorts_even_though_it_lives_on_another_table(): void
    {
        $this->order('Zaid Khan', 5000, now()->toDateTimeString());
        $this->order('Aamir Ali', 9000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'customer', 'direction' => 'asc']))
            ->assertOk()->getContent();

        $this->assertSame(['Aamir Ali', 'Zaid Khan'], $this->renderedOrder($html, ['Aamir Ali', 'Zaid Khan']));
    }

    public function test_sales_person_name_sorts_without_dropping_unassigned_orders(): void
    {
        $this->order('WithSeller', 1000, now()->toDateTimeString(), 'Ali Raza');
        $this->order('NoSeller', 2000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'sales_person', 'direction' => 'asc']))
            ->assertOk()->getContent();

        // A left-join style subquery must not filter out the unassigned order.
        $this->assertStringContainsString('WithSeller', $html);
        $this->assertStringContainsString('NoSeller', $html);
    }

    public function test_every_whitelisted_column_sorts_without_error(): void
    {
        $this->order('Alpha', 1000, now()->toDateTimeString(), 'Ali');
        $this->order('Bravo', 2000, now()->subDay()->toDateTimeString());

        foreach (array_keys(OrderController::SORTS) as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $this->actingAs($this->admin)
                    ->get(route('orders.index', ['sort' => $column, 'direction' => $direction]))
                    ->assertOk();
            }
        }
    }

    public function test_an_unknown_sort_column_falls_back_instead_of_erroring(): void
    {
        $this->order('Alpha', 1000, now()->toDateTimeString());

        // A crafted query string must never reach raw SQL.
        $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'grand_total); DROP TABLE orders;--', 'direction' => 'sideways']))
            ->assertOk();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_sorting_keeps_the_active_filters(): void
    {
        $this->order('Keep', 1000, now()->toDateTimeString());
        $skip = $this->order('Skip', 2000, now()->toDateTimeString());
        $skip->update(['order_status' => OrderStatus::Cancelled->value]);

        $html = $this->actingAs($this->admin)
            ->get(route('orders.index', [
                'order_status' => OrderStatus::New->value,
                'sort'         => 'total',
                'direction'    => 'asc',
            ]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Keep', $html);
        $this->assertStringNotContainsString('>Skip<', $html);
    }

    public function test_headers_link_to_the_opposite_direction_of_the_active_column(): void
    {
        $this->order('Alpha', 1000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'total', 'direction' => 'asc']))
            ->assertOk()->getContent();

        // The active column offers the flip; an inactive one offers its default.
        $this->assertStringContainsString('sort=total&amp;direction=desc', $html);
        $this->assertStringContainsString('sort=customer&amp;direction=asc', $html);
    }
    public function test_orders_can_be_sorted_by_creation_date_in_both_directions(): void
    {
        $this->order('Oldest', 1000, now()->subDays(10)->toDateTimeString());
        $this->order('Middle', 2000, now()->subDays(5)->toDateTimeString());
        $this->order('Newest', 3000, now()->toDateTimeString());

        $asc = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'created', 'direction' => 'asc']))
            ->assertOk()->getContent();
        $this->assertSame(
            ['Oldest', 'Middle', 'Newest'],
            $this->renderedOrder($asc, ['Oldest', 'Middle', 'Newest']),
        );

        $desc = $this->actingAs($this->admin)
            ->get(route('orders.index', ['sort' => 'created', 'direction' => 'desc']))
            ->assertOk()->getContent();
        $this->assertSame(
            ['Newest', 'Middle', 'Oldest'],
            $this->renderedOrder($desc, ['Oldest', 'Middle', 'Newest']),
        );
    }

    public function test_the_order_column_header_offers_a_date_created_sort(): void
    {
        $this->order('Alpha', 1000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Date created', $html);
        // Default view is newest-first, so the link offers the flip to oldest-first.
        $this->assertStringContainsString('sort=created&amp;direction=asc', $html);
    }

    public function test_the_mobile_card_list_exposes_a_sort_control(): void
    {
        $this->order('Alpha', 1000, now()->toDateTimeString());

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="mobileSort"', $html);
        $this->assertStringContainsString('Newest first', $html);
        $this->assertStringContainsString('Oldest first', $html);
    }

}
