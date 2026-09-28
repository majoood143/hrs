@php
    $user = auth()->user();
    $stable = \Filament\Facades\Filament::getTenant();
    $asAdmin = $user instanceof \App\Models\User && $stable instanceof \App\Models\Stable && ! $user->belongsToStable($stable->getKey());
@endphp
@if($asAdmin)
    <div role="status" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white">
        <x-filament::icon icon="heroicon-o-shield-exclamation" class="h-5 w-5 shrink-0" aria-hidden="true" />
        <span>{{ __('stable_panel.admin_mode.banner', ['stable' => $stable->name]) }}</span>
        <a href="{{ \App\Filament\Resources\StableResource::getUrl('view', ['record' => $stable], panel: 'admin') }}" class="inline-flex items-center gap-1 rounded-md bg-white/20 px-2 py-0.5 font-semibold hover:bg-white/30">
            <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />{{ __('stable_panel.admin_mode.back') }}
        </a>
    </div>
@endif
