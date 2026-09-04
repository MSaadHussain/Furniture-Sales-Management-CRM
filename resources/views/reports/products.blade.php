@extends('layouts.app')
@section('title', 'Product Report')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="reports.export" :params="['report' => 'products']" />
    @endcan
@endsection

@section('content')
<div class="space-y-6">
    @include('reports._nav')
    <x-date-range :range="$range" :presets="$presets" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Top products by revenue" :subtitle="$range['label']">
            <div class="h-72"><canvas id="productRevenue"></canvas></div>
        </x-card>

        <x-card title="Category performance" subtitle="Revenue by product category">
            <div class="h-72"><canvas id="categoryRevenue"></canvas></div>
        </x-card>
    </div>

    <x-card padding="p-0" title="Product performance" subtitle="Grouped on the item name recorded at order time">
        @if ($byQuantity->isEmpty())
            <x-empty-state icon="fa-chair" title="No items sold in this period" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Product</th>
                            <th class="ta-th">Category</th>
                            <th class="ta-th text-right">Quantity sold</th>
                            <th class="ta-th text-right">Revenue</th>
                            <th class="ta-th text-right">Average price</th>
                            <th class="ta-th text-right">Orders containing it</th>
                            <th class="ta-th text-right">Avg qty per order</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($byQuantity as $row)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td font-medium">{{ $row->name }}</td>
                                <td class="ta-td text-muted">{{ $row->category ?? '--' }}</td>
                                <td class="ta-td text-right font-semibold">{{ number_format($row->quantity) }}</td>
                                <td class="ta-td text-right"><x-money :amount="$row->revenue" /></td>
                                <td class="ta-td text-right"><x-money :amount="$row->avg_price" /></td>
                                <td class="ta-td text-right">{{ number_format($row->orders) }}</td>
                                <td class="ta-td text-right text-muted">
                                    {{ $row->orders > 0 ? number_format($row->quantity / $row->orders, 1) : '--' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card padding="p-0" title="Colour demand" subtitle="Units sold by colour">
            @if ($colours->isEmpty())
                <x-empty-state icon="fa-palette" title="No colour data in this period" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-line dark:border-strokedark">
                            <tr>
                                <th class="ta-th">Colour</th>
                                <th class="ta-th text-right">Units</th>
                                <th class="ta-th text-right">Orders</th>
                                <th class="ta-th text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line dark:divide-strokedark">
                            @foreach ($colours as $row)
                                <tr>
                                    <td class="ta-td font-medium">{{ $row->name }}</td>
                                    <td class="ta-td text-right font-semibold">{{ number_format($row->quantity) }}</td>
                                    <td class="ta-td text-right">{{ number_format($row->orders) }}</td>
                                    <td class="ta-td text-right"><x-money :amount="$row->revenue" compact /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card padding="p-0" title="Categories" subtitle="Units and revenue by category">
            @if ($categories->isEmpty())
                <x-empty-state icon="fa-layer-group" title="No category data in this period" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-line dark:border-strokedark">
                            <tr>
                                <th class="ta-th">Category</th>
                                <th class="ta-th text-right">Units</th>
                                <th class="ta-th text-right">Orders</th>
                                <th class="ta-th text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line dark:divide-strokedark">
                            @foreach ($categories as $row)
                                <tr>
                                    <td class="ta-td font-medium">{{ $row->name }}</td>
                                    <td class="ta-td text-right font-semibold">{{ number_format($row->quantity) }}</td>
                                    <td class="ta-td text-right">{{ number_format($row->orders) }}</td>
                                    <td class="ta-td text-right"><x-money :amount="$row->revenue" compact /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function initCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(initCharts, 50);
                return;
            }

            const dark = document.documentElement.classList.contains('dark');
            const tick = dark ? '#98A2B3' : '#667085';
            const palette = ['#465FFF', '#12B76A', '#F79009', '#7A5AF8', '#06AED4', '#F04438', '#DC6803', '#2E90FA'];

            const bar = (id, labels, values, label) => {
                const el = document.getElementById(id);
                if (!el || el._chartInstance) return;

                el._chartInstance = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{ label, data: values, backgroundColor: palette, borderRadius: 4 }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { color: tick } },
                            y: { grid: { display: false }, ticks: { color: tick } },
                        },
                    },
                });
            };

            bar('productRevenue', @json($byRevenue->pluck('name')), @json($byRevenue->pluck('revenue')), 'Revenue');
            bar('categoryRevenue', @json($categories->pluck('name')), @json($categories->pluck('revenue')), 'Revenue');
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCharts);
        } else {
            initCharts();
        }
    })();
</script>
@endpush
