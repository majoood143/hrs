<?php

namespace App\Services\WhatsApp;

use App\Filament\Resources\StableResource;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\User;

/** The admin WhatsApp alert for a stable an owner just registered: who, where, and the admin link to approve it. */
final class StableRegistrationAlert
{
    public const TYPE = 'stable_registration';

    public static function message(Stable $stable, User $owner): string
    {
        $stable->loadMissing(['city', 'region']);
        $adminUrl = StableResource::getUrl('view', ['record' => $stable], panel: 'admin');

        return BilingualMessage::make(fn () => implode("\n", array_filter([
            __('whatsapp.new_stable.heading', ['site' => SiteSetting::siteName()]),
            '',
            __('whatsapp.fields.name', ['value' => $stable->name]),
            $stable->city ? __('whatsapp.new_stable.place', ['city' => $stable->city->name, 'region' => $stable->region?->name]) : null,
            __('whatsapp.new_stable.owner', ['name' => $owner->name, 'phone' => $owner->phone]),
            '',
            __('whatsapp.new_stable.admin', ['link' => $adminUrl]),
        ], fn ($line) => $line !== null)));
    }
}
