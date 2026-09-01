@extends('layouts.app')
@section('title', 'Products')

@section('header_actions')
    @can('export-data')
        <x-export-menu route="products.export" />
    @endcan
    @can('create', App\Models\Product::class)
        <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Product
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="ta-label">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Product name or code" class="ta-input">
            </div>
            <div>
                <label class="ta-label">Category</label>
                <select name="category_id" class="ta-input">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">Status</label>
                <select name="status" class="ta-input">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-4">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="{{ route('products.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card padding="p-0">
        @if ($products->isEmpty())
            <x-empty-state icon="fa-chair" title="No products yet"
                           message="Add the furniture you sell so orders can be entered from a catalogue instead of free text." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Code</th>
                            <th class="ta-th">Product</th>
                            <th class="ta-th">Category</th>
                            <th class="ta-th text-right">Default price</th>
                            <th class="ta-th text-right">Times ordered</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($products as $product)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td font-mono text-xs text-muted">{{ $product->product_code }}</td>
                                <td class="ta-td">
                                    <a href="{{ route('products.show', $product) }}" class="font-semibold text-brand hover:underline">
                                        {{ $product->name }}
                                    </a>
                                </td>
                                <td class="ta-td">{{ $product->category?->name }}</td>
                                <td class="ta-td text-right"><x-money :amount="$product->default_price" /></td>
                                <td class="ta-td text-right">{{ $product->order_items_count }}</td>
                                <td class="ta-td">
                                    @if ($product->is_active)
                                        <span class="ta-badge bg-success/10 text-success">Active</span>
                                    @else
                                        <span class="ta-badge bg-muted/10 text-muted">Inactive</span>
                                    @endif
                                </td>
                                <td class="ta-td">
                                    <div class="flex items-center justify-end gap-3">
                                        @can('update', $product)
                                            <form method="POST" action="{{ route('products.toggle', $product) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-muted hover:text-brand"
                                                        title="{{ $product->is_active ? 'Deactivate' : 'Activate' }}">
                                                    <i class="fa-solid {{ $product->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('products.edit', $product) }}" class="text-muted hover:text-brand" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        @endcan
                                        @can('delete', $product)
                                            <form method="POST" action="{{ route('products.destroy', $product) }}"
                                                  onsubmit="return confirm('Remove this product?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-muted hover:text-danger" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $products->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
