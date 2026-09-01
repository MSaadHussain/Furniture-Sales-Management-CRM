@extends('layouts.app')
@section('title', 'Audit Logs')

@section('header_actions')
    <x-export-menu route="audit.export" />
@endsection

@section('content')
<div class="space-y-6">

    <x-card padding="p-4">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div>
                <label class="ta-label">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Action, description or IP" class="ta-input">
            </div>
            <div>
                <label class="ta-label">User</label>
                <select name="user_id" class="ta-input">
                    <option value="">Everyone</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">Action</label>
                <select name="action" class="ta-input">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ta-label">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="ta-input">
            </div>
            <div>
                <label class="ta-label">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="ta-input">
            </div>
            <div class="flex items-end gap-2 md:col-span-5">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="{{ route('audit.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card padding="p-0">
        @if ($logs->isEmpty())
            <x-empty-state icon="fa-clipboard-list" title="No audit entries"
                           message="Order, product, customer, user and settings changes appear here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">When</th>
                            <th class="ta-th">User</th>
                            <th class="ta-th">Action</th>
                            <th class="ta-th">Record</th>
                            <th class="ta-th">Description</th>
                            <th class="ta-th">IP address</th>
                            <th class="ta-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($logs as $log)
                            <tr class="transition hover:bg-surface dark:hover:bg-boxdark/50">
                                <td class="ta-td whitespace-nowrap text-muted">
                                    {{ $log->created_at?->format('d M Y H:i:s') }}
                                </td>
                                <td class="ta-td">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="ta-td">
                                    <span class="ta-badge bg-brand-50 font-mono text-xs text-brand">{{ $log->action }}</span>
                                </td>
                                <td class="ta-td text-muted">{{ $log->recordLabel() }}</td>
                                <td class="ta-td max-w-[320px]">
                                    <span class="block truncate" title="{{ $log->description }}">{{ $log->description }}</span>
                                </td>
                                <td class="ta-td font-mono text-xs text-muted">{{ $log->ip_address }}</td>
                                <td class="ta-td text-right">
                                    @if ($log->old_values || $log->new_values)
                                        <a href="{{ route('audit.show', $log) }}" class="text-muted hover:text-brand" title="View changes">
                                            <i class="fa-solid fa-code-compare"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-4 dark:border-strokedark">{{ $logs->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
