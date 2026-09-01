@props(['amount' => 0, 'compact' => false])

{{-- Currency formatting is driven entirely by Settings, so an unconfigured
     currency simply renders a bare number. --}}
<span {{ $attributes }}>{{ $compact ? \App\Support\Money::compact($amount) : \App\Support\Money::format($amount) }}</span>
