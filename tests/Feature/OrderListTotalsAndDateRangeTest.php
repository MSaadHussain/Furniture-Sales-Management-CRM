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

    public function test_a_cancelled_order_is_not_counted_alongside_live_ones(): void
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

    /**
     * The KPI bar used to apply `countable()` on top of the filter, and
     * `revenueValues()` is only `delivered`. So filtering the list by any other
     * status left the whole bar reading zero while the table showed rows.
     */
    public function test_filtering_by_a_status_makes_the_bar_describe_those_orders(): void
    {
        $this->order(2, null, ['order_status' => OrderStatus::New]);
        $this->order(3, null, ['order_status' => OrderStatus::New]);
        $this->order(9, null, ['order_status' => OrderStatus::Delivered]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index', ['order_status' => 'new']))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(2, (int) $totals->orders);
        $this->assertSame(5, (int) $totals->no_of_orders);
        $this->assertSame(2, (int) $totals->customers);
    }

    public function test_filtering_by_cancelled_counts_the_cancelled_orders(): void
    {
        $this->order(4, null, ['order_status' => OrderStatus::Cancelled]);
        $this->order(7, null, ['order_status' => OrderStatus::Delivered]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index', ['order_status' => 'cancelled']))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(1, (int) $totals->orders);
        $this->assertSame(4, (int) $totals->no_of_orders);
    }

    /**
     * Unfiltered, the bar describes the list. It used to count delivered orders
     * only, so a list full of pending orders sat under an "Orders Matched"
     * figure that matched none of them.
     */
    public function test_without_a_status_filter_the_bar_covers_every_live_order(): void
    {
        $this->order(4, null, ['order_status' => OrderStatus::New]);
        $this->order(7, null, ['order_status' => OrderStatus::Delivered]);
        $this->order(2, null, ['order_status' => OrderStatus::OutForDelivery]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(3, (int) $totals->orders);
        $this->assertSame(13, (int) $totals->no_of_orders);
        $this->assertSame(3, (int) $totals->customers);
    }

    /** Only the orders that never happened are dropped, as the caption says. */
    public function test_cancelled_and_returned_stay_out_of_the_unfiltered_bar(): void
    {
        $this->order(5, null, ['order_status' => OrderStatus::New]);
        $this->order(50, null, ['order_status' => OrderStatus::Cancelled]);
        $this->order(60, null, ['order_status' => OrderStatus::Returned]);

        $totals = $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertOk()
            ->viewData('totals');

        $this->assertSame(1, (int) $totals->orders);
        $this->assertSame(5, (int) $totals->no_of_orders);
    }

    public function test_the_unfiltered_bar_matches_the_rows_on_screen(): void
    {
        foreach ([OrderStatus::New, OrderStatus::Processing, OrderStatus::Delivered] as $status) {
            $this->order(1, null, ['order_status' => $status]);
        }

        $response = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk();

        $this->assertSame(
            count($response->viewData('orders')->items()),
            (int) $response->viewData('totals')->orders,
            'The KPI bar should count exactly the orders the table lists.'
        );
    }

    /* ---------------------------------------------------------------------
     | Status filter grouping
     |--------------------------------------------------------------------- */

    /**
     * label() collapses nine statuses into three, so a dropdown built by
     * looping the cases showed "Pending" six times -- and each of those options
     * matched only its own status.
     */
    public function test_the_status_dropdown_offers_each_label_once(): void
    {
        $groups = OrderStatus::filterGroups();

        $this->assertSame(['pending', 'delivered', 'cancelled'], array_keys($groups));
        $this->assertSame(['Pending', 'Delivered', 'Cancelled'], array_column($groups, 'label'));

        foreach (['orders.index', 'reports.sales'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '>Pending<'), "{$route} lists Pending more than once.");
            $this->assertSame(1, substr_count($html, '>Cancelled<'), "{$route} lists Cancelled more than once.");
        }
    }

    public function test_filtering_by_pending_catches_every_status_behind_that_label(): void
    {
        $this->order(1, null, ['order_status' => OrderStatus::New]);
        $this->order(1, null, ['order_status' => OrderStatus::Processing]);
        $this->order(1, null, ['order_status' => OrderStatus::OutForDelivery]);
        $this->order(1, null, ['order_status' => OrderStatus::Delivered]);

        $orders = $this->actingAs($this->admin)
            ->get(route('orders.index', ['order_status' => 'pending']))
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(3, $orders->items());
    }

    public function test_filtering_by_cancelled_also_catches_returned(): void
    {
        $this->order(1, null, ['order_status' => OrderStatus::Cancelled]);
        $this->order(1, null, ['order_status' => OrderStatus::Returned]);
        $this->order(1, null, ['order_status' => OrderStatus::Delivered]);

        $orders = $this->actingAs($this->admin)
            ->get(route('orders.index', ['order_status' => 'cancelled']))
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(2, $orders->items());
    }

    /** An older bookmarked link with a raw status still resolves. */
    public function test_a_raw_status_value_still_filters(): void
    {
        $this->order(1, null, ['order_status' => OrderStatus::Delivered]);
        $this->order(1, null, ['order_status' => OrderStatus::New]);

        $orders = $this->actingAs($this->admin)
            ->get(route('orders.index', ['order_status' => 'new']))
            ->assertOk()
            ->viewData('orders');

        $this->assertCount(1, $orders->items());
    }

    public function test_the_sales_report_no_longer_offers_a_category_filter(): void
    {
        $html = $this->actingAs($this->admin)->get(route('reports.sales'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="category_id"', $html);
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
