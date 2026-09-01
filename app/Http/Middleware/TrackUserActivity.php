<?php

namespace App\Http\Middleware;

use App\Services\ActivityTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 4 — updates the current user's session activity on each request so we
 * can report time spent. Lightweight: one indexed update per request.
 */
class TrackUserActivity
{
    public function __construct(private ActivityTrackingService $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && $request->hasSession()) {
            try {
                $this->tracker->touch($user->id, $request->session()->getId());
            } catch (\Throwable $e) {
                // Never break a request because tracking failed.
            }
        }

        return $response;
    }
}
