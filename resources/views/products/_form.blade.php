@php
    $isEdit = $product->exists;
    $selectedColours = old('colours', $isEdit ? $product->colours->pluck('id')->all() : []);
@endphp

<form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Product details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="ta-label">Product name <span class="text-danger">*</span></label>
                        <input type="text" name="name" required maxlength="255"
                               value="{{ old('name', $product->name) }}"
                               class="ta-input @error('name') !border-danger @enderror">
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Product code / SKU</label>
                        <input type="text" name="product_code" maxlength="60"
                               value="{{ old('product_code', $product->product_code) }}"
                               class="ta-input @error('product_code') !border-danger @enderror">
                        @error('product_code')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs text-muted">Left blank, one is generated automatically.</p>
                        @enderror
                    </div>

                    <div>
                        <label class="ta-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" required class="ta-input @error('category_id') !border-danger @enderror">
                            <option value="">Choose a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Default price <span class="text-danger">*</span></label>
                        <input type="number" name="default_price" min="0" step="0.01" required
                               value="{{ old('default_price', (float) $product->default_price) }}"
                               class="ta-input @error('default_price') !border-danger @enderror">
                        @error('default_price')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs text-muted">Pre-fills the order form; it can still be overridden per order.</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $isEdit ? $product->is_active : true))
                                   class="h-5 w-5 rounded border-line text-brand focus:ring-brand/40">
                            <span class="text-sm font-medium text-ink dark:text-gray-200">Active (selectable on orders)</span>
                        </label>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="ta-label">Description <span class="text-muted">(optional)</span></label>
                        <textarea name="description" rows="4" maxlength="2000" class="ta-input">{{ old('description', $product->description) }}</textarea>
                    </div>
                </div>
            </x-card>

            <x-card title="Available colours"
                    subtitle="Pick none to offer the full colour master on the order form">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($colours as $colour)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3 py-2.5 transition
                                      hover:border-brand dark:border-strokedark">
                            <input type="checkbox" name="colours[]" value="{{ $colour->id }}"
                                   @checked(in_array($colour->id, (array) $selectedColours))
                                   class="rounded border-line text-brand focus:ring-brand/40">
                            <span class="h-4 w-4 flex-shrink-0 rounded-full border border-line"
                                  style="background-color: {{ $colour->swatch() }}"></span>
                            <span class="truncate text-sm text-ink dark:text-gray-300">{{ $colour->name }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($colours->isEmpty())
                    <p class="text-sm text-muted">
                        No colours defined yet. Add some on the
                        <a href="{{ route('colours.index') }}" class="text-brand hover:underline">Colours</a> screen.
                    </p>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Save">
                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> {{ $isEdit ? 'Save changes' : 'Create product' }}
                    </button>
                    <a href="{{ route('products.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </x-card>
        </div>
    </div>
</form>
