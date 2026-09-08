<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated sales intelligence for the dashboard and reports: KPIs with
 * period-over-period growth, sales trends, product/colour/customer and sales
 * person analytics (requirements 16, 18, 22 to 25).
 *
 * Everything here is a grouped SQL aggregate over indexed columns; no report
 * pulls rows into PHP to count them.
 */
class SalesAnalyticsService
{
    public function __construct(private DateRangeService $dates) {}

    /* ---------------------------------------------------------------------
     | KPIs
     |--------------------------------------------------------------------- */

    /**
     * Headline KPI cards for the selected window, each with the same metric for
     * the preceding window so the UI can show growth.
     */
    public function kpis(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$prevFrom, $prevTo] = $this->dates->previous($from, $to);

        $current  = $this->periodTotals($from, $to);
        $previous = $this->periodTotals($prevFrom, $prevTo);

        return [
            'orders'            => $current['orders'],
            'orders_growth'     => DateRangeService::growth($current['orders'], $previous['orders']),
            'revenue'           => $current['revenue'],
            'revenue_growth'    => DateRangeService::growth($current['revenue'], $previous['revenue']),
            'avg_order_value'   => $current['orders'] > 0 ? round($current['revenue'] / $current['orders'], 2) : 0.0,
            'items_sold'        => $current['items'],
            'new_customers'     => $this->newCustomers($from, $to),
            'new_customers_growth' => DateRangeService::growth(
                $this->newCustomers($from, $to),
                $this->newCustomers($prevFrom, $prevTo),
            ),
            'total_orders'      => Order::countable()->count(),
            'total_revenue'     => (float) Order::countable()->sum('grand_total'),
            'total_customers'   => Customer::count(),
            'cancelled'         => Order::whereBetween('order_created_at', [$from, $to])
                ->where('order_status', OrderStatus::Cancelled->value)->count(),
            'outstanding_balance' => (float) Order::countable()->sum('balance_due'),
        ];
    }

    /** @return array{orders:int,revenue:float,items:int} */
    public function periodTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = Order::query()
            ->countable()
            ->createdBetween($from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.order_status', OrderStatus::revenueValues())
            ->whereBetween('orders.order_created_at', [$from, $to])
            ->sum('order_items.quantity');

        return [
            'orders'  => (int) ($row->orders ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'items'   => (int) $items,
        ];
    }

    public function newCustomers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Customer::whereBetween('created_at', [$from, $to])->count();
    }

    /* ---------------------------------------------------------------------
     | Trends (requirements 18)
     |--------------------------------------------------------------------- */

    /**
     * Orders and revenue bucketed by day, week or month.
     *
     * @return array{labels:array<int,string>,orders:array<int,int>,revenue:array<int,float>}
     */
    public function trend(CarbonImmutable $from, CarbonImmutable $to, ?string $granularity = null): array
    {
        $granularity ??= $this->autoGranularity($from, $to);

        [$format, $step] = match ($granularity) {
            'month' => ['%Y-%m', 'month'],
            'week'  => ['%x-W%v', 'week'],
            default => ['%Y-%m-%d', 'day'],
        };

        $rows = Order::query()
            ->countable()
            ->createdBetween($from, $to)
            ->selectRaw("DATE_FORMAT(order_created_at, ?) as bucket, COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue", [$format])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $labels = [];
        $orders = [];
        $revenue = [];

        $cursor = match ($step) {
            'month' => $from->startOfMonth(),
            'week'  => $from->startOfWeek(),
            default => $from->startOfDay(),
        };

        // Walk the whole window so gaps render as zeroes rather than vanishing.
        while ($cursor->lte($to)) {
            $key = match ($step) {
                'month' => $cursor->format('Y-m'),
                'week'  => $cursor->format('o-\WW'),
                default => $cursor->format('Y-m-d'),
            };

            $row = $rows->get($key);

            $labels[]  = match ($step) {
                'month' => $cursor->format('M Y'),
                'week'  => $cursor->format('d M'),
                default => $cursor->format('d M'),
            };
            $orders[]  = (int) ($row->orders ?? 0);
            $revenue[] = (float) ($row->revenue ?? 0);

            $cursor = match ($step) {
                'month' => $cursor->addMonthNoOverflow(),
                'week'  => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return ['labels' => $labels, 'orders' => $orders, 'revenue' => $revenue, 'granularity' => $granularity];
    }

    private function autoGranularity(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $days = $from->diffInDays($to) + 1;

        return match (true) {
            $days <= 62  => 'day',
            $days <= 210 => 'week',
            default      => 'month',
        };
    }

    /* ---------------------------------------------------------------------
     | Product analytics (requirements 22)
     |--------------------------------------------------------------------- */

    /**
     * Top products by quantity or revenue, grouped on the snapshotted item name
     * so discontinued products still appear in historical reports.
     */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10, string $by = 'quantity'): Collection
    {
        return $this->itemQuery($from, $to)
            ->selectRaw('order_items.item_name_snapshot as name')
            ->selectRaw('MAX(order_items.category_name_snapshot) as category')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders')
            ->selectRaw('AVG(order_items.unit_price) as avg_price')
            ->groupBy('order_items.item_name_snapshot')
            ->orderByDesc($by === 'revenue' ? 'revenue' : 'quantity')
            ->limit($limit)
            ->get();
    }

    public function categoryPerformance(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        return $this->itemQuery($from, $to)
            ->whereNotNull('order_items.category_name_snapshot')
            ->selectRaw('order_items.category_name_snapshot as name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders')
            ->groupBy('order_items.category_name_snapshot')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /* ---------------------------------------------------------------------
     | Colour analytics (requirements 23)
     |--------------------------------------------------------------------- */

    public function colourDemand(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        return $this->itemQuery($from, $to)
            ->whereNotNull('order_items.item_colour')
            ->where('order_items.item_colour', '<>', '')
            ->selectRaw('order_items.item_colour as name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders')
            ->groupBy('order_items.item_colour')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    /* ---------------------------------------------------------------------
     | Sales person analytics (requirements 24)
     |--------------------------------------------------------------------- */

    public function salesPersonPerformance(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->countable()
            ->createdBetween($from, $to)
            ->join('users', 'users.id', '=', 'orders.sales_person_id')
            ->selectRaw('users.id, users.name')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(orders.grand_total), 0) as revenue')
            ->selectRaw('COALESCE(AVG(orders.grand_total), 0) as avg_order')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * Fulfilment outcomes per sales person: how many of the orders they wrote
     * completed, and how many fell through.
     *
     * Unlike salesPersonPerformance(), this counts EVERY order including
     * cancelled and returned ones, because the whole point is the ratio
     * between them.
     *
     * @return Collection<int,array<string,mixed>> keyed by sales person id
     */
    public function salesPersonFulfilment(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->createdBetween($from, $to)
            ->whereNotNull('orders.sales_person_id')
            ->selectRaw('orders.sales_person_id as id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(orders.order_status = ?) as delivered', [OrderStatus::Delivered->value])
            ->selectRaw('SUM(orders.order_status = ?) as cancelled', [OrderStatus::Cancelled->value])
            ->selectRaw('SUM(orders.order_status = ?) as returned', [OrderStatus::Returned->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.order_status = ? THEN orders.grand_total END), 0) as delivered_value', [OrderStatus::Delivered->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN orders.order_status = ? THEN orders.grand_total END), 0) as cancelled_value', [OrderStatus::Cancelled->value])
            ->groupBy('orders.sales_person_id')
            ->get()
            ->keyBy('id')
            ->map(fn ($row) => self::fulfilmentRow(
                (int) $row->total,
                (int) $row->delivered,
                (int) $row->cancelled,
                (int) $row->returned,
                (float) $row->delivered_value,
                (float) $row->cancelled_value,
            ));
    }

    /**
     * Shapes one fulfilment row and derives its ratios.
     *
     * `success_rate` deliberately ignores orders that are still in progress —
     * an order that has not resolved yet is neither a win nor a loss, and
     * counting it as a loss would punish a seller for recent orders.
     */
    public static function fulfilmentRow(
        int $total,
        int $delivered,
        int $cancelled,
        int $returned = 0,
        float $deliveredValue = 0,
        float $cancelledValue = 0,
    ): array {
        $lost    = $cancelled + $returned;
        $settled = $delivered + $lost;

        return [
            'total'           => $total,
            'delivered'       => $delivered,
            'cancelled'       => $cancelled,
            'returned'        => $returned,
            'lost'            => $lost,
            'settled'         => $settled,
            'in_progress'     => max(0, $total - $settled),
            'delivered_value' => $deliveredValue,
            'cancelled_value' => $cancelledValue,
            // Of the orders that reached an outcome, how many completed.
            'success_rate'    => $settled > 0 ? round(($delivered / $settled) * 100, 1) : null,
            // Cancellations as a share of everything they wrote.
            'cancel_rate'     => $total > 0 ? round(($lost / $total) * 100, 1) : null,
            // Delivered-per-cancelled, e.g. 4.0 means 4 delivered for each lost.
            'ratio'           => $lost > 0 ? round($delivered / $lost, 1) : null,
        ];
    }

    /* ---------------------------------------------------------------------
     | Customer analytics (requirements 25)
     |--------------------------------------------------------------------- */

    /** @return array{total:int,new:int,returning:int,avg_orders:float,avg_spend:float} */
    public function customerStats(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = Order::query()
            ->countable()
            ->selectRaw('COUNT(DISTINCT customer_id) as buyers, COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue')
            ->first();

        $buyers = (int) ($totals->buyers ?? 0);

        $returning = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('order_status', OrderStatus::revenueValues())
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            'total'      => Customer::count(),
            'new'        => $this->newCustomers($from, $to),
            'buyers'     => $buyers,
            'returning'  => $returning,
            'avg_orders' => $buyers > 0 ? round(((int) $totals->orders) / $buyers, 2) : 0.0,
            'avg_spend'  => $buyers > 0 ? round(((float) $totals->revenue) / $buyers, 2) : 0.0,
        ];
    }

    public function topCustomers(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        return Order::query()
            ->countable()
            ->createdBetween($from, $to)
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->selectRaw('customers.id, customers.name, customers.zip_code, customers.phone')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(orders.grand_total), 0) as revenue')
            ->selectRaw('MAX(orders.order_created_at) as last_order_at')
            ->groupBy('customers.id', 'customers.name', 'customers.zip_code', 'customers.phone')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /* ---------------------------------------------------------------------
     | Shared
     |--------------------------------------------------------------------- */

    /** Order items joined to their revenue-counting orders within a window. */
    private function itemQuery(CarbonImmutable $from, CarbonImmutable $to)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.order_status', OrderStatus::revenueValues())
            ->whereBetween('orders.order_created_at', [$from, $to]);
    }

    /** Order counts grouped by status, for the dashboard status breakdown. */
    public function statusBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Order::query()
            ->createdBetween($from, $to)
            ->selectRaw('order_status, COUNT(*) as total')
            ->groupBy('order_status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->order_status->value => (int) $row->total]);

        $out = [];
        foreach (OrderStatus::cases() as $status) {
            $out[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $out;
    }
}
