@props(['status'])

{{-- Renders any backed enum that exposes label(), color() and icon(). --}}
<span {{ $attributes->merge(['class' => 'ta-badge gap-1.5']) }}
      style="background-color: {{ $status->color() }}1A; color: {{ $status->color() }};">
    @if (method_exists($status, 'icon'))
        <i class="fa-solid {{ $status->icon() }} text-[10px]"></i>
    @endif
    {{ $status->label() }}
</span>
