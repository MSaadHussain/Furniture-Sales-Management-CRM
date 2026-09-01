@props(['value' => null, 'invert' => false])

{{-- Period-over-period change. A null value means there was no baseline to
     compare against, which the requirements say must read as N/A. --}}
@php
    $isNull = $value === null;
    $up     = ! $isNull && $value >= 0;
    $good   = $invert ? ! $up : $up;
    $color  = $isNull ? '#98A2B3' : ($good ? '#12B76A' : '#F04438');
@endphp

<span {{ $attributes->merge(['class' => 'ta-badge']) }}
      style="background-color: {{ $color }}1A; color: {{ $color }};">
    @unless ($isNull)
        <i class="fa-solid {{ $up ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1 text-[10px]"></i>
    @endunless
    {{ \App\Services\DateRangeService::growthLabel($value) }}
</span>
