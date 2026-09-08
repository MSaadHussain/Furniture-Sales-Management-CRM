<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Delivered vs cancelled ratio per sales person on the sales person report.
 */
class SalesPersonRatioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function orders(User $seller, OrderStatus $status, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Order::factory()->create([
                'customer_id'      => Customer::factory(),
                'sales_person_id'  => $seller->id,
                'order_status'     => $status->value,
                'order_created_at' => now(),
                'grand_total'      => 10000,
            ]);
        }
    }

    private function thisMonth(): array
    {
        return [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth()];
    }

    public function test_the_ratio_counts_delivered_against_cancelled(): void
    {
        $seller = User::factory()->salesPerson()->create(['name' => 'Ali']);

        $this->orders($seller, OrderStatus::Delivered, 8);
        $this->orders($seller, OrderStatus::Cancelled, 2);
        $this->orders($seller, OrderStatus::Processing, 5);   // still open

        [$from, $to] = $this->thisMonth();
        $row = app(SalesAnalyticsService::class)->salesPersonFulfilment($from, $to)->get($seller->id);

        $this->assertSame(15, $row['total']);
        $this->assertSame(8, $row['delivered']);
        $this->assertSame(2, $row['cancelled']);
        $this->assertSame(5, $row['in_progress']);

        // 8 delivered of 10 settled = 80%. The 5 open orders must not count.
        $this->assertSame(80.0, $row['success_rate']);
        $this->assertSame(4.0, $row['ratio']);
    }

    public function test_returned_orders_count_as_lost_alongside_cancelled(): void
    {
        $seller = User::factory()->salesPerson()->create();

        $this->orders($seller, OrderStatus::Delivered, 6);
        $this->orders($seller, OrderStatus::Cancelled, 1);
        $this->orders($seller, OrderStatus::Returned, 1);

        [$from, $to] = $this->thisMonth();
        $row = app(SalesAnalyticsService::class)->salesPersonFulfilment($from, $to)->get($seller->id);

        $this->assertSame(1, $row['returned']);
        $this->assertSame(2, $row['lost']);
        $this->assertSame(75.0, $row['success_rate']);
        $this->assertSame(3.0, $row['ratio']);
    }

    public function test_a_seller_with_no_cancellations_has_no_finite_ratio(): void
    {
        $seller = User::factory()->salesPerson()->create();
        $this->orders($seller, OrderStatus::Delivered, 4);

        [$from, $to] = $this->thisMonth();
        $row = app(SalesAnalyticsService::class)->salesPersonFulfilment($from, $to)->get($seller->id);

        $this->assertSame(100.0, $row['success_rate']);
        $this->assertNull($row['ratio'], 'Dividing by zero cancellations must not produce a number.');
    }

    public function test_a_seller_with_nothing_settled_reports_no_rate(): void
    {
        $seller = User::factory()->salesPerson()->create();
        $this->orders($seller, OrderStatus::Processing, 3);

        [$from, $to] = $this->thisMonth();
        $row = app(SalesAnalyticsService::class)->salesPersonFulfilment($from, $to)->get($seller->id);

        $this->assertSame(3, $row['in_progress']);
        $this->assertNull($row['success_rate']);
    }

    public function test_the_report_shows_the_ratio_section(): void
    {
        $ali = User::factory()->salesPerson()->create(['name' => 'Ali Raza']);
        $this->orders($ali, OrderStatus::Delivered, 8);
        $this->orders($ali, OrderStatus::Cancelled, 2);

        $html = $this->actingAs($this->admin)->get(route('reports.sales-persons'))->assertOk()->getContent();

        $this->assertStringContainsString('Delivered vs Cancelled Ratio', $html);
        $this->assertStringContainsString('Team Completion Rate', $html);
        $this->assertStringContainsString('Ali Raza', $html);
        $this->assertStringContainsString('4 : 1', $html);
        $this->assertStringContainsString('80%', $html);
    }

    public function test_the_cancellations_card_stays_blank_when_nobody_has_cancelled(): void
    {
        $clean = User::factory()->salesPerson()->create(['name' => 'Shamrez Khan']);
        $this->orders($clean, OrderStatus::Delivered, 4);

        $html = $this->actingAs($this->admin)->get(route('reports.sales-persons'))->assertOk()->getContent();

        // The seller belongs on the board, but not under "Most Cancellations".
        preg_match('/Most Cancellations.*?<\/div>\s*<\/div>/s', $html, $card);
        $this->assertNotEmpty($card);
        $this->assertStringNotContainsString('Shamrez Khan', $card[0]);
        $this->assertStringContainsString('No cancellations in this period', $card[0]);
    }

    public function test_the_cancellations_card_names_the_seller_who_actually_cancelled(): void
    {
        $clean   = User::factory()->salesPerson()->create(['name' => 'Clean Record']);
        $sloppy  = User::factory()->salesPerson()->create(['name' => 'Sloppy Seller']);

        $this->orders($clean, OrderStatus::Delivered, 5);
        $this->orders($sloppy, OrderStatus::Delivered, 2);
        $this->orders($sloppy, OrderStatus::Cancelled, 3);

        $html = $this->actingAs($this->admin)->get(route('reports.sales-persons'))->assertOk()->getContent();

        preg_match('/Most Cancellations.*?<\/div>\s*<\/div>/s', $html, $card);
        $this->assertStringContainsString('Sloppy Seller', $card[0]);
        $this->assertStringNotContainsString('Clean Record', $card[0]);
    }

    public function test_cancelled_orders_still_stay_out_of_revenue(): void
    {
        $seller = User::factory()->salesPerson()->create();
        $this->orders($seller, OrderStatus::Delivered, 2);
        $this->orders($seller, OrderStatus::Cancelled, 3);

        [$from, $to] = $this->thisMonth();
        $perf = app(SalesAnalyticsService::class)->salesPersonPerformance($from, $to)->firstWhere('id', $seller->id);

        // Revenue counts the 2 delivered only, even though the ratio sees all 5.
        $this->assertSame(2, (int) $perf->orders);
        $this->assertEquals(20000.0, (float) $perf->revenue);
    }

    public function test_the_export_includes_the_ratio_columns(): void
    {
        $seller = User::factory()->salesPerson()->create(['name' => 'Ali Raza']);
        $this->orders($seller, OrderStatus::Delivered, 8);
        $this->orders($seller, OrderStatus::Cancelled, 2);

        $csv = $this->actingAs($this->admin)
            ->get(route('reports.export', ['report' => 'sales-persons', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Delivered:Cancelled Ratio', $csv);
        $this->assertStringContainsString('Completion Rate', $csv);
        $this->assertStringContainsString('4:1', $csv);
        $this->assertStringContainsString('80%', $csv);
    }
}
