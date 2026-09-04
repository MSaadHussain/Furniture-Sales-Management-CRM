@extends('layouts.app')
@section('title', 'System Data Reset')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    understood: {{ old('confirm_understanding') ? 'true' : 'false' }},
    irreversible: {{ old('confirm_irreversible') ? 'true' : 'false' }},
    typedPhrase: '{{ old('confirm_phrase', '') }}',
    adminPassword: '',
    get isUnlocked() {
        return this.understood && this.irreversible && this.typedPhrase.trim() === 'DELETE ALL DATA' && this.adminPassword.length > 0;
    }
}">
    {{-- Header & Back Navigation --}}
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-brand transition mb-1">
                <i class="fa-solid fa-arrow-left"></i> Back to Settings
            </a>
            <h1 class="text-2xl font-black text-ink dark:text-white flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-danger/10 text-danger text-base">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <span>System Data Reset</span>
            </h1>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-danger/10 px-3 py-1 text-xs font-bold text-danger">
            <i class="fa-solid fa-shield-halved"></i> Administrator Only
        </span>
    </div>

    {{-- Live Database Statistics --}}
    <div class="rounded-2xl border border-line bg-white p-5 shadow-xs dark:border-strokedark dark:bg-boxdark">
        <h3 class="text-xs font-bold uppercase tracking-wider text-muted mb-3">Live System Records in Database</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['orders']) }}</span>
                <span class="text-[11px] text-muted font-medium">Orders</span>
            </div>
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['items']) }}</span>
                <span class="text-[11px] text-muted font-medium">Order Items</span>
            </div>
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['customers']) }}</span>
                <span class="text-[11px] text-muted font-medium">Customers</span>
            </div>
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['products']) }}</span>
                <span class="text-[11px] text-muted font-medium">Products</span>
            </div>
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['staff']) }}</span>
                <span class="text-[11px] text-muted font-medium">Sales Reps</span>
            </div>
            <div class="rounded-xl border border-line/60 bg-surface/50 p-3 text-center dark:border-strokedark dark:bg-boxdark2">
                <span class="block text-xl font-black text-ink dark:text-white">{{ number_format($stats['logs']) }}</span>
                <span class="text-[11px] text-muted font-medium">Audit Logs</span>
            </div>
        </div>
    </div>

    {{-- Danger Notice Box --}}
    <div class="rounded-2xl border border-danger/30 bg-danger/5 p-5 dark:border-danger/40 dark:bg-danger/10">
        <div class="flex items-start gap-3.5">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-danger/15 text-danger">
                <i class="fa-solid fa-fire text-base"></i>
            </span>
            <div class="space-y-1 text-xs">
                <h4 class="text-sm font-black text-danger">Permanent Destructive Action</h4>
                <p class="text-ink/80 dark:text-gray-200 leading-relaxed">
                    Executing this reset will permanently delete all orders, ordered products, customer directories, and logs from the system.
                    <strong>Your administrator account and system preferences will remain safe.</strong>
                </p>
            </div>
        </div>
    </div>

    {{-- Multi-Step Confirmation & Relogin Form --}}
    <div class="rounded-2xl border border-line bg-white p-6 shadow-xs dark:border-strokedark dark:bg-boxdark">
        <form method="POST" action="{{ route('settings.reset.execute') }}" class="space-y-6">
            @csrf

            {{-- Step 1: Checkbox Acknowledgments --}}
            <div class="space-y-3 pb-5 border-b border-line/60 dark:border-strokedark">
                <span class="text-xs font-bold uppercase tracking-wider text-danger flex items-center gap-1.5">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white text-[10px]">1</span>
                    Confirm Acknowledgments
                </span>

                <div class="space-y-2.5">
                    <label class="flex items-start gap-3 rounded-xl border border-line bg-surface/40 p-3.5 cursor-pointer dark:border-strokedark dark:bg-boxdark2 hover:bg-surface transition">
                        <input type="checkbox" name="confirm_understanding" value="1"
                               x-model="understood"
                               class="mt-0.5 h-4 w-4 rounded border-line text-danger focus:ring-danger/40">
                        <span class="text-xs font-semibold text-ink dark:text-gray-200">
                            I understand that all transactional orders, line items, customer histories, and analytical reports will be completely wiped.
                        </span>
                    </label>
                    @error('confirm_understanding')<p class="text-xs text-danger font-medium">{{ $message }}</p>@enderror

                    <label class="flex items-start gap-3 rounded-xl border border-line bg-surface/40 p-3.5 cursor-pointer dark:border-strokedark dark:bg-boxdark2 hover:bg-surface transition">
                        <input type="checkbox" name="confirm_irreversible" value="1"
                               x-model="irreversible"
                               class="mt-0.5 h-4 w-4 rounded border-line text-danger focus:ring-danger/40">
                        <span class="text-xs font-semibold text-ink dark:text-gray-200">
                            I acknowledge that this action is irreversible and cannot be rolled back or recovered.
                        </span>
                    </label>
                    @error('confirm_irreversible')<p class="text-xs text-danger font-medium">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Optional Wipe Scopes --}}
            <div class="space-y-3 pb-5 border-b border-line/60 dark:border-strokedark">
                <span class="text-xs font-bold uppercase tracking-wider text-muted flex items-center gap-1.5">
                    <i class="fa-solid fa-sliders text-xs"></i> Optional Scopes
                </span>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 rounded-xl border border-line/60 bg-surface/30 p-3 cursor-pointer dark:border-strokedark dark:bg-boxdark2">
                        <input type="checkbox" name="wipe_catalogue" value="1" @checked(old('wipe_catalogue'))
                               class="mt-0.5 h-4 w-4 rounded border-line text-danger focus:ring-danger/40">
                        <div>
                            <span class="text-xs font-bold text-ink dark:text-gray-200 block">Wipe Product Catalogue</span>
                            <span class="text-[11px] text-muted block">Deletes all products, categories and colours</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-line/60 bg-surface/30 p-3 cursor-pointer dark:border-strokedark dark:bg-boxdark2">
                        <input type="checkbox" name="wipe_staff" value="1" @checked(old('wipe_staff'))
                               class="mt-0.5 h-4 w-4 rounded border-line text-danger focus:ring-danger/40">
                        <div>
                            <span class="text-xs font-bold text-ink dark:text-gray-200 block">Wipe Sales Representatives</span>
                            <span class="text-[11px] text-muted block">Removes non-admin sales person accounts</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Step 2: Verification Phrase --}}
            <div class="space-y-3 pb-5 border-b border-line/60 dark:border-strokedark">
                <span class="text-xs font-bold uppercase tracking-wider text-danger flex items-center gap-1.5">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white text-[10px]">2</span>
                    Type Confirmation Phrase
                </span>

                <div>
                    <label class="text-xs text-ink/80 dark:text-gray-300 block mb-1.5">
                        Please type <strong class="font-mono bg-danger/10 text-danger px-2 py-0.5 rounded text-xs">DELETE ALL DATA</strong> in uppercase below:
                    </label>
                    <input type="text" name="confirm_phrase"
                           x-model="typedPhrase"
                           placeholder="DELETE ALL DATA"
                           autocomplete="off"
                           class="ta-input !font-mono !text-xs !tracking-wider uppercase w-full">
                    @error('confirm_phrase')<p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Step 3: Admin Password Re-Authentication (Relogin verification) --}}
            <div class="space-y-3 pb-5 border-b border-line/60 dark:border-strokedark">
                <span class="text-xs font-bold uppercase tracking-wider text-danger flex items-center gap-1.5">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white text-[10px]">3</span>
                    Administrator Password Re-Authentication
                </span>

                <div>
                    <label class="text-xs text-ink/80 dark:text-gray-300 block mb-1.5">
                        Enter your current Administrator password to authorize this action:
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-muted text-xs"></i>
                        <input type="password" name="password"
                               x-model="adminPassword"
                               placeholder="Enter your administrator password"
                               class="ta-input !pl-9 !text-xs w-full">
                    </div>
                    @error('password')<p class="mt-1 text-xs text-danger font-medium">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Submit and Cancel Actions --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('settings.index') }}" class="btn btn-light !text-xs">
                    Cancel & Keep Data
                </a>

                <button type="submit"
                        :disabled="!isUnlocked"
                        :class="isUnlocked ? 'bg-danger hover:bg-danger/90 text-white cursor-pointer shadow-md' : 'bg-line text-muted cursor-not-allowed dark:bg-strokedark'"
                        class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold transition">
                    <i class="fa-solid fa-trash-can"></i>
                    <span>Permanently Wipe All Data</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
