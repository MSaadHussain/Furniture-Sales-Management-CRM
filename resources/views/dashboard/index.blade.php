@extends('layouts.app')
@section('title', 'Dashboard')

@section('header_actions')
    @can('manage-orders')
        <a href="{{ route('orders.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Order
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-4">

    <x-date-range :range="$range" :presets="$presets" class="!p-3 sm:!p-3.5" />

    {{-- ============================ Row 1: KPI Cards ============================ --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Orders in period" icon="fa-receipt" icon-color="#465FFF"
                     :value="number_format($kpis['orders'])"
                     :delta="\App\Services\DateRangeService::growthLabel($kpis['orders_growth'])"
                     :delta-up="($kpis['orders_growth'] ?? 0) >= 0"
                     :hint="number_format($kpis['total_orders']) . ' all time'" />

        <x-stat-card label="Revenue in period" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($kpis['revenue'])"
                     :delta="\App\Services\DateRangeService::growthLabel($kpis['revenue_growth'])"
                     :delta-up="($kpis['revenue_growth'] ?? 0) >= 0"
                     :hint="\App\Support\Money::compact($kpis['total_revenue']) . ' all time'" />

        <x-stat-card label="Average order value" icon="fa-scale-balanced" icon-color="#7A5AF8"
                     :value="\App\Support\Money::compact($kpis['avg_order_value'])"
                     :hint="number_format($kpis['items_sold']) . ' items sold'" />

        <x-stat-card label="Customers" icon="fa-users" icon-color="#F79009"
                     :value="number_format($kpis['total_customers'])"
                     :delta="\App\Services\DateRangeService::growthLabel($kpis['new_customers_growth'])"
                     :delta-up="($kpis['new_customers_growth'] ?? 0) >= 0"
                     :hint="number_format($kpis['new_customers']) . ' new this period'"
                     :href="Gate::allows('view-customers') ? route('customers.index') : null" />
    </div>

    {{-- ==================== Row 2: Sales Trend & Operations ========================= --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 items-start">

        {{-- Left 8 cols: Sales Trend + Next 7 Days load --}}
        <div class="xl:col-span-8 space-y-4">
            <x-card padding="p-4 sm:p-5" title="Sales trend" :subtitle="$range['label'] . ' · by ' . $trend['granularity']">
                <div class="h-56">
                    <canvas id="salesTrendChart"></canvas>
                </div>

                {{-- Integrated Next 7 Days Delivery Outlook strip --}}
                <div class="mt-4 pt-3 border-t border-line/60 dark:border-strokedark">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted">Next 7 Days Delivery Schedule</span>
                        <a href="{{ route('deliveries.index') }}" class="text-[11px] font-semibold text-brand hover:underline">Delivery Hub &rarr;</a>
                    </div>
                    <div class="grid grid-cols-7 gap-1.5 text-center">
                        @foreach ($upcoming as $day)
                            <a href="{{ route('deliveries.index', ['date' => $day['date']->toDateString()]) }}"
                               class="rounded-lg border border-line/80 py-1.5 px-1 transition hover:border-brand hover:bg-brand/5 dark:border-strokedark {{ $day['date']->isToday() ? 'border-brand bg-brand/10 font-bold' : '' }}">
                                <p class="text-[10px] uppercase text-muted leading-tight">{{ $day['date']->format('D') }}</p>
                                <p class="text-[11px] font-semibold text-ink dark:text-gray-300 leading-tight">{{ $day['date']->format('d') }}</p>
                                <p class="mt-1 text-sm font-bold {{ $day['total'] > 0 ? 'text-brand' : 'text-muted' }} leading-none">{{ $day['total'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Right 4 cols: Today's Operations & Delivery Performance Cockpit --}}
        <div class="xl:col-span-4 space-y-4">
            <x-card padding="p-4 sm:p-5">
                <div class="flex items-center justify-between border-b border-line/60 pb-3 mb-3 dark:border-strokedark">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-muted">Today's Deliveries</span>
                        <p class="text-xs font-medium text-ink dark:text-gray-300">{{ $today['date']->format('d M Y') }}</p>
                    </div>
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/10 text-brand">
                        <i class="fa-solid fa-truck text-sm"></i>
                    </span>
                </div>

                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="text-3xl font-extrabold text-ink dark:text-white">{{ $today['scheduled'] }}</span>
                        <span class="text-xs text-muted ml-1">orders</span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold text-brand"><x-money :amount="$today['value']" compact /></span>
                        <span class="block text-[10px] text-muted">cargo value</span>
                    </div>
                </div>

                {{-- Status breakdown for today --}}
                <div class="mt-3 space-y-1.5">
                    @foreach (\App\Enums\OrderStatus::cases() as $status)
                        @continue(($today['by_status'][$status->value] ?? 0) === 0)
                        <a href="{{ route('deliveries.index', ['order_status' => $status->value]) }}"
                           class="flex items-center justify-between rounded-lg px-2 py-1 text-xs transition hover:bg-surface dark:hover:bg-boxdark">
                            <span class="flex items-center gap-1.5 text-ink dark:text-gray-300">
                                <i class="fa-solid {{ $status->icon() }} text-[10px]" style="color: {{ $status->color() }}"></i>
                                {{ $status->label() }}
                            </span>
                            <span class="font-bold text-ink dark:text-white">{{ $today['by_status'][$status->value] }}</span>
                        </a>
                    @endforeach

                    @if ($today['scheduled'] === 0)
                        <p class="py-2 text-center text-xs text-muted">No deliveries scheduled for today.</p>
                    @endif
                </div>

                {{-- Overdue Warning Alert --}}
                @if ($overdue > 0)
                    <a href="{{ route('reports.deliveries', ['performance' => 'pending']) }}"
                       class="mt-3 flex items-center gap-2 rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger font-medium">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                        <span><strong>{{ $overdue }}</strong> overdue delivery target{{ $overdue === 1 ? '' : 's' }}</span>
                    </a>
                @endif

                {{-- On-Time Performance Summary --}}
                <div class="mt-3 pt-3 border-t border-line/60 dark:border-strokedark">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted font-medium">On-time Delivery Rate</span>
                        <span class="font-bold text-ink dark:text-white">{{ $performance['rate'] === null ? 'N/A' : $performance['rate'] . '%' }}</span>
                    </div>
                    <div class="mt-1.5 grid grid-cols-3 gap-1 text-center text-[11px]">
                        <div class="rounded bg-success/10 py-1 text-success font-semibold">
                            <span>{{ $performance['on_time'] }}</span> <span class="text-[9px] block font-normal">On-time</span>
                        </div>
                        <div class="rounded bg-danger/10 py-1 text-danger font-semibold">
                            <span>{{ $performance['late'] }}</span> <span class="text-[9px] block font-normal">Late</span>
                        </div>
                        <div class="rounded bg-surface py-1 text-muted font-semibold dark:bg-boxdark2">
                            <span>{{ $pending }}</span> <span class="text-[9px] block font-normal">Pending</span>
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    {{-- ================= Row 3: High-Density Tri-Grid (ZIPs, Products/Colours, Sales Team) ================= --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 items-start">

        {{-- 1. Top ZIP / Postal Areas --}}
        <x-card padding="p-4" title="Top Postal Areas" :subtitle="'By order volume · ' . $range['label']">
            <x-slot:actions>
                <a href="{{ route('reports.zip') }}" class="text-xs font-semibold text-brand hover:underline">Full &rarr;</a>
            </x-slot:actions>

            @if ($topZips->isEmpty())
                <p class="py-6 text-center text-xs text-muted">No orders in this period.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($topZips->take(5) as $zip)
                        <div class="flex items-center justify-between text-xs">
                            <div class="min-w-0">
                                <a href="{{ route('reports.zip', ['zip' => $zip['zip_code']]) }}" class="font-bold text-ink hover:text-brand dark:text-white">
                                    {{ $zip['zip_code'] }}
                                </a>
                                <span class="text-[11px] text-muted block">{{ $zip['customers'] }} customer(s)</span>
                            </div>
                            <div class="text-right flex items-center gap-3">
                                <div class="hidden sm:block w-16">
                                    <div class="h-1.5 w-full rounded-full bg-line dark:bg-strokedark overflow-hidden">
                                        <div class="h-full bg-brand rounded-full" style="width: {{ min(100, $zip['order_share']) }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-muted">{{ $zip['order_share'] }}% share</span>
                                </div>
                                <div>
                                    <span class="font-bold text-ink dark:text-white"><x-money :amount="$zip['revenue']" compact /></span>
                                    <span class="text-[10px] text-muted block">{{ $zip['orders'] }} order(s)</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- 2. Top Products & Popular Colours (Stacked Clean Card) --}}
        <x-card padding="p-4" title="Products & Colours" subtitle="Catalogue demand in period">
            <div class="space-y-3">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1.5">Top Selling Items</span>
                    @forelse ($topProducts->take(3) as $product)
                        <div class="mb-1.5 flex items-center justify-between text-xs last:mb-0">
                            <span class="truncate text-ink dark:text-gray-300 pr-2 font-medium" title="{{ $product->name }}">{{ $product->name }}</span>
                            <span class="font-bold text-ink dark:text-white flex-shrink-0">{{ number_format($product->quantity) }} sold</span>
                        </div>
                    @empty
                        <p class="text-xs text-muted">No product sales in this period.</p>
                    @endforelse
                </div>

                <div class="pt-2.5 border-t border-line/60 dark:border-strokedark">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1.5">Popular Colour Finishes</span>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse ($colours->take(5) as $colour)
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface/50 px-2 py-1 text-[11px] font-medium text-ink dark:border-strokedark dark:bg-boxdark2 dark:text-gray-300">
                                <span class="h-2.5 w-2.5 rounded-full border border-black/10"
                                      style="background-color: {{ $swatches[$colour->name] ?? '#98A2B3' }}"></span>
                                <span>{{ $colour->name }}</span>
                                <strong class="text-brand ml-0.5">({{ $colour->quantity }})</strong>
                            </span>
                        @empty
                            <p class="text-xs text-muted">No colour data in this period.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-card>

        {{-- 3. Sales Team & Marketing Highlights --}}
        <x-card padding="p-4" title="Team & Intelligence" subtitle="Attribution & opportunities">
            <div class="space-y-3">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1.5">Sales Person Leaderboard</span>
                    @forelse ($salesPersons->take(3) as $person)
                        <div class="mb-1.5 flex items-center justify-between text-xs last:mb-0">
                            <span class="truncate text-ink dark:text-gray-300 font-medium">{{ $person->name }}</span>
                            <div class="text-right flex items-center gap-2">
                                <span class="text-[10px] text-muted">{{ $person->orders }} ord</span>
                                <span class="font-bold text-brand"><x-money :amount="$person->revenue" compact /></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-muted">No sales attributed in period.</p>
                    @endforelse
                </div>

                @if (!empty($insights))
                    <div class="pt-2.5 border-t border-line/60 dark:border-strokedark space-y-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted block">Strategic Signals</span>
                        @foreach (array_slice($insights, 0, 2) as $insight)
                            <div class="flex items-start gap-2 rounded-lg bg-surface/60 p-2 text-xs dark:bg-boxdark2">
                                <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded text-[10px]"
                                      style="background-color: {{ $insight['color'] }}1A; color: {{ $insight['color'] }};">
                                    <i class="fa-solid {{ $insight['icon'] }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <span class="font-bold text-ink dark:text-white leading-tight block">{{ $insight['title'] }}</span>
                                    <span class="text-[11px] text-muted leading-tight block">{{ $insight['detail'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const el = document.getElementById('salesTrendChart');
        if (!el || typeof Chart === 'undefined') return;

        const labels  = @json($trend['labels']);
        const orders  = @json($trend['orders']);
        const revenue = @json($trend['revenue']);
        const dark    = document.documentElement.classList.contains('dark');
        const grid    = dark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
        const tick    = dark ? '#98A2B3' : '#667085';

        new Chart(el, {
            data: {
                labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Revenue',
                        data: revenue,
                        yAxisID: 'y',
                        borderColor: '#465FFF',
                        backgroundColor: 'rgba(70,95,255,.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: .35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                    {
                        type: 'bar',
                        label: 'Orders',
                        data: orders,
                        yAxisID: 'y1',
                        backgroundColor: 'rgba(18,183,106,.35)',
                        borderRadius: 4,
                        barPercentage: .6,
                        categoryPercentage: .7,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { labels: { color: tick, boxWidth: 12 } } },
                scales: {
                    x:  { grid: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkipPadding: 16 } },
                    y:  { position: 'left',  grid: { color: grid }, ticks: { color: tick } },
                    y1: { position: 'right', grid: { display: false }, ticks: { color: tick, precision: 0 } },
                },
            },
        });
    })();
</script>
@endpush
