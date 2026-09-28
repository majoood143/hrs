<?php

namespace App\Filament\Stable\Pages\Tenancy;

use App\Filament\Stable\Schemas\StableProfileForm;
use App\Support\PhoneNumber;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** The stable's public profile, as its owner keeps it: what the stable page on the site shows. */
class EditStableProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('stable_panel.profile.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('profile')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('stable_panel.profile.tabs.info'))
                            ->icon('heroicon-o-information-circle')
                            ->columns(2)
                            ->schema([...StableProfileForm::names(), ...StableProfileForm::contact()]),
                        Tab::make(__('stable_panel.profile.tabs.location'))
                            ->icon('heroicon-o-map-pin')
                            ->columns(3)
                            ->schema(StableProfileForm::location()),
                        Tab::make(__('stable_panel.profile.tabs.description'))
                            ->icon('heroicon-o-document-text')
                            ->columns(2)
                            ->schema(StableProfileForm::description()),
                        Tab::make(__('stable_panel.profile.tabs.amenities'))
                            ->icon('heroicon-o-sparkles')
                            ->schema(StableProfileForm::amenities()),
                        Tab::make(__('stable_panel.profile.tabs.hours'))
                            ->icon('heroicon-o-clock')
                            ->schema(StableProfileForm::openingHours()),
                        Tab::make(__('stable_panel.profile.tabs.photos'))
                            ->icon('heroicon-o-photo')
                            ->schema(StableProfileForm::photos()),
                    ]),
            ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('phone', $data)) {
            $data['phone'] = PhoneNumber::normalize($data['phone']);
        }

        $record->update($data);

        return $record;
    }
}
