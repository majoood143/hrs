<?php

namespace App\Http\Resources\V1;

use App\Models\Stallion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A breeding stallion (list card and detail: the same fields).
 *
 * @mixin Stallion
 */
class StallionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            /** Country of birth as the catalogue writes it ("FR", "OM"…). */
            'origin' => $this->origin,
            /** @var array{value: string, label: string}|null */
            'colour' => $this->colour ? ['value' => $this->colour, 'label' => (string) $this->colour_label] : null,
            'foaled_year' => $this->foaled_year,
            'sire' => $this->sire,
            'dam' => $this->dam,
            /**
             * sire, dam (in the request's language), then sire_sire … dam_dam_dam ("Name (FR)", Latin); null where unknown.
             *
             * @var array<string, string|null>
             */
            'pedigree' => $this->pedigreeTree(),
            'pedigree_url' => $this->pedigree_url,
            /** The catalogue's race record; null where it has none. */
            'record' => [
                'runs' => $this->runs,
                'wins' => $this->wins,
                'places' => $this->places,
                'rating' => $this->rating,
            ],
            /** The racing club's horse profile (`racing/horse/{id}`), else null. */
            'racing_horse_id' => $this->racing_horse_id,
            'highlights' => $this->highlights,
            /** The stud standing him, when known. */
            'stud' => $this->stud,
            'city' => LookupResource::make($this->whenLoaded('city')),
            'contact_number' => $this->contact_number,
            'photo_url' => $this->photo_url,
            'views_count' => (int) $this->views_count,
            /** The stallion's page on the website, for sharing. */
            'share_url' => route('stallions.show', $this->resource),
        ];
    }
}
