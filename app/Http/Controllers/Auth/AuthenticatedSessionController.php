<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private ActivityTrackingService $tracker) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Logging in counts as a fresh password confirmation (starts the 24h timer).
        $request->session()->put('auth.password_confirmed_at', time());

        // Phase 4: start a tracking session + log the login.
        $this->tracker->startSession($request->user()->id, $request->session()->getId());
        $this->tracker->logActivity('auth.login', 'Logged in');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Phase 4: close the tracking session + log the logout (before logout).
        if ($user = $request->user()) {
            $this->tracker->logActivity('auth.logout', 'Logged out');
            $this->tracker->endSession($user->id, $request->session()->getId());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
