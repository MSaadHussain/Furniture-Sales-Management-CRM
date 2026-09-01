@extends('layouts.app')
@section('title', $product->name)
@section('breadcrumb')
    <a href="{{ route('products.index') }}" class="hover:text-brand">Products</a> / {{ $product->name }}
@endsection

@section('header_actions')
    @can('update', $product)
        <a href="{{ route('products.edit', $product) }}" class="btn btn-light">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
    @endcan
@endsection

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Units sold" icon="fa-boxes-stacked" icon-color="#465FFF"
                     :value="number_format($performance->quantity ?? 0)" />
        <x-stat-card label="Revenue" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($performance->revenue ?? 0)" />
        <x-stat-card label="Orders containing it" icon="fa-receipt" icon-color="#7A5AF8"
                     :value="number_format($performance->orders ?? 0)" />
        <x-stat-card label="Average sold price" icon="fa-tag" icon-color="#F79009"
                     :value="\App\Support\Money::format($performance->avg_price ?? 0)"
                     :hint="'List price ' . \App\Support\Money::format($product->default_price)" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Details">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><dt class="text-xs uppercase tracking-wide text-muted">Product code</dt><dd class="mt-0.5 font-mono text-sm text-ink dark:text-gray-200">{{ $product->product_code }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-muted">Category</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $product->category?->name }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wide text-muted">Default price</dt><dd class="mt-0.5 text-ink dark:text-gray-200"><x-money :amount="$product->default_price" /></dd></div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-muted">Status</dt>
                    <dd class="mt-0.5">
                        @if ($product->is_active)
                            <span class="ta-badge bg-success/10 text-success">Active</span>
                        @else
                            <span class="ta-badge bg-muted/10 text-muted">Inactive</span>
                        @endif
                    </dd>
                </div>
                @if ($product->description)
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-muted">Description</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-sm text-ink dark:text-gray-300">{{ $product->description }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-muted">Offered colours</dt>
                    <dd class="mt-2 flex flex-wrap gap-2">
                        @forelse ($product->colours as $colour)
                            <span class="ta-badge gap-2 border border-line dark:border-strokedark">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $colour->swatch() }}"></span>
                                {{ $colour->name }}
                            </span>
                        @empty
                            <span class="text-sm text-muted">All active colours are offered.</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Colour demand" subtitle="What customers actually ordered">
            @forelse ($colourMix as $row)
                <div class="mb-3 flex items-center justify-between gap-3 last:mb-0">
                    <span class="truncate text-sm text-ink dark:text-gray-300">{{ $row->name }}</span>
                    <span class="text-sm font-bold text-ink dark:text-white">{{ $row->quantity }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-muted">Not sold yet.</p>
            @endforelse
        </x-card>
    </div>
</div>
@endsection
