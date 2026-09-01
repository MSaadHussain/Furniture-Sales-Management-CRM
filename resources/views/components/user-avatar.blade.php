@props([
    'user',
    'px' => 40,
    'text' => 'text-sm',
])

@php $url = $user?->avatarUrl(); @endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => 'rounded-full object-cover']) }}
         style="height: {{ $px }}px; width: {{ $px }}px;">
@else
    <span {{ $attributes->merge(['class' => "flex items-center justify-center rounded-full font-bold text-white $text"]) }}
          style="height: {{ $px }}px; width: {{ $px }}px; background-color: {{ $user?->avatar_color ?? '#465FFF' }};">
        {{ $user?->initials() }}
    </span>
@endif
