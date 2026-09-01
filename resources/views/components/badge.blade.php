@props([
    'label' => '',
    'color' => '#667085',
])

{{-- TailAdmin-style tinted pill badge. Color drives both the soft bg and the text. --}}
<span {{ $attributes->merge(['class' => 'ta-badge']) }}
      style="background-color: {{ $color }}1A; color: {{ $color }};">
    {{ $label !== '' ? $label : $slot }}
</span>
