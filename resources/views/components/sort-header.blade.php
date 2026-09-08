@props([
    'column',              // the sort key, must match a controller whitelist entry
    'label',
    'align'    => 'left',  // left | right | center
    'default'  => 'desc',  // direction applied on the first click
    'sub'      => null,    // optional second sort key rendered under the label
    'subLabel' => null,
    'subDefault' => 'desc',
])

@php
    // The controller falls back to its own default when `sort` is absent, so an
    // unsorted page still highlights the column it is actually ordered by.
    $current = request('sort', 'created');
    $currentDirection = strtolower((string) request('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

    // Clicking the active column flips it; a new column starts at its default.
    $link = function (string $key, string $fallback) use ($current, $currentDirection) {
        $active = $current === $key;

        return [
            'active' => $active,
            'url'    => request()->fullUrlWithQuery([
                'sort'      => $key,
                'direction' => $active ? ($currentDirection === 'asc' ? 'desc' : 'asc') : $fallback,
                'page'      => null,
            ]),
            'icon'   => ! $active
                ? 'fa-sort'
                : ($currentDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'),
        ];
    };

    $main = $link($column, $default);
    $second = $sub ? $link($sub, $subDefault) : null;

    $justify = match ($align) {
        'right'  => 'justify-end',
        'center' => 'justify-center',
        default  => 'justify-start',
    };
    $textAlign = match ($align) {
        'right'  => 'text-right',
        'center' => 'text-center',
        default  => '',
    };
@endphp

<th {{ $attributes->merge(['class' => 'ta-th ' . $textAlign]) }}>
    <a href="{{ $main['url'] }}"
       class="group inline-flex items-center gap-1.5 {{ $justify }} transition hover:text-brand {{ $main['active'] ? 'text-brand' : '' }}"
       title="Sort by {{ $label }}">
        <span>{{ $label }}</span>
        <i class="fa-solid {{ $main['icon'] }} text-[10px] {{ $main['active'] ? 'opacity-100' : 'opacity-40 group-hover:opacity-80' }}"></i>
    </a>

    @if ($second)
        {{-- Second sort key for a column whose cell shows two values. --}}
        <a href="{{ $second['url'] }}"
           class="group mt-0.5 flex items-center gap-1 text-[10px] font-normal normal-case {{ $justify }} transition hover:text-brand {{ $second['active'] ? 'text-brand' : 'text-muted' }}"
           title="Sort by {{ $subLabel }}">
            <span>{{ $subLabel }}</span>
            <i class="fa-solid {{ $second['icon'] }} text-[9px] {{ $second['active'] ? 'opacity-100' : 'opacity-40 group-hover:opacity-80' }}"></i>
        </a>
    @endif
</th>
