@extends('layouts.app')
@section('title', 'Audit Entry')
@section('breadcrumb')
    <a href="{{ route('audit.index') }}" class="hover:text-brand">Audit Logs</a> / Entry #{{ $log->id }}
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <x-card class="lg:col-span-2" padding="p-0" title="Changes"
            subtitle="Values before and after the action">
        @php $changes = $log->changes(); @endphp

        @if (empty($changes))
            <x-empty-state icon="fa-code-compare" title="No field-level changes recorded"
                           message="This action did not store a before/after snapshot." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px]">
                    <thead class="border-b border-line dark:border-strokedark">
                        <tr>
                            <th class="ta-th">Field</th>
                            <th class="ta-th">Before</th>
                            <th class="ta-th">After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line dark:divide-strokedark">
                        @foreach ($changes as $change)
                            <tr>
                                <td class="ta-td font-medium">{{ $change['field'] }}</td>
                                <td class="ta-td">
                                    <span class="rounded bg-danger/10 px-2 py-0.5 font-mono text-xs text-danger">
                                        {{ $change['before'] === null || $change['before'] === '' ? 'empty' : $change['before'] }}
                                    </span>
                                </td>
                                <td class="ta-td">
                                    <span class="rounded bg-success/10 px-2 py-0.5 font-mono text-xs text-success">
                                        {{ $change['after'] === null || $change['after'] === '' ? 'empty' : $change['after'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Entry">
        <dl class="space-y-3 text-sm">
            <div><dt class="text-xs uppercase tracking-wide text-muted">When</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $log->created_at?->format('d M Y H:i:s') }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-muted">User</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $log->user?->name ?? 'System' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-muted">Action</dt><dd class="mt-0.5 font-mono text-xs text-ink dark:text-gray-200">{{ $log->action }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-muted">Record</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $log->recordLabel() }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-muted">Description</dt><dd class="mt-0.5 text-ink dark:text-gray-200">{{ $log->description ?: '--' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-muted">IP address</dt><dd class="mt-0.5 font-mono text-xs text-ink dark:text-gray-200">{{ $log->ip_address ?: '--' }}</dd></div>
        </dl>

        <a href="{{ route('audit.index') }}" class="btn btn-light mt-5 w-full">
            <i class="fa-solid fa-arrow-left"></i> Back to audit logs
        </a>
    </x-card>
</div>
@endsection
