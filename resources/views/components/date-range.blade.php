@props([
    'range',      // ['from' => Carbon, 'to' => Carbon, 'label' => string, 'preset' => string]
    'presets',    // key => label
    'action' => null,
])

{{-- The date filter shared by the dashboard and every report (requirements 16.2).
     Preset links keep the rest of the query string so other filters survive. --}}
<div x-data="{ custom: {{ $range['preset'] === 'custom' ? 'true' : 'false' }} }"
     {{ $attributes->merge(['class' => 'ta-card p-4']) }}>

    <div class="flex flex-wrap items-center gap-2">
        <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-muted">Period</span>

        @foreach ($presets as $key => $label)
            @if ($key === 'custom')
                <button type="button" @click="custom = !custom"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                               {{ $range['preset'] === 'custom'
                                   ? 'bg-brand text-white'
                                   : 'border border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark' }}">
                    <i class="fa-regular fa-calendar mr-1 text-xs"></i>{{ $label }}
                </button>
            @else
                <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                          {{ $range['preset'] === $key
                              ? 'bg-brand text-white'
                              : 'border border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark' }}">
                    {{ $label }}
                </a>
            @endif
        @endforeach

        <span class="ml-auto text-sm text-muted">
            {{ $range['from']->format('d M Y') }} &ndash; {{ $range['to']->format('d M Y') }}
        </span>
    </div>

    <form method="GET" action="{{ $action ?? url()->current() }}" x-show="custom" x-cloak x-collapse
          class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4 dark:border-strokedark">
        {{-- Carry every other active filter through the custom-range submit. --}}
        @foreach (request()->except(['range', 'from', 'to', 'page']) as $key => $value)
            @if (! is_array($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="range" value="custom">

        <div>
            <label class="ta-label">From</label>
            <input type="date" name="from" value="{{ $range['from']->format('Y-m-d') }}" class="ta-input">
        </div>
        <div>
            <label class="ta-label">To</label>
            <input type="date" name="to" value="{{ $range['to']->format('Y-m-d') }}" class="ta-input">
        </div>
        <button type="submit" class="btn btn-primary">Apply</button>
    </form>
</div>
