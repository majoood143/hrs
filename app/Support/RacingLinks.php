<?php

namespace App\Support;

class RacingLinks
{
    /**
     * Where a parsed table cell / entity link points on this site (a race or a horse / owner / jockey / trainer).
     *
     * @param  ?array{type: string, id: int}  $link
     */
    public static function href(?array $link): ?string
    {
        return match (true) {
            ! $link => null,
            $link['type'] === 'race' => route('racing.race', $link['id']),
            in_array($link['type'], ['horse', 'owner', 'jockey', 'trainer'], true) => route('racing.profile', [$link['type'], $link['id']]),
            default => null,
        };
    }
}
