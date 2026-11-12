<?php

namespace App\Filament\Support;

use App\Support\SecretSetting;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * A credential field on a settings page (gateway keys, SMS and SMTP passwords). It opens empty:
 * the stored value never goes to the browser (Livewire keeps form state in the page). Blank on
 * save keeps the stored one (`SecretSetting::setUnlessBlank`), so it is only required while none is stored.
 */
final class SecretInput
{
    /** @param  (Closure(Get): bool)|null  $requiredWhen  e.g. "this gateway is enabled" */
    public static function make(string $name, string $settingKey, ?Closure $requiredWhen = null): TextInput
    {
        $stored = SecretSetting::get($settingKey) !== '';

        return TextInput::make($name)
            ->password()
            ->revealable()
            ->placeholder($stored ? __('admin_secrets.saved') : null)
            ->required(fn (Get $get): bool => ! $stored && ($requiredWhen === null || $requiredWhen($get)))
            ->maxLength(255);
    }
}
