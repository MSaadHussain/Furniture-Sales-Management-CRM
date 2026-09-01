@extends('layouts.app')
@section('title', 'Users & Sales Persons')

@section('header_actions')
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i> Register user
    </a>
@endsection

@section('content')
<div class="space-y-6">

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="ta-label">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Name, email or phone" class="ta-input">
            </div>
            <div>
                <label class="ta-label">Role</label>
                <select name="role" class="ta-input">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>
                            {{ $role->label() }}
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
                <a href="{{ route('users.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px]">
                <thead class="border-b border-line dark:border-strokedark">
                    <tr>
                        <th class="ta-th">User</th>
                        <th class="ta-th">Contact</th>
                        <th class="ta-th">Role</th>
                        <th class="ta-th text-right">Orders sold</th>
                        <th class="ta-th">Status</th>
                        <th class="ta-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line dark:divide-strokedark">
                    @foreach ($users as $user)
                        <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                            <td class="ta-td">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$user" :px="36" text="text-xs" />
                                    <div>
                                        <p class="font-medium text-ink dark:text-gray-200">{{ $user->name }}</p>
                                        @if ($user->id === auth()->id())
                                            <p class="text-xs text-brand">This is you</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="ta-td">
                                <p class="break-all">{{ $user->email }}</p>
                                @if ($user->phone)<p class="text-xs text-muted">{{ $user->phone }}</p>@endif
                            </td>
                            <td class="ta-td">
                                <span class="ta-badge"
                                      style="background-color: {{ $user->role->color() }}1A; color: {{ $user->role->color() }};">
                                    {{ $user->role->label() }}
                                </span>
                            </td>
                            <td class="ta-td text-right">{{ $user->orders_count }}</td>
                            <td class="ta-td">
                                @if ($user->is_active)
                                    <span class="ta-badge bg-success/10 text-success">Active</span>
                                @else
                                    <span class="ta-badge bg-muted/10 text-muted">Inactive</span>
                                @endif
                            </td>
                            <td class="ta-td">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('users.edit', $user) }}" class="text-muted hover:text-brand" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    @unless ($user->id === auth()->id())
                                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="text-muted hover:text-brand"
                                                    title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="fa-solid {{ $user->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              onsubmit="return confirm('Delete this user? Their past orders keep the attribution.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-muted hover:text-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $users->links() }}</div>
    </x-card>

    <x-card title="What each role can do">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($roles as $role)
                <div class="rounded-xl border border-line p-4 dark:border-strokedark">
                    <span class="ta-badge" style="background-color: {{ $role->color() }}1A; color: {{ $role->color() }};">
                        {{ $role->label() }}
                    </span>
                    <p class="mt-2 text-sm text-muted">{{ $role->description() }}</p>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-muted">
            Extra Manager permissions (cancelling orders, editing customers or products) are granted in
            <a href="{{ route('settings.index') }}" class="text-brand hover:underline">Settings</a>.
        </p>
    </x-card>
</div>
@endsection
