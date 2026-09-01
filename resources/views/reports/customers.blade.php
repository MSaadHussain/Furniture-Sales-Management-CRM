@extends('layouts.app')
@section('title', 'Customer Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'customers']" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Total customers" icon="fa-users" icon-color="#465FFF" :value="number_format($stats['total'])" />
        <x-stat-card label="New this period" icon="fa-user-plus" icon-color="#12B76A" :value="number_format($stats['new'])" />
        <x-stat-card label="Returning customers" icon="fa-rotate" icon-color="#7A5AF8" :value="number_format($stats['returning'])"
                     :hint="number_format($stats['buyers']) . ' have ordered'" />
        <x-stat-card label="Avg orders per customer" icon="fa-receipt" icon-color="#F79009" :value="number_format($stats['avg_orders'], 2)" />
        <x-stat-card label="Avg customer spend" icon="fa-sack-dollar" icon-color="#06AED4"
                     :value="\App\Support\Money::compact($stats['avg_spend'])" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2" padding="p-0" title="Top customers by revenue" :subtitle="$range['label']">
            @if ($top->isEmpty())
                <x-empty-state icon="fa-users" title="No customer orders in this period" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px]">
                        <thead class="border-b border-line dark:border-strokedark">
                            <tr>
                                <th class="ta-th">Customer</th>
                                <th class="ta-th">Phone</th>
                                <th class="ta-th">ZIP</th>
                                <th class="ta-th text-right">Orders</th>
                                <th class="ta-th text-right">Total spend</th>
                                <th class="ta-th">Last order</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line dark:divide-strokedark">
                            @foreach ($top as $row)
                                <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                    <td class="ta-td">
                                        <a href="{{ route('customers.show', $row->id) }}" class="font-semibold text-brand hover:underline">
                                            {{ $row->name }}
                                        </a>
                                    </td>
                                    <td class="ta-td">{{ $row->phone }}</td>
                                    <td class="ta-td font-semibold">{{ $row->zip_code }}</td>
                                    <td class="ta-td text-right">{{ number_format($row->orders) }}</td>
                                    <td class="ta-td text-right font-semibold"><x-money :amount="$row->revenue" /></td>
                                    <td class="ta-td text-muted">
                                        {{ \Illuminate\Support\Carbon::parse($row->last_order_at)->format('d M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card padding="p-0" title="Customers by ZIP" subtitle="Where your customer base lives">
            @if ($distribution->isEmpty())
                <x-empty-state icon="fa-map-pin" title="No ZIP data yet" />
            @else
                <div class="divide-y divide-line dark:divide-strokedark">
                    @foreach ($distribution as $row)
                        <div class="flex items-center justify-between px-5 py-2.5">
                            <a href="{{ route('reports.zip', ['zip' => $row->zip_code]) }}"
                               class="text-sm font-semibold text-ink hover:text-brand dark:text-gray-300">{{ $row->zip_code }}</a>
                            <span class="text-sm text-muted">{{ number_format($row->customers) }} customers</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
