<?php

namespace App\Services\Racing;

/**
 * Values mirror the `type` parameter of the racing source's search page.
 */
enum RacingSearchType: int
{
    case All = 0;
    case Horse = 1;
    case Owner = 2;
    case Jockey = 3;
    case Trainer = 4;

    /** Result-group key used by RacingParser::parseSearch() and the routes. */
    public function group(): ?string
    {
        return $this === self::All ? null : strtolower($this->name);
    }

    public function label(): string
    {
        return __('racing.types.' . strtolower($this->name));
    }

    public static function fromGroup(string $group): self
    {
        foreach (self::cases() as $case) {
            if ($case->group() === $group) {
                return $case;
            }
        }

        return self::All;
    }

    public static function fromInput(mixed $value, self $default = self::Horse): self
    {
        return is_numeric($value) ? (self::tryFrom((int) $value) ?? $default) : $default;
    }
}
