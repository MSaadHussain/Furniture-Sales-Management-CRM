@props([
    'icon' => 'fa-inbox',
    'title' => 'Nothing here yet',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface text-muted dark:bg-boxdark">
        <i class="fa-solid {{ $icon }} text-xl"></i>
    </span>
    <p class="text-sm font-semibold text-ink dark:text-gray-200">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 max-w-sm text-sm text-muted">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
