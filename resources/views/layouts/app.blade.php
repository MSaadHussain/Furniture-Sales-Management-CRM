<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ \App\Models\Setting::get('business_name') ?: config('app.name') }}</title>
    <link rel="icon" href="data:,">

    {{-- Pre-paint theme to avoid a flash of the wrong mode --}}
    <script>
        if (localStorage.getItem('ta-theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Compiled Tailwind + JS (Vite) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Safety net: if the Vite bundle has not been built yet, load Chart.js
         from a CDN so the dashboard and reports still render. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.2/chart.umd.min.js"></script>

    <style>[x-cloak]{display:none !important;}</style>
    @stack('head')
</head>
<body class="font-sans">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

        {{-- Sidebar --}}
        @include('layouts.partials.sidebar')

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/40 lg:hidden" x-transition.opacity></div>

        {{-- Content column --}}
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            @include('layouts.partials.header')

            <main class="flex-1">
                <div class="mx-auto w-full max-w-screen-2xl p-4 sm:p-6 lg:p-8">

                    {{-- Page heading + actions (mirrors AdminLTE content-header) --}}
                    @hasSection('title')
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h1 class="text-2xl font-bold text-ink dark:text-white">@yield('title', 'Dashboard')</h1>
                                @hasSection('breadcrumb')
                                    <nav class="mt-1 text-sm text-muted">@yield('breadcrumb')</nav>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">@yield('header_actions')</div>
                        </div>
                    @endif

                    {{-- Flash toast (same session key the app already uses) --}}
                    @if (session('toast'))
                        <div x-data="{ show: true }" x-show="show" x-transition
                             class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-success/30
                                    bg-success/10 px-4 py-3 text-sm text-success">
                            <span><i class="fa-solid fa-circle-check mr-2"></i>{{ session('toast') }}</span>
                            <button @click="show = false"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i>{{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Phase 3: Idle logout (auto-logout after inactivity) --}}
    @auth
    @php($__sec = \App\Http\Controllers\SecurityController::values())
    @if($__sec['idle_logout_enabled'])
    <div id="idleWarning" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
        <div style="background:#fff;color:#1c2434;max-width:380px;width:90%;padding:24px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;">
            <div style="font-size:34px;line-height:1;margin-bottom:10px;">&#9203;</div>
            <h3 style="font-size:18px;font-weight:700;margin:0 0 6px;">You&rsquo;ve been inactive</h3>
            <p style="font-size:14px;color:#64748b;margin:0 0 18px;">You will be logged out in <strong id="idleCountdown">60</strong> seconds.</p>
            <button id="idleStayBtn" style="background:#465FFF;color:#fff;border:0;padding:10px 22px;border-radius:9px;font-weight:600;cursor:pointer;">Stay signed in</button>
        </div>
    </div>
    <form id="idleLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
    <script>
    (function () {
        var IDLE_MIN = {{ (int) $__sec['idle_timeout_minutes'] }};
        var WARN_SECONDS = 60;
        var idleMs = Math.max(60, IDLE_MIN * 60) * 1000;
        var warnTimer, logoutTimer, countdownTimer, secondsLeft;
        var warning = document.getElementById('idleWarning');
        var countdownEl = document.getElementById('idleCountdown');

        function doLogout() { document.getElementById('idleLogoutForm').submit(); }

        function showWarning() {
            secondsLeft = WARN_SECONDS;
            countdownEl.textContent = secondsLeft;
            warning.style.display = 'flex';
            countdownTimer = setInterval(function () {
                secondsLeft--;
                countdownEl.textContent = secondsLeft;
                if (secondsLeft <= 0) clearInterval(countdownTimer);
            }, 1000);
            logoutTimer = setTimeout(doLogout, WARN_SECONDS * 1000);
        }

        function resetTimers() {
            clearTimeout(warnTimer); clearTimeout(logoutTimer); clearInterval(countdownTimer);
            warning.style.display = 'none';
            // Start warning (idleMs - warn window), then logout at idleMs.
            var warnAt = Math.max(1000, idleMs - WARN_SECONDS * 1000);
            warnTimer = setTimeout(showWarning, warnAt);
        }

        ['mousemove','mousedown','keydown','scroll','touchstart','click'].forEach(function (ev) {
            window.addEventListener(ev, function () {
                // Ignore activity while the warning is up — user must click "Stay".
                if (warning.style.display === 'flex') return;
                resetTimers();
            }, { passive: true });
        });

        document.getElementById('idleStayBtn').addEventListener('click', function () {
            // Ping the server to keep the session alive, then reset.
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function(){});
            resetTimers();
        });

        resetTimers();
    })();
    </script>
    @endif
    @endauth

    @stack('scripts')
</body>
</html>
