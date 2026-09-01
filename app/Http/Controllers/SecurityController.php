<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Session security controls (requirements 4.4). Admin only.
 */
class SecurityController extends Controller implements HasMiddleware
{
    public function __construct(private AuditService $audit) {}

    public static function middleware(): array
    {
        return ['can:manage-settings'];
    }

    public function index()
    {
        return view('settings.security', ['settings' => self::values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'idle_logout_enabled'  => ['nullable', 'boolean'],
            'idle_timeout_minutes' => ['required', 'integer', 'between:1,240'],
            'require_password_24h' => ['nullable', 'boolean'],
        ]);

        $before = self::values();

        Setting::put('idle_logout_enabled', $request->boolean('idle_logout_enabled'));
        Setting::put('idle_timeout_minutes', (int) $data['idle_timeout_minutes']);
        Setting::put('require_password_24h', $request->boolean('require_password_24h'));

        $this->audit->log('settings.security_updated', null, 'Updated security settings', $before, self::values());

        return back()->with('toast', 'Security settings saved.');
    }

    /** Current values with defaults. Also read by the layout and middleware. */
    public static function values(): array
    {
        return [
            'idle_logout_enabled'  => (bool) Setting::get('idle_logout_enabled', true),
            'idle_timeout_minutes' => (int) Setting::get('idle_timeout_minutes', 15),
            'require_password_24h' => (bool) Setting::get('require_password_24h', true),
        ];
    }
}
