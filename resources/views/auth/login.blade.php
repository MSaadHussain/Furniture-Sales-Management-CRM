<x-guest-layout>
    @section('title', 'Sign in')

    <h1 class="text-xl font-bold text-ink">Sign in</h1>
    <p class="mt-1 text-sm text-muted">Enter your credentials to reach the CRM.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="email" class="ta-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username" class="ta-input">
        </div>

        <div>
            <label for="password" class="ta-label">Password</label>
            <div x-data="{ show: false }" class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'" type="password"
                       required autocomplete="current-password" class="ta-input pr-11">
                <button type="button" @click="show = !show" tabindex="-1"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                    <i class="fa-regular" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" class="rounded border-line text-brand focus:ring-brand/40">
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand hover:underline">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-full">
            <i class="fa-solid fa-right-to-bracket"></i> Sign in
        </button>
    </form>
</x-guest-layout>
