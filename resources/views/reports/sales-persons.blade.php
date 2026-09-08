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
                <table class="w-full">
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

    {{-- ============================ Row 4: Delivered vs Cancelled Ratio ============================ --}}
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Team Completion Rate</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 text-xs">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <p class="mt-2 text-2xl font-black text-emerald-600">
                {{ $team['success_rate'] === null ? 'N/A' : $team['success_rate'] . '%' }}
            </p>
            <p class="text-xs text-muted mt-0.5">
                {{ number_format($team['delivered']) }} delivered &middot; {{ number_format($team['lost']) }} cancelled
                @if ($team['in_progress'] > 0)
                    &middot; {{ number_format($team['in_progress']) }} still open
                @endif
            </p>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Best Completion</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand text-xs">
                    <i class="fa-solid fa-award"></i>
                </span>
            </div>
            <p class="mt-2 text-xl font-bold text-ink dark:text-white truncate">{{ $bestRate?->name ?? 'N/A' }}</p>
            <p class="text-xs text-muted mt-0.5">
                @if ($bestRate)
                    <strong class="text-emerald-600 font-semibold">{{ $bestRate->success_rate }}%</strong>
                    ({{ $bestRate->delivered }} delivered / {{ $bestRate->lost }} cancelled)
                @else
                    No settled orders in period
                @endif
            </p>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Most Cancellations</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-danger/10 text-danger text-xs">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
            </div>
            {{-- Left blank when nobody has cancelled anything: naming a seller
                 with a clean record as the top canceller would be misleading. --}}
            @if ($worstRate)
                <p class="mt-2 text-xl font-bold text-ink dark:text-white truncate">{{ $worstRate->name }}</p>
                <p class="text-xs text-muted mt-0.5">
                    <strong class="text-danger font-semibold">{{ $worstRate->success_rate }}%</strong>
                    completion ({{ $worstRate->lost }} cancelled)
                </p>
            @else
                <p class="mt-2 text-xl font-bold text-muted">&mdash;</p>
                <p class="text-xs text-muted mt-0.5">No cancellations in this period</p>
            @endif
        </div>
    </div>

    <x-card padding="p-0" title="Delivered vs Cancelled Ratio"
            :subtitle="'How many orders each seller actually completed · ' . $range['label']">
        @php $settledRows = $rows->filter(fn ($r) => $r->total > 0); @endphp

        @if ($settledRows->isEmpty())
            <x-empty-state icon="fa-scale-balanced" title="No orders in this period"
                           message="Ratios appear once orders have been attributed to a sales person." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Sales Person</th>
                            <th class="ta-th text-right">Orders</th>
                            <th class="ta-th text-right">Delivered</th>
                            <th class="ta-th text-right">Cancelled</th>
                            <th class="ta-th text-right">In Progress</th>
                            <th class="ta-th text-right">Ratio</th>
                            <th class="ta-th">Completion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($settledRows->sortByDesc('success_rate') as $row)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td font-medium">{{ $row->name }}</td>
                                <td class="ta-td text-right text-muted">{{ number_format($row->total) }}</td>
                                <td class="ta-td text-right font-semibold text-emerald-600">{{ number_format($row->delivered) }}</td>
                                <td class="ta-td text-right font-semibold {{ $row->lost > 0 ? 'text-danger' : 'text-muted' }}">
                                    {{ number_format($row->lost) }}
                                    @if ($row->returned > 0)
                                        <span class="block text-[10px] font-normal text-muted">incl. {{ $row->returned }} returned</span>
                                    @endif
                                </td>
                                <td class="ta-td text-right text-muted">{{ number_format($row->in_progress) }}</td>
                                <td class="ta-td text-right font-bold">
                                    @if ($row->ratio !== null)
                                        <span title="{{ $row->ratio }} delivered for every 1 cancelled">{{ $row->ratio }} : 1</span>
                                    @elseif ($row->delivered > 0)
                                        <span class="text-emerald-600" title="No cancellations">{{ $row->delivered }} : 0</span>
                                    @else
                                        <span class="text-muted">--</span>
                                    @endif
                                </td>
                                <td class="ta-td">
                                    @if ($row->success_rate === null)
                                        <span class="text-xs text-muted">Nothing settled yet</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            {{-- Delivered vs cancelled, as a share of settled orders only. --}}
                                            <span class="flex h-2 w-28 overflow-hidden rounded-full bg-line dark:bg-strokedark">
                                                <span class="block h-full bg-emerald-500" style="width: {{ $row->success_rate }}%"></span>
                                                <span class="block h-full bg-danger" style="width: {{ 100 - $row->success_rate }}%"></span>
                                            </span>
                                            <span class="text-xs font-semibold {{ $row->success_rate >= 80 ? 'text-emerald-600' : ($row->success_rate >= 50 ? 'text-amber-500' : 'text-danger') }}">
                                                {{ $row->success_rate }}%
                                            </span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-line bg-surface/50 dark:border-strokedark dark:bg-boxdark2">
                        <tr>
                            <td class="ta-td font-bold">Team total</td>
                            <td class="ta-td text-right font-bold">{{ number_format($team['total']) }}</td>
                            <td class="ta-td text-right font-bold text-emerald-600">{{ number_format($team['delivered']) }}</td>
                            <td class="ta-td text-right font-bold text-danger">{{ number_format($team['lost']) }}</td>
                            <td class="ta-td text-right font-bold text-muted">{{ number_format($team['in_progress']) }}</td>
                            <td class="ta-td text-right font-bold">{{ $team['ratio'] !== null ? $team['ratio'] . ' : 1' : '--' }}</td>
                            <td class="ta-td font-bold">{{ $team['success_rate'] === null ? 'N/A' : $team['success_rate'] . '%' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="border-t border-line px-5 py-3 text-[11px] text-muted dark:border-strokedark">
                Completion counts only orders that reached an outcome, so orders still in progress
                do not drag a seller down. Ratio reads as delivered orders per cancelled order.
            </p>
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
        function initChart() {
            const el = document.getElementById('salesPersonCompareChart');
            if (!el) return;
            if (typeof Chart === 'undefined') {
                setTimeout(initChart, 50);
                return;
            }
            if (el._chartInstance) return;

            const dark = document.documentElement.classList.contains('dark');
            const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
            const tick = dark ? '#98A2B3' : '#667085';

            const labels = @json($rows->pluck('name'));
            const revenueData = @json($rows->pluck('revenue'));
            const ordersData = @json($rows->pluck('orders'));

            el._chartInstance = new Chart(el, {
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
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.dataset.yAxisID === 'y') {
                                        label += '€ ' + Number(context.parsed.y).toLocaleString();
                                    } else {
                                        label += Number(context.parsed.y).toLocaleString();
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x:  { grid: { display: false }, ticks: { color: tick, font: { size: 11, weight: 'bold' } } },
                        y:  {
                            position: 'left',
                            grid: { color: grid },
                            ticks: {
                                color: tick,
                                font: { size: 10 },
                                callback: function(value) { return '€' + (value >= 1000 ? (value/1000) + 'k' : value); }
                            }
                        },
                        y1: {
                            position: 'right',
                            grid: { display: false },
                            ticks: { color: tick, precision: 0, font: { size: 10 } }
                        },
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
