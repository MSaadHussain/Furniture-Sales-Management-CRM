@extends('layouts.app')
@section('title', 'Delivery Calendar')
@section('breadcrumb')
    <a href="{{ route('deliveries.index') }}" class="hover:text-brand">Deliveries</a> / Calendar
@endsection

@section('header_actions')
    <a href="{{ route('deliveries.index') }}" class="btn btn-light">
        <i class="fa-solid fa-list"></i> Daily board
    </a>
@endsection

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Scheduled this month" icon="fa-truck" icon-color="#465FFF" :value="$monthTotal" />
        <x-stat-card label="Value this month" icon="fa-sack-dollar" icon-color="#12B76A"
                     :value="\App\Support\Money::compact($monthValue)" />
        <x-stat-card label="Month" icon="fa-calendar" icon-color="#7A5AF8" :value="$month->format('M Y')" />
    </div>

    <x-card padding="p-0">
        <div class="flex items-center justify-between border-b border-line px-5 py-4 dark:border-strokedark">
            <a href="{{ route('deliveries.calendar', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')]) }}"
               class="btn btn-light"><i class="fa-solid fa-chevron-left"></i></a>

            <h3 class="text-lg font-semibold text-ink dark:text-white">{{ $month->format('F Y') }}</h3>

            <a href="{{ route('deliveries.calendar', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')]) }}"
               class="btn btn-light"><i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="overflow-x-auto p-4">
            <div class="min-w-[640px]">
                {{-- Weekday header, Monday first to match the ISO week used above. --}}
                <div class="mb-2 grid grid-cols-7 gap-2">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                        <div class="py-1 text-center text-xs font-semibold uppercase tracking-wide text-muted">{{ $weekday }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-2">
                    @for ($blank = 0; $blank < $leadingBlank; $blank++)
                        <div class="min-h-[92px] rounded-xl border border-dashed border-line/60 dark:border-strokedark/60"></div>
                    @endfor

                    @for ($day = 1; $day <= $daysInMonth; $day++)
                        @php
                            $date  = $month->copy()->day($day);
                            $key   = $date->toDateString();
                            $stats = $counts[$key] ?? null;
                            $total = $stats['total'] ?? 0;
                        @endphp

                        <a href="{{ route('deliveries.index', ['date' => $key]) }}"
                           class="flex min-h-[92px] flex-col rounded-xl border p-2 transition hover:border-brand hover:shadow-card
                                  {{ $date->isToday()
                                      ? 'border-brand bg-brand/5'
                                      : ($total > 0 ? 'border-line dark:border-strokedark' : 'border-line/60 dark:border-strokedark/60') }}">
                            <span class="text-xs font-semibold {{ $date->isToday() ? 'text-brand' : 'text-muted' }}">{{ $day }}</span>

                            @if ($total > 0)
                                <span class="mt-auto">
                                    <span class="block text-2xl font-bold text-ink dark:text-white">{{ $total }}</span>
                                    <span class="block text-[11px] text-muted">
                                        {{ $stats['pending'] }} pending &middot; {{ $stats['delivered'] }} done
                                    </span>
                                </span>
                            @else
                                <span class="mt-auto text-[11px] text-muted">&mdash;</span>
                            @endif
                        </a>
                    @endfor
                </div>
            </div>
        </div>
    </x-card>
</div>
@endsection
