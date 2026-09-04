@extends('layouts.app')
@section('title', 'Sales Dashboard')

@section('content')
<div class="space-y-6" x-data="{ tab: 'today' }">

    {{-- ============================ Row 1: Daily Target Spotlight Banner ============================ --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-brand to-brand-600 p-6 text-white shadow-lg shadow-brand/15">
        <div class="relative z-10 flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="space-y-1.5">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-xs">
                    <i class="fa-solid fa-bullseye text-amber-300"></i>
                    <span>Daily Sales Goal</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl">
                    Welcome back, {{ $user->name }}
                </h1>
                <p class="text-sm text-white/80">
                    @if ($remainingToTarget > 0)
                        You need <strong class="text-white font-bold">{{ $remainingToTarget }}</strong> more {{ Str::plural('order', $remainingToTarget) }} to hit today's target of <strong class="text-white">{{ $dailyTarget }}</strong>.
                    @else
                        🎉 Amazing work! You've achieved today's target of <strong class="text-white">{{ $dailyTarget }} orders</strong>!
                    @endif
                </p>
            </div>

            {{-- Progress Ring & Goal Bar --}}
            <div class="flex flex-col items-start gap-2 rounded-2xl bg-white/10 p-4 backdrop-blur-md md:items-end md:min-w-[240px]">
                <div class="flex w-full items-center justify-between text-xs font-bold text-white/90">
                    <span>Today's Progress</span>
                    <span>{{ $todayOrdersCount }} / {{ $dailyTarget }} Orders</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-black/20">
                    <div class="h-full rounded-full bg-amber-400 transition-all duration-500 shadow-xs"
                         style="width: {{ $targetAchievedPct }}%"></div>
                </div>
                <div class="text-[11px] font-semibold text-white/75">
                    {{ $targetAchievedPct }}% Completed
                </div>
            </div>
        </div>

        {{-- Subtle decorative background circles --}}
        <div class="pointer-events-none absolute -right-8 -bottom-8 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
    </div>

    {{-- ============================ Row 2: Volume Stats (No Prices) ============================ --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between text-xs font-semibold text-muted">
                <span>Today's Orders</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                    <i class="fa-solid fa-calendar-day"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-ink dark:text-white">
                {{ number_format($todayOrdersCount) }}
            </div>
            <div class="mt-1 text-[11px] text-muted">
                Goal: {{ $dailyTarget }} orders
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between text-xs font-semibold text-muted">
                <span>This Week</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    <i class="fa-solid fa-calendar-week"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-ink dark:text-white">
                {{ number_format($weekOrdersCount) }}
            </div>
            <div class="mt-1 text-[11px] text-muted">
                Orders booked
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between text-xs font-semibold text-muted">
                <span>This Month</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-ink dark:text-white">
                {{ number_format($monthOrdersCount) }}
            </div>
            <div class="mt-1 text-[11px] text-muted">
                Total monthly orders
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-xs dark:border-strokedark dark:bg-boxdark">
            <div class="flex items-center justify-between text-xs font-semibold text-muted">
                <span>Items Sold</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-ink dark:text-white">
                {{ number_format($monthItemsCount) }}
            </div>
            <div class="mt-1 text-[11px] text-muted">
                Furniture units this month
            </div>
        </div>
    </div>

    {{-- ============================ Row 3: Orders & Leaderboard ============================ --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
        
        {{-- Left: Your Assigned Orders --}}
        <div class="xl:col-span-7 space-y-4">
            <div class="rounded-2xl border border-line bg-white p-5 shadow-xs dark:border-strokedark dark:bg-boxdark">
                <div class="flex items-center justify-between border-b border-line/60 pb-3 mb-4 dark:border-strokedark">
                    <div>
                        <h3 class="text-base font-bold text-ink dark:text-white">Your Recent Orders</h3>
                        <p class="text-xs text-muted">Orders assigned to you by admin / manager</p>
                    </div>
                    <span class="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-bold text-brand">
                        {{ $myOrders->count() }} Orders
                    </span>
                </div>

                <div class="space-y-3">
                    @forelse ($myOrders as $order)
                        <div class="rounded-xl border border-line bg-surface/30 p-3.5 transition hover:border-brand/40 dark:border-strokedark dark:bg-boxdark2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-ink dark:text-white">{{ $order->order_number }}</span>
                                        <span class="text-xs text-muted">· {{ $order->created_at->diffForHumans() }}</span>
                                    </div>
                                    
                                    {{-- Items Breakdown --}}
                                    <div class="mt-1.5 text-xs text-ink/80 dark:text-gray-300">
                                        @foreach ($order->items as $item)
                                            <span class="inline-flex items-center gap-1 rounded bg-white px-1.5 py-0.5 font-medium border border-line text-[11px] mr-1 mb-1 dark:bg-boxdark dark:border-strokedark">
                                                <strong class="text-brand">{{ $item->quantity }}x</strong> {{ $item->item_name_snapshot }}
                                                @if ($item->item_colour)
                                                    <span class="text-muted">({{ $item->item_colour }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Status Badges --}}
                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <span class="ta-badge text-[11px] {{ $order->order_status->badgeClass() }}">
                                        {{ $order->order_status->label() }}
                                    </span>
                                    @if ($order->requested_delivery_date)
                                        <span class="text-[10px] font-semibold text-muted">
                                            <i class="fa-solid fa-truck text-[9px] mr-0.5"></i>
                                            {{ $order->requested_delivery_date->format('d M') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">
                            <i class="fa-solid fa-inbox text-3xl text-muted/40 mb-2"></i>
                            <p>No orders assigned to you yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right: Sales Leaderboard --}}
        <div class="xl:col-span-5 space-y-4">
            <div class="rounded-2xl border border-line bg-white p-5 shadow-xs dark:border-strokedark dark:bg-boxdark">
                
                <div class="flex items-center justify-between border-b border-line/60 pb-3 mb-4 dark:border-strokedark">
                    <div>
                        <h3 class="text-base font-bold text-ink dark:text-white">Leaderboard</h3>
                        <p class="text-xs text-muted">Sales representative ranking</p>
                    </div>

                    {{-- Period Switcher --}}
                    <div class="flex items-center rounded-xl bg-surface p-1 dark:bg-boxdark2">
                        <button type="button" x-on:click="tab = 'today'"
                                :class="tab === 'today' ? 'bg-white font-bold text-brand shadow-xs dark:bg-boxdark dark:text-white' : 'text-muted hover:text-ink'"
                                class="rounded-lg px-2.5 py-1 text-xs transition">Today</button>
                        <button type="button" x-on:click="tab = 'week'"
                                :class="tab === 'week' ? 'bg-white font-bold text-brand shadow-xs dark:bg-boxdark dark:text-white' : 'text-muted hover:text-ink'"
                                class="rounded-lg px-2.5 py-1 text-xs transition">Week</button>
                        <button type="button" x-on:click="tab = 'month'"
                                :class="tab === 'month' ? 'bg-white font-bold text-brand shadow-xs dark:bg-boxdark dark:text-white' : 'text-muted hover:text-ink'"
                                class="rounded-lg px-2.5 py-1 text-xs transition">Month</button>
                    </div>
                </div>

                {{-- Leaderboard: Today --}}
                <div x-show="tab === 'today'" class="space-y-2.5">
                    @forelse ($leaderboardToday as $idx => $seller)
                        <div class="flex items-center justify-between rounded-xl border p-3 transition {{ $seller->id === $user->id ? 'border-brand bg-brand/5 dark:bg-brand/10' : 'border-line bg-surface/20 dark:border-strokedark dark:bg-boxdark2' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black {{ $idx === 0 ? 'bg-amber-100 text-amber-700' : ($idx === 1 ? 'bg-slate-200 text-slate-700' : ($idx === 2 ? 'bg-amber-700/20 text-amber-800' : 'text-muted')) }}">
                                    @if ($idx === 0) 🥇 @elseif ($idx === 1) 🥈 @elseif ($idx === 2) 🥉 @else #{{ $idx + 1 }} @endif
                                </span>
                                <div>
                                    <div class="text-xs font-bold text-ink dark:text-white flex items-center gap-1.5">
                                        <span>{{ $seller->name }}</span>
                                        @if ($seller->id === $user->id)
                                            <span class="rounded bg-brand px-1 py-0.2 text-[9px] font-bold text-white">YOU</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black text-brand">{{ $seller->orders_count }}</span>
                                <span class="text-[11px] text-muted ml-0.5">{{ Str::plural('order', $seller->orders_count) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-muted">No orders recorded today yet.</p>
                    @endforelse
                </div>

                {{-- Leaderboard: Week --}}
                <div x-show="tab === 'week'" x-cloak class="space-y-2.5">
                    @forelse ($leaderboardWeek as $idx => $seller)
                        <div class="flex items-center justify-between rounded-xl border p-3 transition {{ $seller->id === $user->id ? 'border-brand bg-brand/5 dark:bg-brand/10' : 'border-line bg-surface/20 dark:border-strokedark dark:bg-boxdark2' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black {{ $idx === 0 ? 'bg-amber-100 text-amber-700' : ($idx === 1 ? 'bg-slate-200 text-slate-700' : ($idx === 2 ? 'bg-amber-700/20 text-amber-800' : 'text-muted')) }}">
                                    @if ($idx === 0) 🥇 @elseif ($idx === 1) 🥈 @elseif ($idx === 2) 🥉 @else #{{ $idx + 1 }} @endif
                                </span>
                                <div>
                                    <div class="text-xs font-bold text-ink dark:text-white flex items-center gap-1.5">
                                        <span>{{ $seller->name }}</span>
                                        @if ($seller->id === $user->id)
                                            <span class="rounded bg-brand px-1 py-0.2 text-[9px] font-bold text-white">YOU</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black text-brand">{{ $seller->orders_count }}</span>
                                <span class="text-[11px] text-muted ml-0.5">{{ Str::plural('order', $seller->orders_count) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-muted">No orders this week yet.</p>
                    @endforelse
                </div>

                {{-- Leaderboard: Month --}}
                <div x-show="tab === 'month'" x-cloak class="space-y-2.5">
                    @forelse ($leaderboardMonth as $idx => $seller)
                        <div class="flex items-center justify-between rounded-xl border p-3 transition {{ $seller->id === $user->id ? 'border-brand bg-brand/5 dark:bg-brand/10' : 'border-line bg-surface/20 dark:border-strokedark dark:bg-boxdark2' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black {{ $idx === 0 ? 'bg-amber-100 text-amber-700' : ($idx === 1 ? 'bg-slate-200 text-slate-700' : ($idx === 2 ? 'bg-amber-700/20 text-amber-800' : 'text-muted')) }}">
                                    @if ($idx === 0) 🥇 @elseif ($idx === 1) 🥈 @elseif ($idx === 2) 🥉 @else #{{ $idx + 1 }} @endif
                                </span>
                                <div>
                                    <div class="text-xs font-bold text-ink dark:text-white flex items-center gap-1.5">
                                        <span>{{ $seller->name }}</span>
                                        @if ($seller->id === $user->id)
                                            <span class="rounded bg-brand px-1 py-0.2 text-[9px] font-bold text-white">YOU</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black text-brand">{{ $seller->orders_count }}</span>
                                <span class="text-[11px] text-muted ml-0.5">{{ Str::plural('order', $seller->orders_count) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-muted">No orders this month yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ============================ Row 4: Products & Colours (Volume Only) ============================ --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card padding="p-5" title="Top Selling Products" subtitle="Popular furniture units sold">
            <div class="space-y-2.5">
                @forelse ($topProducts->take(5) as $product)
                    <div class="flex items-center justify-between text-xs">
                        <span class="truncate text-ink dark:text-gray-300 font-semibold" title="{{ $product->name }}">{{ $product->name }}</span>
                        <span class="font-bold text-brand bg-brand/10 px-2 py-0.5 rounded">{{ number_format($product->quantity) }} sold</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-muted">No product sales in this period.</p>
                @endforelse
            </div>
        </x-card>

        <x-card padding="p-5" title="Popular Colours" subtitle="Customer finish preferences">
            <div class="flex flex-wrap gap-2">
                @forelse ($colours->take(6) as $colour)
                    <span class="inline-flex items-center gap-2 rounded-xl border border-line bg-surface/50 px-3 py-1.5 text-xs font-medium text-ink dark:border-strokedark dark:bg-boxdark2 dark:text-gray-300">
                        <span class="h-3.5 w-3.5 rounded-full border border-black/10"
                              style="background-color: {{ $swatches[$colour->name] ?? '#98A2B3' }}"></span>
                        <span>{{ $colour->name }}</span>
                        <strong class="text-brand font-bold">({{ $colour->quantity }})</strong>
                    </span>
                @empty
                    <p class="py-4 text-center text-xs text-muted">No colour data in this period.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</div>
@endsection
