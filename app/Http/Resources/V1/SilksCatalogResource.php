<?php

namespace App\Http\Resources\V1;

use App\Support\Silks\SilksCatalog;
use App\Support\Silks\SilksDesign;
use App\Support\Silks\SilksLink;
use App\Support\Silks\SilksTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the silks designer offers right now, in the request's language (SilksCatalog). Pattern SVGs
 * are sanitized shapes in their area's local box (SilksTemplate::BOXES), painted with
 * `currentColor` = the pattern colour. "plain" is never in the lists: every area can always be plain.
 *
 * @property SilksCatalog $resource
 */
class SilksCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $catalog = $this->resource;
        $default = SilksDesign::fromQuery([], $catalog);
        $pattern = fn (array $pattern) => ['key' => $pattern['key'], 'name' => $pattern['name'], 'svg' => $pattern['svg']];

        return [
            /** @var list<array{key: string, name: string, hex: string}> */
            'colors' => array_values(array_map(fn (array $color) => ['key' => $color['key'], 'name' => $color['name'], 'hex' => $color['hex']], $catalog->colors)),
            /** @var array{body: list<array{key: string, name: string, svg: string}>, sleeves: list<array{key: string, name: string, svg: string}>, cap: list<array{key: string, name: string, svg: string}>} */
            'patterns' => collect(SilksTemplate::AREAS)->mapWithKeys(fn (string $area) => [$area => array_values(array_map($pattern, $catalog->patterns[$area]))])->all(),
            /** The name of the built-in "plain" pattern. */
            'plain' => $catalog->plainName(),
            /**
             * The design a new visitor starts with (and "Start again").
             *
             * @var array{body: array{pattern: string, base: string, accent: string}, sleeves: array{pattern: string, base: string, accent: string}, cap: array{pattern: string, base: string, accent: string}}
             */
            'default' => $default->choice,
            /**
             * Description templates (`:base`, `:colour`, `:pattern`), joined with `separator`, like the website.
             *
             * @var array{body: string, body_pattern: string, sleeves: string, sleeves_pattern: string, cap: string, cap_pattern: string, separator: string}
             */
            'describe' => trans('silks.describe'),
            /**
             * The website page with the designer; a design opens there as `?silks=<compact>&lang=<en|ar>`
             * (`pattern.main.accent` per area, joined with `~`). Null when no page has the designer.
             *
             * @var string|null
             */
            'page_url' => SilksLink::designerPage(),
        ];
    }
}
