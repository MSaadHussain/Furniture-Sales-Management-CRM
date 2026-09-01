<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the audit trail required by section 28: who did what, to which record,
 * when, from where, and (where useful) the before/after values.
 */
class AuditService
{
    /** Never record these, whatever the caller passes. */
    private const REDACT = [
        'password', 'password_confirmation', 'current_password',
        'remember_token', '_token', 'token',
    ];

    public function log(
        string $action,
        ?object $auditable = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $log = AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id'   => $auditable instanceof Model ? $auditable->getKey() : ($auditable?->id ?? null),
            'description'    => $description,
            'old_values'     => $oldValues ? $this->redact($oldValues) : null,
            'new_values'     => $newValues ? $this->redact($newValues) : null,
            'ip_address'     => Request::ip(),
        ]);

        // Mirror into the activity log so the per-user activity report is complete.
        // Never let a logging failure break the action being audited.
        try {
            UserActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => $action,
                'description' => $description,
                'model_type'  => $log->auditable_type,
                'model_id'    => $log->auditable_id,
                'route_name'  => optional(Request::route())->getName(),
                'url'         => substr((string) Request::fullUrl(), 0, 500),
                'method'      => Request::method(),
                'ip_address'  => Request::ip(),
                'user_agent'  => substr((string) Request::userAgent(), 0, 500),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return $log;
    }

    /**
     * Audit an update by diffing a model against the attributes it had before.
     * Only genuinely changed keys are stored, so the trail stays readable.
     */
    public function logChanges(string $action, Model $model, array $before, ?string $description = null): ?AuditLog
    {
        $after   = $model->getAttributes();
        $changed = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] != $value) {
                $changed[$key] = $value;
            }
        }

        unset($changed['updated_at']);

        if ($changed === []) {
            return null;
        }

        return $this->log(
            $action,
            $model,
            $description,
            array_intersect_key($before, $changed),
            $changed,
        );
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $data[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif ($value instanceof \BackedEnum) {
                $data[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $data[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }
}
