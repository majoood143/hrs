@php
    $row = function (string $label, $value) {
        if (blank($value)) {
            $value = '—';
        }

        return '<div class="flex items-center justify-between gap-4 py-1.5">'
            . '<span class="text-xs font-medium text-gray-500 dark:text-gray-400">' . e($label) . '</span>'
            . '<span class="text-sm font-semibold text-gray-800 dark:text-gray-100 text-right">' . e($value) . '</span>'
            . '</div>';
    };
@endphp

<div class="space-y-4">

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700 px-4">
        {!! $row(__('admin_service_order.agent_details.ip_address'), $order->ip_address) !!}
    </div>

    @if (! $device['available'])
        <div class="text-center text-gray-500 py-8">
            {{ __('admin_service_order.agent_details.no_data') }}
        </div>
    @elseif ($device['is_bot'])
        <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 p-4">
            <div class="flex items-center gap-2 mb-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                <span class="font-semibold text-amber-700 dark:text-amber-400">{{ __('admin_service_order.agent_details.bot_detected') }}</span>
            </div>
            <div class="divide-y divide-amber-100 dark:divide-amber-800">
                {!! $row(__('admin_service_order.agent_details.bot_name'), $device['bot_name']) !!}
                {!! $row(__('admin_service_order.agent_details.bot_category'), $device['bot_category']) !!}
                {!! $row(__('admin_service_order.agent_details.bot_producer'), $device['bot_producer']) !!}
            </div>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-2">
                <x-heroicon-o-device-phone-mobile class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ __('admin_service_order.agent_details.device') }}</span>
                <span class="ms-auto text-xs px-2 py-0.5 rounded-full {{ $device['is_mobile'] ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    {{ $device['is_mobile'] ? __('admin_service_order.agent_details.mobile') : __('admin_service_order.agent_details.desktop') }}
                </span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                {!! $row(__('admin_service_order.agent_details.device_type'), $device['device_type']) !!}
                {!! $row(__('admin_service_order.agent_details.brand'), $device['brand']) !!}
                {!! $row(__('admin_service_order.agent_details.model'), $device['model']) !!}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-2">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ __('admin_service_order.agent_details.operating_system') }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                {!! $row(__('admin_service_order.agent_details.os_name'), $device['os_name']) !!}
                {!! $row(__('admin_service_order.agent_details.os_version'), $device['os_version']) !!}
                {!! $row(__('admin_service_order.agent_details.os_platform'), $device['os_platform']) !!}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-2">
                <x-heroicon-o-globe-alt class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                <span class="font-semibold text-gray-700 dark:text-gray-200">{{ __('admin_service_order.agent_details.browser') }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                {!! $row(__('admin_service_order.agent_details.client_type'), $device['client_type']) !!}
                {!! $row(__('admin_service_order.agent_details.client_name'), $device['client_name']) !!}
                {!! $row(__('admin_service_order.agent_details.client_version'), $device['client_version']) !!}
                {!! $row(__('admin_service_order.agent_details.client_engine'), $device['client_engine']) !!}
            </div>
        </div>
    @endif

    @if ($order->user_agent)
        <div>
            <div class="text-xs font-semibold text-gray-500 mb-1">{{ __('admin_service_order.agent_details.raw_user_agent') }}</div>
            <pre class="text-xs bg-gray-50 dark:bg-gray-800 rounded p-2 overflow-x-auto whitespace-pre-wrap">{{ $order->user_agent }}</pre>
        </div>
    @endif
</div>
