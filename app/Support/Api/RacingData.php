<?php

namespace App\Support\Api;

use App\Support\RacingLinks;

/**
 * The racing parsers' arrays (Services\Racing) as the mobile API sends them: images through our
 * own proxy (the app never talks to the source), links as `{type, id}` the app opens itself, and
 * `web_url`s for sharing. Text stays exactly as the source wrote it, in the request's language.
 */
final class RacingData
{
    /** An image path on the source (/img/...) as an absolute URL of our proxy. */
    public static function image(?string $path): ?string
    {
        return $path ? route('racing.image', ['p' => $path]) : null;
    }

    /**
     * @param  ?array{type: string, id: int}  $link
     * @return ?array{type: string, id: int}
     */
    public static function link(?array $link): ?array
    {
        return $link && RacingLinks::href($link) !== null ? ['type' => $link['type'], 'id' => (int) $link['id']] : null;
    }

    /**
     * @param  array{headers: list<string>, rows: list<list<array{text: string, link: ?array, title?: ?string}>>}  $table
     * @return array{headers: list<string>, rows: list<list<array{text: string, link: ?array{type: string, id: int}, title: ?string}>>}
     */
    public static function table(array $table): array
    {
        return [
            'headers' => array_values($table['headers'] ?? []),
            'rows' => array_map(fn (array $row) => array_map(fn (array $cell) => [
                'text' => (string) $cell['text'],
                'link' => self::link($cell['link'] ?? null),
                'title' => $cell['title'] ?? null,
            ], $row), $table['rows'] ?? []),
        ];
    }

    /**
     * @param  ?array{id: int, name: string}  $entity
     * @return ?array{id: int, name: string}
     */
    public static function entity(?array $entity): ?array
    {
        return $entity ? ['id' => (int) $entity['id'], 'name' => (string) $entity['name']] : null;
    }

    /**
     * A race-card runner (with its weight) or a form-guide runner (with its last runs): the horse,
     * its people and its picture.
     *
     * @param  'card'|'form-guide'  $page
     */
    public static function runner(array $runner, string $page): array
    {
        $extra = $page === 'card'
            ? ['weight' => $runner['weight'] ?? null, 'weight_note' => $runner['weight_note'] ?? null]
            : ['runs' => self::table($runner['runs'] ?? ['headers' => [], 'rows' => []])];

        return [
            'no' => $runner['no'] ?? null,
            'gate' => $runner['gate'] ?? null,
            'horse' => self::entity($runner['horse'] ?? null),
            'meta' => array_values($runner['meta'] ?? []),
            'record' => $runner['record'] ?? null,
            'owner' => self::entity($runner['owner'] ?? null),
            'trainer' => self::entity($runner['trainer'] ?? null),
            'jockey' => self::entity($runner['jockey'] ?? null),
            'image_url' => self::image($runner['image'] ?? null),
        ] + $extra;
    }
}
