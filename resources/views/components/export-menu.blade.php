@props(['route', 'params' => []])

{{-- Export dropdown: CSV + Excel, preserving the current page filters. --}}
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" @click.outside="open = false" class="btn btn-light">
        <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-xs"></i>
    </button>
    <div x-show="open" x-cloak
         class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-lg dark:border-strokedark dark:bg-boxdark">
        <a href="{{ route($route, array_merge(request()->query(), $params, ['format' => 'csv'])) }}"
           class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-surface dark:text-gray-200 dark:hover:bg-boxdark2">
            <i class="fa-solid fa-file-csv text-green-600"></i> CSV (.csv)
        </a>
        <a href="{{ route($route, array_merge(request()->query(), $params, ['format' => 'xlsx'])) }}"
           class="flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-surface dark:text-gray-200 dark:hover:bg-boxdark2">
            <i class="fa-solid fa-file-excel text-emerald-700"></i> Excel (.xlsx)
        </a>
    </div>
</div>
