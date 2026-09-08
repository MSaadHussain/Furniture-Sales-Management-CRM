@extends('layouts.app')
@section('title', $customer->name)
@section('breadcrumb')
    <a href="{{ route('customers.index') }}" class="hover:text-brand">Customers</a> / {{ $customer->name }}
@endsection

@section('header_actions')
    @can('manage-orders')
        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New order
        </a>
    @endcan
    @can('update', $customer)
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-light">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    {{-- Customer history summary (requirements 7.4) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Orders" icon="fa-receipt" icon-color="#465FFF" :value="number_format($stats['orders'])"
                     :hint="$customer->isReturning() ? 'Returning customer' : 'First-time customer'" />
        <x-stat-card label="Total spent" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($stats['total_spent'])" />
        <x-stat-card label="First order" icon="fa-calendar-plus" icon-color="#7A5AF8"
                     :value="$stats['first_order']?->format('d M Y') ?? '--'" />
        <x-stat-card label="Last order" icon="fa-calendar-check" icon-color="#F79009"
                     :value="$stats['last_order']?->format('d M Y') ?? '--'"
                     :hint="$stats['delivered'] . ' delivered'" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            <x-card padding="p-0" title="Order history">
                @if ($orders->isEmpty())
                    <x-empty-state icon="fa-receipt" title="No orders yet"
                                   message="This customer has no orders on record." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px]">
                            <thead class="border-b border-line dark:border-strokedark">
                                <tr>
                                    <th class="ta-th">Order</th>
                                    <th class="ta-th">Items</th>
                                    <th class="ta-th">Requested</th>
                                    <th class="ta-th">Delivered</th>
                                    <th class="ta-th">Status</th>
                                    <th class="ta-th text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line dark:divide-strokedark">
                                @foreach ($orders as $order)
                                    <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                        <td class="ta-td">
                                            <a href="{{ route('orders.show', $order) }}" class="font-bold text-sm text-brand hover:underline" title="{{ $order->order_number }}">
                                                {{ $order->display_number }}
                                            </a>
                                            <p class="text-xs text-muted">{{ $order->order_created_at?->format('d M Y') }}</p>
                                        </td>
                                        <td class="ta-td max-w-[200px]">
                                            <span class="block truncate" title="{{ $order->itemSummary(5) }}">{{ $order->itemSummary(2) }}</span>
                                        </td>
                                        <td class="ta-td">{{ $order->requested_delivery_date?->format('d M Y') }}</td>
                                        <td class="ta-td">
                                            @if ($order->actual_delivery_date)
                                                <span style="color: {{ $order->deliveryPerformance()->color() }}">
                                                    {{ $order->actual_delivery_date->format('d M Y') }}
                                                </span>
                                            @else
                                                <span class="text-muted">--</span>
                                            @endif
                                        </td>
                                        <td class="ta-td"><x-status-pill :status="$order->order_status" /></td>
                                        <td class="ta-td text-right font-semibold"><x-money :amount="$order->grand_total" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $orders->links() }}</div>
                @endif
            </x-card>

            <x-card padding="p-0" title="Products previously purchased">
                @if ($products->isEmpty())
                    <x-empty-state icon="fa-chair" title="Nothing purchased yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px]">
                            <thead class="border-b border-line dark:border-strokedark">
                                <tr>
                                    <th class="ta-th">Item</th>
                                    <th class="ta-th">Colour</th>
                                    <th class="ta-th text-right">Units</th>
                                    <th class="ta-th">Last bought</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line dark:divide-strokedark">
                                @foreach ($products as $row)
                                    <tr>
                                        <td class="ta-td font-medium">{{ $row->name }}</td>
                                        <td class="ta-td">{{ $row->colour ?: '--' }}</td>
                                        <td class="ta-td text-right">{{ $row->quantity }}</td>
                                        <td class="ta-td text-muted">
                                            {{ \Illuminate\Support\Carbon::parse($row->last_bought)->format('d M Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Contact">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs uppercase tracking-wide text-muted">Customer ID</dt><dd class="mt-0.5 font-medium text-ink dark:text-gray-200">#{{ $customer->id }}</dd></div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">Phone</dt>
                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $customer->phone }}</dd>
                        @if ($customer->phone_alt)
                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $customer->phone_alt }} <span class="text-xs text-muted">(alt)</span></dd>
                        @endif
                    </div>
                    <div><dt class="text-xs uppercase tracking-wide text-muted">Email</dt><dd class="mt-0.5 break-all text-ink dark:text-gray-200">{{ $customer->email ?: '--' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-muted">Address</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $customer->address ?: '--' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-muted">City / State</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ collect([$customer->city, $customer->state])->filter()->implode(', ') ?: '--' }}</dd></div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-muted">ZIP / postal code</dt>
                        <dd class="mt-0.5">
                            <a href="{{ route('reports.zip', ['zip' => $customer->zip_code]) }}"
                               class="font-semibold text-brand hover:underline">{{ $customer->zip_code }}</a>
                        </dd>
                    </div>
                </dl>
            </x-card>

            @if ($customer->notes)
                <x-card title="Notes">
                    <p class="whitespace-pre-line text-sm text-ink dark:text-gray-300">{{ $customer->notes }}</p>
                </x-card>
            @endif

            @can('delete', $customer)
                <x-card title="Danger zone">
                    <p class="text-sm text-muted">
                        A customer with order history cannot be deleted; historical sales data is preserved.
                    </p>
                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-4"
                          onsubmit="return confirm('Delete this customer permanently?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger w-full">
                            <i class="fa-solid fa-trash"></i> Delete customer
                        </button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</div>
@endsection
