@extends('layouts.app')
@section('title', 'Order ' . $order->display_number)
@section('breadcrumb')
    <a href="{{ route('orders.index') }}" class="hover:text-brand">Sales</a> / {{ $order->display_number }} <span class="text-xs text-muted">({{ $order->order_number }})</span>
@endsection

@section('header_actions')
    <button type="button"
            data-copy-btn="true"
            x-data="{ copied: false, text: @js($order->copyDetailsText()) }"
            x-on:click="window.copyOrderToClipboard(text); copied = true; setTimeout(() => copied = false, 2000)"
            class="btn btn-light"
            :class="{ '!bg-emerald-500/10 !text-emerald-600 !border-emerald-500/30': copied }">
        <i class="fa-solid" :class="copied ? 'fa-check text-emerald-600' : 'fa-copy'"></i>
        <span x-text="copied ? 'Copied Details!' : 'Copy Details'"></span>
    </button>
    @can('update', $order)
        <a href="{{ route('orders.edit', $order) }}" class="btn btn-light">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
    @endcan
    @can('cancel', $order)
        <button type="button" class="btn btn-light text-danger" x-data @click="$dispatch('open-cancel')">
            <i class="fa-solid fa-ban"></i> Cancel order
        </button>
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    @if ($order->order_status === \App\Enums\OrderStatus::Cancelled)
        <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            <i class="fa-solid fa-ban mr-1"></i>
            This order was cancelled and is now read-only.
            @if ($order->cancellation_reason) Reason: {{ $order->cancellation_reason }} @endif
        </div>
    @elseif ($order->isOverdue())
        <div class="rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            The requested delivery date passed {{ $order->requested_delivery_date->diffForHumans() }} and no delivery has been recorded.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- ==================== Items ==================== --}}
            <x-card padding="p-0" title="Items" :subtitle="$order->totalQuantity() . ' units across ' . $order->items->count() . ' line(s)'">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-line dark:border-strokedark">
                            <tr>
                                <th class="ta-th">Item</th>
                                <th class="ta-th">Colour</th>
                                <th class="ta-th text-right">Qty</th>
                                <th class="ta-th text-right">Unit price</th>
                                <th class="ta-th text-right">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line dark:divide-strokedark">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="ta-td">
                                        <p class="font-medium text-ink dark:text-white">{{ $item->item_name_snapshot }}</p>
                                        @if ($item->category_name_snapshot)
                                            <p class="text-xs text-muted">{{ $item->category_name_snapshot }}</p>
                                        @endif
                                        @if ($item->notes)
                                            <p class="mt-1 text-xs italic text-muted">{{ $item->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="ta-td">
                                        @if ($item->item_colour)
                                            <span class="flex items-center gap-2">
                                                <span class="h-3 w-3 rounded-full border border-line"
                                                      style="background-color: {{ $item->colour?->swatch() ?? '#98A2B3' }}"></span>
                                                <span class="font-medium text-ink dark:text-gray-200">{{ $item->item_colour }}</span>
                                            </span>
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </td>
                                    <td class="ta-td text-right font-bold text-ink dark:text-white">{{ $item->quantity }}</td>
                                    <td class="ta-td text-right font-medium text-ink dark:text-gray-200"><x-money :amount="$item->unit_price" /></td>
                                    <td class="ta-td text-right font-bold text-brand"><x-money :amount="$item->line_total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-line dark:border-strokedark">
                            <tr class="bg-surface/50 dark:bg-boxdark/60">
                                <td colspan="4" class="ta-td text-right font-bold text-ink dark:text-white">Grand total</td>
                                <td class="ta-td text-right text-lg font-black text-brand"><x-money :amount="$order->grand_total" /></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            {{-- ==================== Customer ==================== --}}
            <x-card title="Customer">
                <x-slot:actions>
                    @if ($order->customer)
                        <a href="{{ route('customers.show', $order->customer) }}" class="text-sm font-medium text-brand hover:underline">
                            Full profile
                        </a>
                    @endif
                </x-slot:actions>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">Name</dt>
                        <dd class="mt-0.5 font-medium text-ink dark:text-gray-200">{{ $order->customer?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">Phone</dt>
                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $order->customer?->phone ?? '--' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">Email</dt>
                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $order->customer?->email ?? '--' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">ZIP / postal code</dt>
                        <dd class="mt-0.5 font-semibold text-ink dark:text-gray-200">{{ $order->zip_code ?? '--' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted">Address</dt>
                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $order->customer?->fullAddress() ?: '--' }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- ==================== Previous orders ==================== --}}
            @if ($history->isNotEmpty())
                <x-card padding="p-0" title="Previous orders from this customer">
                    <div class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($history as $previous)
                            <a href="{{ route('orders.show', $previous) }}"
                               class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <span>
                                    <span class="block text-sm font-semibold text-brand" title="{{ $previous->order_number }}">{{ $previous->display_number }}</span>
                                    <span class="block text-xs text-muted">{{ $previous->order_created_at?->format('d M Y') }}</span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <x-status-pill :status="$previous->order_status" />
                                    <span class="text-sm font-semibold text-ink dark:text-white"><x-money :amount="$previous->grand_total" /></span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($order->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-ink dark:text-gray-300">{{ $order->notes }}</p>
                </x-card>
            @endif
        </div>

        {{-- ==================== Sidebar ==================== --}}
        <div class="space-y-6">

            <x-card title="Status">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted">Order status</span>
                        <x-status-pill :status="$order->order_status" />
                    </div>

                    @can('changeStatus', $order)
                        <form method="POST" action="{{ route('orders.status', $order) }}"
                              class="border-t border-line pt-4 dark:border-strokedark">
                            @csrf @method('PATCH')
                            <label class="ta-label">Change Status</label>
                            <div class="flex gap-2">
                                <select name="order_status" class="ta-input font-bold">
                                    <option value="new" @selected(!in_array($order->order_status, [\App\Enums\OrderStatus::Delivered, \App\Enums\OrderStatus::Cancelled]))>Pending</option>
                                    <option value="delivered" @selected($order->order_status === \App\Enums\OrderStatus::Delivered)>Delivered</option>
                                    <option value="cancelled" @selected($order->order_status === \App\Enums\OrderStatus::Cancelled)>Cancelled</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    @endcan
                </div>
            </x-card>

            {{-- Dates: three separate fields, never overwritten (requirements 9.2) --}}
            <x-card title="Dates">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Order created</dt>
                        <dd class="text-right font-medium text-ink dark:text-gray-200">{{ $order->order_created_at?->format('d M Y h:i A') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Requested delivery</dt>
                        <dd class="text-right font-medium text-ink dark:text-gray-200">{{ $order->requested_delivery_date?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Actual delivery</dt>
                        <dd class="text-right font-medium text-ink dark:text-gray-200">
                            {{ $order->actual_delivery_date?->format('d M Y') ?? 'Not yet delivered' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-line pt-3 dark:border-strokedark">
                        <dt class="text-muted">Performance</dt>
                        <dd>
                            <span class="ta-badge"
                                  style="background-color: {{ $order->deliveryPerformance()->color() }}1A; color: {{ $order->deliveryPerformance()->color() }};">
                                {{ $order->deliveryPerformance()->label() }}
                                @if ($order->daysLate() > 0) ({{ $order->daysLate() }}d) @endif
                            </span>
                        </dd>
                    </div>
                </dl>

                @can('recordDelivery', $order)
                    @unless ($order->actual_delivery_date)
                        <form method="POST" action="{{ route('orders.deliver', $order) }}"
                              class="mt-4 border-t border-line pt-4 dark:border-strokedark">
                            @csrf @method('PATCH')
                            <label class="ta-label">Record actual delivery</label>
                            <div class="flex gap-2">
                                <input type="date" name="actual_delivery_date" required
                                       max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="ta-input">
                                <button type="submit" class="btn btn-primary whitespace-nowrap">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </div>
                            @error('actual_delivery_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            <p class="mt-1 text-xs text-muted">The requested date above stays unchanged.</p>
                        </form>
                    @endunless
                @endcan
            </x-card>

            <x-card title="Record">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Grand total</dt><dd class="font-bold text-ink dark:text-white"><x-money :amount="$order->grand_total" /></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Sales Person</dt><dd class="text-ink dark:text-gray-200">{{ $order->salesPerson?->name ?? 'Not assigned' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Entered by</dt><dd class="text-ink dark:text-gray-200">{{ $order->creator?->name ?? 'System' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Last updated by</dt><dd class="text-ink dark:text-gray-200">{{ $order->updater?->name ?? '--' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Last updated</dt><dd class="text-ink dark:text-gray-200">{{ $order->updated_at?->diffForHumans() }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</div>

{{-- Cancel confirmation --}}
@can('cancel', $order)
    <div x-data="{ open: false }" x-on:open-cancel.window="open = true" x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div x-on:click.outside="open = false" class="w-full max-w-md rounded-2xl bg-white p-6 dark:bg-boxdark2">
            <h3 class="text-lg font-semibold text-ink dark:text-white">Cancel Order {{ $order->display_number }}?</h3>
            <p class="mt-1 text-sm text-muted">
                The order stops counting towards revenue and becomes read-only. This is recorded in the audit log.
            </p>
            <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-4 space-y-3">
                @csrf @method('PATCH')
                <div>
                    <label class="ta-label">Reason (optional)</label>
                    <textarea name="cancellation_reason" rows="3" maxlength="500" class="ta-input"></textarea>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-danger flex-1">Cancel order</button>
                    <button type="button" class="btn btn-light" x-on:click="open = false">Keep order</button>
                </div>
            </form>
        </div>
    </div>
@endcan
@endsection
