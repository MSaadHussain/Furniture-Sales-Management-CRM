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
        $today    = today();
        $tomorrow = today()->addDay();

        return view('dashboard.index', [
            'range'        => compact('from', 'to', 'label', 'preset'),
            'presets'      => DateRangeService::presets(),
            'kpis'         => $this->sales->kpis($from, $to),
            'trend'        => $this->sales->trend($from, $to),
            'today'          => $this->deliveries->daySummary($today),
            'tomorrow'       => $this->deliveries->daySummary($tomorrow),
            'tomorrowOrders' => $this->deliveries->ordersForDate($tomorrow)->get(),
            'todayOrders'    => $this->deliveries->ordersForDate($today)->get(),
            'recentOrders'   => \App\Models\Order::with(['customer', 'salesPerson'])->latest('order_created_at')->take(5)->get(),
            'upcoming'       => $this->deliveries->upcoming(7),
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
     * The Sales Person dashboard:
     *  - Daily sales target progress (how many orders to achieve today).
     *  - Volume stats (Today, This Week, This Month, Items Sold) with NO price or revenue numbers.
     *  - Sales Leaderboard (Daily, Weekly, Monthly) across active sales persons.
     *  - Recent assigned orders (products, statuses, delivery dates) with NO customer PII or price info.
     */
    private function salesPersonDashboard(Request $request, $from, $to, string $label, string $preset)
    {
        $user = $request->user();
        $dailyTarget = max(1, (int) \App\Models\Setting::get('daily_sales_target', 5));

        $todayOrdersCount = \App\Models\Order::query()
            ->where('sales_person_id', $user->id)
            ->whereDate('order_created_at', today())
            ->where('order_status', \App\Enums\OrderStatus::Delivered->value)
            ->count();

        $weekOrdersCount = \App\Models\Order::query()
            ->where('sales_person_id', $user->id)
            ->whereBetween('order_created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('order_status', \App\Enums\OrderStatus::Delivered->value)
            ->count();

        $monthOrdersCount = \App\Models\Order::query()
            ->where('sales_person_id', $user->id)
            ->whereBetween('order_created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('order_status', \App\Enums\OrderStatus::Delivered->value)
            ->count();

        $monthItemsCount = (int) \App\Models\OrderItem::query()
            ->whereHas('order', function ($q) use ($user) {
                $q->where('sales_person_id', $user->id)
                  ->whereBetween('order_created_at', [now()->startOfMonth(), now()->endOfMonth()])
                  ->where('order_status', \App\Enums\OrderStatus::Delivered->value);
            })
            ->sum('quantity');

        $myOrders = \App\Models\Order::query()
            ->where('sales_person_id', $user->id)
            ->with(['items'])
            ->orderByDesc('order_created_at')
            ->take(12)
            ->get();

        $leaderboardToday = $this->getLeaderboard(today()->startOfDay(), today()->endOfDay());
        $leaderboardWeek  = $this->getLeaderboard(now()->startOfWeek(), now()->endOfWeek());
        $leaderboardMonth = $this->getLeaderboard(now()->startOfMonth(), now()->endOfMonth());

        return view('dashboard.sales-person', [
            'user'               => $user,
            'dailyTarget'        => $dailyTarget,
            'todayOrdersCount'   => $todayOrdersCount,
            'targetAchievedPct'  => min(100, (int) round(($todayOrdersCount / $dailyTarget) * 100)),
            'remainingToTarget'  => max(0, $dailyTarget - $todayOrdersCount),
            'weekOrdersCount'    => $weekOrdersCount,
            'monthOrdersCount'   => $monthOrdersCount,
            'monthItemsCount'    => $monthItemsCount,
            'myOrders'           => $myOrders,
            'leaderboardToday'   => $leaderboardToday,
            'leaderboardWeek'    => $leaderboardWeek,
            'leaderboardMonth'   => $leaderboardMonth,
            'topProducts'        => $this->sales->topProducts($from, $to, 6, 'quantity'),
            'colours'            => $this->sales->colourDemand($from, $to, 6),
            'swatches'           => $this->swatches(),
        ]);
    }

    private function getLeaderboard($from, $to)
    {
        return \App\Models\User::query()
            ->where('role', \App\Enums\UserRole::SalesPerson)
            ->where('is_active', true)
            ->leftJoin('orders', function ($join) use ($from, $to) {
                $join->on('orders.sales_person_id', '=', 'users.id')
                    ->where('orders.order_status', '=', \App\Enums\OrderStatus::Delivered->value)
                    ->whereBetween('orders.order_created_at', [$from, $to]);
            })
            ->selectRaw('users.id, users.name, COUNT(orders.id) as orders_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('orders_count')
            ->orderBy('users.name')
            ->get();
    }
}
