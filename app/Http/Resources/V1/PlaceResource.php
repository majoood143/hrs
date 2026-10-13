<?php

namespace App\Http\Resources\V1;

use App\Models\Shop;
use App\Models\Stable;
use App\Models\StableReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A directory place in full (stable, clinic, center or shop): what the website's page shows.
 *
 * @property-read Model $resource
 */
class PlaceResource extends JsonResource
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function toArray(Request $request): array
    {
        $place = $this->resource;
        $type = $place->getAttribute('type');
        // stables / clinics / centers / shops: also the name of the place's translation file.
        $section = $place->getTable();

        return [
            'id' => (int) $place->getKey(),
            'slug' => (string) $place->getAttribute('slug'),
            'name' => (string) $place->getAttribute('name'),
            /**
             * Plain text, paragraphs separated by line breaks (rich text arrives without markup).
             *
             * @var string|null
             */
            'description' => method_exists($place, 'descriptionText') ? $place->descriptionText() : $place->getAttribute('description'),
            'type' => $type ? OptionResource::of((string) $type, (string) $place->getAttribute('type_label')) : null,
            'country' => LookupResource::make($this->whenLoaded('country')),
            'region' => LookupResource::make($this->whenLoaded('region')),
            'city' => LookupResource::make($this->whenLoaded('city')),
            /** @var string|null */
            'address' => $place->getAttribute('address'),
            /**
             * A maps link (Google Maps…) as the owner entered it.
             *
             * @var string|null
             */
            'map_link' => $place->getAttribute('map_link'),
            /** @var string|null */
            'phone' => $place->getAttribute('phone'),
            /**
             * Stables only.
             *
             * @var string|null
             */
            'email' => $place->getAttribute('email'),
            /** @var string|null */
            'website_url' => $place->getAttribute('website_url'),
            /** @var string|null */
            'instagram_url' => $place->getAttribute('instagram_url'),
            'services' => LookupResource::collection($this->whenLoaded('services')),
            /** @var string|null */
            'cover_photo_url' => $place->getAttribute('cover_photo_url'),
            /** @var list<string> */
            'gallery_urls' => array_values((array) $place->getAttribute('gallery_urls')),
            /** Monday first, every day present. */
            'opening_hours' => collect(self::DAYS)->map(function (string $day) use ($place, $section) {
                $hours = (array) $place->getAttribute('opening_hours');
                $opensAt = data_get($hours, "{$day}.opens_at");
                $closesAt = data_get($hours, "{$day}.closes_at");
                $closed = (bool) data_get($hours, "{$day}.closed", true) || ! $opensAt || ! $closesAt;

                return [
                    /** @example mon */
                    'day' => $day,
                    'label' => (string) __("{$section}.days.{$day}"),
                    'closed' => $closed,
                    /** `H:i`, null when closed. */
                    'opens_at' => $closed ? null : (string) $opensAt,
                    'closes_at' => $closed ? null : (string) $closesAt,
                ];
            })->all(),
            /** Shops only. */
            'is_online' => (bool) $place->getAttribute('is_online'),
            /** Shops only. */
            'delivery_scope' => $place instanceof Shop && $place->delivery_scope
                ? OptionResource::of($place->delivery_scope, (string) $place->delivery_scope_label)
                : null,
            /** Shops only. */
            'payment_options' => OptionResource::collection($place instanceof Shop
                ? collect($place->payment_options ?? [])->map(fn (string $option) => ['value' => $option, 'label' => __('shops.payment_options_list.'.$option)])
                : collect()),
            /**
             * Stables only: the average of visible reviews.
             *
             * @var array{average: float|null, count: int}|null
             */
            'rating' => $place instanceof Stable ? $place->rating() : null,
            /** Stables only: the latest 10 visible reviews. */
            'reviews' => $place instanceof Stable
                ? $place->reviews()->visible()->latest('id')->take(10)->get()->map(fn (StableReview $review) => [
                    'id' => (int) $review->id,
                    /** "First L.", never the full name. */
                    'author' => $review->displayName(),
                    /** 1–5. */
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    /** The stable's answer. */
                    'reply' => $review->reply,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->all()
                : [],
            /** @var int|null */
            'views_count' => $place->getAttribute('views_count'),
            'created_at' => $place->getAttribute('created_at')?->toIso8601String(),
            /** The place's page on the website, for sharing. */
            'share_url' => route($section.'.show', $place->getAttribute('slug')),
        ];
    }
}
