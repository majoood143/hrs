<?php

namespace App\Services\Racing;

/**
 * Parses the jqGrid JSON behind the Statistics page (jockeys, owners, trainers):
 * {"TOTAL":n,"RECORDS":n,"PAGE":1,"ROWS":[[id, rank, name, 1st, 2nd, 3rd, 4th, 5th, runs, prize money], ...]}
 */
class RacingStatsParser
{
    /**
     * @return list<array{id: int, name: string, rank: int, wins: int, runs: int}>
     *
     * @throws RacingUnavailableException when the source answered with something other than the grid JSON
     */
    public function parse(string $body): array
    {
        $data = json_decode(trim($body), true);

        if (! is_array($data) || ! array_key_exists('ROWS', $data)) {
            throw new RacingUnavailableException('Racing source returned an unexpected statistics response.');
        }

        // the source sends "" (not []) when nothing matches
        $rows = is_array($data['ROWS']) ? $data['ROWS'] : [];
        $parsed = [];

        foreach ($rows as $row) {
            $id = (int) ($row[0] ?? 0);
            $name = trim((string) ($row[2] ?? ''));

            if ($id < 1 || $name === '') {
                continue;
            }

            $parsed[] = [
                'id' => $id,
                'name' => $name,
                'rank' => (int) ($row[1] ?? 0),
                'wins' => (int) ($row[3] ?? 0),
                'runs' => (int) ($row[8] ?? 0),
            ];
        }

        return $parsed;
    }
}
