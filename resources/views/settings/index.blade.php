@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<form method="POST" action="{{ route('settings.update') }}" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @csrf
    @method('PATCH')

    <div class="space-y-6 lg:col-span-2">

        <x-card title="Business" subtitle="How the CRM identifies itself">
            <div>
                <label class="ta-label">Business name</label>
                <input type="text" name="business_name" maxlength="120"
                       value="{{ old('business_name', $settings['business_name']) }}"
                       placeholder="{{ config('app.name') }}" class="ta-input">
                <p class="mt-1 text-xs text-muted">
                    Shown in the sidebar, page titles and the sign-in screen.
                    Leave blank to use the APP_NAME value from the environment file.
                </p>
                @error('business_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>
        </x-card>

        <x-card title="Currency" subtitle="Applies to every amount shown in the CRM and in exports">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="ta-label">Symbol</label>
                    <input type="text" name="currency_symbol" maxlength="8"
                           value="{{ old('currency_symbol', $settings['currency_symbol']) }}"
                           placeholder="e.g. Rs. or $" class="ta-input">
                    @error('currency_symbol')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ta-label">Decimal places</label>
                    <select name="currency_decimals" class="ta-input">
                        @for ($i = 0; $i <= 4; $i++)
                            <option value="{{ $i }}" @selected((int) old('currency_decimals', $settings['currency_decimals']) === $i)>{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="ta-label">Symbol position</label>
                    <select name="currency_position" class="ta-input">
                        <option value="before" @selected(old('currency_position', $settings['currency_position']) === 'before')>Before the amount</option>
                        <option value="after" @selected(old('currency_position', $settings['currency_position']) === 'after')>After the amount</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-line bg-surface px-4 py-3 dark:border-strokedark dark:bg-boxdark">
                <p class="text-xs uppercase tracking-wide text-muted">Preview</p>
                <p class="mt-1 text-lg font-semibold text-ink dark:text-white">
                    <x-money :amount="85000" /> <span class="text-sm font-normal text-muted">/ <x-money :amount="82400000" compact /></span>
                </p>
                @unless ($settings['currency_symbol'])
                    <p class="mt-1 text-xs text-muted">No symbol set yet, so amounts render as plain numbers.</p>
                @endunless
            </div>
        </x-card>

        <x-card title="Manager permissions"
                subtitle="Managers run day-to-day operations. Anything destructive or system-level is off until you grant it here.">
            <div class="space-y-4">
                @foreach ($permissions as $key => $meta)
                    <label class="flex items-start justify-between gap-4">
                        <span>
                            <span class="block text-sm font-medium text-ink dark:text-gray-200">{{ $meta['label'] }}</span>
                            <span class="block text-xs text-muted">{{ $meta['hint'] }}</span>
                        </span>
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key])
                               class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-line text-brand focus:ring-brand/40">
                    </label>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-muted">
                Sales Persons are never affected by these switches. They can only reach the dashboard.
            </p>
        </x-card>

        <x-card title="Defaults" subtitle="Starting values for new screens and orders">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="ta-label">Default dashboard period</label>
                    <select name="default_date_range" class="ta-input">
                        @foreach (\App\Services\DateRangeService::presets() as $key => $label)
                            @continue($key === 'custom')
                            <option value="{{ $key }}" @selected(old('default_date_range', $settings['default_date_range']) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ta-label">Default lead time (days)</label>
                    <input type="number" name="default_delivery_lead_days" min="0" max="365"
                           value="{{ old('default_delivery_lead_days', $settings['default_delivery_lead_days']) }}" class="ta-input">
                    <p class="mt-1 text-xs text-muted">Pre-fills requested delivery date.</p>
                </div>
                <div>
                    <label class="ta-label">Daily Target (orders/day)</label>
                    <input type="number" name="daily_sales_target" min="1" max="1000"
                           value="{{ old('daily_sales_target', $settings['daily_sales_target']) }}" class="ta-input">
                    <p class="mt-1 text-xs text-muted">Target per sales person.</p>
                </div>
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Save">
            <button type="submit" class="btn btn-primary w-full">
                <i class="fa-solid fa-floppy-disk"></i> Save settings
            </button>
            <p class="mt-3 text-xs text-muted">Every settings change is written to the audit log.</p>
        </x-card>

        <x-card title="Other settings">
            <div class="space-y-2">
                <a href="{{ route('settings.security') }}"
                   class="flex items-center justify-between rounded-lg border border-line px-3 py-2.5 text-sm transition hover:border-brand dark:border-strokedark">
                    <span class="flex items-center gap-2 text-ink dark:text-gray-300">
                        <i class="fa-solid fa-shield-halved w-4 text-muted"></i> Security
                    </span>
                    <i class="fa-solid fa-arrow-right text-xs text-muted"></i>
                </a>
                <a href="{{ route('audit.index') }}"
                   class="flex items-center justify-between rounded-lg border border-line px-3 py-2.5 text-sm transition hover:border-brand dark:border-strokedark">
                    <span class="flex items-center gap-2 text-ink dark:text-gray-300">
                        <i class="fa-solid fa-clipboard-list w-4 text-muted"></i> Audit logs
                    </span>
                    <i class="fa-solid fa-arrow-right text-xs text-muted"></i>
                </a>
                <a href="{{ route('activity.logs') }}"
                   class="flex items-center justify-between rounded-lg border border-line px-3 py-2.5 text-sm transition hover:border-brand dark:border-strokedark">
                    <span class="flex items-center gap-2 text-ink dark:text-gray-300">
                        <i class="fa-solid fa-clock-rotate-left w-4 text-muted"></i> User activity
                    </span>
                    <i class="fa-solid fa-arrow-right text-xs text-muted"></i>
                </a>
            </div>
        </x-card>

        @if (auth()->user()->isAdmin())
            <div class="rounded-2xl border border-danger/30 bg-danger/5 p-4 dark:border-danger/40 dark:bg-danger/10">
                <div class="flex items-center gap-2 text-xs font-bold text-danger uppercase tracking-wider mb-1.5">
                    <i class="fa-solid fa-triangle-exclamation"></i> Danger Zone
                </div>
                <p class="text-xs text-ink/80 dark:text-gray-300 mb-3">
                    Permanently wipe all operational data (orders, customers, and analytics) with multi-step confirmation and password verification.
                </p>
                <a href="{{ route('settings.reset') }}"
                   class="inline-flex items-center justify-center gap-2 w-full rounded-xl bg-danger hover:bg-danger/90 text-white font-bold text-xs px-3.5 py-2 transition shadow-xs">
                    <i class="fa-solid fa-trash-can"></i> Reset All Data
                </a>
            </div>
        @endif
    </div>
</form>
@endsection
