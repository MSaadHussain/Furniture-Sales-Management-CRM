<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function orderDue(string $date, OrderStatus $status = OrderStatus::ReadyForDelivery): Order
    {
        return Order::factory()->create([
            'customer_id'             => Customer::factory(),
            'requested_delivery_date' => $date,
            'order_status'            => $status->value,
        ]);
    }

    public function test_the_delivery_board_defaults_to_today(): void
    {
        $today    = $this->orderDue(today()->toDateString());
        $tomorrow = $this->orderDue(today()->addDay()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee($today->order_number)
            ->assertDontSee($tomorrow->order_number);
    }

    public function test_another_delivery_date_can_be_selected(): void
    {
        $tomorrow = $this->orderDue(today()->addDay()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('deliveries.index', ['date' => today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee($tomorrow->order_number);
    }

    public function test_the_day_summary_counts_by_status_and_excludes_cancelled_value(): void
    {
        $this->orderDue(today()->toDateString(), OrderStatus::ReadyForDelivery);
        $this->orderDue(today()->toDateString(), OrderStatus::ReadyForDelivery);
        $this->orderDue(today()->toDateString(), OrderStatus::OutForDelivery);
        $this->orderDue(today()->toDateString(), OrderStatus::Delivered);
        $cancelled = $this->orderDue(today()->toDateString(), OrderStatus::Cancelled);

        $summary = app(DeliveryService::class)->daySummary(today());

        $this->assertSame(5, $summary['scheduled']);
        $this->assertSame(2, $summary['by_status'][OrderStatus::ReadyForDelivery->value]);
        $this->assertSame(1, $summary['by_status'][OrderStatus::OutForDelivery->value]);
        $this->assertSame(1, $summary['delivered']);
        // Delivered, ready x2 and out-for-delivery remain; cancelled is excluded.
        $this->assertSame(3, $summary['outstanding']);
        $this->assertLessThan(
            (float) Order::sum('grand_total'),
            $summary['value'],
            'Cancelled orders must not count towards the day value.',
        );
        $this->assertNotNull($cancelled);
    }

    public function test_the_calendar_shows_a_count_for_each_date(): void
    {
        $this->orderDue(today()->toDateString());
        $this->orderDue(today()->toDateString());
        $this->orderDue(today()->addDays(3)->toDateString());

        $counts = app(DeliveryService::class)->monthCounts(
            (int) today()->format('Y'),
            (int) today()->format('m'),
        );

        $this->assertSame(2, $counts[today()->toDateString()]['total']);

        $this->actingAs($this->admin)
            ->get(route('deliveries.calendar'))
            ->assertOk()
            ->assertSee(today()->format('F Y'));
    }

    public function test_on_time_and_late_deliveries_are_classified_correctly(): void
    {
        $onTime = Order::factory()->create([
            'customer_id'             => Customer::factory(),
            'requested_delivery_date' => today()->subDays(5),
        ])->fresh();
        $onTime->update(['actual_delivery_date' => today()->subDays(6), 'order_status' => OrderStatus::Delivered->value]);

        $exact = Order::factory()->create([
            'customer_id'             => Customer::factory(),
            'requested_delivery_date' => today()->subDays(4),
        ])->fresh();
        $exact->update(['actual_delivery_date' => today()->subDays(4), 'order_status' => OrderStatus::Delivered->value]);

        $late = Order::factory()->create([
            'customer_id'             => Customer::factory(),
            'requested_delivery_date' => today()->subDays(3),
        ])->fresh();
        $late->update(['actual_delivery_date' => today()->subDay(), 'order_status' => OrderStatus::Delivered->value]);

        $pending = $this->orderDue(today()->addDay()->toDateString());

        $this->assertSame('On Time', $onTime->fresh()->deliveryPerformance()->label());
        // Delivered exactly on the requested date still counts as on time.
        $this->assertSame('On Time', $exact->fresh()->deliveryPerformance()->label());
        $this->assertSame('Late', $late->fresh()->deliveryPerformance()->label());
        $this->assertSame(2, $late->fresh()->daysLate());
        $this->assertSame('Pending', $pending->deliveryPerformance()->label());
    }

    public function test_the_on_time_rate_is_calculated_over_completed_deliveries(): void
    {
        foreach ([-6, -5, -4] as $offset) {
            $order = Order::factory()->create([
                'customer_id'             => Customer::factory(),
                'requested_delivery_date' => today()->addDays($offset),
            ]);
            $order->update(['actual_delivery_date' => today()->addDays($offset), 'order_status' => OrderStatus::Delivered->value]);
        }

        $lateOrder = Order::factory()->create([
            'customer_id'             => Customer::factory(),
            'requested_delivery_date' => today()->subDays(10),
        ]);
        $lateOrder->update(['actual_delivery_date' => today()->subDays(8), 'order_status' => OrderStatus::Delivered->value]);

        $performance = app(DeliveryService::class)->performance(
            \Carbon\CarbonImmutable::today()->subDays(30),
            \Carbon\CarbonImmutable::today(),
        );

        $this->assertSame(4, $performance['delivered']);
        $this->assertSame(3, $performance['on_time']);
        $this->assertSame(1, $performance['late']);
        $this->assertSame(75.0, $performance['rate']);
    }

    public function test_the_on_time_rate_is_null_when_nothing_has_been_delivered(): void
    {
        $performance = app(DeliveryService::class)->performance(
            \Carbon\CarbonImmutable::today()->subDays(30),
            \Carbon\CarbonImmutable::today(),
        );

        $this->assertNull($performance['rate']);
    }

    public function test_overdue_open_orders_are_counted(): void
    {
        $this->orderDue(today()->subDays(3)->toDateString(), OrderStatus::Processing);
        $this->orderDue(today()->subDays(1)->toDateString(), OrderStatus::ReadyForDelivery);
        $this->orderDue(today()->addDay()->toDateString(), OrderStatus::ReadyForDelivery);

        $delivered = $this->orderDue(today()->subDays(5)->toDateString(), OrderStatus::Delivered);
        $delivered->update(['actual_delivery_date' => today()->subDays(5)]);

        $this->assertSame(2, app(DeliveryService::class)->overdueCount());
    }

    public function test_a_delivery_can_be_marked_from_the_board(): void
    {
        $order = $this->orderDue(today()->toDateString());

        $this->actingAs($this->admin)
            ->patch(route('orders.deliver', $order), ['actual_delivery_date' => today()->toDateString()])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->order_status);
        $this->assertTrue($order->actual_delivery_date->isToday());
    }

    public function test_the_delivery_board_can_be_exported(): void
    {
        $this->orderDue(today()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('deliveries.export', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
