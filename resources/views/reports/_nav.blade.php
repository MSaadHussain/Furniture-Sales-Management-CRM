{{-- Tab strip shared by every report, keeping the active date range in the link. --}}
@php
    $carry = request()->only(['range', 'from', 'to']);

    $tabs = [
        ['route' => 'reports.sales',         'label' => 'Sales',         'icon' => 'fa-receipt'],
        ['route' => 'reports.products',      'label' => 'Products',      'icon' => 'fa-chair'],
        ['route' => 'reports.customers',     'label' => 'Customers',     'icon' => 'fa-users'],
        ['route' => 'reports.zip',           'label' => 'ZIP Codes',     'icon' => 'fa-map-location-dot'],
        ['route' => 'reports.sales-persons', 'label' => 'Sales People',  'icon' => 'fa-user-tie'],
        ['route' => 'reports.deliveries',    'label' => 'Deliveries',    'icon' => 'fa-truck'],
    ];
@endphp

<div class="flex flex-wrap gap-2">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route'], $carry) }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                  {{ request()->routeIs($tab['route'])
                      ? 'bg-brand text-white'
                      : 'border border-line text-ink hover:bg-surface dark:border-strokedark dark:text-gray-300 dark:hover:bg-boxdark' }}">
            <i class="fa-solid {{ $tab['icon'] }} text-xs"></i>{{ $tab['label'] }}
        </a>
    @endforeach
</div>
