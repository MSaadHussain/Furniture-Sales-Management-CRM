@extends('layouts.app')
@section('title', 'Product Categories')
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="hover:text-brand">Products</a> / Categories
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <x-card class="lg:col-span-2" padding="p-0" title="Categories"
            :subtitle="$categories->total() . ' categor' . ($categories->total() === 1 ? 'y' : 'ies')">
        @if ($categories->isEmpty())
            <x-empty-state icon="fa-layer-group" title="No categories yet"
                           message="Categories group your products and drive the category performance report." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Name</th>
                            <th class="ta-th text-right">Products</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th text-right">Order</th>
                            <th class="ta-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($categories as $category)
                            <tr x-data="{ editing: false }">
                                <td class="ta-td" colspan="5">
                                    {{-- Read mode --}}
                                    <div x-show="!editing" class="flex flex-wrap items-center gap-4">
                                        <span class="min-w-[160px] flex-1 font-medium text-ink dark:text-gray-200">{{ $category->name }}</span>
                                        <span class="w-24 text-right text-sm text-muted">{{ $category->products_count }} products</span>
                                        <span class="w-24">
                                            @if ($category->is_active)
                                                <span class="ta-badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="ta-badge bg-muted/10 text-muted">Inactive</span>
                                            @endif
                                        </span>
                                        <span class="w-12 text-right text-sm text-muted">{{ $category->sort_order }}</span>
                                        @can('manage-products')
                                            <span class="flex items-center gap-3">
                                                <button type="button" x-on:click="editing = true" class="text-muted hover:text-brand" title="Edit">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <form method="POST" action="{{ route('categories.destroy', $category) }}"
                                                      onsubmit="return confirm('Remove this category?');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-muted hover:text-danger" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </span>
                                        @endcan
                                    </div>

                                    {{-- Inline edit --}}
                                    @can('manage-products')
                                        <form x-show="editing" x-cloak method="POST" action="{{ route('categories.update', $category) }}"
                                              class="flex flex-wrap items-end gap-3">
                                            @csrf @method('PATCH')
                                            <div class="min-w-[180px] flex-1">
                                                <label class="ta-label">Name</label>
                                                <input type="text" name="name" value="{{ $category->name }}" required class="ta-input">
                                            </div>
                                            <div class="w-24">
                                                <label class="ta-label">Order</label>
                                                <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" class="ta-input">
                                            </div>
                                            <label class="flex h-[42px] items-center gap-2">
                                                <input type="checkbox" name="is_active" value="1" @checked($category->is_active)
                                                       class="rounded border-line text-brand focus:ring-brand/40">
                                                <span class="text-sm text-ink dark:text-gray-300">Active</span>
                                            </label>
                                            <button type="submit" class="btn btn-primary">Save</button>
                                            <button type="button" class="btn btn-light" x-on:click="editing = false">Cancel</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $categories->links() }}</div>
        @endif
    </x-card>

    @can('manage-products')
        <x-card title="Add a category">
            <form method="POST" action="{{ route('categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="ta-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                           class="ta-input @error('name') !border-danger @enderror" placeholder="e.g. Coffee Table">
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ta-label">Description</label>
                    <textarea name="description" rows="3" maxlength="1000" class="ta-input">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="ta-label">Sort order</label>
                    <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="ta-input">
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-brand focus:ring-brand/40">
                    <span class="text-sm text-ink dark:text-gray-300">Active</span>
                </label>
                <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-plus"></i> Add category</button>
            </form>
        </x-card>
    @endcan
</div>
@endsection
