@extends('layouts.app')
@section('title', 'Delivery Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'deliveries']" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" />

    <p class="text-sm text-muted">
        This report windows on the <strong>requested</strong> delivery date, so it shows what was promised in the
        period and whether it was met.
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="On-time rate" icon="fa-gauge-high" icon-color="#12B76A"
                     :value="$performance['rate'] === null ? 'N/A' : $performance['rate'] . '%'" />
        <x-stat-card label="Delivered" icon="fa-house-circle-check" icon-color="#465FFF"
                     :value="number_format($performance['delivered'])" />
        <x-stat-card label="On time" icon="fa-circle-check" icon-color="#12B76A"
                     :value="number_format($performance['on_time'])" />
        <x-stat-card label="Late" icon="fa-clock" icon-color="#F04438"
                     :value="number_format($performance['late'])"
                     :hint="$performance['avg_days_late'] . ' days late on average'" />
        <x-stat-card label="Overdue now" icon="fa-triangle-exclamation" icon-color="#F79009"
                     :value="number_format($overdue)" hint="Across all dates" />
    </div>

    <x-card padding="p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            @foreach (request()->only(['range', 'from', 'to']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div>
                <label class="ta-label">Performance</label>
                <select name="performance" class="ta-input">
                    <option value="">All</option>
                    <option value="on_time" @selected(($filters['performance'] ?? '') === 'on_time')>On time</option>
                    <option value="late" @selected(($filters['performance'] ?? '') === 'late')>Late</option>
                    <option value="pending" @selected(($filters['performance'] ?? '') === 'pending')>Not yet delivered</option>
                </select>
            </div>
            <div>
                <label class="ta-label">Order status</label>
                <select name="order_status" class="ta-input">
                    <option value="">All</option>
                    <option value="new" @selected(($filters['order_status'] ?? '') === 'new')>Pending</option>
                    <option value="delivered" @selected(($filters['order_status'] ?? '') === 'delivered')>Delivered</option>
                    <option value="cancelled" @selected(($filters['order_status'] ?? '') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
            <a href="{{ route('reports.deliveries', request()->only(['range', 'from', 'to'])) }}" class="btn btn-light">Reset</a>
        </form>
    </x-card>

    <x-card padding="p-0">
        @if ($orders->isEmpty())
            <x-empty-state icon="fa-truck" title="No deliveries in this period" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Requested</th>
                            <th class="ta-th">Actual</th>
                            <th class="ta-th">Order</th>
                            <th class="ta-th">Customer</th>
                            <th class="ta-th">ZIP</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th">Performance</th>
                            <th class="ta-th text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($orders as $order)
                            @php $result = $order->deliveryPerformance(); @endphp
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td">{{ $order->requested_delivery_date?->format('d M Y') }}</td>
                                <td class="ta-td">{{ $order->actual_delivery_date?->format('d M Y') ?? '--' }}</td>
                                <td class="ta-td">
                                    <a href="{{ route('orders.show', $order) }}" class="font-semibold text-brand hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="ta-td">{{ $order->customer?->name }}</td>
                                <td class="ta-td font-semibold">{{ $order->zip_code }}</td>
                                <td class="ta-td"><x-status-pill :status="$order->order_status" /></td>
                                <td class="ta-td">
                                    <span class="ta-badge"
                                          style="background-color: {{ $result->color() }}1A; color: {{ $result->color() }};">
                                        {{ $result->label() }}
                                        @if ($order->daysLate() > 0) ({{ $order->daysLate() }}d) @endif
                                    </span>
                                </td>
                                <td class="ta-td text-right font-semibold"><x-money :amount="$order->grand_total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $orders->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
