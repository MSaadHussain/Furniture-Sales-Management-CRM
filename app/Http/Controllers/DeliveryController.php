<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Services\DeliveryService;
use App\Services\Export\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;

/**
 * Delivery operations (requirements 14 and 35). The board defaults to today,
 * which is what Admin and Manager should land on after logging in.
 */
class DeliveryController extends Controller implements HasMiddleware
{
    public function __construct(private DeliveryService $deliveries) {}

    public static function middleware(): array
    {
        return ['can:manage-deliveries'];
    }

    /** The daily delivery board for a chosen date, defaulting to today. */
    public function index(Request $request)
    {
        $date = $this->resolveDate($request);

        $orders = $this->deliveries->ordersForDate($date)
            ->when($request->filled('order_status'), fn ($q) => $q->where('order_status', $request->query('order_status')))
            ->paginate(25)
            ->withQueryString();

        return view('deliveries.index', [
            'date'     => $date,
            'summary'  => $this->deliveries->daySummary($date),
            'orders'   => $orders,
            'statuses' => OrderStatus::cases(),
            'overdue'  => $this->deliveries->overdueCount(),
            'filters'  => $request->only(['date', 'order_status']),
        ]);
    }

    /** Month calendar with a delivery count under every date. */
    public function calendar(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
            : today()->startOfMonth();

        $counts = $this->deliveries->monthCounts((int) $month->format('Y'), (int) $month->format('m'));

        // Pad the grid so the first of the month lands under the right weekday.
        $firstWeekday = (int) $month->copy()->startOfMonth()->dayOfWeekIso;

        return view('deliveries.calendar', [
            'month'        => $month,
            'counts'       => $counts,
            'leadingBlank' => $firstWeekday - 1,
            'daysInMonth'  => (int) $month->daysInMonth,
            'monthTotal'   => (int) $counts->sum('total'),
            'monthValue'   => (float) $counts->sum('value'),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export-data');

        $date  = $this->resolveDate($request);
        $query = $this->deliveries->ordersForDate($date);

        $headers = [
            'Order Number', 'Customer', 'Phone', 'ZIP', 'City', 'Items',
            'Sales Person', 'Requested Delivery', 'Actual Delivery', 'Performance',
            'Order Status', 'Payment Status', 'Total',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $order) {
                yield [
                    $order->order_number,
                    $order->customer?->name,
                    $order->customer?->phone,
                    $order->zip_code,
                    $order->customer?->city,
                    $order->itemSummary(3),
                    $order->salesPerson?->name,
                    $order->requested_delivery_date?->format('Y-m-d'),
                    $order->actual_delivery_date?->format('Y-m-d'),
                    $order->deliveryPerformance()->label(),
                    $order->order_status->label(),
                    $order->payment_status->label(),
                    $order->grand_total,
                ];
            }
        };

        return TabularExport::download(
            'deliveries-' . $date->format('Y-m-d'),
            $headers,
            $rows(),
            $request->query('format', 'csv'),
        );
    }

    private function resolveDate(Request $request): Carbon
    {
        return $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : today();
    }
}
