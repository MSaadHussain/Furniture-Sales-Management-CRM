{{--
    Main navigation. Everything below the dashboard is wrapped in a capability
    gate, so a Sales Person sees a sidebar containing only "Dashboard"
    (requirements 6.2). Hiding the link is presentation only; the controllers
    enforce the same rules server-side.
--}}
@php
    $brand = \App\Models\Setting::get('business_name') ?: config('app.name');
@endphp

<aside
    class="fixed left-0 top-0 z-50 flex h-screen w-72 flex-col overflow-y-hidden bg-sidebar
           transition-transform duration-300 ease-in-out lg:static lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- Brand --}}
    <div class="flex items-center justify-between gap-2 px-6 py-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand text-white">
                <i class="fa-solid fa-couch"></i>
            </span>
            <span class="text-[15px] font-bold tracking-wide text-white">{{ $brand }}</span>
        </a>
        <button class="text-gray-400 lg:hidden" @click="sidebarOpen = false">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <nav class="no-scrollbar flex flex-1 flex-col overflow-y-auto px-4 pb-6">
        <p class="mb-3 mt-2 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Menu</p>
        <ul class="flex flex-col gap-1">

            <li>
                <a href="{{ route('dashboard') }}"
                   class="ta-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i><span>Dashboard</span>
                </a>
            </li>

            @can('view-orders')
                <li x-data="{ open: {{ request()->routeIs('orders.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="ta-nav-link w-full {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-receipt w-5 text-center"></i>
                        <span class="flex-1 text-left">Sales</span>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                    </button>
                    <ul x-show="open" x-collapse class="mt-1 flex flex-col gap-1 pl-9">
                        <li>
                            <a href="{{ route('orders.index') }}"
                               class="ta-nav-sub {{ request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.edit') ? 'active' : '' }}">
                                All Orders
                            </a>
                        </li>
                        @can('manage-orders')
                            <li>
                                <a href="{{ route('orders.create') }}"
                                   class="ta-nav-sub {{ request()->routeIs('orders.create') ? 'active' : '' }}">
                                    Add New Order
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endcan

            @can('view-customers')
                <li>
                    <a href="{{ route('customers.index') }}"
                       class="ta-nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-users w-5 text-center"></i><span>Customers</span>
                    </a>
                </li>
            @endcan

            @can('view-products')
                <li x-data="{ open: {{ request()->routeIs('products.*') || request()->routeIs('categories.*') || request()->routeIs('colours.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="ta-nav-link w-full {{ request()->routeIs('products.*') || request()->routeIs('categories.*') || request()->routeIs('colours.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-chair w-5 text-center"></i>
                        <span class="flex-1 text-left">Products</span>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                    </button>
                    <ul x-show="open" x-collapse class="mt-1 flex flex-col gap-1 pl-9">
                        <li><a href="{{ route('products.index') }}" class="ta-nav-sub {{ request()->routeIs('products.*') ? 'active' : '' }}">Products</a></li>
                        <li><a href="{{ route('categories.index') }}" class="ta-nav-sub {{ request()->routeIs('categories.*') ? 'active' : '' }}">Categories</a></li>
                        <li><a href="{{ route('colours.index') }}" class="ta-nav-sub {{ request()->routeIs('colours.*') ? 'active' : '' }}">Colours</a></li>
                    </ul>
                </li>
            @endcan

            @can('manage-deliveries')
                <li x-data="{ open: {{ request()->routeIs('deliveries.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="ta-nav-link w-full {{ request()->routeIs('deliveries.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-truck w-5 text-center"></i>
                        <span class="flex-1 text-left">Deliveries</span>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                    </button>
                    <ul x-show="open" x-collapse class="mt-1 flex flex-col gap-1 pl-9">
                        <li><a href="{{ route('deliveries.index') }}" class="ta-nav-sub {{ request()->routeIs('deliveries.index') ? 'active' : '' }}">Today & Daily Board</a></li>
                        <li><a href="{{ route('deliveries.calendar') }}" class="ta-nav-sub {{ request()->routeIs('deliveries.calendar') ? 'active' : '' }}">Delivery Calendar</a></li>
                    </ul>
                </li>
            @endcan

            @can('view-reports')
                <li x-data="{ open: {{ request()->routeIs('reports.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="ta-nav-link w-full {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-chart-column w-5 text-center"></i>
                        <span class="flex-1 text-left">Reports</span>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                    </button>
                    <ul x-show="open" x-collapse class="mt-1 flex flex-col gap-1 pl-9">
                        <li><a href="{{ route('reports.sales') }}" class="ta-nav-sub {{ request()->routeIs('reports.sales') ? 'active' : '' }}">Sales Report</a></li>
                        <li><a href="{{ route('reports.products') }}" class="ta-nav-sub {{ request()->routeIs('reports.products') ? 'active' : '' }}">Product Report</a></li>
                        <li><a href="{{ route('reports.customers') }}" class="ta-nav-sub {{ request()->routeIs('reports.customers') ? 'active' : '' }}">Customer Report</a></li>
                        <li><a href="{{ route('reports.zip') }}" class="ta-nav-sub {{ request()->routeIs('reports.zip') ? 'active' : '' }}">ZIP Code Report</a></li>
                        <li><a href="{{ route('reports.sales-persons') }}" class="ta-nav-sub {{ request()->routeIs('reports.sales-persons') ? 'active' : '' }}">Sales Person Report</a></li>
                        <li><a href="{{ route('reports.deliveries') }}" class="ta-nav-sub {{ request()->routeIs('reports.deliveries') ? 'active' : '' }}">Delivery Report</a></li>
                    </ul>
                </li>
            @endcan
        </ul>

        @can('manage-users')
            <p class="mb-3 mt-6 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Admin</p>
            <ul class="flex flex-col gap-1">
                <li>
                    <a href="{{ route('users.index') }}"
                       class="ta-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-user-shield w-5 text-center"></i><span>Users & Sales Persons</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('audit.index') }}"
                       class="ta-nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-clipboard-list w-5 text-center"></i><span>Audit Logs</span>
                    </a>
                </li>
                <li x-data="{ open: {{ request()->routeIs('settings.*') || request()->routeIs('activity.*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="ta-nav-link w-full {{ request()->routeIs('settings.*') || request()->routeIs('activity.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-gear w-5 text-center"></i>
                        <span class="flex-1 text-left">Settings</span>
                        <i class="fa-solid fa-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
                    </button>
                    <ul x-show="open" x-collapse class="mt-1 flex flex-col gap-1 pl-9">
                        <li><a href="{{ route('settings.index') }}" class="ta-nav-sub {{ request()->routeIs('settings.index') ? 'active' : '' }}">General</a></li>
                        <li><a href="{{ route('settings.security') }}" class="ta-nav-sub {{ request()->routeIs('settings.security') ? 'active' : '' }}">Security</a></li>
                        <li><a href="{{ route('activity.logs') }}" class="ta-nav-sub {{ request()->routeIs('activity.logs') ? 'active' : '' }}">User Activity</a></li>
                        <li><a href="{{ route('activity.sessions') }}" class="ta-nav-sub {{ request()->routeIs('activity.sessions') ? 'active' : '' }}">Time Spent</a></li>
                    </ul>
                </li>
            </ul>
        @endcan

        <div class="mt-auto rounded-2xl bg-white/5 p-4 text-center">
            <p class="text-sm font-semibold text-white">{{ $brand }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ auth()->user()->role->label() }}</p>
        </div>
    </nav>
</aside>
