@php
    $currentLocale = app()->getLocale();
    $currentUrl = url()->current();
    $query = collect(request()->query())->except('lang');
@endphp

<div class="flex items-center gap-x-2 px-3">
    <a
        href="{{ $currentUrl }}?{{ http_build_query($query->merge(['lang' => 'en'])->toArray()) }}"
        @class([
            'text-sm font-medium',
            'text-primary-600 underline' => $currentLocale === 'en',
            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $currentLocale !== 'en',
        ])
    >EN</a>
    <span class="text-gray-300 dark:text-gray-600">/</span>
    <a
        href="{{ $currentUrl }}?{{ http_build_query($query->merge(['lang' => 'ar'])->toArray()) }}"
        @class([
            'text-sm font-medium',
            'text-primary-600 underline' => $currentLocale === 'ar',
            'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $currentLocale !== 'ar',
        ])
    >AR</a>
</div>
