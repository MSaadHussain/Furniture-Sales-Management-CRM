@extends('layouts.app')
@section('title', $date->isToday() ? "Today's Deliveries" : 'Deliveries — ' . $date->format('d M Y'))

@section('header_actions')
    <a href="{{ route('deliveries.calendar', ['month' => $date->format('Y-m')]) }}" class="btn btn-light">
        <i class="fa-regular fa-calendar"></i> Calendar
    </a>
    @can('export-data')
        <x-export-menu route="deliveries.export" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    {{-- ================= Date picker ================= --}}
    <x-card padding="p-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="ta-label">Delivery date</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}" class="ta-input">
            </div>
            <div>
                <label class="ta-label">Order status</label>
                <select name="order_status" class="ta-input">
                    <option value="">All</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['order_status'] ?? '') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Show</button>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('deliveries.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
                   class="btn btn-light" title="Previous day"><i class="fa-solid fa-chevron-left"></i></a>
                <a href="{{ route('deliveries.index') }}" class="btn btn-light">Today</a>
                <a href="{{ route('deliveries.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
                   class="btn btn-light" title="Next day"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
        </form>
    </x-card>

    {{-- ================= Day summary ================= --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Scheduled" icon="fa-truck" icon-color="#465FFF"
                     :value="$summary['scheduled']" :hint="$date->format('l, d M Y')" />
        <x-stat-card label="Still to go out" icon="fa-hourglass-half" icon-color="#F79009"
                     :value="max(0, $summary['outstanding'])" />
        <x-stat-card label="Delivered" icon="fa-house-circle-check" icon-color="#12B76A"
                     :value="$summary['delivered']" />
        <x-stat-card label="Value" icon="fa-sack-dollar" icon-color="#7A5AF8"
                     :value="\App\Support\Money::compact($summary['value'])"
                     hint="Excludes cancelled and returned" />
    </div>

    @if ($overdue > 0)
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            <strong>{{ $overdue }}</strong> open order{{ $overdue === 1 ? '' : 's' }} across all dates are past their requested delivery date.
            <a href="{{ route('reports.deliveries', ['performance' => 'pending']) }}" class="font-semibold underline">Review them</a>
        </div>
    @endif

    {{-- ================= Status breakdown ================= --}}
    <x-card padding="p-4">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('deliveries.index', ['date' => $date->toDateString()]) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                      {{ empty($filters['order_status'])
                          ? 'bg-brand text-white'
                          : 'border border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300' }}">
                All ({{ $summary['scheduled'] }})
            </a>
            @foreach ($statuses as $status)
                @continue(($summary['by_status'][$status->value] ?? 0) === 0)
                <a href="{{ route('deliveries.index', ['date' => $date->toDateString(), 'order_status' => $status->value]) }}"
                   class="flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition
                          {{ ($filters['order_status'] ?? '') === $status->value ? 'border-transparent text-white' : 'border-line dark:border-strokedark' }}"
                   style="{{ ($filters['order_status'] ?? '') === $status->value
                       ? 'background-color: ' . $status->color() . ';'
                       : 'color: ' . $status->color() . ';' }}">
                    <i class="fa-solid {{ $status->icon() }} text-xs"></i>
                    {{ $status->label() }} ({{ $summary['by_status'][$status->value] }})
                </a>
            @endforeach
        </div>
    </x-card>

    {{-- ================= Orders table ================= --}}
    <x-card padding="p-0">
        @if ($orders->isEmpty())
            <x-empty-state icon="fa-truck" title="Nothing scheduled"
                           :message="'No deliveries are scheduled for ' . $date->format('d M Y') . '.'" />
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[1000px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Order</th>
                            <th class="ta-th">Customer</th>
                            <th class="ta-th">Phone</th>
                            <th class="ta-th">ZIP</th>
                            <th class="ta-th">Items</th>
                            <th class="ta-th">Sales Person</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th">Payment</th>
                            <th class="ta-th text-right">Total</th>
                            <th class="ta-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($orders as $order)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td">
                                    <a href="{{ route('orders.show', $order) }}" class="font-semibold text-brand hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="ta-td font-medium">{{ $order->customer?->name }}</td>
                                <td class="ta-td">{{ $order->customer?->phone }}</td>
                                <td class="ta-td font-semibold">{{ $order->zip_code }}</td>
                                <td class="ta-td max-w-[220px]">
                                    <span class="block truncate" title="{{ $order->itemSummary(5) }}">{{ $order->itemSummary(2) }}</span>
                                </td>
                                <td class="ta-td">{{ $order->salesPerson?->name ?? '--' }}</td>
                                <td class="ta-td"><x-status-pill :status="$order->order_status" /></td>
                                <td class="ta-td"><x-status-pill :status="$order->payment_status" /></td>
                                <td class="ta-td text-right font-semibold"><x-money :amount="$order->grand_total" /></td>
                                <td class="ta-td">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('recordDelivery', $order)
                                            @unless ($order->actual_delivery_date)
                                                <form method="POST" action="{{ route('orders.deliver', $order) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="actual_delivery_date" value="{{ today()->toDateString() }}">
                                                    <button type="submit" class="btn btn-light px-2.5 py-1.5 text-success"
                                                            title="Mark delivered today">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @endcan
                                        <a href="{{ route('orders.show', $order) }}" class="btn btn-light px-2.5 py-1.5" title="Open">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line lg:hidden dark:divide-strokedark">
                @foreach ($orders as $order)
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('orders.show', $order) }}" class="font-semibold text-brand">{{ $order->order_number }}</a>
                                <p class="truncate text-sm font-medium text-ink dark:text-gray-200">{{ $order->customer?->name }}</p>
                                <p class="text-xs text-muted">{{ $order->customer?->phone }} &middot; {{ $order->zip_code }}</p>
                                <p class="mt-1 truncate text-xs text-muted">{{ $order->itemSummary(2) }}</p>
                            </div>
                            <span class="text-right">
                                <span class="block font-semibold text-ink dark:text-white"><x-money :amount="$order->grand_total" compact /></span>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <x-status-pill :status="$order->order_status" />
                            <x-status-pill :status="$order->payment_status" />
                            @can('recordDelivery', $order)
                                @unless ($order->actual_delivery_date)
                                    <form method="POST" action="{{ route('orders.deliver', $order) }}" class="ml-auto">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="actual_delivery_date" value="{{ today()->toDateString() }}">
                                        <button type="submit" class="btn btn-light px-3 py-1.5 text-success">
                                            <i class="fa-solid fa-check"></i> Delivered
                                        </button>
                                    </form>
                                @endunless
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $orders->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
