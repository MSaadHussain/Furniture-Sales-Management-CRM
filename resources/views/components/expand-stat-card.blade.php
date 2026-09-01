@props([
    'label' => '',
    'value' => '0',
    'icon' => 'fa-chart-simple',
    'iconColor' => '#465FFF',
    'hint' => null,
    'rows' => [],   // each row: ['label', 'icon', 'color', 'value', 'href']
])

<div x-data="{ open: false }"
     @click="open = !open"
     class="ta-card cursor-pointer p-5 transition hover:-translate-y-0.5 hover:shadow-lg"
     :class="open && 'ring-2 ring-brand/30'">
    <div class="flex items-center justify-between">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl"
             style="background-color: {{ $iconColor }}1A; color: {{ $iconColor }};">
            <i class="fa-solid {{ $icon }} text-lg"></i>
        </div>
        <i class="fa-solid fa-chevron-down text-xs text-muted transition-transform duration-300" :class="open && 'rotate-180'"></i>
    </div>

    <div class="mt-4">
        <span class="text-sm font-medium text-muted">{{ $label }}</span>
        <h4 class="mt-1 text-3xl font-bold text-ink dark:text-white">{{ $value }}</h4>
        @if ($hint)
            <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
        @endif
    </div>

    <div x-show="open" x-collapse @click.stop class="mt-4 space-y-2 border-t border-line pt-3 dark:border-strokedark">
        @foreach ($rows as $row)
            <a href="{{ $row['href'] }}" class="flex items-center justify-between rounded-lg px-2 py-2 transition hover:bg-surface dark:hover:bg-boxdark/60">
                <span class="flex items-center gap-2 text-sm text-ink dark:text-gray-200">
                    <i class="fa-solid {{ $row['icon'] }} w-4 text-center" style="color: {{ $row['color'] }}"></i> {{ $row['label'] }}
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-sm font-bold text-ink dark:text-white">{{ $row['value'] }}</span>
                    <i class="fa-solid fa-arrow-right text-xs text-muted"></i>
                </span>
            </a>
        @endforeach
    </div>
</div>
