@props([
    'label' => '',
    'value' => '0',
    'icon' => 'fa-chart-simple',
    'img' => null,            // image icon (path/URL) — takes precedence over $icon
    'iconColor' => '#465FFF',
    'delta' => null,          // e.g. '+12.5%'
    'deltaUp' => true,        // arrow/colour direction
    'hint' => null,           // small caption under the value
    'href' => null,           // when set, the whole card becomes a link
])

@php
    $tag = $href ? 'a' : 'div';
    $linkClasses = $href
        ? 'block transition hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-brand/40'
        : '';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    class="ta-card p-5 {{ $linkClasses }}">
    <div class="flex items-center justify-between">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl"
             style="background-color: {{ $iconColor }}1A; color: {{ $iconColor }};">
            @if ($img)
                <img src="{{ $img }}" alt="{{ $label }}" class="h-6 w-6 object-contain">
            @else
                <i class="fa-solid {{ $icon }} text-lg"></i>
            @endif
        </div>
        @if ($href)
            <i class="fa-solid fa-arrow-right text-xs text-muted"></i>
        @endif
    </div>

    <div class="mt-4 flex items-end justify-between">
        <div>
            <span class="text-sm font-medium text-muted">{{ $label }}</span>
            <h4 class="mt-1 text-3xl font-bold text-ink dark:text-white">{{ $value }}</h4>
            @if ($hint)
                <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
            @endif
        </div>

        @if (! is_null($delta))
            <span class="ta-badge {{ $deltaUp ? 'text-success' : 'text-danger' }}"
                  style="background-color: {{ $deltaUp ? '#12B76A' : '#F04438' }}1A;">
                <i class="fa-solid {{ $deltaUp ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1 text-[10px]"></i>
                {{ $delta }}
            </span>
        @endif
    </div>
</{{ $tag }}>