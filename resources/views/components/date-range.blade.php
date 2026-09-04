@props([
    'range',      // ['from' => Carbon, 'to' => Carbon, 'label' => string, 'preset' => string]
    'presets',    // key => label
    'action' => null,
])

@php
    $mainPresets = [
        'today'      => 'Today',
        'this_week'  => 'This Week',
        'this_month' => 'This Month',
    ];
    $morePresets = [
        'yesterday'     => 'Yesterday',
        'last_7_days'   => 'Last 7 Days',
        'last_month'    => 'Last Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'this_year'     => 'This Year',
    ];
    $isMoreActive = array_key_exists($range['preset'], $morePresets);
@endphp

<div x-data="{ custom: {{ $range['preset'] === 'custom' ? 'true' : 'false' }}, openMenu: false }"
     {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-white p-3 sm:p-4 shadow-xs dark:border-strokedark dark:bg-boxdark']) }}>

    {{-- Main Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        
        {{-- Left: Desktop Quick Buttons & Mobile Native/Custom Dropdown --}}
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            
            {{-- Mobile Dropdown Selector (Visible on mobile only) --}}
            <div class="w-full sm:hidden">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <select onchange="if(this.value === 'custom') { window.Alpine.evaluate(this, 'custom = true'); } else { window.location.href = this.value; }"
                                class="ta-input !py-2 !text-xs font-semibold !pr-8 bg-surface/50">
                            @foreach ($presets as $key => $label)
                                @if ($key === 'custom')
                                    <option value="custom" @selected($range['preset'] === 'custom')>📅 Custom Range...</option>
                                @else
                                    <option value="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
                                            @selected($range['preset'] === $key)>
                                        {{ $label }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Desktop Primary Quick Pills (Hidden on mobile) --}}
            <div class="hidden sm:flex items-center gap-1.5 rounded-xl bg-surface/60 p-1 border border-line/60 dark:border-strokedark dark:bg-boxdark2">
                @foreach ($mainPresets as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
                       class="rounded-lg px-3 py-1 text-xs font-semibold transition
                              {{ $range['preset'] === $key
                                  ? 'bg-brand text-white shadow-xs'
                                  : 'text-muted hover:text-ink dark:text-gray-300 dark:hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach

                {{-- More Presets Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition
                                   {{ $isMoreActive
                                       ? 'bg-brand text-white shadow-xs'
                                       : 'text-muted hover:text-ink dark:text-gray-300 dark:hover:text-white' }}">
                        <span>{{ $isMoreActive ? $morePresets[$range['preset']] : 'More' }}</span>
                        <i class="fa-solid fa-chevron-down text-[9px] transition" :class="{ 'rotate-180': open }"></i>
                    </button>

                    <div x-show="open" x-cloak x-transition
                         class="absolute left-0 mt-1 z-50 w-44 rounded-xl border border-line bg-white p-1 shadow-xl dark:border-strokedark dark:bg-boxdark">
                        @foreach ($morePresets as $key => $label)
                            <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
                               class="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-xs font-medium transition
                                      {{ $range['preset'] === $key ? 'bg-brand/10 text-brand font-bold' : 'text-ink hover:bg-surface dark:text-gray-200 dark:hover:bg-boxdark2' }}">
                                <span>{{ $label }}</span>
                                @if ($range['preset'] === $key)
                                    <i class="fa-solid fa-check text-[10px]"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Custom Range Toggle Button (Desktop) --}}
            <button type="button" @click="custom = !custom"
                    class="hidden sm:inline-flex items-center gap-1.5 rounded-xl border border-line px-3 py-1.5 text-xs font-semibold transition dark:border-strokedark
                           {{ $range['preset'] === 'custom'
                               ? 'bg-brand text-white border-brand shadow-xs'
                               : 'bg-white text-ink hover:bg-surface dark:bg-boxdark dark:text-gray-300 dark:hover:bg-boxdark2' }}">
                <i class="fa-regular fa-calendar text-xs"></i>
                <span>Custom</span>
            </button>
        </div>

        {{-- Right: Date Range Label --}}
        <div class="flex items-center justify-between sm:justify-end gap-2 text-xs font-semibold text-muted">
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-surface/80 px-2.5 py-1 text-ink/80 dark:bg-boxdark2 dark:text-gray-300">
                <i class="fa-regular fa-calendar-days text-brand text-[11px]"></i>
                <span>{{ $range['from']->format('d M Y') }} &ndash; {{ $range['to']->format('d M Y') }}</span>
            </span>
        </div>
    </div>

    {{-- Collapsible Custom Date Form --}}
    <form method="GET" action="{{ $action ?? url()->current() }}" x-show="custom" x-cloak x-collapse
          class="mt-3.5 flex flex-wrap items-end gap-3 border-t border-line/60 pt-3.5 dark:border-strokedark">
        
        {{-- Preserve Other Query Parameters --}}
        @foreach (request()->except(['range', 'from', 'to', 'page']) as $key => $value)
            @if (! is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="range" value="custom">

        <div class="w-full sm:w-auto">
            <label class="text-[11px] font-bold text-muted block mb-1">From Date</label>
            <input type="text" name="from" value="{{ $range['from']->format('Y-m-d') }}"
                   x-datepicker
                   class="ta-input !py-1.5 !text-xs font-semibold">
        </div>
        <div class="w-full sm:w-auto">
            <label class="text-[11px] font-bold text-muted block mb-1">To Date</label>
            <input type="text" name="to" value="{{ $range['to']->format('Y-m-d') }}"
                   x-datepicker
                   class="ta-input !py-1.5 !text-xs font-semibold">
        </div>
        <button type="submit" class="btn btn-primary !py-1.5 !px-4 text-xs font-bold w-full sm:w-auto">
            Apply Range
        </button>
    </form>
</div>
