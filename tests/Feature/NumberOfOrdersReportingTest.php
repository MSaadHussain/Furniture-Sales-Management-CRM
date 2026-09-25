<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The operator-entered "No. of Orders" counter in reporting.
 *
 * It is a reference figure, not a quantity and not the row count: the reports
 * add it up per sales person and for the period, alongside the real number of
 * orders, and the two are deliberately shown as separate columns.
 */
class NumberOfOrdersReportingTest extends TestCase
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

    private function order(?int $count, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id'      => Customer::factory(),
            'sales_person_id'  => $this->seller->id,
            'number_of_orders' => $count,
            'order_created_at' => today(),
            'order_status'     => OrderStatus::Delivered,
        ], $overrides));
    }

    public function test_the_sales_report_totals_the_counter_for_the_period(): void
    {
        $this->order(3);
        $this->order(4);
        $this->order(null);   // never filled in

        $response = $this->actingAs($this->admin)
            ->get(route('reports.sales', ['range' => 'this_month']))
            ->assertOk();

        $this->assertSame(7, (int) $response->viewData('totals')->no_of_orders);
        $this->assertSame(3, (int) $response->viewData('totals')->orders);
    }

    public function test_the_sales_report_lists_the_counter_per_order(): void
    {
        $this->order(9);

        $this->actingAs($this->admin)
            ->get(route('reports.sales', ['range' => 'this_month']))
            ->assertOk()
            ->assertSee('No. of Orders')
            ->assertSee('9');
    }

    public function test_a_sales_person_row_sums_the_counter_across_their_orders(): void
    {
        $other = User::factory()->salesPerson()->create(['name' => 'Shamrez']);

        $this->order(2);
        $this->order(5);
        $this->order(10, ['sales_person_id' => $other->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.sales-persons', ['range' => 'this_month']))
            ->assertOk();

        $rows = $response->viewData('rows')->keyBy('name');

        $this->assertSame(7, (int) $rows['Mujeeb']->no_of_orders);
        $this->assertSame(2, (int) $rows['Mujeeb']->orders);
        $this->assertSame(10, (int) $rows['Shamrez']->no_of_orders);

        // The team total is the sum of every seller's.
        $this->assertSame(17, (int) $response->viewData('totalNoOfOrders'));
    }

    public function test_cancelled_orders_are_left_out_just_like_revenue(): void
    {
        $this->order(6);
        $this->order(100, ['order_status' => OrderStatus::Cancelled]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.sales-persons', ['range' => 'this_month']))
            ->assertOk();

        $this->assertSame(6, (int) $response->viewData('totalNoOfOrders'));
    }

    public function test_a_seller_with_no_orders_reads_zero_rather_than_blank(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.sales-persons', ['range' => 'this_month']))
            ->assertOk();

        $this->assertSame(0, (int) $response->viewData('rows')->firstWhere('name', 'Mujeeb')->no_of_orders);
        $this->assertSame(0, (int) $response->viewData('totalNoOfOrders'));
    }

    /**
     * The ratio table counts every order a seller wrote, so its counter has to
     * do the same -- the leaderboard's figure covers delivered orders only.
     */
    public function test_the_ratio_table_sums_across_all_of_a_sellers_orders(): void
    {
        $this->order(3);                                              // delivered
        $this->order(5, ['order_status' => OrderStatus::New]);         // still open
        $this->order(4, ['order_status' => OrderStatus::Cancelled]);   // lost

        $response = $this->actingAs($this->admin)
            ->get(route('reports.sales-persons', ['range' => 'this_month']))
            ->assertOk();

        $row = $response->viewData('rows')->firstWhere('name', 'Mujeeb');

        // Every order on the row.
        $this->assertSame(3, (int) $row->total);
        $this->assertSame(12, (int) $row->no_of_orders_all);

        // The leaderboard figure stays delivered-only, matching its own column.
        $this->assertSame(3, (int) $row->no_of_orders);

        $this->assertSame(12, (int) $response->viewData('team')['no_of_orders_all']);
    }

    /** Every report that lists or groups orders carries the counter. */
    public function test_it_reaches_the_customer_delivery_and_zip_reports(): void
    {
        $this->order(6, ['zip_code' => '75013', 'requested_delivery_date' => today()]);
        $this->order(4, ['zip_code' => '75013', 'requested_delivery_date' => today()]);

        $customers = $this->actingAs($this->admin)
            ->get(route('reports.customers', ['range' => 'this_month']))
            ->assertOk()
            ->viewData('top');
        $this->assertSame(10, (int) $customers->sum('no_of_orders'));

        $zips = $this->actingAs($this->admin)
            ->get(route('reports.zip', ['range' => 'this_month']))
            ->assertOk()
            ->viewData('ranking');
        $this->assertSame(10, (int) collect($zips)->firstWhere('zip_code', '75013')['no_of_orders']);

        foreach (['reports.customers', 'reports.zip', 'reports.deliveries'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route, ['range' => 'this_month']))->assertOk()->getContent();
            $this->assertStringContainsString('No. of Orders', $html, "{$route} has no No. of Orders column.");
        }
    }

    public function test_every_report_export_carries_the_counter(): void
    {
        $this->order(8, ['zip_code' => '75013', 'requested_delivery_date' => today()]);

        foreach (['customers', 'zip', 'deliveries'] as $report) {
            $csv = $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'range' => 'this_month', 'format' => 'csv']))
                ->assertOk()
                ->streamedContent();

            $this->assertStringContainsString('No. of Orders', $csv, "The {$report} export has no No. of Orders column.");
        }
    }

    public function test_both_exports_carry_the_counter(): void
    {
        $this->order(8);

        $sales = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'sales', 'range' => 'this_month', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('No. of Orders', $sales);

        $people = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'sales-persons', 'range' => 'this_month', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('No. of Orders', $people);
        $this->assertStringContainsString('8', $people);
    }
}
