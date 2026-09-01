<?php

namespace App\Services;

use App\Models\UserActivityLog;
use App\Models\UserSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityTrackingService
{
    /** Keys that must never be stored in metadata. */
    private const SENSITIVE = [
        'password', 'password_confirmation', 'current_password', 'token',
        '_token', 'api_key', 'secret', 'otp', 'otp_hash', 'webhook_secret',
    ];

    /** Record the start of a session at login. */
    public function startSession(int $userId, string $sessionId): void
    {
        UserSession::create([
            'user_id'          => $userId,
            'session_id'       => $sessionId,
            'login_at'         => now(),
            'last_activity_at' => now(),
            'active_seconds'   => 0,
            'ip_address'       => Request::ip(),
            'user_agent'       => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /** Update last activity + accumulate active time. Called by middleware. */
    public function touch(int $userId, string $sessionId): void
    {
        $session = UserSession::where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->whereNull('logout_at')
            ->latest('id')
            ->first();

        if (! $session) {
            // No open session row (e.g. tracking added mid-session) — create one.
            $this->startSession($userId, $sessionId);
            return;
        }

        $last = $session->last_activity_at ?? $session->login_at ?? now();
        // Seconds elapsed since the last recorded activity (always positive).
        $gap  = (int) abs($last->diffInSeconds(now()));

        // Ignore sub-second / zero gaps so rapid double requests don't no-op,
        // but still record at least 1s of activity per request.
        if ($gap < 1) {
            $gap = 1;
        }

        // Gaps under 5 minutes count as active time; larger gaps count as idle.
        if ($gap <= 300) {
            $session->active_seconds += $gap;
        } else {
            $session->idle_seconds += $gap;
        }

        $session->last_activity_at = now();
        $session->save();
    }

    /** Close the session at logout. */
    public function endSession(int $userId, string $sessionId): void
    {
        $session = UserSession::where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->whereNull('logout_at')
            ->latest('id')
            ->first();

        if ($session) {
            $session->logout_at = now();
            $session->last_activity_at = now();
            $session->save();
        }
    }

    /** Log an important action. Metadata is masked of any sensitive values. */
    public function logActivity(string $action, ?string $description = null, ?object $model = null, array $metadata = []): void
    {
        UserActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'description' => $description,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->getKey(),
            'route_name'  => optional(Request::route())->getName(),
            'url'         => substr((string) Request::fullUrl(), 0, 500),
            'method'      => Request::method(),
            'ip_address'  => Request::ip(),
            'user_agent'  => substr((string) Request::userAgent(), 0, 500),
            'metadata'    => $metadata ? $this->mask($metadata) : null,
            'created_at'  => now(),
        ]);
    }

    /** Replace sensitive values with [hidden]. */
    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE, true)) {
                $data[$key] = '[hidden]';
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}