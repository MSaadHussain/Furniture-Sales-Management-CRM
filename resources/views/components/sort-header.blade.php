@props([
    'column',              // the sort key, must match a controller whitelist entry
    'label',
    'align'   => 'left',   // left | right | center
    'default' => 'desc',   // direction applied on the first click
])

@php
    // The controller falls back to its own default when `sort` is absent, so an
    // unsorted page still highlights the column it is actually ordered by.
    $active = request('sort', 'created') === $column;

    $currentDirection = strtolower((string) request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

    // Clicking the active column flips it; a new column starts at its default.
    $nextDirection = $active
        ? ($currentDirection === 'asc' ? 'desc' : 'asc')
        : $default;

    $icon = ! $active
        ? 'fa-sort'
        : ($currentDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down');

    $justify = match ($align) {
        'right'  => 'justify-end',
        'center' => 'justify-center',
        default  => 'justify-start',
    };
@endphp

<th {{ $attributes->merge(['class' => 'ta-th ' . ($align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : ''))]) }}>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]) }}"
       class="group inline-flex items-center gap-1.5 {{ $justify }} transition hover:text-brand {{ $active ? 'text-brand' : '' }}"
       title="Sort by {{ $label }} ({{ $nextDirection === 'asc' ? 'ascending' : 'descending' }})">
        <span>{{ $label }}</span>
        <i class="fa-solid {{ $icon }} text-[10px] {{ $active ? 'opacity-100' : 'opacity-40 group-hover:opacity-80' }}"></i>
    </a>
</th>
