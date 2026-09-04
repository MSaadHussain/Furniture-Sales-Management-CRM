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
    @auth
        @if (!auth()->user()->isAdmin())
            <style>
                /* Prevent text selection and copying of customer/order data for non-admins */
                table, table tbody, .ta-card, .protect-copy {
                    -webkit-user-select: none !important;
                    -moz-user-select: none !important;
                    -ms-user-select: none !important;
                    user-select: none !important;
                    -webkit-touch-callout: none !important;
                }
            </style>
        @endif
    @endauth
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
                <div class="mx-auto w-full max-w-screen-2xl px-4 py-3 sm:px-6 sm:py-4 lg:px-8 lg:py-4">

                    {{-- Page heading + actions (mirrors AdminLTE content-header) --}}
                    @hasSection('title')
                        <div class="mb-3.5 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h1 class="text-xl font-bold text-ink dark:text-white sm:text-2xl">@yield('title', 'Dashboard')</h1>
                                @hasSection('breadcrumb')
                                    <nav class="mt-0.5 text-xs text-muted">@yield('breadcrumb')</nav>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">@yield('header_actions')</div>
                        </div>
                    @endif

                    {{-- Flash toast (same session key the app already uses) --}}
                    @if (session('toast'))
                        <div x-data="{ show: true }" x-show="show" x-transition
                             class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-success/30
                                    bg-success/10 px-4 py-2.5 text-sm text-success">
                            <span><i class="fa-solid fa-circle-check mr-2"></i>{{ session('toast') }}</span>
                            <button @click="show = false"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-2.5 text-sm text-danger">
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
    <script>
    window.copyOrderToClipboard = function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        } else {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            return new Promise(function(resolve, reject) {
                document.execCommand('copy') ? resolve() : reject();
                textarea.remove();
            });
        }
    };
    </script>
    @if (!auth()->user()->isAdmin())
    <script>
    (function () {
        // Prevent copying, cutting, and context menu on tables and customer/order cards for non-admin users
        document.addEventListener('copy', function (e) {
            var target = e.target;
            if (target && target.closest('[data-copy-btn]')) return;
            if (target && (target.closest('table') || target.closest('.ta-card') || target.closest('.protect-copy'))) {
                e.preventDefault();
                if (e.clipboardData) {
                    e.clipboardData.setData('text/plain', '');
                }
                return false;
            }
        });

        document.addEventListener('cut', function (e) {
            var target = e.target;
            if (target && target.closest('[data-copy-btn]')) return;
            if (target && (target.closest('table') || target.closest('.ta-card') || target.closest('.protect-copy'))) {
                e.preventDefault();
                return false;
            }
        });

        document.addEventListener('contextmenu', function (e) {
            var target = e.target;
            if (target && target.closest('[data-copy-btn]')) return;
            if (target && (target.closest('table') || target.closest('.ta-card') || target.closest('.protect-copy'))) {
                e.preventDefault();
                return false;
            }
        });
    })();
    </script>
    @endif
    @endauth

    {{-- ================= Global Animated Loading Overlay & Action Blocker ================= --}}
    <div id="globalLoadingOverlay"
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/40 backdrop-blur-[2px] transition-all duration-200 pointer-events-auto select-none"
         style="display: none;">
        <div class="flex flex-col items-center justify-center rounded-2xl border border-line bg-white/95 px-8 py-6 shadow-2xl dark:border-strokedark dark:bg-boxdark/95 min-w-[260px] max-w-sm text-center">
            {{-- Modern Dual-Ring Brand Spinner --}}
            <div class="relative mb-3.5 flex h-12 w-12 items-center justify-center">
                <div class="absolute h-12 w-12 rounded-full border-4 border-brand/20"></div>
                <div class="h-12 w-12 animate-spin rounded-full border-4 border-brand border-t-transparent"></div>
                <i class="fa-solid fa-sync text-brand text-xs absolute"></i>
            </div>
            
            <h4 id="globalLoadingTitle" class="text-sm font-bold text-ink dark:text-white">
                Updating, please wait...
            </h4>
            <p id="globalLoadingSubtitle" class="mt-1 text-xs text-muted">
                Saving changes and refreshing...
            </p>
        </div>
    </div>

    <script>
    (function () {
        window.showLoading = function(title, subtitle) {
            var overlay = document.getElementById('globalLoadingOverlay');
            if (!overlay) return;
            if (title) document.getElementById('globalLoadingTitle').textContent = title;
            if (subtitle !== undefined) document.getElementById('globalLoadingSubtitle').textContent = subtitle;
            overlay.style.display = 'flex';
            document.body.style.pointerEvents = 'none';
            overlay.style.pointerEvents = 'auto';
        };

        window.hideLoading = function() {
            var overlay = document.getElementById('globalLoadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
                document.body.style.pointerEvents = '';
            }
        };

        // Ensure overlay hides if page is restored from bfcache (browser back/forward)
        window.addEventListener('pageshow', function() {
            window.hideLoading();
        });

        // Intercept all standard form submissions (excluding file downloads / export)
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            if (form.hasAttribute('data-no-loading') || form.classList.contains('no-loading') || form.target === '_blank') return;
            
            var action = (form.getAttribute('action') || '').toLowerCase();
            if (action.includes('export') || form.querySelector('input[name="format"]')) return;

            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            var btnText = submitBtn ? submitBtn.innerText.trim() : '';

            var title = 'Updating, please wait...';
            if (btnText && btnText.toLowerCase().includes('create')) title = 'Creating, please wait...';
            else if (btnText && btnText.toLowerCase().includes('save')) title = 'Saving, please wait...';
            else if (btnText && btnText.toLowerCase().includes('delete')) title = 'Deleting, please wait...';

            window.showLoading(title, 'Saving changes and reloading...');
        });
    })();
    </script>

    @stack('scripts')
</body>
</html>
