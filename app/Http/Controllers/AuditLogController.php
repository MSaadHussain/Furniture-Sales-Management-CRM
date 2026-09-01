<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Export\TabularExport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Audit trail viewer (requirements 28). Admin only.
 */
class AuditLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:view-audit'];
    }

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('user')
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs'    => $logs,
            'users'   => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only(['search', 'user_id', 'action', 'from', 'to']),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        return view('audit.show', ['log' => $auditLog->load('user')]);
    }

    public function export(Request $request)
    {
        $query = $this->filtered($request)->with('user')->latest('created_at');

        $headers = ['When', 'User', 'Action', 'Record', 'Description', 'IP Address', 'Changes'];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $log) {
                $changes = collect($log->changes())
                    ->map(fn ($c) => "{$c['field']}: {$c['before']} -> {$c['after']}")
                    ->implode('; ');

                yield [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->action,
                    $log->recordLabel(),
                    $log->description,
                    $log->ip_address,
                    $changes,
                ];
            }
        };

        return TabularExport::download('audit-log-' . now()->format('Y-m-d'), $headers, $rows(), $request->query('format', 'csv'));
    }

    private function filtered(Request $request)
    {
        return AuditLog::query()
            ->search($request->query('search'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()));
    }
}
