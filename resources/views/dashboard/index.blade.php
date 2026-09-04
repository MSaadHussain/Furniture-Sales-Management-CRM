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

    <x-date-range :range="$range" :presets="$presets" />

    {{-- ============================ Row 1: Delivery Operations & Orders Details ============================ --}}
    @php
        $initialTab = $todayOrders->isNotEmpty() ? 'today' : ($tomorrowOrders->isNotEmpty() ? 'tomorrow' : 'recent');
    @endphp

    <div x-data="{ activeTab: '{{ $initialTab }}' }" class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        
        {{-- Left Card: Deliveries Summary --}}
        <div class="rounded-2xl border border-line bg-white p-6 sm:p-7 shadow-xs dark:border-strokedark dark:bg-boxdark flex flex-col justify-between">
            <div>
                {{-- Header --}}
                <div class="flex items-center gap-2.5 pb-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand/10 text-brand text-sm font-bold">
                        <i class="fa-solid fa-truck"></i>
                    </span>
                    <h3 class="text-base font-bold text-ink dark:text-white">Deliveries</h3>
                </div>

                <div class="mt-2 space-y-2">
                    {{-- Today Row --}}
                    <div @click="activeTab = 'today'"
                         class="flex items-center justify-between p-3 rounded-xl cursor-pointer transition select-none"
                         :class="activeTab === 'today' ? 'bg-brand/5 border border-brand/20 dark:bg-brand/10' : 'hover:bg-surface/70 border border-transparent'">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-base font-bold text-ink dark:text-white">Today</h4>
                                <template x-if="activeTab === 'today'">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand"></span>
                                </template>
                            </div>
                            <p class="text-xs text-muted font-medium mt-0.5">{{ $today['date']->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-black text-ink dark:text-white">{{ $today['scheduled'] }}</span>
                            <span class="block text-xs text-muted">Deliveries</span>
                        </div>
                    </div>

                    <div class="border-t border-line/60 dark:border-strokedark"></div>

                    {{-- Tomorrow Row --}}
                    <div @click="activeTab = 'tomorrow'"
                         class="flex items-center justify-between p-3 rounded-xl cursor-pointer transition select-none"
                         :class="activeTab === 'tomorrow' ? 'bg-brand/5 border border-brand/20 dark:bg-brand/10' : 'hover:bg-surface/70 border border-transparent'">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-base font-bold text-ink dark:text-white">Tomorrow</h4>
                                <template x-if="activeTab === 'tomorrow'">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand"></span>
                                </template>
                            </div>
                            <p class="text-xs text-muted font-medium mt-0.5">{{ $tomorrow['date']->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-black text-ink dark:text-white">{{ $tomorrow['scheduled'] }}</span>
                            <span class="block text-xs text-muted">Deliveries</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-3 border-t border-line/40 dark:border-strokedark">
                <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:underline">
                    View All Deliveries &rarr;
                </a>
            </div>
        </div>

        {{-- Right Card: Delivery / Recent Orders Details --}}
        <div class="rounded-2xl border border-line bg-white p-6 sm:p-7 shadow-xs dark:border-strokedark dark:bg-boxdark flex flex-col justify-between">
            <div>
                {{-- Header with Tab Switcher --}}
                <div class="flex items-center justify-between pb-3 border-b border-line/40 dark:border-strokedark">
                    <h3 class="text-base font-bold text-ink dark:text-white"
                        x-text="activeTab === 'today' ? 'Today\'s Deliveries' : (activeTab === 'tomorrow' ? 'Tomorrow\'s Deliveries' : 'Recent Orders')">
                        Recent Orders
                    </h3>

                    <div class="flex items-center gap-1">
                        <button type="button" @click="activeTab = 'today'"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition"
                                :class="activeTab === 'today' ? 'bg-brand text-white shadow-xs' : 'text-muted hover:text-ink bg-surface dark:bg-boxdark2'">
                            Today ({{ $today['scheduled'] }})
                        </button>
                        <button type="button" @click="activeTab = 'tomorrow'"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition"
                                :class="activeTab === 'tomorrow' ? 'bg-brand text-white shadow-xs' : 'text-muted hover:text-ink bg-surface dark:bg-boxdark2'">
                            Tomorrow ({{ $tomorrow['scheduled'] }})
                        </button>
                        <button type="button" @click="activeTab = 'recent'"
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold transition"
                                :class="activeTab === 'recent' ? 'bg-brand text-white shadow-xs' : 'text-muted hover:text-ink bg-surface dark:bg-boxdark2'">
                            Recent
                        </button>
                    </div>
                </div>

                {{-- Tab 1: Today's Deliveries --}}
                <div x-show="activeTab === 'today'" class="divide-y divide-line/60 dark:divide-strokedark">
                    @forelse ($todayOrders as $order)
                        <div class="py-3.5 first:pt-2 last:pb-1">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-base text-brand hover:underline" title="{{ $order->order_number }}">
                                        {{ $order->display_number }}
                                    </a>
                                    <x-status-pill :status="$order->order_status" />
                                </div>
                                <span class="font-bold text-sm sm:text-base text-ink dark:text-white">
                                    <x-money :amount="$order->grand_total" />
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 mt-1">
                                <p class="text-xs font-medium text-ink dark:text-gray-200 truncate">
                                    {{ $order->customer?->name ?? 'Unknown Customer' }}
                                    @if($order->customer?->phone)
                                        <span class="font-normal text-muted">({{ $order->customer?->phone }})</span>
                                    @endif
                                </p>
                                <a href="{{ route('orders.show', $order) }}" class="text-xs font-semibold text-brand hover:underline whitespace-nowrap">
                                    View Order &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">
                            <i class="fa-solid fa-truck-ramp-box text-2xl text-muted/50 mb-2 block"></i>
                            No deliveries scheduled for today.
                        </div>
                    @endforelse
                </div>

                {{-- Tab 2: Tomorrow's Deliveries --}}
                <div x-show="activeTab === 'tomorrow'" class="divide-y divide-line/60 dark:divide-strokedark" style="display: none;">
                    @forelse ($tomorrowOrders as $order)
                        <div class="py-3.5 first:pt-2 last:pb-1">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-base text-brand hover:underline" title="{{ $order->order_number }}">
                                        {{ $order->display_number }}
                                    </a>
                                    <x-status-pill :status="$order->order_status" />
                                </div>
                                <span class="font-bold text-sm sm:text-base text-ink dark:text-white">
                                    <x-money :amount="$order->grand_total" />
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 mt-1">
                                <p class="text-xs font-medium text-ink dark:text-gray-200 truncate">
                                    {{ $order->customer?->name ?? 'Unknown Customer' }}
                                    @if($order->customer?->phone)
                                        <span class="font-normal text-muted">({{ $order->customer?->phone }})</span>
                                    @endif
                                </p>
                                <a href="{{ route('orders.show', $order) }}" class="text-xs font-semibold text-brand hover:underline whitespace-nowrap">
                                    View Order &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">
                            <i class="fa-solid fa-calendar-day text-2xl text-muted/50 mb-2 block"></i>
                            No deliveries scheduled for tomorrow.
                        </div>
                    @endforelse
                </div>

                {{-- Tab 3: Recent Orders --}}
                <div x-show="activeTab === 'recent'" class="divide-y divide-line/60 dark:divide-strokedark" style="display: none;">
                    @forelse ($recentOrders as $order)
                        <div class="py-3.5 first:pt-2 last:pb-1">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-base text-brand hover:underline" title="{{ $order->order_number }}">
                                        {{ $order->display_number }}
                                    </a>
                                    <x-status-pill :status="$order->order_status" />
                                </div>
                                <span class="font-bold text-sm sm:text-base text-ink dark:text-white">
                                    <x-money :amount="$order->grand_total" />
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-3 mt-1">
                                <p class="text-xs font-medium text-ink dark:text-gray-200 truncate">
                                    {{ $order->customer?->name ?? 'Unknown Customer' }}
                                    @if($order->customer?->phone)
                                        <span class="font-normal text-muted">({{ $order->customer?->phone }})</span>
                                    @endif
                                </p>
                                <a href="{{ route('orders.show', $order) }}" class="text-xs font-semibold text-brand hover:underline whitespace-nowrap">
                                    View Order &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">
                            No recent orders found.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-6 pt-3 border-t border-line/40 dark:border-strokedark">
                <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:underline">
                    View All Orders &rarr;
                </a>
            </div>
        </div>

    </div>

    {{-- ============================ Row 2: Clean KPI Summary Cards ============================ --}}
    <div class="rounded-2xl border border-line bg-white p-5 sm:p-6 shadow-xs dark:border-strokedark dark:bg-boxdark">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-line/70 dark:divide-strokedark">
            
            {{-- Stat 1: Today's Deliveries --}}
            <div class="flex items-center gap-4 sm:px-4 first:pl-0">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-brand dark:bg-brand/10 text-xl">
                    <i class="fa-solid fa-bag-shopping"></i>
                </span>
                <div>
                    <span class="text-2xl font-black text-ink dark:text-white">{{ $today['scheduled'] }}</span>
                    <span class="block text-xs font-medium text-muted">Today's Deliveries</span>
                </div>
            </div>

            {{-- Stat 2: Orders in Selected Range / Month --}}
            <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:px-6">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 text-xl">
                    <i class="fa-solid fa-bag-shopping"></i>
                </span>
                <div>
                    <span class="text-2xl font-black text-ink dark:text-white">{{ number_format($kpis['orders']) }}</span>
                    <span class="block text-xs font-medium text-muted">Orders {{ $range['label'] }}</span>
                </div>
            </div>

            {{-- Stat 3: Total Sales in Selected Range / Month --}}
            <div class="flex items-center gap-4 pt-4 sm:pt-0 sm:px-6">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400 text-xl">
                    <i class="fa-solid fa-euro-sign"></i>
                </span>
                <div>
                    <span class="text-2xl font-black text-ink dark:text-white"><x-money :amount="$kpis['revenue']" compact /></span>
                    <span class="block text-xs font-medium text-muted">Total {{ $range['label'] }}</span>
                </div>
            </div>

        </div>
    </div>

    {{-- ==================== Row 3: Sales Trend & 7-Day Schedule ========================= --}}
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
        function initChart() {
            const el = document.getElementById('salesTrendChart');
            if (!el) return;
            if (typeof Chart === 'undefined') {
                setTimeout(initChart, 50);
                return;
            }
            if (el._chartInstance) return;

            const labels  = @json($trend['labels']);
            const orders  = @json($trend['orders']);
            const revenue = @json($trend['revenue']);
            const dark    = document.documentElement.classList.contains('dark');
            const grid    = dark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
            const tick    = dark ? '#98A2B3' : '#667085';

            el._chartInstance = new Chart(el, {
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
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initChart);
        } else {
            initChart();
        }
    })();
</script>
@endpush
