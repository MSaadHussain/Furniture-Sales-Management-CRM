@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
{{-- Restricted read-only dashboard. No customer records, no order controls,
     no ZIP or per-person comparison (requirements 36 / 45 / 56). --}}
<div class="space-y-4">

    <x-date-range :range="$range" :presets="$presets" class="!p-3 sm:!p-3.5" />

    {{-- ============================ Row 1: KPI Cards ============================ --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
        <x-stat-card label="Orders in period" icon="fa-receipt" icon-color="#465FFF"
                     :value="number_format($kpis['orders'])"
                     :delta="\App\Services\DateRangeService::growthLabel($kpis['orders_growth'])"
                     :delta-up="($kpis['orders_growth'] ?? 0) >= 0"
                     :hint="number_format($kpis['total_orders']) . ' all time'" />

        <x-stat-card label="Sales in period" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($kpis['revenue'])"
                     :delta="\App\Services\DateRangeService::growthLabel($kpis['revenue_growth'])"
                     :delta-up="($kpis['revenue_growth'] ?? 0) >= 0"
                     :hint="\App\Support\Money::compact($kpis['total_revenue']) . ' all time'" />

        <x-stat-card label="Average order value" icon="fa-scale-balanced" icon-color="#7A5AF8"
                     :value="\App\Support\Money::compact($kpis['avg_order_value'])"
                     :hint="number_format($kpis['items_sold']) . ' items sold in period'" />
    </div>

    {{-- ============================ Row 2: Your Performance & Trend ============================ --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 items-start">
        {{-- Your Numbers --}}
        <div class="xl:col-span-4">
            <x-card padding="p-4" title="Your Performance" :subtitle="'Your attribution · ' . $range['label']">
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between rounded-xl border border-line bg-surface/40 p-3 dark:border-strokedark dark:bg-boxdark2">
                        <span class="text-xs text-muted font-medium">Your Orders</span>
                        <span class="text-lg font-bold text-ink dark:text-white">{{ number_format($mine->orders) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-line bg-surface/40 p-3 dark:border-strokedark dark:bg-boxdark2">
                        <span class="text-xs text-muted font-medium">Your Revenue</span>
                        <span class="text-lg font-bold text-brand"><x-money :amount="$mine->revenue" compact /></span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-line bg-surface/40 p-3 dark:border-strokedark dark:bg-boxdark2">
                        <span class="text-xs text-muted font-medium">Your Avg Order</span>
                        <span class="text-lg font-bold text-ink dark:text-white"><x-money :amount="$mine->avg_order" compact /></span>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sales Trend --}}
        <div class="xl:col-span-8">
            <x-card padding="p-4" title="Sales Trend" :subtitle="$range['label'] . ' · by ' . $trend['granularity']">
                <div class="h-56">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </x-card>
        </div>
    </div>

    {{-- ============================ Row 3: Products & Colours ============================ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card padding="p-4" title="Top Products" subtitle="Units sold across the business">
            <div class="space-y-2">
                @forelse ($topProducts->take(4) as $product)
                    <div class="flex items-center justify-between text-xs last:mb-0">
                        <span class="truncate text-ink dark:text-gray-300 font-medium" title="{{ $product->name }}">{{ $product->name }}</span>
                        <span class="font-bold text-ink dark:text-white">{{ number_format($product->quantity) }} sold</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-muted">No sales in this period.</p>
                @endforelse
            </div>
        </x-card>

        <x-card padding="p-4" title="Popular Colours" subtitle="Customer finish preferences">
            <div class="flex flex-wrap gap-1.5">
                @forelse ($colours->take(6) as $colour)
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface/50 px-2.5 py-1 text-xs font-medium text-ink dark:border-strokedark dark:bg-boxdark2 dark:text-gray-300">
                        <span class="h-3 w-3 rounded-full border border-black/10"
                              style="background-color: {{ $swatches[$colour->name] ?? '#98A2B3' }}"></span>
                        <span>{{ $colour->name }}</span>
                        <strong class="text-brand">({{ $colour->quantity }})</strong>
                    </span>
                @empty
                    <p class="py-4 text-center text-xs text-muted">No colour data in this period.</p>
                @endforelse
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

        const dark = document.documentElement.classList.contains('dark');
        const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
        const tick = dark ? '#98A2B3' : '#667085';

        new Chart(el, {
            type: 'line',
            data: {
                labels: @json($trend['labels']),
                datasets: [{
                    label: 'Revenue',
                    data: @json($trend['revenue']),
                    borderColor: '#465FFF',
                    backgroundColor: 'rgba(70,95,255,.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: .35,
                    pointRadius: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkipPadding: 16 } },
                    y: { grid: { color: grid }, ticks: { color: tick } },
                },
            },
        });
    })();
</script>
@endpush
