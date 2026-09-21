<?php

namespace App\Services\Racing;

use Carbon\Carbon;

/**
 * Parses the jqGrid JSON behind the Handicap Ratings page:
 * {"TOTAL":n,"RECORDS":n,"PAGE":1,"ROWS":[[horseId, name, "45 <sup class='ratingNeg'>-3</sup>", 45, " 06/01/17", " 05/01/17"], ...]}
 */
class RacingHandicapParser
{
    /**
     * @return list<array{id: int, name: string, rating: ?int, label: ?string, change: ?int, rating_date: ?string, last_ran: ?string}>
     *
     * @throws RacingUnavailableException when the source answered with something other than the grid JSON
     */
    public function parse(string $body): array
    {
        $data = json_decode(trim($body), true);

        if (! is_array($data) || ! array_key_exists('ROWS', $data)) {
            throw new RacingUnavailableException('Racing source returned an unexpected handicap response.');
        }

        // the source sends "" (not []) when nothing matches
        $rows = is_array($data['ROWS']) ? $data['ROWS'] : [];
        $parsed = [];

        foreach ($rows as $row) {
            $id = (int) ($row[0] ?? 0);
            $name = trim((string) ($row[1] ?? ''));

            if ($id < 1 || $name === '') {
                continue;
            }

            $rating = $row[3] ?? '';

            $parsed[] = [
                'id' => $id,
                'name' => $name,
                'rating' => is_numeric($rating) ? (int) $rating : null,
                // "NOR" = not officially rated; blank = never rated
                'label' => is_string($rating) && $rating !== '' && ! is_numeric($rating) ? $rating : null,
                'change' => $this->change((string) ($row[2] ?? '')),
                'rating_date' => $this->date((string) ($row[4] ?? '')),
                'last_ran' => $this->date((string) ($row[5] ?? '')),
            ];
        }

        return $parsed;
    }

    private function change(string $html): ?int
    {
        return preg_match("/rating(?:Pos|Neg)'?\"?>\s*([+-]?\d+)\s*</", $html, $m) ? (int) $m[1] : null;
    }

    /** dd/mm/yy => Y-m-d (sortable), or null. */
    private function date(string $value): ?string
    {
        $value = trim($value);

        if (! preg_match('#^\d{2}/\d{2}/\d{2}$#', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('d/m/y', $value);

        return $date ? $date->format('Y-m-d') : null;
    }
}
