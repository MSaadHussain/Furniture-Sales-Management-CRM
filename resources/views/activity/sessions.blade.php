@extends('layouts.app')
@section('title', 'User Time Spent')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-ink dark:text-white">User Time Spent</h1>
        <p class="text-sm text-muted">Login sessions, durations, and last activity.</p>
    </div>
    <a href="{{ route('activity.logs') }}" class="btn btn-light"><i class="fa-solid fa-list"></i> Activity Logs</a>
</div>

<x-card>
    {{-- Filters --}}
    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <select name="user_id" class="ta-input">
            <option value="">All users</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ta-input" placeholder="From">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ta-input" placeholder="To">
        <div class="flex gap-2">
            <button class="btn btn-brand flex-1"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="{{ route('activity.sessions') }}" class="btn btn-light">Reset</a>
        </div>
    </form>

    {{-- DESKTOP TABLE --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full">
            <thead>
                <tr class="border-b border-line dark:border-strokedark">
                    <th class="ta-th">User</th>
                    <th class="ta-th">Login</th>
                    <th class="ta-th">Logout</th>
                    <th class="ta-th">Last activity</th>
                    <th class="ta-th">Time spent</th>
                    <th class="ta-th">Device</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line dark:divide-strokedark">
                @forelse ($sessions as $s)
                    <tr class="hover:bg-surface/60 dark:hover:bg-boxdark/60">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                      style="background-color: {{ $s->user?->avatar_color ?? '#465FFF' }}">
                                    {{ $s->user?->initials() ?? '—' }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink dark:text-gray-200">{{ $s->user?->name ?? '—' }}</p>
                                    <p class="truncate text-xs text-muted">{{ $s->user?->role?->label() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-ink dark:text-gray-300">{{ $s->login_at?->format('d M, H:i') }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm">
                            @if ($s->isActive())
                                <span class="ta-badge bg-success/10 text-success"><i class="fa-solid fa-circle mr-1 text-[6px]"></i> Active</span>
                            @else
                                <span class="text-muted">{{ $s->logout_at?->format('d M, H:i') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-muted">{{ $s->last_activity_at?->diffForHumans() }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-ink dark:text-gray-200">{{ $s->durationLabel() }}</td>
                        <td class="px-5 py-4">
                            <p class="text-sm text-ink dark:text-gray-300">{{ $s->deviceLabel() }}</p>
                            <p class="text-xs text-muted">{{ $s->ip_address }}</p>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">No sessions recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="space-y-3 md:hidden">
        @forelse ($sessions as $s)
            <div class="rounded-xl border border-line p-4 dark:border-strokedark">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                              style="background-color: {{ $s->user?->avatar_color ?? '#465FFF' }}">
                            {{ $s->user?->initials() ?? '—' }}
                        </span>
                        <div>
                            <p class="text-sm font-medium text-ink dark:text-gray-200">{{ $s->user?->name ?? '—' }}</p>
                            <p class="text-xs text-muted">{{ $s->user?->role?->label() }}</p>
                        </div>
                    </div>
                    @if ($s->isActive())
                        <span class="ta-badge bg-success/10 text-success">Active</span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-muted">Login</dt><dd class="text-right text-ink dark:text-gray-300">{{ $s->login_at?->format('d M, H:i') }}</dd>
                    <dt class="text-muted">Logout</dt><dd class="text-right text-ink dark:text-gray-300">{{ $s->isActive() ? '—' : $s->logout_at?->format('d M, H:i') }}</dd>
                    <dt class="text-muted">Last activity</dt><dd class="text-right text-ink dark:text-gray-300">{{ $s->last_activity_at?->diffForHumans() }}</dd>
                    <dt class="text-muted">Time spent</dt><dd class="text-right font-semibold text-ink dark:text-gray-200">{{ $s->durationLabel() }}</dd>
                    <dt class="text-muted">Device</dt><dd class="text-right text-ink dark:text-gray-300">{{ $s->deviceLabel() }}</dd>
                    <dt class="text-muted">IP</dt><dd class="text-right text-muted">{{ $s->ip_address }}</dd>
                </dl>
            </div>
        @empty
            <p class="py-10 text-center text-muted">No sessions recorded yet.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $sessions->links() }}</div>
</x-card>
@endsection
