<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'login_at', 'logout_at', 'last_activity_at',
        'active_seconds', 'idle_seconds', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'login_at'         => 'datetime',
            'logout_at'        => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human-friendly total time spent (active seconds). */
    public function durationLabel(): string
    {
        $secs = (int) $this->active_seconds;
        if ($secs < 60) {
            return "{$secs}s";
        }
        $h = intdiv($secs, 3600);
        $m = intdiv($secs % 3600, 60);

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    /** Short browser + OS label parsed from the user agent. */
    public function deviceLabel(): string
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return 'Unknown device';
        }

        $browser = 'Browser';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                $browser = $name;
                break;
            }
        }

        $os = 'Unknown OS';
        foreach (['Windows NT 10' => 'Windows', 'Windows' => 'Windows', 'Macintosh' => 'macOS', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Linux' => 'Linux'] as $needle => $name) {
            if (str_contains($ua, $needle)) {
                $os = $name;
                break;
            }
        }

        return "{$browser} · {$os}";
    }

    /** Whether the session is still open (no logout recorded). */
    public function isActive(): bool
    {
        return $this->logout_at === null;
    }
}
