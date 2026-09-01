<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery operations: the daily delivery board (requirements 14.1, 35), the
 * delivery calendar (14.3) and on-time performance (15).
 */
class DeliveryService
{
    /**
     * Headline numbers for a single delivery date: how many are scheduled and
     * how they break down by status.
     *
     * @return array{date:Carbon,scheduled:int,value:float,by_status:array<string,int>,delivered:int,outstanding:int}
     */
    public function daySummary(CarbonInterface $date): array
    {
        $rows = Order::query()
            ->forDeliveryDate($date)
            ->selectRaw('order_status, COUNT(*) as total, SUM(grand_total) as value')
            ->groupBy('order_status')
            ->get();

        $byStatus = [];
        foreach (OrderStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }

        $scheduled = 0;
        $value     = 0.0;

        foreach ($rows as $row) {
            $byStatus[$row->order_status->value] = (int) $row->total;
            $scheduled += (int) $row->total;

            if ($row->order_status->countsAsRevenue()) {
                $value += (float) $row->value;
            }
        }

        $delivered = $byStatus[OrderStatus::Delivered->value] ?? 0;

        return [
            'date'        => Carbon::parse($date),
            'scheduled'   => $scheduled,
            'value'       => $value,
            'by_status'   => $byStatus,
            'delivered'   => $delivered,
            // Still to go out today, excluding cancelled and returned.
            'outstanding' => $scheduled
                - $delivered
                - ($byStatus[OrderStatus::Cancelled->value] ?? 0)
                - ($byStatus[OrderStatus::Returned->value] ?? 0),
        ];
    }

    /** The orders scheduled for a given date, ready for the operations table. */
    public function ordersForDate(CarbonInterface $date)
    {
        return Order::query()
            ->with(['customer', 'salesPerson', 'items'])
            ->forDeliveryDate($date)
            ->orderByRaw("FIELD(order_status, 'out_for_delivery', 'ready_for_delivery', 'processing', 'confirmed', 'new', 'delayed', 'delivered', 'returned', 'cancelled')")
            ->orderBy('order_number');
    }

    /**
     * Scheduled-delivery counts for every date in a month, keyed by Y-m-d, so
     * the calendar can render a number under each day.
     *
     * @return Collection<string,array{total:int,delivered:int,pending:int,value:float}>
     */
    public function monthCounts(int $year, int $month): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return Order::query()
            ->whereBetween('requested_delivery_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('requested_delivery_date as day')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(order_status = ?) as delivered', [OrderStatus::Delivered->value])
            ->selectRaw('SUM(CASE WHEN order_status IN (?, ?) THEN 0 ELSE grand_total END) as value', [
                OrderStatus::Cancelled->value,
                OrderStatus::Returned->value,
            ])
            ->groupBy('requested_delivery_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString())
            ->map(fn ($row) => [
                'total'     => (int) $row->total,
                'delivered' => (int) $row->delivered,
                'pending'   => (int) $row->total - (int) $row->delivered,
                'value'     => (float) $row->value,
            ]);
    }

    /**
     * On-time delivery rate over a window of actual deliveries.
     *
     * @return array{delivered:int,on_time:int,late:int,rate:?float,avg_days_late:float}
     */
    public function performance(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = Order::query()
            ->whereNotNull('actual_delivery_date')
            ->whereBetween('actual_delivery_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as delivered')
            ->selectRaw('SUM(actual_delivery_date <= requested_delivery_date) as on_time')
            ->selectRaw('AVG(GREATEST(DATEDIFF(actual_delivery_date, requested_delivery_date), 0)) as avg_days_late')
            ->first();

        $delivered = (int) ($row->delivered ?? 0);
        $onTime    = (int) ($row->on_time ?? 0);

        return [
            'delivered'     => $delivered,
            'on_time'       => $onTime,
            'late'          => $delivered - $onTime,
            'rate'          => $delivered > 0 ? round(($onTime / $delivered) * 100, 1) : null,
            'avg_days_late' => round((float) ($row->avg_days_late ?? 0), 1),
        ];
    }

    /** Open orders whose requested delivery date has already passed. */
    public function overdueCount(): int
    {
        return Order::query()
            ->open()
            ->whereNull('actual_delivery_date')
            ->whereDate('requested_delivery_date', '<', today())
            ->count();
    }

    /** Open orders scheduled from today onwards. */
    public function pendingCount(): int
    {
        return Order::query()
            ->open()
            ->whereNull('actual_delivery_date')
            ->count();
    }

    /**
     * Upcoming delivery load for the next N days, used by the dashboard strip.
     *
     * @return array<int,array{date:Carbon,total:int}>
     */
    public function upcoming(int $days = 7): array
    {
        $start = today();
        $end   = today()->addDays($days - 1);

        $counts = Order::query()
            ->open()
            ->whereBetween('requested_delivery_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('requested_delivery_date as day, COUNT(*) as total')
            ->groupBy('requested_delivery_date')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->day)->toDateString() => (int) $row->total]);

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $out[] = [
                'date'  => $day,
                'total' => (int) ($counts[$day->toDateString()] ?? 0),
            ];
        }

        return $out;
    }
}
