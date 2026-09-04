{{-- Top header: hamburger, order search, theme toggle, profile menu. --}}
<header class="sticky top-0 z-40 flex w-full border-b border-line bg-white/95 backdrop-blur
               dark:border-strokedark dark:bg-boxdark2/95">
    <div class="flex flex-1 items-center justify-between gap-4 px-4 py-3 sm:px-6">

        <div class="flex flex-1 items-center gap-3">
            <button class="flex h-10 w-10 items-center justify-center rounded-lg border border-line
                           text-ink dark:border-strokedark dark:text-gray-300 lg:hidden"
                    @click="sidebarOpen = true">
                <i class="fa-solid fa-bars"></i>
            </button>

            {{-- Global search hits the order list, which already searches order
                 number, customer name, phone, ZIP and item name. --}}
            @can('view-orders')
                <form method="GET" action="{{ route('orders.index') }}" class="hidden max-w-md flex-1 sm:block">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-muted"></i>
                        <input type="search" name="search" value="{{ request('search') }}"
                               placeholder="Search order number, customer, phone, ZIP..."
                               class="ta-input pl-11" />
                    </div>
                </form>
            @endcan
        </div>

        <div class="flex items-center gap-2 sm:gap-3">

            @can('manage-deliveries')
                @php
                    $tomorrowCount = (int) ($tomorrowDeliveryCount ?? 0);
                @endphp
                <a href="{{ route('deliveries.index', ['date' => today()->addDay()->toDateString()]) }}"
                   class="relative inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition {{ $tomorrowCount > 0 ? 'border-red-300 bg-red-50 text-red-700 shadow-sm hover:bg-red-100 hover:border-red-400 dark:border-red-900/80 dark:bg-red-950/40 dark:text-red-300' : 'border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark' }}"
                   title="{{ $tomorrowCount > 0 ? 'Reminder: ' . $tomorrowCount . ' order(s) due for delivery tomorrow!' : 'Deliveries scheduled for tomorrow' }}">
                    @if ($tomorrowCount > 0)
                        {{-- Blinking Red Light Indicator --}}
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
                        </span>
                        <i class="fa-solid fa-truck-fast text-red-600 dark:text-red-400 animate-pulse"></i>
                    @else
                        <i class="fa-solid fa-truck text-muted"></i>
                    @endif
                    <span class="hidden sm:inline">Tomorrow</span>
                    <span class="ta-badge {{ $tomorrowCount > 0 ? '!bg-red-600 !text-white font-black shadow-xs' : 'bg-surface text-muted dark:bg-boxdark dark:text-gray-400' }}">
                        {{ $tomorrowCount }}
                    </span>
                </a>
            @endcan

            <button @click="$store.theme.toggle()"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-line
                           text-ink transition hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark"
                    title="Toggle dark mode">
                <i class="fa-solid fa-moon" x-show="!$store.theme.dark"></i>
                <i class="fa-solid fa-sun" x-show="$store.theme.dark" x-cloak></i>
            </button>

            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center gap-2.5">
                    <x-user-avatar :user="auth()->user()" :px="40" />
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium text-ink dark:text-gray-200">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-muted">{{ auth()->user()->role->label() }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-down hidden text-xs text-muted sm:block"></i>
                </button>

                <div x-show="open" x-cloak @click.outside="open = false" x-transition
                     class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white
                            shadow-dropdown dark:border-strokedark dark:bg-boxdark2">
                    <div class="border-b border-line px-4 py-3 dark:border-strokedark">
                        <p class="text-sm font-semibold text-ink dark:text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-muted">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}"
                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-ink transition hover:bg-surface
                              dark:text-gray-300 dark:hover:bg-boxdark">
                        <i class="fa-solid fa-user w-4 text-muted"></i> Edit profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-line dark:border-strokedark">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-danger transition hover:bg-surface
                                       dark:hover:bg-boxdark">
                            <i class="fa-solid fa-right-from-bracket w-4"></i> Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
