@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-5 sm:p-6',
])

@php
    // Flush cards (p-0) host edge-to-edge tables/lists; their header needs
    // its own padding and a divider so titles don't hug the card corner.
    $flush = trim($padding) === 'p-0';
@endphp

<div {{ $attributes->merge(['class' => 'ta-card ' . $padding]) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex items-start justify-between gap-4 {{ $flush ? 'border-b border-line px-6 py-4 dark:border-strokedark' : 'mb-5' }}">
            <div>
                @if ($title)
                    <h3 class="text-lg font-semibold text-ink dark:text-white">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
