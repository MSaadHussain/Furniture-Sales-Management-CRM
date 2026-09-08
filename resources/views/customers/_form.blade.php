@php $isEdit = $customer->exists; @endphp

<form method="POST" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Customer details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="ta-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" required maxlength="255"
                               value="{{ old('name', $customer->name) }}"
                               class="ta-input @error('name') !border-danger @enderror">
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" required maxlength="40"
                               value="{{ old('phone', $customer->phone) }}"
                               class="ta-input @error('phone') !border-danger @enderror">
                        @error('phone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Additional Phone <span class="text-muted">(optional)</span></label>
                        <input type="text" name="phone_alt" maxlength="40"
                               value="{{ old('phone_alt', $customer->phone_alt) }}"
                               class="ta-input @error('phone_alt') !border-danger @enderror">
                        @error('phone_alt')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs text-muted">Searched and matched for duplicates just like the main number.</p>
                        @enderror
                    </div>

                    <div>
                        <label class="ta-label">Email <span class="text-muted">(optional)</span></label>
                        <input type="email" name="email" maxlength="255"
                               value="{{ old('email', $customer->email) }}"
                               class="ta-input @error('email') !border-danger @enderror">
                        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="ta-label">Address</label>
                        <input type="text" name="address" maxlength="255"
                               value="{{ old('address', $customer->address) }}" class="ta-input">
                    </div>

                    <div>
                        <label class="ta-label">City</label>
                        <input type="text" name="city" maxlength="120"
                               value="{{ old('city', $customer->city) }}" class="ta-input">
                    </div>

                    <div>
                        <label class="ta-label">State / Province <span class="text-muted">(optional)</span></label>
                        <input type="text" name="state" maxlength="120"
                               value="{{ old('state', $customer->state) }}" class="ta-input">
                    </div>

                    <div>
                        <label class="ta-label">ZIP / postal code <span class="text-danger">*</span></label>
                        <input type="text" name="zip_code" required maxlength="20"
                               value="{{ old('zip_code', $customer->zip_code) }}"
                               class="ta-input @error('zip_code') !border-danger @enderror">
                        @error('zip_code')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-xs text-muted">Required for the location and advertising reports.</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="ta-label">Notes</label>
                        <textarea name="notes" rows="4" maxlength="2000" class="ta-input">{{ old('notes', $customer->notes) }}</textarea>
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Save">
                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> {{ $isEdit ? 'Save changes' : 'Create customer' }}
                    </button>
                    <a href="{{ $isEdit ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </x-card>

            @if ($isEdit)
                <x-card title="Record">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Customer ID</dt><dd class="font-medium text-ink dark:text-gray-200">#{{ $customer->id }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Created</dt><dd class="text-ink dark:text-gray-200">{{ $customer->created_at?->format('d M Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Updated</dt><dd class="text-ink dark:text-gray-200">{{ $customer->updated_at?->format('d M Y') }}</dd></div>
                    </dl>
                </x-card>
            @endif
        </div>
    </div>
</form>
