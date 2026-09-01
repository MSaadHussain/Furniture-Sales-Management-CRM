@extends('layouts.app')
@section('title', 'User Activity Logs')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-ink dark:text-white">User Activity Logs</h1>
        <p class="text-sm text-muted">Actions performed by users across the CRM.</p>
    </div>
    <a href="{{ route('activity.sessions') }}" class="btn btn-light"><i class="fa-solid fa-clock"></i> Time Spent</a>
</div>

<x-card>
    {{-- Filters --}}
    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <select name="user_id" class="ta-input">
            <option value="">All users</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <select name="action" class="ta-input">
            <option value="">All actions</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ta-input">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ta-input">
        <div class="flex gap-2">
            <button class="btn btn-brand flex-1"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('activity.logs') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    {{-- DESKTOP TABLE --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full">
            <thead>
                <tr class="border-b border-line dark:border-strokedark">
                    <th class="ta-th">When</th>
                    <th class="ta-th">User</th>
                    <th class="ta-th">Action</th>
                    <th class="ta-th">Description</th>
                    <th class="ta-th">Page</th>
                    <th class="ta-th">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line dark:divide-strokedark">
                @forelse ($logs as $log)
                    <tr class="hover:bg-surface/60 dark:hover:bg-boxdark/60">
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-ink dark:text-gray-300">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-ink dark:text-gray-300">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-5 py-4"><span class="ta-badge bg-brand-50 text-brand">{{ $log->action }}</span></td>
                        <td class="px-5 py-4 text-sm text-muted">{{ $log->description }}</td>
                        <td class="px-5 py-4 text-sm text-muted">{{ $log->route_name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-muted">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="space-y-3 md:hidden">
        @forelse ($logs as $log)
            <div class="rounded-xl border border-line p-4 dark:border-strokedark">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <span class="ta-badge bg-brand-50 text-brand">{{ $log->action }}</span>
                    <span class="text-xs text-muted">{{ $log->created_at?->format('d M, H:i') }}</span>
                </div>
                <p class="text-sm font-medium text-ink dark:text-gray-200">{{ $log->user?->name ?? 'System' }}</p>
                @if ($log->description)<p class="mt-1 text-sm text-muted">{{ $log->description }}</p>@endif
                <p class="mt-1 text-xs text-muted">{{ $log->route_name ?? '—' }} · {{ $log->ip_address }}</p>
            </div>
        @empty
            <p class="py-10 text-center text-muted">No activity recorded yet.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
</x-card>
@endsection
