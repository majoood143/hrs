<?php

namespace App\Enums;

/** The kinds of service a stable can put up for booking. Riding training for now; more later. */
enum StableOfferingType: string
{
    case RidingTraining = 'riding_training';

    public function label(): string
    {
        return __('stable_panel.offering_types.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::RidingTraining => 'heroicon-o-academic-cap',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
