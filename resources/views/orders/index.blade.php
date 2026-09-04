@extends('layouts.app')
@section('title', 'All Orders')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="orders.export" />
    @endcan
    @can('manage-orders')
        <a href="{{ route('orders.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add New Order
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-3">

    {{-- ========================= Compact Top KPI Bar ========================= --}}
    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-xl border border-line bg-white px-3.5 py-2.5 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </span>
                <div>
                    <span class="text-[11px] font-bold text-muted block leading-tight">Orders Matched</span>
                    <span class="text-base font-black text-ink dark:text-white leading-tight">{{ number_format($totals->orders ?? 0) }}</span>
                </div>
            </div>
            <span class="text-[10px] text-muted">Excl. cancelled</span>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-line bg-white px-3.5 py-2.5 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 text-xs">
                    <i class="fa-solid fa-sack-dollar"></i>
                </span>
                <div>
                    <span class="text-[11px] font-bold text-muted block leading-tight">Matched Revenue</span>
                    <span class="text-base font-black text-emerald-600 dark:text-emerald-400 leading-tight"><x-money :amount="$totals->revenue ?? 0" compact /></span>
                </div>
            </div>
            <span class="text-[10px] text-muted">In period</span>
        </div>

        <div class="flex items-center justify-between rounded-xl border border-line bg-white px-3.5 py-2.5 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500 text-xs">
                    <i class="fa-solid fa-hourglass-half"></i>
                </span>
                <div>
                    <span class="text-[11px] font-bold text-muted block leading-tight">Outstanding Balance</span>
                    <span class="text-base font-black text-amber-600 dark:text-amber-400 leading-tight"><x-money :amount="$totals->outstanding ?? 0" compact /></span>
                </div>
            </div>
            <span class="text-[10px] text-muted">Uncollected</span>
        </div>
    </div>

    {{-- ========================= Compact 1-Row Filter Bar ========================= --}}
    @php
        $hasAdvancedFilters = !empty($filters['zip_code']) || !empty($filters['category_id']) || !empty($filters['performance']) || !empty($filters['from']) || !empty($filters['to']);
    @endphp
    <div class="rounded-xl border border-line bg-white p-2.5 shadow-xs dark:border-strokedark dark:bg-boxdark"
         x-data="{ showAdvanced: {{ $hasAdvancedFilters ? 'true' : 'false' }} }">
        <form method="GET" class="space-y-2">
            {{-- Main 1-Row Filter Controls --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-muted text-xs"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Search order #, customer, phone, product..."
                           class="ta-input !py-1.5 !pl-8 !text-xs w-full">
                </div>

                {{-- Status --}}
                <div class="w-36">
                    <select name="order_status" class="ta-input !py-1.5 !text-xs w-full font-semibold">
                        <option value="">Status: All</option>
                        <option value="new" @selected(($filters['order_status'] ?? '') === 'new')>Pending</option>
                        <option value="delivered" @selected(($filters['order_status'] ?? '') === 'delivered')>Delivered</option>
                        <option value="cancelled" @selected(($filters['order_status'] ?? '') === 'cancelled')>Cancelled</option>
                    </select>
                </div>

                {{-- Sales Person --}}
                <div class="w-36">
                    <select name="sales_person_id" class="ta-input !py-1.5 !text-xs w-full">
                        <option value="">Seller: All</option>
                        @foreach ($salesPersons as $person)
                            <option value="{{ $person->id }}" @selected((int) ($filters['sales_person_id'] ?? 0) === $person->id)>
                                {{ $person->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-1.5">
                    <button type="submit" class="btn btn-primary !py-1.5 !px-3 !text-xs">
                        <i class="fa-solid fa-filter text-[10px]"></i> Filter
                    </button>
                    @if (request()->hasAny(['search', 'order_status', 'payment_status', 'sales_person_id', 'zip_code', 'category_id', 'performance', 'from', 'to']))
                        <a href="{{ route('orders.index') }}" class="btn btn-light !py-1.5 !px-2.5 !text-xs text-muted" title="Clear Filters">
                            <i class="fa-solid fa-rotate-left text-[10px]"></i>
                        </a>
                    @endif
                    <button type="button"
                            x-on:click="showAdvanced = !showAdvanced"
                            :class="showAdvanced || {{ $hasAdvancedFilters ? 'true' : 'false' }} ? 'bg-brand/10 text-brand border-brand/30' : 'bg-surface text-muted border-line dark:border-strokedark dark:bg-boxdark2'"
                            class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition"
                            title="Toggle more filters">
                        <i class="fa-solid fa-sliders text-[10px]"></i>
                        <span x-text="showAdvanced ? 'Less' : 'More'"></span>
                    </button>
                </div>
            </div>

            {{-- Collapsible Advanced Filters (ZIP, Category, Delivery, Dates) --}}
            <div x-show="showAdvanced" x-collapse x-cloak class="pt-2 border-t border-line/60 dark:border-strokedark grid grid-cols-2 gap-2 sm:grid-cols-5 text-xs">
                <div>
                    <label class="text-[10px] font-bold text-muted block mb-0.5">ZIP Code</label>
                    <input type="text" name="zip_code" value="{{ $filters['zip_code'] ?? '' }}" class="ta-input !py-1 !text-xs" placeholder="e.g. 54000">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted block mb-0.5">Category</label>
                    <select name="category_id" class="ta-input !py-1 !text-xs">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted block mb-0.5">Delivery Status</label>
                    <select name="performance" class="ta-input !py-1 !text-xs">
                        <option value="">All Delivery</option>
                        <option value="pending" @selected(($filters['performance'] ?? '') === 'pending')>Not yet delivered</option>
                        <option value="on_time" @selected(($filters['performance'] ?? '') === 'on_time')>Delivered on time</option>
                        <option value="late" @selected(($filters['performance'] ?? '') === 'late')>Delivered late</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted block mb-0.5">Created From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ta-input !py-1 !text-xs">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted block mb-0.5">Created To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ta-input !py-1 !text-xs">
                </div>
            </div>
        </form>
    </div>

    {{-- ========================= Results ========================= --}}
    <x-card padding="p-0">
        @if ($orders->isEmpty())
            <x-empty-state icon="fa-receipt" title="No orders found"
                           message="Adjust the filters above, or create the first order.">
                @can('manage-orders')
                    <x-slot:action>
                        <a href="{{ route('orders.create') }}" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> Add New Order
                        </a>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @else
            {{-- Desktop compact table with NO horizontal scrolling --}}
            <div class="hidden lg:block">
                <table class="w-full text-left table-auto">
                    <thead class="border-b border-line dark:border-strokedark bg-surface/40 dark:bg-boxdark2">
                        <tr>
                            <th class="ta-th py-3 px-3">Order</th>
                            <th class="ta-th py-3 px-3">Customer</th>
                            <th class="ta-th py-3 px-3">Items</th>
                            <th class="ta-th py-3 px-3">Sales Person</th>
                            <th class="ta-th py-3 px-3">Delivery</th>
                            <th class="ta-th py-3 px-3">Order Status</th>
                            <th class="ta-th py-3 px-3 text-right">Total</th>
                            <th class="ta-th py-3 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark text-xs">
                        @foreach ($orders as $order)
                            <tr class="transition hover:bg-surface/60 dark:hover:bg-boxdark/50">
                                {{-- Order Number & Date --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-brand hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="text-[10px] text-muted">{{ $order->order_created_at?->format('d M Y') }}</p>
                                </td>

                                {{-- Customer Name & Contact --}}
                                <td class="py-2.5 px-3">
                                    <p class="font-bold text-ink dark:text-white truncate max-w-[150px]" title="{{ $order->customer?->name }}">
                                        {{ $order->customer?->name ?? 'Unknown' }}
                                    </p>
                                    <p class="text-[10px] text-muted truncate max-w-[150px]">
                                        {{ $order->customer?->phone }}
                                        @if ($order->zip_code) &middot; <span class="font-medium text-ink dark:text-gray-300">{{ $order->zip_code }}</span> @endif
                                    </p>
                                </td>

                                {{-- Items Summary --}}
                                <td class="py-2.5 px-3 max-w-[170px]">
                                    <p class="truncate font-medium text-ink dark:text-gray-200" title="{{ $order->itemSummary(5) }}">
                                        {{ $order->itemSummary(2) }}
                                    </p>
                                    <span class="text-[10px] text-muted">{{ $order->totalQuantity() }} item(s)</span>
                                </td>

                                {{-- Sales Person --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-ink dark:text-gray-300">
                                        <i class="fa-solid fa-user-tie text-[10px] text-muted"></i>
                                        <span>{{ $order->salesPerson?->name ?? '--' }}</span>
                                    </span>
                                </td>

                                 {{-- Delivery Schedule & Inline Record Delivery --}}
                                <td class="py-2.5 px-3">
                                    <div class="whitespace-nowrap">
                                        <p class="font-medium text-ink dark:text-gray-200">{{ $order->requested_delivery_date?->format('d M Y') }}</p>
                                        
                                        @if ($order->order_status === \App\Enums\OrderStatus::Delivered && $order->actual_delivery_date)
                                            <span class="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-1.5 py-0.2 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                                <i class="fa-solid fa-check-circle text-[9px]"></i>
                                                <span>Delivered {{ $order->actual_delivery_date->format('d M') }}</span>
                                            </span>
                                        @elseif ($order->isOverdue() && $order->order_status !== \App\Enums\OrderStatus::Cancelled)
                                            <span class="inline-flex items-center gap-1 rounded bg-danger/10 px-1.5 py-0.2 text-[10px] font-bold text-danger">
                                                <i class="fa-solid fa-clock text-[9px]"></i> Overdue
                                            </span>
                                        @endif

                                        {{-- Inline Mark Delivered Action --}}
                                        @can('manage-orders')
                                            @if ($order->order_status !== \App\Enums\OrderStatus::Delivered && $order->order_status !== \App\Enums\OrderStatus::Cancelled)
                                                <form method="POST" action="{{ route('orders.deliver', $order) }}" class="mt-1">
                                                    @csrf
                                                    <input type="hidden" name="actual_delivery_date" value="{{ today()->toDateString() }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 rounded bg-surface hover:bg-brand hover:text-white px-2 py-0.5 text-[10px] font-bold text-brand transition border border-line dark:border-strokedark dark:bg-boxdark2"
                                                            title="Mark order delivered today">
                                                        <i class="fa-solid fa-truck-ramp-box text-[9px]"></i>
                                                        <span>Mark Delivered</span>
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>

                                {{-- Inline Order Status Dropdown --}}
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    @can('manage-orders')
                                        <form method="POST" action="{{ route('orders.status', $order) }}" x-data x-ref="statusForm{{ $order->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="order_status"
                                                    x-on:change="$refs['statusForm{{ $order->id }}'].submit()"
                                                    class="rounded-lg text-[11px] font-bold px-2.5 py-1 border shadow-xs transition cursor-pointer focus:ring-1 focus:outline-none {{ $order->order_status === \App\Enums\OrderStatus::Delivered ? '!bg-emerald-600 !text-white !border-emerald-600' : ($order->order_status === \App\Enums\OrderStatus::Cancelled ? '!bg-red-600 !text-white !border-red-600' : '!bg-amber-100 !text-amber-800 !border-amber-300 dark:!bg-amber-900/50 dark:!text-amber-200') }}">
                                                <option value="new" @selected(!in_array($order->order_status, [\App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled])) class="bg-white text-ink dark:bg-boxdark dark:text-white font-semibold">Pending</option>
                                                <option value="delivered" @selected($order->order_status === \App\Enums\OrderStatus::Delivered) class="bg-white text-ink dark:bg-boxdark dark:text-white font-semibold">Delivered</option>
                                                <option value="cancelled" @selected($order->order_status === \App\Enums\OrderStatus::Cancelled) class="bg-white text-ink dark:bg-boxdark dark:text-white font-semibold">Cancelled</option>
                                            </select>
                                        </form>
                                    @else
                                        <x-status-pill :status="$order->order_status" />
                                    @endcan
                                </td>

                                {{-- Total Revenue in € --}}
                                <td class="py-2.5 px-3 text-right font-black text-xs text-ink dark:text-white whitespace-nowrap">
                                    <x-money :amount="$order->grand_total" />
                                </td>

                                {{-- Action Link --}}
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1" x-data="{ copied: false, text: @js($order->copyDetailsText()) }">
                                        <button type="button"
                                                data-copy-btn="true"
                                                x-on:click="window.copyOrderToClipboard(text); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted transition hover:bg-brand/10 hover:text-brand"
                                                :class="{ '!bg-emerald-500/10 !text-emerald-600': copied }"
                                                :title="copied ? 'Copied to clipboard!' : 'Copy order details'">
                                            <i class="fa-solid text-xs" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                        </button>
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted transition hover:bg-brand/10 hover:text-brand"
                                           title="View Order Details">
                                            <i class="fa-solid fa-arrow-right text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="divide-y divide-line lg:hidden dark:divide-strokedark">
                @foreach ($orders as $order)
                    <div class="p-4 transition hover:bg-surface dark:hover:bg-boxdark/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('orders.show', $order) }}" class="font-bold text-brand hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <p class="truncate text-sm font-medium text-ink dark:text-gray-200">{{ $order->customer?->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $order->itemSummary(2) }}</p>
                            </div>
                            <span class="text-right">
                                <span class="block font-bold text-ink dark:text-white"><x-money :amount="$order->grand_total" /></span>
                                <span class="mt-1 block text-xs text-muted">{{ $order->requested_delivery_date?->format('d M Y') }}</span>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                @can('manage-orders')
                                    <form method="POST" action="{{ route('orders.status', $order) }}" x-data x-ref="statusFormM{{ $order->id }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="order_status"
                                                x-on:change="$refs['statusFormM{{ $order->id }}'].submit()"
                                                class="rounded-lg text-xs font-bold px-2 py-1 border shadow-xs {{ $order->order_status === \App\Enums\OrderStatus::Delivered ? '!bg-emerald-600 !text-white !border-emerald-600' : ($order->order_status === \App\Enums\OrderStatus::Cancelled ? '!bg-red-600 !text-white !border-red-600' : '!bg-amber-100 !text-amber-800 !border-amber-300') }}">
                                            <option value="new" @selected(!in_array($order->order_status, [\App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled])) class="bg-white text-ink">Pending</option>
                                            <option value="delivered" @selected($order->order_status === \App\Enums\OrderStatus::Delivered) class="bg-white text-ink">Delivered</option>
                                            <option value="cancelled" @selected($order->order_status === \App\Enums\OrderStatus::Cancelled) class="bg-white text-ink">Cancelled</option>
                                        </select>
                                    </form>
                                @else
                                    <x-status-pill :status="$order->order_status" />
                                @endcan
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        data-copy-btn="true"
                                        x-data="{ copied: false, text: @js($order->copyDetailsText()) }"
                                        x-on:click="window.copyOrderToClipboard(text); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-muted hover:text-brand"
                                        :class="{ '!text-emerald-600': copied }">
                                    <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                </button>
                                <a href="{{ route('orders.show', $order) }}" class="text-xs font-semibold text-brand hover:underline">
                                    Details &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-line px-5 py-3.5 dark:border-strokedark">
                {{ $orders->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
