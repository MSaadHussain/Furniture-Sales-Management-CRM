@extends('layouts.app')
@section('title', 'User Activity Logs')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-ink dark:text-white">User Activity Logs</h1>
        <p class="text-sm text-muted">Actions performed by users across the CRM.</p>
    </div>
    <a href="{{ route('activity.sessions') }}" class="btn btn-light"><i class="fa-solid fa-clock"></i> Time Spent Report</a>
</div>

<x-card>
    <form method="GET" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
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
        <div class="sm:col-span-4">
            <button class="btn btn-brand"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('activity.logs') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-nowrap">
            <thead class="border-b border-line dark:border-strokedark">
                <tr>
                    <th class="ta-th">When</th><th class="ta-th">User</th><th class="ta-th">Action</th><th class="ta-th">Description</th><th class="ta-th">Page</th><th class="ta-th">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-sm">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                        <td class="text-sm">{{ $log->user?->name ?? 'System' }}</td>
                        <td><span class="ta-badge">{{ $log->action }}</span></td>
                        <td class="text-sm text-muted">{{ $log->description }}</td>
                        <td class="text-sm text-muted">{{ $log->route_name ?? $log->url }}</td>
                        <td class="text-sm text-muted">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-card>
@endsection
