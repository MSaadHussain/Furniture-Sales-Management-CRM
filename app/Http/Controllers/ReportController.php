<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Services\DateRangeService;
use App\Services\DeliveryService;
use App\Services\Export\TabularExport;
use App\Services\SalesAnalyticsService;
use App\Services\ZipAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * The six reports required by section 26. Each one shares the same date-range
 * filter and each exports to CSV or XLSX.
 */
class ReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private DateRangeService $dates,
        private SalesAnalyticsService $sales,
        private ZipAnalyticsService $zips,
        private DeliveryService $deliveries,
    ) {}

    public static function middleware(): array
    {
        return ['can:view-reports'];
    }

    /* ---------------------------------------------------------------------
     | 26.1 Sales report
     |--------------------------------------------------------------------- */

    public function sales(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        $orders = $this->salesQuery($request, $from, $to)
            ->with(['customer', 'salesPerson'])
            ->latest('order_created_at')
            ->paginate(25)
            ->withQueryString();

        $totals = $this->salesQuery($request, $from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(balance_due), 0) as outstanding')
            ->first();

        return view('reports.sales', $this->shared($from, $to, $label, $preset) + [
            'orders'       => $orders,
            'totals'       => $totals,
            'trend'        => $this->sales->trend($from, $to),
            'salesPersons' => User::selectableSalesPersons()->get(['id', 'name']),
            'categories'   => Category::orderBy('name')->get(['id', 'name']),
            'filters'      => $request->only(['sales_person_id', 'category_id', 'zip_code', 'order_status', 'payment_status', 'product']),
        ]);
    }

    /* ---------------------------------------------------------------------
     | 26.2 Product report
     |--------------------------------------------------------------------- */

    public function products(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        return view('reports.products', $this->shared($from, $to, $label, $preset) + [
            'byQuantity' => $this->sales->topProducts($from, $to, 50, 'quantity'),
            'byRevenue'  => $this->sales->topProducts($from, $to, 15, 'revenue'),
            'categories' => $this->sales->categoryPerformance($from, $to, 15),
            'colours'    => $this->sales->colourDemand($from, $to, 15),
        ]);
    }

    /* ---------------------------------------------------------------------
     | 26.3 Customer report
     |--------------------------------------------------------------------- */

    public function customers(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        return view('reports.customers', $this->shared($from, $to, $label, $preset) + [
            'stats'        => $this->sales->customerStats($from, $to),
            'top'          => $this->sales->topCustomers($from, $to, 50),
            'distribution' => $this->zips->customerDistribution(15),
        ]);
    }

    /* ---------------------------------------------------------------------
     | 26.4 ZIP code report
     |--------------------------------------------------------------------- */

    public function zip(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        $sort    = $request->query('sort', 'orders');
        $ranking = $this->zips->ranking($from, $to, $sort, 100);

        // Drill-down: what sells in the ZIP the user clicked.
        $focus    = $request->query('zip');
        $focusMix = $focus ? $this->zips->productMixForZip($focus, $from, $to) : collect();

        return view('reports.zip', $this->shared($from, $to, $label, $preset) + [
            'ranking'  => $ranking,
            'sort'     => $sort,
            'sorts'    => ZipAnalyticsService::SORTS,
            'insights' => $this->zips->insights($from, $to, $this->sales),
            'quiet'    => $this->zips->quietAreas($from, $to)->take(10),
            'focus'    => $focus,
            'focusMix' => $focusMix,
        ]);
    }

    /* ---------------------------------------------------------------------
     | 26.5 Sales person report
     |--------------------------------------------------------------------- */

    public function salesPersons(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        $activeSellers = User::where('role', \App\Enums\UserRole::SalesPerson->value)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $performance = $this->sales->salesPersonPerformance($from, $to)->keyBy('id');

        // Include all active sales persons so the comparison board covers the full team
        $rows = $activeSellers->map(function ($user) use ($performance) {
            $perf = $performance->get($user->id);
            return (object) [
                'id'        => $user->id,
                'name'      => $user->name,
                'orders'    => (int) ($perf->orders ?? 0),
                'revenue'   => (float) ($perf->revenue ?? 0),
                'avg_order' => (float) ($perf->avg_order ?? 0),
            ];
        });

        // Also append any historical attributed sellers who closed orders in this window
        foreach ($performance as $perf) {
            if (! $rows->contains('id', $perf->id)) {
                $rows->push($perf);
            }
        }

        $rows = $rows->sortByDesc('revenue')->values();

        $totalRevenue = (float) $rows->sum('revenue');
        $totalOrders  = (int) $rows->sum('orders');
        $topEarner    = $rows->first(fn ($r) => $r->revenue > 0);
        $topCloser    = $rows->sortByDesc('orders')->first(fn ($r) => $r->orders > 0);
        $topTicket    = $rows->sortByDesc('avg_order')->first(fn ($r) => $r->avg_order > 0);

        return view('reports.sales-persons', $this->shared($from, $to, $label, $preset) + [
            'rows'         => $rows,
            'total'        => $totalRevenue,
            'totalRevenue' => $totalRevenue,
            'totalOrders'  => $totalOrders,
            'topEarner'    => $topEarner,
            'topCloser'    => $topCloser,
            'topTicket'    => $topTicket,
        ]);
    }

    /* ---------------------------------------------------------------------
     | 26.6 Delivery report
     |--------------------------------------------------------------------- */

    public function deliveries(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        $orders = $this->deliveryQuery($request, $from, $to)
            ->with(['customer', 'salesPerson'])
            ->orderBy('requested_delivery_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('reports.deliveries', $this->shared($from, $to, $label, $preset) + [
            'orders'      => $orders,
            'performance' => $this->deliveries->performance($from, $to),
            'overdue'     => $this->deliveries->overdueCount(),
            'filters'     => $request->only(['performance', 'order_status']),
            'statuses'    => OrderStatus::cases(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Exports
     |--------------------------------------------------------------------- */

    public function export(Request $request, string $report)
    {
        $this->authorize('export-data');

        [$from, $to] = $this->dates->resolve($request);
        $format = $request->query('format', 'csv');
        $stamp  = now()->format('Y-m-d');

        return match ($report) {
            'sales'         => $this->exportSales($request, $from, $to, $format, $stamp),
            'products'      => $this->exportRows(
                'product-report-' . $stamp,
                ['Product', 'Category', 'Quantity Sold', 'Revenue', 'Average Price', 'Orders Containing Product'],
                $this->sales->topProducts($from, $to, 1000, 'quantity')->map(fn ($r) => [
                    $r->name, $r->category, $r->quantity, $r->revenue, round((float) $r->avg_price, 2), $r->orders,
                ]),
                $format,
            ),
            'customers'     => $this->exportRows(
                'customer-report-' . $stamp,
                ['Customer', 'Phone', 'ZIP', 'Orders', 'Total Spend', 'Last Order'],
                $this->sales->topCustomers($from, $to, 5000)->map(fn ($r) => [
                    $r->name, $r->phone, $r->zip_code, $r->orders, $r->revenue,
                    $r->last_order_at ? \Illuminate\Support\Carbon::parse($r->last_order_at)->format('Y-m-d') : null,
                ]),
                $format,
            ),
            // Aggregate only: no customer-level rows leave the building (44/45).
            'zip'           => $this->exportRows(
                'zip-report-' . $stamp,
                ['ZIP', 'Customers', 'Orders', 'Revenue', 'Average Order Value', 'Share of Orders %', 'Previous Orders', 'Order Growth %'],
                $this->zips->ranking($from, $to, $request->query('sort', 'orders'), 1000)->map(fn ($r) => [
                    $r['zip_code'], $r['customers'], $r['orders'], $r['revenue'], $r['avg_order'],
                    $r['order_share'], $r['prev_orders'], DateRangeService::growthLabel($r['order_growth']),
                ]),
                $format,
            ),
            'sales-persons' => $this->exportRows(
                'sales-person-report-' . $stamp,
                ['Sales Person', 'Orders', 'Revenue', 'Average Order Value'],
                $this->sales->salesPersonPerformance($from, $to)->map(fn ($r) => [
                    $r->name, $r->orders, $r->revenue, round((float) $r->avg_order, 2),
                ]),
                $format,
            ),
            'deliveries'    => $this->exportDeliveries($request, $from, $to, $format, $stamp),
            default         => abort(404),
        };
    }

    private function exportSales(Request $request, $from, $to, string $format, string $stamp)
    {
        $query = $this->salesQuery($request, $from, $to)->with(['customer', 'salesPerson'])->latest('order_created_at');

        $headers = [
            'Order Number', 'Creation Date', 'Requested Delivery Date', 'Actual Delivery Date',
            'Customer', 'ZIP', 'Sales Person', 'Total', 'Payment Status', 'Order Status',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $o) {
                yield [
                    $o->order_number,
                    $o->order_created_at?->format('Y-m-d H:i'),
                    $o->requested_delivery_date?->format('Y-m-d'),
                    $o->actual_delivery_date?->format('Y-m-d'),
                    $o->customer?->name,
                    $o->zip_code,
                    $o->salesPerson?->name,
                    $o->grand_total,
                    $o->payment_status->label(),
                    $o->order_status->label(),
                ];
            }
        };

        return TabularExport::download('sales-report-' . $stamp, $headers, $rows(), $format);
    }

    private function exportDeliveries(Request $request, $from, $to, string $format, string $stamp)
    {
        $query = $this->deliveryQuery($request, $from, $to)->with(['customer'])->orderBy('requested_delivery_date');

        $headers = [
            'Requested Delivery Date', 'Actual Delivery Date', 'Order Number',
            'Customer', 'ZIP', 'Order Status', 'On Time / Late', 'Days Late', 'Total',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $o) {
                yield [
                    $o->requested_delivery_date?->format('Y-m-d'),
                    $o->actual_delivery_date?->format('Y-m-d'),
                    $o->order_number,
                    $o->customer?->name,
                    $o->zip_code,
                    $o->order_status->label(),
                    $o->deliveryPerformance()->label(),
                    $o->daysLate(),
                    $o->grand_total,
                ];
            }
        };

        return TabularExport::download('delivery-report-' . $stamp, $headers, $rows(), $format);
    }

    /** @param \Illuminate\Support\Collection<int,array> $rows */
    private function exportRows(string $name, array $headers, $rows, string $format)
    {
        return TabularExport::download($name, $headers, $rows->all(), $format);
    }

    /* ---------------------------------------------------------------------
     | Shared query building
     |--------------------------------------------------------------------- */

    private function salesQuery(Request $request, $from, $to)
    {
        return Order::query()
            ->createdBetween($from, $to)
            ->when($request->filled('sales_person_id'), fn ($q) => $q->where('sales_person_id', $request->integer('sales_person_id')))
            ->when($request->filled('zip_code'), fn ($q) => $q->where('zip_code', 'like', $request->query('zip_code') . '%'))
            ->when($request->filled('order_status'), fn ($q) => $q->where('order_status', $request->query('order_status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->query('payment_status')))
            ->when($request->filled('product'), fn ($q) => $q->whereHas('items', fn ($i) => $i->where('item_name_snapshot', 'like', '%' . $request->query('product') . '%')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('items.product', fn ($p) => $p->where('category_id', $request->integer('category_id'))));
    }

    /** The delivery report windows on the requested delivery date, not creation. */
    private function deliveryQuery(Request $request, $from, $to)
    {
        return Order::query()
            ->whereBetween('requested_delivery_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('order_status'), fn ($q) => $q->where('order_status', $request->query('order_status')))
            ->when($request->query('performance') === 'on_time', fn ($q) => $q->whereNotNull('actual_delivery_date')->whereColumn('actual_delivery_date', '<=', 'requested_delivery_date'))
            ->when($request->query('performance') === 'late', fn ($q) => $q->whereNotNull('actual_delivery_date')->whereColumn('actual_delivery_date', '>', 'requested_delivery_date'))
            ->when($request->query('performance') === 'pending', fn ($q) => $q->whereNull('actual_delivery_date'));
    }

    private function shared($from, $to, string $label, string $preset): array
    {
        return [
            'range'           => compact('from', 'to', 'label', 'preset'),
            'presets'         => DateRangeService::presets(),
            'orderStatuses'   => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ];
    }
}
