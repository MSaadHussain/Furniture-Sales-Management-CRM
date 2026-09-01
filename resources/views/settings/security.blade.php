@extends('layouts.app')
@section('title', 'Security Settings')
@section('breadcrumb')
    <a href="{{ route('settings.index') }}" class="hover:text-brand">Settings</a> / Security
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <form method="POST" action="{{ route('settings.security.update') }}" class="space-y-6 lg:col-span-2">
        @csrf
        @method('PATCH')

        <x-card title="Session security" subtitle="Automatic logout and password re-confirmation">
            <div class="space-y-5">
                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-ink dark:text-gray-200">Enable idle logout</span>
                        <span class="block text-xs text-muted">Signs a user out automatically after a period of inactivity.</span>
                    </span>
                    <input type="checkbox" name="idle_logout_enabled" value="1" @checked($settings['idle_logout_enabled'])
                           class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-line text-brand focus:ring-brand/40">
                </label>

                <div>
                    <label class="ta-label">Idle timeout (minutes)</label>
                    <input type="number" name="idle_timeout_minutes" min="1" max="240"
                           value="{{ old('idle_timeout_minutes', $settings['idle_timeout_minutes']) }}"
                           class="ta-input @error('idle_timeout_minutes') !border-danger @enderror">
                    @error('idle_timeout_minutes')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1 text-xs text-muted">A warning appears 60 seconds before the logout fires.</p>
                    @enderror
                </div>

                <hr class="border-line dark:border-strokedark">

                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-ink dark:text-gray-200">Re-confirm password every 24 hours</span>
                        <span class="block text-xs text-muted">Users confirm their password once a day. It does not sign them out.</span>
                    </span>
                    <input type="checkbox" name="require_password_24h" value="1" @checked($settings['require_password_24h'])
                           class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-line text-brand focus:ring-brand/40">
                </label>
            </div>
        </x-card>

        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Save security settings
        </button>
    </form>

    <x-card title="Always on" subtitle="Protections that are not configurable">
        <ul class="space-y-3 text-sm">
            @foreach ([
                'Passwords are hashed with the application hasher; plain text is never stored.',
                'CSRF protection on every form submission.',
                'Login attempts are rate limited by email and IP address.',
                'Every permission is enforced in the backend, not just hidden in the interface.',
                'Sessions are invalidated and regenerated on logout.',
                'Public self-registration is disabled; Admins create all accounts.',
            ] as $item)
                <li class="flex gap-2.5">
                    <i class="fa-solid fa-circle-check mt-0.5 text-success"></i>
                    <span class="text-ink dark:text-gray-300">{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
@endsection
