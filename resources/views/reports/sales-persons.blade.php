@extends('layouts.app')
@section('title', 'Sales Person Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'sales-persons']" />
    @endcan
@endsection

@section('content')
<div class="space-y-4">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" class="!p-3 sm:!p-3.5" />

    {{-- ============================ Row 1: Team Production & Top Performers ============================ --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Team Production --}}
        <div class="rounded-2xl border border-brand/20 bg-brand/5 p-4 shadow-sm dark:border-brand/30 dark:bg-brand/10">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Team Production</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/15 text-brand text-xs">
                    <i class="fa-solid fa-users"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-black text-brand"><x-money :amount="$totalRevenue" compact /></p>
            <p class="text-xs text-muted mt-0.5">{{ number_format($totalOrders) }} orders closed &middot; {{ $rows->count() }} sales reps</p>
        </div>

        {{-- Top Revenue Earner --}}
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Top Revenue Closer</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500 text-xs font-bold">
                    <i class="fa-solid fa-trophy"></i>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold text-ink dark:text-white truncate">
                {{ $topEarner ? $topEarner->name : 'N/A' }}
            </p>
            <p class="text-xs text-muted mt-0.5">
                @if ($topEarner)
                    <strong class="text-brand font-semibold"><x-money :amount="$topEarner->revenue" compact /></strong> ({{ $topEarner->orders }} orders)
                @else
                    No sales in period
                @endif
            </p>
        </div>

        {{-- Most Orders Closed --}}
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Most Orders Closed</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold text-ink dark:text-white truncate">
                {{ $topCloser ? $topCloser->name : 'N/A' }}
            </p>
            <p class="text-xs text-muted mt-0.5">
                @if ($topCloser)
                    <strong class="text-emerald-600 font-semibold">{{ $topCloser->orders }} orders</strong> (<x-money :amount="$topCloser->revenue" compact />)
                @else
                    No orders in period
                @endif
            </p>
        </div>

        {{-- Highest Average Deal Size --}}
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Best Average Deal</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600 text-xs">
                    <i class="fa-solid fa-scale-balanced"></i>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold text-ink dark:text-white truncate">
                {{ $topTicket ? $topTicket->name : 'N/A' }}
            </p>
            <p class="text-xs text-muted mt-0.5">
                @if ($topTicket)
                    <strong class="text-purple-600 font-semibold"><x-money :amount="$topTicket->avg_order" compact /></strong> / order
                @else
                    No sales in period
                @endif
            </p>
        </div>
    </div>

    {{-- ============================ Row 2: Visual Comparison Chart ============================ --}}
    <x-card padding="p-4 sm:p-5" title="Sales Team Comparison" :subtitle="'Revenue & order volume comparison · ' . $range['label']">
        <div class="h-60">
            <canvas id="salesPersonCompareChart"></canvas>
        </div>
    </x-card>

    {{-- ============================ Row 3: Comparative Leaderboard Table ============================ --}}
    <x-card padding="p-0" title="Sales Representative Leaderboard" :subtitle="'Full roster breakdown · ' . $range['label']">
        @if ($rows->isEmpty())
            <x-empty-state icon="fa-user-tie" title="No sales persons registered"
                           message="Add sales persons under User Management to view comparison." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[550px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th w-16 text-center">Rank</th>
                            <th class="ta-th">Sales Person</th>
                            <th class="ta-th text-right">Orders Closed</th>
                            <th class="ta-th text-right">Total Revenue</th>
                            <th class="ta-th text-right">Average Order Value</th>
                            <th class="ta-th text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($rows as $index => $row)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td text-center">
                                    @if ($row->revenue > 0 && $index === 0)
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-400/20 text-amber-500 font-bold text-xs" title="1st Place">🥇</span>
                                    @elseif ($row->revenue > 0 && $index === 1)
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-300/30 text-slate-500 font-bold text-xs" title="2nd Place">🥈</span>
                                    @elseif ($row->revenue > 0 && $index === 2)
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-700/20 text-amber-700 font-bold text-xs" title="3rd Place">🥉</span>
                                    @else
                                        <span class="text-xs font-semibold text-muted">#{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="ta-td">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand/10 text-brand text-xs font-bold">
                                            {{ strtoupper(substr($row->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <span class="font-bold text-ink dark:text-white text-xs block">{{ $row->name }}</span>
                                            <span class="text-[10px] text-muted block">Sales Representative</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="ta-td text-right font-semibold text-xs">
                                    <span class="rounded-lg bg-surface px-2.5 py-1 text-ink dark:bg-boxdark2 dark:text-gray-300 border border-line/60 dark:border-strokedark">
                                        {{ number_format($row->orders) }} order{{ $row->orders === 1 ? '' : 's' }}
                                    </span>
                                </td>
                                <td class="ta-td text-right font-black text-sm text-brand">
                                    <x-money :amount="$row->revenue" />
                                </td>
                                <td class="ta-td text-right font-semibold text-xs text-ink dark:text-gray-300">
                                    <x-money :amount="$row->avg_order" />
                                </td>
                                <td class="ta-td text-center">
                                    @if ($row->orders > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            <i class="fa-solid fa-circle-check text-[9px]"></i> Active Closer
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-500/10 px-2.5 py-0.5 text-[11px] font-medium text-muted">
                                            Awaiting Sales
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <p class="text-[11px] text-muted">
        Sales Persons do not see this report. It is visible to Admin and Manager only.
    </p>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const el = document.getElementById('salesPersonCompareChart');
        if (!el || typeof Chart === 'undefined') return;

        const dark = document.documentElement.classList.contains('dark');
        const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
        const tick = dark ? '#98A2B3' : '#667085';

        const labels = @json($rows->pluck('name'));
        const revenueData = @json($rows->pluck('revenue'));
        const ordersData = @json($rows->pluck('orders'));

        new Chart(el, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue (€)',
                        data: revenueData,
                        yAxisID: 'y',
                        backgroundColor: '#2563EB',
                        borderRadius: 6,
                        barPercentage: .5,
                        categoryPercentage: .6,
                    },
                    {
                        label: 'Orders Closed',
                        data: ordersData,
                        yAxisID: 'y1',
                        backgroundColor: '#12B76A',
                        borderRadius: 6,
                        barPercentage: .5,
                        categoryPercentage: .6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { color: tick, boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    x:  { grid: { display: false }, ticks: { color: tick, maxRotation: 0 } },
                    y:  { position: 'left',  grid: { color: grid }, ticks: { color: tick } },
                    y1: { position: 'right', grid: { display: false }, ticks: { color: tick, precision: 0 } },
                },
            },
        });
    })();
</script>
@endpush
