<?php

namespace App\Http\Resources\V1;

use App\Models\Jockey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A jockey (list card and detail: the same fields, the detail adds the region and description).
 *
 * @mixin Jockey
 */
class JockeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = Jockey::typeOptions();

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            /** @var array<int, array{value: string, label: string}> */
            'types' => collect($this->types ?? [])
                ->filter(fn ($type) => isset($options[$type]))
                ->map(fn (string $type) => ['value' => $type, 'label' => $options[$type]])
                ->values()
                ->all(),
            'years_experience' => $this->years_experience,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'region' => LookupResource::make($this->whenLoaded('region')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            'description' => $this->description,
            'cover_photo_url' => $this->cover_photo_url,
            'contact_number' => (string) $this->contact_number,
            /** Their racing club profile (`racing/jockey/{id}`), once an admin checked it; else null. */
            'racing_jockey_id' => $this->verified_racing_id,
            'views_count' => (int) $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
            /** The jockey's page on the website, for sharing. */
            'share_url' => route('jockeys.show', $this->resource),
        ];
    }
}
