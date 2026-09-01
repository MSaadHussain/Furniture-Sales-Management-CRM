<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 — require every signed-in user to re-confirm their password once
 * every 24 hours. This does NOT log the user out; it just redirects to the
 * password-confirm screen until they confirm, then resets the timer.
 *
 * Loop-safe: skips the confirm-password routes, logout, and non-GET requests
 * to auth endpoints.
 */
class ConfirmPasswordEvery24h
{
    /** Seconds in the window (24 hours). */
    private const WINDOW = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Not logged in -> let auth middleware handle it.
        if (! $user) {
            return $next($request);
        }

        // Bypass check in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        // Never intercept the confirm-password screen or logout, otherwise we
        // create an infinite redirect loop.
        $routeName = optional($request->route())->getName();
        if (in_array($routeName, ['password.confirm', 'logout'], true)
            || $request->is('confirm-password')) {
            return $next($request);
        }

        // Only guard normal page loads (GET); let posts/AJAX through so we don't
        // break form submissions mid-flight.
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Respect the Security Settings toggle (default on).
        if (! (bool) \App\Models\Setting::get('require_password_24h', true)) {
            return $next($request);
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

        if ((time() - $confirmedAt) > self::WINDOW) {
            // Remember where they were going, then send to confirm screen.
            $request->session()->put('url.intended', $request->fullUrl());

            if (Route::has('password.confirm')) {
                return redirect()->route('password.confirm');
            }
        }

        return $next($request);
    }
}
