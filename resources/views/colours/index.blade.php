@extends('layouts.app')
@section('title', 'Product Colours')
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="hover:text-brand">Products</a> / Colours
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <x-card class="lg:col-span-2" padding="p-0" title="Colour master"
            subtitle="Item colour is captured on every order line, which is what drives the colour demand report">
        @if ($colours->isEmpty())
            <x-empty-state icon="fa-palette" title="No colours yet"
                           message="Add the finishes you sell so colour demand can be reported." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Colour</th>
                            <th class="ta-th text-right">Products</th>
                            <th class="ta-th text-right">Units sold</th>
                            <th class="ta-th">Status</th>
                            <th class="ta-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($colours as $colour)
                            <tr x-data="{ editing: false }">
                                <td class="ta-td" colspan="5">
                                    <div x-show="!editing" class="flex flex-wrap items-center gap-4">
                                        <span class="flex min-w-[160px] flex-1 items-center gap-3">
                                            <span class="h-6 w-6 flex-shrink-0 rounded-lg border border-line"
                                                  style="background-color: {{ $colour->swatch() }}"></span>
                                            <span class="font-medium text-ink dark:text-gray-200">{{ $colour->name }}</span>
                                            <span class="font-mono text-xs text-muted">{{ $colour->hex }}</span>
                                        </span>
                                        <span class="w-24 text-right text-sm text-muted">{{ $colour->products_count }} products</span>
                                        <span class="w-24 text-right text-sm font-semibold text-ink dark:text-white">
                                            {{ number_format($demand[$colour->id] ?? 0) }}
                                        </span>
                                        <span class="w-24">
                                            @if ($colour->is_active)
                                                <span class="ta-badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="ta-badge bg-muted/10 text-muted">Inactive</span>
                                            @endif
                                        </span>
                                        @can('manage-products')
                                            <span class="flex items-center gap-3">
                                                <button type="button" x-on:click="editing = true" class="text-muted hover:text-brand" title="Edit">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <form method="POST" action="{{ route('colours.destroy', $colour) }}"
                                                      onsubmit="return confirm('Remove this colour? Past orders keep the colour name on record.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-muted hover:text-danger" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </span>
                                        @endcan
                                    </div>

                                    @can('manage-products')
                                        <form x-show="editing" x-cloak method="POST" action="{{ route('colours.update', $colour) }}"
                                              class="flex flex-wrap items-end gap-3">
                                            @csrf @method('PATCH')
                                            <div class="min-w-[160px] flex-1">
                                                <label class="ta-label">Name</label>
                                                <input type="text" name="name" value="{{ $colour->name }}" required class="ta-input">
                                            </div>
                                            <div class="w-32">
                                                <label class="ta-label">Hex</label>
                                                <input type="color" name="hex" value="{{ $colour->swatch() }}" class="ta-input h-[42px] p-1">
                                            </div>
                                            <div class="w-24">
                                                <label class="ta-label">Order</label>
                                                <input type="number" name="sort_order" value="{{ $colour->sort_order }}" min="0" class="ta-input">
                                            </div>
                                            <label class="flex h-[42px] items-center gap-2">
                                                <input type="checkbox" name="is_active" value="1" @checked($colour->is_active)
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
        @endif
    </x-card>

    @can('manage-products')
        <x-card title="Add a colour">
            <form method="POST" action="{{ route('colours.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="ta-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="80" value="{{ old('name') }}"
                           class="ta-input @error('name') !border-danger @enderror" placeholder="e.g. Walnut">
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ta-label">Swatch</label>
                    <input type="color" name="hex" value="{{ old('hex', '#8E8E93') }}" class="ta-input h-[46px] p-1">
                    @error('hex')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ta-label">Sort order</label>
                    <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="ta-input">
                </div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-brand focus:ring-brand/40">
                    <span class="text-sm text-ink dark:text-gray-300">Active</span>
                </label>
                <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-plus"></i> Add colour</button>
            </form>
        </x-card>
    @endcan
</div>
@endsection
