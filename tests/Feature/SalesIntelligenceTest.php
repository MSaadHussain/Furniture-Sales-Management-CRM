<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\DateRangeService;
use App\Services\SalesAnalyticsService;
use App\Services\ZipAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard and report aggregates: ZIP intelligence, product and colour
 * demand, sales person performance and growth comparisons
 * (requirements 19 to 26, 43).
 */
class SalesIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** Builds an order with one item, dated and located as given. */
    private function sale(string $zip, string $createdAt, string $product, string $colour, int $qty, float $price, ?User $seller = null): Order
    {
        $category = Category::firstOrCreate(['name' => 'Sofa'], ['slug' => 'sofa', 'is_active' => true]);
        $colourModel = Colour::firstOrCreate(['name' => $colour], ['hex' => '#8E8E93', 'is_active' => true]);
        $productModel = Product::firstOrCreate(
            ['name' => $product],
            ['product_code' => strtoupper(substr(md5($product), 0, 8)), 'category_id' => $category->id, 'default_price' => $price, 'is_active' => true],
        );

        $customer = Customer::factory()->create(['zip_code' => $zip]);
        $total    = $qty * $price;

        $order = Order::factory()->create([
            'customer_id'      => $customer->id,
            'sales_person_id'  => $seller?->id,
            'zip_code'         => $zip,
            'order_created_at' => $createdAt,
            'subtotal'         => $total,
            'grand_total'      => $total,
            'order_status'     => OrderStatus::Delivered->value,
        ]);

        OrderItem::create([
            'order_id'               => $order->id,
            'product_id'             => $productModel->id,
            'colour_id'              => $colourModel->id,
            'item_name_snapshot'     => $product,
            'item_colour'            => $colour,
            'category_name_snapshot' => $category->name,
            'quantity'               => $qty,
            'unit_price'             => $price,
            'discount'               => 0,
            'line_total'             => $total,
        ]);

        return $order;
    }

    private function thisMonth(): array
    {
        return [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth()];
    }

    /* ----------------------------------------------------------------- */

    public function test_zip_ranking_orders_areas_by_volume_and_computes_share(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $this->sale('54000', $today, 'Coffee Table', 'Walnut', 1, 35000);
        $this->sale('54000', $today, 'Dining Chair', 'Black', 6, 8000);
        $this->sale('44000', $today, '3-Seater Sofa', 'Grey', 1, 85000);

        [$from, $to] = $this->thisMonth();
        $ranking = app(ZipAnalyticsService::class)->ranking($from, $to, 'orders');

        $this->assertSame('54000', $ranking->first()['zip_code']);
        $this->assertSame(3, $ranking->first()['orders']);
        $this->assertSame(75.0, $ranking->first()['order_share']);
        $this->assertSame(25.0, $ranking->last()['order_share']);
    }

    public function test_zip_ranking_can_be_sorted_by_revenue(): void
    {
        $today = today()->toDateString();

        // Two cheap orders in one area, one expensive order in another.
        $this->sale('11111', $today, 'Dining Chair', 'Black', 1, 8000);
        $this->sale('11111', $today, 'Dining Chair', 'Black', 1, 8000);
        $this->sale('22222', $today, 'King Size Bed', 'Walnut', 1, 120000);

        [$from, $to] = $this->thisMonth();
        $service = app(ZipAnalyticsService::class);

        $this->assertSame('11111', $service->ranking($from, $to, 'orders')->first()['zip_code']);
        $this->assertSame('22222', $service->ranking($from, $to, 'revenue')->first()['zip_code']);
    }

    public function test_cancelled_orders_are_excluded_from_revenue_analytics(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $cancelled = $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $cancelled->update(['order_status' => OrderStatus::Cancelled->value]);

        [$from, $to] = $this->thisMonth();
        $totals = app(SalesAnalyticsService::class)->periodTotals($from, $to);

        $this->assertSame(1, $totals['orders']);
        $this->assertEquals(85000.0, $totals['revenue']);
    }

    public function test_growth_compares_against_the_previous_period_of_equal_length(): void
    {
        // Window: the last 5 days. Its baseline is the 5 days before that.
        $current  = CarbonImmutable::today();
        $previous = $current->subDays(7);

        $this->sale('54000', $current->toDateString(), '3-Seater Sofa', 'Grey', 1, 100000);
        $this->sale('54000', $current->toDateString(), '3-Seater Sofa', 'Grey', 1, 100000);
        $this->sale('54000', $previous->toDateString(), '3-Seater Sofa', 'Grey', 1, 100000);

        $from = $current->subDays(4);
        $to   = $current;

        $ranking = app(ZipAnalyticsService::class)->ranking($from, $to, 'orders');
        $row     = $ranking->firstWhere('zip_code', '54000');

        $this->assertSame(2, $row['orders']);
        $this->assertSame(1, $row['prev_orders']);
        $this->assertSame(100.0, $row['order_growth']);
    }

    public function test_growth_reads_as_not_available_when_there_is_no_baseline(): void
    {
        $this->assertNull(DateRangeService::growth(10, 0));
        $this->assertSame('N/A', DateRangeService::growthLabel(null));
        $this->assertSame('+50.0%', DateRangeService::growthLabel(50.0));
        $this->assertSame('-25.0%', DateRangeService::growthLabel(-25.0));
    }

    public function test_top_products_are_ranked_by_quantity_and_by_revenue(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, 'Dining Chair', 'Black', 12, 8000);   // 96,000
        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 2, 85000);   // 170,000

        [$from, $to] = $this->thisMonth();
        $service = app(SalesAnalyticsService::class);

        $this->assertSame('Dining Chair', $service->topProducts($from, $to, 5, 'quantity')->first()->name);
        $this->assertSame('3-Seater Sofa', $service->topProducts($from, $to, 5, 'revenue')->first()->name);
    }

    public function test_colour_demand_is_aggregated_from_item_level_colours(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, 'Dining Chair', 'Grey', 6, 8000);
        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $this->sale('54000', $today, 'Coffee Table', 'Walnut', 2, 35000);

        [$from, $to] = $this->thisMonth();
        $colours = app(SalesAnalyticsService::class)->colourDemand($from, $to);

        $this->assertSame('Grey', $colours->first()->name);
        $this->assertSame(7, (int) $colours->first()->quantity);
    }

    public function test_sales_person_performance_is_grouped_per_seller(): void
    {
        $ali   = User::factory()->salesPerson()->create(['name' => 'Ali']);
        $ahmed = User::factory()->salesPerson()->create(['name' => 'Ahmed']);
        $today = today()->toDateString();

        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 100000, $ali);
        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 100000, $ali);
        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 60000, $ahmed);

        [$from, $to] = $this->thisMonth();
        $rows = app(SalesAnalyticsService::class)->salesPersonPerformance($from, $to);

        $this->assertSame('Ali', $rows->first()->name);
        $this->assertSame(2, (int) $rows->first()->orders);
        $this->assertEquals(200000.0, (float) $rows->first()->revenue);
        $this->assertEquals(100000.0, (float) $rows->first()->avg_order);
    }

    public function test_returning_customers_are_identified(): void
    {
        $customer = Customer::factory()->create(['zip_code' => '54000']);

        Order::factory()->count(2)->create([
            'customer_id'  => $customer->id,
            'order_status' => OrderStatus::Delivered->value,
        ]);

        $this->assertTrue($customer->fresh()->isReturning());
        $this->assertSame(2, $customer->fresh()->orderCount());

        [$from, $to] = $this->thisMonth();
        $stats = app(SalesAnalyticsService::class)->customerStats($from, $to);

        $this->assertSame(1, $stats['returning']);
    }

    public function test_the_product_mix_for_one_zip_can_be_drilled_into(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, 'Dining Chair', 'Black', 6, 8000);
        $this->sale('44000', $today, '3-Seater Sofa', 'Grey', 1, 85000);

        [$from, $to] = $this->thisMonth();
        $mix = app(ZipAnalyticsService::class)->productMixForZip('54000', $from, $to);

        $this->assertCount(1, $mix);
        $this->assertSame('Dining Chair', $mix->first()->name);
    }

    public function test_marketing_insights_name_the_leading_area(): void
    {
        $today = today()->toDateString();

        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $this->sale('54000', $today, '3-Seater Sofa', 'Grey', 1, 85000);
        $this->sale('44000', $today, 'Coffee Table', 'Walnut', 1, 35000);

        [$from, $to] = $this->thisMonth();
        $insights = app(ZipAnalyticsService::class)->insights($from, $to, app(SalesAnalyticsService::class));

        $this->assertNotEmpty($insights);
        $this->assertStringContainsString('54000', $insights[0]['title']);
    }

    /* ----------------------------------------------------------------- */
    /* Report screens                                                     */
    /* ----------------------------------------------------------------- */

    public function test_every_report_screen_renders(): void
    {
        $this->sale('54000', today()->toDateString(), '3-Seater Sofa', 'Grey', 1, 85000);

        foreach (['sales', 'products', 'customers', 'zip', 'sales-persons', 'deliveries'] as $report) {
            $this->actingAs($this->admin)
                ->get(route('reports.' . $report))
                ->assertOk();
        }
    }

    public function test_every_report_exports_to_csv_and_xlsx(): void
    {
        $this->sale('54000', today()->toDateString(), '3-Seater Sofa', 'Grey', 1, 85000);

        foreach (['sales', 'products', 'customers', 'zip', 'sales-persons', 'deliveries'] as $report) {
            $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'format' => 'csv']))
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');

            $this->actingAs($this->admin)
                ->get(route('reports.export', ['report' => $report, 'format' => 'xlsx']))
                ->assertOk();
        }
    }

    public function test_the_zip_export_contains_no_customer_level_data(): void
    {
        $this->sale('54000', today()->toDateString(), '3-Seater Sofa', 'Grey', 1, 85000);
        $customer = Customer::where('zip_code', '54000')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'zip', 'format' => 'csv']));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('54000', $csv);
        $this->assertStringNotContainsString($customer->name, $csv);
        $this->assertStringNotContainsString($customer->phone, $csv);
    }

    public function test_the_dashboard_renders_with_data(): void
    {
        $this->sale('54000', today()->toDateString(), '3-Seater Sofa', 'Grey', 1, 85000);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('54000')
            ->assertSee('3-Seater Sofa');
    }

    public function test_the_dashboard_honours_the_date_range_filter(): void
    {
        $this->sale('54000', today()->subMonths(6)->toDateString(), 'Old Sofa', 'Grey', 1, 85000);
        $this->sale('44000', today()->toDateString(), 'New Sofa', 'Grey', 1, 85000);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertSee('New Sofa')
            ->assertDontSee('Old Sofa');
    }
}
