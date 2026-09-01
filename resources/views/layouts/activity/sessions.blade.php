@extends('layouts.app')
@section('title', 'User Time Spent')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-ink dark:text-white">User Time Spent</h1>
        <p class="text-sm text-muted">Login sessions, durations, and last activity.</p>
    </div>
    <a href="{{ route('activity.logs') }}" class="btn btn-light"><i class="fa-solid fa-list"></i> Activity Logs</a>
</div>

<x-card>
    <form method="GET" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
        <select name="user_id" class="ta-input">
            <option value="">All users</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ta-input">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ta-input">
        <div>
            <button class="btn btn-brand"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('activity.sessions') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-nowrap">
            <thead class="border-b border-line dark:border-strokedark">
                <tr>
                    <th class="ta-th">User</th><th class="ta-th">Role</th><th class="ta-th">Login</th><th class="ta-th">Logout</th>
                    <th class="ta-th">Last activity</th><th class="ta-th">Time spent</th><th class="ta-th">IP</th><th class="ta-th">Device</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $s)
                    <tr>
                        <td class="text-sm">{{ $s->user?->name ?? '—' }}</td>
                        <td class="text-sm text-muted">{{ $s->user?->role?->label() }}</td>
                        <td class="whitespace-nowrap text-sm">{{ $s->login_at?->format('d M, H:i') }}</td>
                        <td class="whitespace-nowrap text-sm">{{ $s->logout_at?->format('d M, H:i') ?? '— active' }}</td>
                        <td class="whitespace-nowrap text-sm text-muted">{{ $s->last_activity_at?->diffForHumans() }}</td>
                        <td class="text-sm font-semibold">{{ $s->durationLabel() }}</td>
                        <td class="text-sm text-muted">{{ $s->ip_address }}</td>
                        <td class="max-w-[180px] truncate text-xs text-muted" title="{{ $s->user_agent }}">{{ $s->user_agent }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-muted">No sessions recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
</x-card>
@endsection
