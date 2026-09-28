<?php

namespace App\Filament\Stable\Pages\Tenancy;

use App\Filament\Stable\Schemas\StableProfileForm;
use App\Models\User;
use App\Services\Stables\StableRegistration;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** An owner adds another stable. Like the first one, it waits for the admins. */
class RegisterStable extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('stable_panel.register_stable.title');
    }

    /** Owners add stables; an admin working in the panel does that from the admin instead. */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStableOwner();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ...StableProfileForm::names(),
                ...StableProfileForm::location(),
                ...StableProfileForm::contact(),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        /** @var User $owner */
        $owner = auth()->user();

        return app(StableRegistration::class)->createStable($owner, $data);
    }
}
