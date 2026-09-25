@extends('layouts.app')
@section('title', 'Sales Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'sales']" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Orders" icon="fa-receipt" icon-color="#465FFF" :value="number_format($totals->orders ?? 0)" />
        {{-- The operator-entered counter, summed. Not the order count above. --}}
        <x-stat-card label="No. of Orders" icon="fa-list-ol" icon-color="#7A5AF8"
                     :value="number_format($totals->no_of_orders ?? 0)" />
        <x-stat-card label="Revenue" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($totals->revenue ?? 0)" />
        <x-stat-card label="Outstanding" icon="fa-hourglass-half" icon-color="#F79009"
                     :value="\App\Support\Money::compact($totals->outstanding ?? 0)" />
    </div>

    {{-- Filters (requirements 26.1) --}}
    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach (request()->only(['range', 'from', 'to']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <div>
                <label class="ta-label">Sales Person</label>
                <select name="sales_person_id" class="ta-input">
                    <option value="">All</option>
                    @foreach ($salesPersons as $person)
                        <option value="{{ $person->id }}" class="font-bold"
                                @selected((int) ($filters['sales_person_id'] ?? 0) === $person->id)>
                            {{ $person->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">Product contains</label>
                <input type="text" name="product" value="{{ $filters['product'] ?? '' }}" class="ta-input" placeholder="e.g. Sofa">
            </div>
            <div>
                <label class="ta-label">ZIP code</label>
                <input type="text" name="zip_code" value="{{ $filters['zip_code'] ?? '' }}" class="ta-input">
            </div>
            <div>
                <label class="ta-label">Order status</label>
                <select name="order_status" class="ta-input">
                    <option value="">All</option>
                    {{-- label() collapses nine statuses into three, so listing the
                         cases showed "Pending" six times. One option per label. --}}
                    @foreach (\App\Enums\OrderStatus::filterGroups() as $key => $group)
                        <option value="{{ $key }}" @selected(($filters['order_status'] ?? '') === $key)>{{ $group['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">Confirmation</label>
                <select name="confirmation_status" class="ta-input">
                    <option value="">All</option>
                    @foreach (\App\Enums\ConfirmationStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($filters['confirmation_status'] ?? '') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-3 xl:col-span-6">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="{{ route('reports.sales', request()->only(['range', 'from', 'to'])) }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card padding="p-0">
        @if ($orders->isEmpty())
            <x-empty-state icon="fa-receipt" title="No orders in this period" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Order</th>
                            <th class="ta-th">Created</th>
                            <th class="ta-th">Requested</th>
                            <th class="ta-th">Customer</th>
                            <th class="ta-th text-center">No. of Orders</th>
                            <th class="ta-th">ZIP</th>
                            <th class="ta-th">Sales Person</th>
                            <th class="ta-th text-right">Total</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th">Confirmation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($orders as $order)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td">
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-sm text-brand hover:underline" title="{{ $order->order_number }}">
                                        {{ $order->display_number }}
                                    </a>
                                </td>
                                <td class="ta-td text-muted">{{ $order->order_created_at?->format('d M Y') }}</td>
                                <td class="ta-td">{{ $order->requested_delivery_date?->format('d M Y') }}</td>
                                <td class="ta-td">{{ $order->customer?->name }}</td>
                                <td class="ta-td text-center font-semibold">{{ $order->number_of_orders !== null ? $order->number_of_orders : '--' }}</td>
                                <td class="ta-td font-semibold">{{ $order->zip_code }}</td>
                                <td class="ta-td">{{ $order->salesPerson?->name ?? '--' }}</td>
                                <td class="ta-td text-right font-semibold"><x-money :amount="$order->grand_total" /></td>
                                <td class="ta-td"><x-status-pill :status="$order->order_status" /></td>
                                <td class="ta-td">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                          style="background-color: {{ $order->confirmation_status?->color() }}1A; color: {{ $order->confirmation_status?->color() }};">
                                        <i class="fa-solid {{ $order->confirmation_status?->icon() }} text-[9px]"></i>
                                        {{ $order->confirmation_status?->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $orders->links() }}</div>
        @endif
    </x-card>

    {{-- Charts sit below the figures: the numbers are what the page is read for. --}}
    <x-card title="Revenue over time" :subtitle="$range['label']">
        <div class="h-64"><canvas id="reportTrend"></canvas></div>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function initChart() {
            const el = document.getElementById('reportTrend');
            if (!el) return;
            if (typeof Chart === 'undefined') {
                setTimeout(initChart, 50);
                return;
            }
            if (el._chartInstance) return;

            const dark = document.documentElement.classList.contains('dark');
            const tick = dark ? '#98A2B3' : '#667085';

            el._chartInstance = new Chart(el, {
                type: 'bar',
                data: {
                    labels: @json($trend['labels']),
                    datasets: [{
                        label: 'Revenue',
                        data: @json($trend['revenue']),
                        backgroundColor: 'rgba(70,95,255,.6)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkipPadding: 16 } },
                        y: { ticks: { color: tick } },
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
