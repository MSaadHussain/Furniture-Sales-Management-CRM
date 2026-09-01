@extends('layouts.app')
@section('title', 'Customers')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="customers.export" />
    @endcan
    @can('create', App\Models\Customer::class)
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Customer
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
                <label class="ta-label">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Name, phone, email, ZIP or customer ID" class="ta-input">
            </div>
            <div>
                <label class="ta-label">ZIP code</label>
                <input type="text" name="zip_code" value="{{ $filters['zip_code'] ?? '' }}" class="ta-input">
            </div>
            <div>
                <label class="ta-label">City</label>
                <select name="city" class="ta-input">
                    <option value="">All</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">Type</label>
                <select name="type" class="ta-input">
                    <option value="">All</option>
                    <option value="returning" @selected(($filters['type'] ?? '') === 'returning')>Returning</option>
                    <option value="new" @selected(($filters['type'] ?? '') === 'new')>New / single order</option>
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-5">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="{{ route('customers.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card padding="p-0">
        @if ($customers->isEmpty())
            <x-empty-state icon="fa-users" title="No customers found"
                           message="Customers are created here or automatically from the order form." />
        @else
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[900px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">ID</th>
                            <th class="ta-th">Customer</th>
                            <th class="ta-th">Contact</th>
                            <th class="ta-th">Location</th>
                            <th class="ta-th text-right">Orders</th>
                            <th class="ta-th text-right">Total spent</th>
                            <th class="ta-th">Last order</th>
                            <th class="ta-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($customers as $customer)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td text-muted">#{{ $customer->id }}</td>
                                <td class="ta-td">
                                    <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-brand hover:underline">
                                        {{ $customer->name }}
                                    </a>
                                    @if ($customer->orders_count > 1)
                                        <span class="ta-badge ml-2 bg-success/10 text-success">Returning</span>
                                    @endif
                                </td>
                                <td class="ta-td">
                                    <p>{{ $customer->phone }}</p>
                                    @if ($customer->email)<p class="text-xs text-muted">{{ $customer->email }}</p>@endif
                                </td>
                                <td class="ta-td">
                                    <p class="font-medium">{{ $customer->zip_code }}</p>
                                    <p class="text-xs text-muted">{{ $customer->city }}</p>
                                </td>
                                <td class="ta-td text-right font-semibold">{{ $customer->orders_count }}</td>
                                <td class="ta-td text-right"><x-money :amount="$customer->orders_value ?? 0" /></td>
                                <td class="ta-td text-muted">
                                    {{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y') : '--' }}
                                </td>
                                <td class="ta-td text-right">
                                    <a href="{{ route('customers.show', $customer) }}" class="text-muted hover:text-brand">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-line lg:hidden dark:divide-strokedark">
                @foreach ($customers as $customer)
                    <a href="{{ route('customers.show', $customer) }}" class="block p-4 transition hover:bg-surface dark:hover:bg-boxdark/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink dark:text-gray-200">{{ $customer->name }}</p>
                                <p class="text-xs text-muted">{{ $customer->phone }} &middot; {{ $customer->zip_code }}</p>
                            </div>
                            <span class="text-right">
                                <span class="block font-semibold text-ink dark:text-white"><x-money :amount="$customer->orders_value ?? 0" compact /></span>
                                <span class="block text-xs text-muted">{{ $customer->orders_count }} orders</span>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $customers->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
