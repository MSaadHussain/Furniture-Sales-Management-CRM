@php $isEdit = $user->exists; @endphp

<form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Account details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ta-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" required maxlength="120"
                               value="{{ old('name', $user->name) }}"
                               class="ta-input @error('name') !border-danger @enderror">
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" required maxlength="191"
                               value="{{ old('email', $user->email) }}"
                               class="ta-input @error('email') !border-danger @enderror">
                        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="ta-label">Phone <span class="text-muted">(optional)</span></label>
                        <input type="text" name="phone" maxlength="40"
                               value="{{ old('phone', $user->phone) }}" class="ta-input">
                    </div>

                    <div>
                        <label class="ta-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="ta-input @error('role') !border-danger @enderror">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}"
                                    @selected(old('role', $user->role?->value ?? 'sales_person') === $role->value)>
                                    {{ $role->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $isEdit ? $user->is_active : true))
                                   class="h-5 w-5 rounded border-line text-brand focus:ring-brand/40">
                            <span>
                                <span class="block text-sm font-medium text-ink dark:text-gray-200">Active</span>
                                <span class="block text-xs text-muted">Only active users can sign in or be selected on an order.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </x-card>

            <x-card :title="$isEdit ? 'Change password' : 'Password'"
                    :subtitle="$isEdit ? 'Leave blank to keep the current password' : null">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ta-label">Password @unless ($isEdit)<span class="text-danger">*</span>@endunless</label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="ta-input @error('password') !border-danger @enderror">
                        @error('password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="ta-label">Confirm password</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="ta-input">
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Save">
                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> {{ $isEdit ? 'Save changes' : 'Register user' }}
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </x-card>

            <x-card title="Role permissions">
                <div class="space-y-3">
                    @foreach ($roles as $role)
                        <div>
                            <span class="ta-badge" style="background-color: {{ $role->color() }}1A; color: {{ $role->color() }};">
                                {{ $role->label() }}
                            </span>
                            <p class="mt-1 text-xs text-muted">{{ $role->description() }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
</form>
