<?php

namespace App\Http\Resources\V1;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A promo. Send taps to `click_url` (the site's /promo/{ad}/go), never straight to the target, so clicks are counted.
 *
 * @mixin Ad
 */
class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** `image` or `video`. */
            'media_type' => (string) ($this->media_type ?: 'image'),
            'media_url' => $this->media_path ? Storage::disk('public')->url($this->media_path) : null,
            'alt' => ($this->getTranslation('alt_text', app()->getLocale()) ?: $this->customer_name) ?: null,
            'click_url' => $this->target_url ? route('promo.click', $this->resource) : null,
        ];
    }
}
