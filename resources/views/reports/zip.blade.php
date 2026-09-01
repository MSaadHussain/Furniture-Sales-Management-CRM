@extends('layouts.app')
@section('title', 'ZIP Code Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'zip']" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" />

    {{-- Recommended advertising areas (requirements 44) --}}
    @if (count($insights))
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($insights as $insight)
                <div class="ta-card flex gap-3 p-4">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
                          style="background-color: {{ $insight['color'] }}1A; color: {{ $insight['color'] }};">
                        <i class="fa-solid {{ $insight['icon'] }}"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink dark:text-gray-200">{{ $insight['title'] }}</p>
                        <p class="mt-0.5 text-sm text-muted">{{ $insight['detail'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Sorting (requirements 19) --}}
    <x-card padding="p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-muted">Rank by</span>
            @foreach ($sorts as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['sort' => $key]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                          {{ $sort === $key
                              ? 'bg-brand text-white'
                              : 'border border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </x-card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" padding="p-0" title="ZIP / postal code ranking"
                :subtitle="$range['label'] . ' · growth compares against the previous period of equal length'">
            @if ($ranking->isEmpty())
                <x-empty-state icon="fa-map-location-dot" title="No orders in this period"
                               message="ZIP analytics need orders with a postal code recorded." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px]">
                        <thead class="border-b border-line dark:border-strokedark">
                            <tr>
                                <th class="ta-th">#</th>
                                <th class="ta-th">ZIP</th>
                                <th class="ta-th text-right">Customers</th>
                                <th class="ta-th text-right">Orders</th>
                                <th class="ta-th text-right">Revenue</th>
                                <th class="ta-th text-right">Avg order</th>
                                <th class="ta-th text-right">% of orders</th>
                                <th class="ta-th text-right">Growth</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line dark:divide-strokedark">
                            @foreach ($ranking as $index => $row)
                                <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50
                                           {{ $focus === $row['zip_code'] ? 'bg-brand/5' : '' }}">
                                    <td class="ta-td text-muted">{{ $index + 1 }}</td>
                                    <td class="ta-td">
                                        <a href="{{ request()->fullUrlWithQuery(['zip' => $row['zip_code']]) }}"
                                           class="font-semibold text-brand hover:underline">{{ $row['zip_code'] }}</a>
                                    </td>
                                    <td class="ta-td text-right">{{ number_format($row['customers']) }}</td>
                                    <td class="ta-td text-right font-semibold">{{ number_format($row['orders']) }}</td>
                                    <td class="ta-td text-right"><x-money :amount="$row['revenue']" /></td>
                                    <td class="ta-td text-right"><x-money :amount="$row['avg_order']" /></td>
                                    <td class="ta-td text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="hidden h-1.5 w-14 overflow-hidden rounded-full bg-line dark:bg-strokedark sm:block">
                                                <span class="block h-full rounded-full bg-brand" style="width: {{ min(100, $row['order_share']) }}%"></span>
                                            </span>
                                            <span class="text-muted">{{ $row['order_share'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="ta-td text-right"><x-growth :value="$row['order_growth']" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div class="space-y-6">
            {{-- Drill-down: what sells in one area (requirements 22) --}}
            <x-card padding="p-0"
                    :title="$focus ? 'What sells in ' . $focus : 'Area drill-down'"
                    :subtitle="$focus ? 'Top items in this ZIP for the period' : 'Pick a ZIP from the table to see its product mix'">
                @if (! $focus)
                    <x-empty-state icon="fa-hand-pointer" title="No area selected"
                                   message="Click any ZIP code in the ranking." />
                @elseif ($focusMix->isEmpty())
                    <x-empty-state icon="fa-chair" title="No items sold here in this period" />
                @else
                    <div class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($focusMix as $row)
                            <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                                <span class="min-w-0 truncate text-sm text-ink dark:text-gray-300">{{ $row->name }}</span>
                                <span class="text-right">
                                    <span class="block text-sm font-bold text-ink dark:text-white">{{ number_format($row->quantity) }}</span>
                                    <span class="block text-xs text-muted"><x-money :amount="$row->revenue" compact /></span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-line px-5 py-3 dark:border-strokedark">
                        <a href="{{ route('orders.index', ['zip_code' => $focus]) }}" class="text-sm font-medium text-brand hover:underline">
                            View all orders in {{ $focus }}
                        </a>
                    </div>
                @endif
            </x-card>

            {{-- Areas that stopped ordering --}}
            <x-card padding="p-0" title="Areas that went quiet"
                    subtitle="Ordered in the previous period, nothing in this one">
                @if ($quiet->isEmpty())
                    <x-empty-state icon="fa-circle-check" title="No areas dropped off" />
                @else
                    <div class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($quiet as $row)
                            <div class="flex items-center justify-between px-5 py-2.5">
                                <span class="text-sm font-semibold text-ink dark:text-gray-300">{{ $row['zip_code'] }}</span>
                                <span class="text-sm text-muted">
                                    was {{ $row['prev_orders'] }} orders &middot; <x-money :amount="$row['prev_revenue']" compact />
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>

    <p class="text-xs text-muted">
        ZIP analytics are aggregated. No individual customer records are exposed or exported from this report.
    </p>
</div>
@endsection
