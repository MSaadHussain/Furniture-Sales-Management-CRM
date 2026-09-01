<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * ZIP/postal-code sales intelligence (requirements 19, 20, 21, 44).
 *
 * Aggregates orders by the ZIP snapshotted on the order, so an area keeps
 * credit for a sale even if the customer later moves. Everything returned here
 * is aggregate-level, never customer-level, which is what the privacy rule in
 * section 45 asks for.
 */
class ZipAnalyticsService
{
    public const SORTS = [
        'orders'   => 'Most orders',
        'revenue'  => 'Highest revenue',
        'growth'   => 'Fastest growth',
        'avg'      => 'Highest average order value',
    ];

    public function __construct(private DateRangeService $dates) {}

    /**
     * ZIP ranking for a window, each row carrying its share of total orders and
     * its growth against the preceding window of equal length.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function ranking(CarbonImmutable $from, CarbonImmutable $to, string $sort = 'orders', int $limit = 50): Collection
    {
        [$prevFrom, $prevTo] = $this->dates->previous($from, $to);

        $current  = $this->aggregate($from, $to);
        $previous = $this->aggregate($prevFrom, $prevTo)->keyBy('zip_code');

        $totalOrders  = (int) $current->sum('orders');
        $totalRevenue = (float) $current->sum('revenue');

        $rows = $current->map(function ($row) use ($previous, $totalOrders, $totalRevenue) {
            $prior = $previous->get($row->zip_code);

            return [
                'zip_code'       => $row->zip_code,
                'orders'         => (int) $row->orders,
                'revenue'        => (float) $row->revenue,
                'customers'      => (int) $row->customers,
                'avg_order'      => $row->orders > 0 ? round(((float) $row->revenue) / (int) $row->orders, 2) : 0.0,
                'order_share'    => $totalOrders > 0 ? round(((int) $row->orders / $totalOrders) * 100, 1) : 0.0,
                'revenue_share'  => $totalRevenue > 0 ? round(((float) $row->revenue / $totalRevenue) * 100, 1) : 0.0,
                'prev_orders'    => (int) ($prior->orders ?? 0),
                'prev_revenue'   => (float) ($prior->revenue ?? 0),
                'order_growth'   => DateRangeService::growth((int) $row->orders, (int) ($prior->orders ?? 0)),
                'revenue_growth' => DateRangeService::growth((float) $row->revenue, (float) ($prior->revenue ?? 0)),
            ];
        });

        return $this->sort($rows, $sort)->take($limit)->values();
    }

    /** Raw per-ZIP aggregate for a window. */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->countable()
            ->createdBetween($from, $to)
            ->whereNotNull('zip_code')
            ->where('zip_code', '<>', '')
            ->selectRaw('zip_code')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT customer_id) as customers')
            ->groupBy('zip_code')
            ->get();
    }

    private function sort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'revenue' => $rows->sortByDesc('revenue'),
            'avg'     => $rows->sortByDesc('avg_order'),
            // Areas with no prior baseline sort last rather than as infinite growth.
            'growth'  => $rows->sortByDesc(fn ($r) => $r['order_growth'] ?? -INF),
            default   => $rows->sortByDesc('orders'),
        };
    }

    /**
     * Plain-language marketing opportunities for the dashboard (requirements 21
     * and 44). Decision support only: it reports, it never spends.
     *
     * @return array<int,array{icon:string,color:string,title:string,detail:string}>
     */
    public function insights(CarbonImmutable $from, CarbonImmutable $to, SalesAnalyticsService $sales): array
    {
        $insights = [];
        $ranking  = $this->ranking($from, $to, 'orders', 50);

        if ($top = $ranking->first()) {
            $insights[] = [
                'icon'   => 'fa-location-crosshairs',
                'color'  => '#465FFF',
                'title'  => "ZIP {$top['zip_code']} leads on order volume",
                'detail' => "{$top['orders']} orders ({$top['order_share']}% of the period) from {$top['customers']} customers.",
            ];
        }

        // Fastest grower, restricted to areas with a real baseline and enough
        // volume that the percentage means something.
        $grower = $ranking
            ->filter(fn ($r) => $r['order_growth'] !== null && $r['order_growth'] > 0 && $r['orders'] >= 3)
            ->sortByDesc('order_growth')
            ->first();

        if ($grower && (! isset($top) || $grower['zip_code'] !== $top['zip_code'])) {
            $insights[] = [
                'icon'   => 'fa-arrow-trend-up',
                'color'  => '#12B76A',
                'title'  => "ZIP {$grower['zip_code']} is growing fastest",
                'detail' => "Orders up " . DateRangeService::growthLabel($grower['order_growth'])
                    . " against the previous period ({$grower['prev_orders']} to {$grower['orders']}).",
            ];
        }

        // Areas that went quiet: had orders last period, none this one.
        if ($quiet = $this->quietAreas($from, $to)->first()) {
            $insights[] = [
                'icon'   => 'fa-arrow-trend-down',
                'color'  => '#F04438',
                'title'  => "ZIP {$quiet['zip_code']} has gone quiet",
                'detail' => "{$quiet['prev_orders']} orders in the previous period, none in this one. Worth a look.",
            ];
        }

        if ($product = $sales->topProducts($from, $to, 1, 'quantity')->first()) {
            $insights[] = [
                'icon'   => 'fa-fire',
                'color'  => '#F79009',
                'title'  => "{$product->name} is the top seller",
                'detail' => "{$product->quantity} units across {$product->orders} orders.",
            ];
        }

        if ($colour = $sales->colourDemand($from, $to, 1)->first()) {
            $insights[] = [
                'icon'   => 'fa-palette',
                'color'  => '#7A5AF8',
                'title'  => "{$colour->name} is the most requested colour",
                'detail' => "{$colour->quantity} units sold in {$colour->name}. Useful for stock and ad creative.",
            ];
        }

        return $insights;
    }

    /** ZIPs that ordered in the previous window but not in this one. */
    public function quietAreas(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        [$prevFrom, $prevTo] = $this->dates->previous($from, $to);

        $currentZips = $this->aggregate($from, $to)->pluck('zip_code')->all();

        return $this->aggregate($prevFrom, $prevTo)
            ->reject(fn ($row) => in_array($row->zip_code, $currentZips, true))
            ->sortByDesc('orders')
            ->map(fn ($row) => [
                'zip_code'     => $row->zip_code,
                'prev_orders'  => (int) $row->orders,
                'prev_revenue' => (float) $row->revenue,
            ])
            ->values();
    }

    /**
     * What sells in one specific ZIP, for the drill-down panel
     * (requirements 22: product performance by ZIP).
     */
    public function productMixForZip(string $zip, CarbonImmutable $from, CarbonImmutable $to, int $limit = 8): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.order_status', OrderStatus::revenueValues())
            ->whereBetween('orders.order_created_at', [$from, $to])
            ->where('orders.zip_code', $zip)
            ->selectRaw('order_items.item_name_snapshot as name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.item_name_snapshot')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    /** Customer counts per ZIP, independent of any order window. */
    public function customerDistribution(int $limit = 20): Collection
    {
        return Customer::query()
            ->whereNotNull('zip_code')
            ->where('zip_code', '<>', '')
            ->selectRaw('zip_code, COUNT(*) as customers')
            ->groupBy('zip_code')
            ->orderByDesc('customers')
            ->limit($limit)
            ->get();
    }
}
