<?php

namespace App\Http\Controllers;

use App\Services\DateRangeService;
use App\Services\DeliveryService;
use App\Services\SalesAnalyticsService;
use App\Services\ZipAnalyticsService;
use Illuminate\Http\Request;

/**
 * Two dashboards behind one route (requirements 36):
 *  - Admin/Manager get the full management view.
 *  - Sales Persons get a restricted, read-only summary with no customer data.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DateRangeService $dates,
        private SalesAnalyticsService $sales,
        private ZipAnalyticsService $zips,
        private DeliveryService $deliveries,
    ) {}

    public function index(Request $request)
    {
        [$from, $to, $label, $preset] = $this->dates->resolve($request);

        return $request->user()->isSalesPerson()
            ? $this->salesPersonDashboard($request, $from, $to, $label, $preset)
            : $this->managementDashboard($request, $from, $to, $label, $preset);
    }

    private function managementDashboard(Request $request, $from, $to, string $label, string $preset)
    {
        $today = today();

        return view('dashboard.index', [
            'range'        => compact('from', 'to', 'label', 'preset'),
            'presets'      => DateRangeService::presets(),
            'kpis'         => $this->sales->kpis($from, $to),
            'trend'        => $this->sales->trend($from, $to),
            'today'        => $this->deliveries->daySummary($today),
            'upcoming'     => $this->deliveries->upcoming(7),
            'overdue'      => $this->deliveries->overdueCount(),
            'pending'      => $this->deliveries->pendingCount(),
            'performance'  => $this->deliveries->performance($from, $to),
            'topZips'      => $this->zips->ranking($from, $to, 'orders', 6),
            'topProducts'  => $this->sales->topProducts($from, $to, 6, 'quantity'),
            'colours'      => $this->sales->colourDemand($from, $to, 6),
            'swatches'     => $this->swatches(),
            'salesPersons' => $this->sales->salesPersonPerformance($from, $to)->take(6),
            'insights'     => $this->zips->insights($from, $to, $this->sales),
            'statusMix'    => $this->sales->statusBreakdown($from, $to),
        ]);
    }

    /** Colour name to hex map, so charts and swatches avoid a lookup per row. */
    private function swatches(): array
    {
        return \App\Models\Colour::pluck('hex', 'name')
            ->map(fn ($hex) => $hex ?: '#98A2B3')
            ->all();
    }

    /**
     * The restricted dashboard: business-level totals and trend only. No
     * customer records, no ZIP detail, no per-person performance
     * (requirements 36 / 45 / 56).
     */
    private function salesPersonDashboard(Request $request, $from, $to, string $label, string $preset)
    {
        $kpis = $this->sales->kpis($from, $to);
        $myPerf = $this->sales->salesPersonPerformance($from, $to)
            ->firstWhere('id', $request->user()->id);

        return view('dashboard.sales-person', [
            'range'       => compact('from', 'to', 'label', 'preset'),
            'presets'     => DateRangeService::presets(),
            'kpis'        => $kpis,
            'trend'       => $this->sales->trend($from, $to),
            'topProducts' => $this->sales->topProducts($from, $to, 8, 'quantity'),
            'colours'     => $this->sales->colourDemand($from, $to, 6),
            'swatches'    => $this->swatches(),
            // The signed-in user is allowed to see their own numbers for the selected period.
            'mine'        => $myPerf ?: (object) [
                'id'        => $request->user()->id,
                'name'      => $request->user()->name,
                'orders'    => 0,
                'revenue'   => 0.0,
                'avg_order' => 0.0,
            ],
        ]);
    }
}
