@php
    $brand = \App\Models\Setting::get('business_name') ?: config('app.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ $brand }}</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen items-center justify-center bg-sidebar px-4 py-10">
        <div class="w-full max-w-md">

            <div class="mb-8 flex items-center justify-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand text-lg text-white">
                    <i class="fa-solid fa-couch"></i>
                </span>
                <span class="text-xl font-bold tracking-wide text-white">{{ $brand }}</span>
            </div>

            <div class="rounded-2xl bg-white p-7 shadow-card-lg sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">
                Accounts are created by an administrator.
            </p>
        </div>
    </div>
</body>
</html>
