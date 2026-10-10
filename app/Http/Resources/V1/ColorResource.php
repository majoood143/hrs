<?php

namespace App\Http\Resources\V1;

use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Color
 */
class ColorResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, hex: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'hex' => $this->hex_code ?: null,
        ];
    }
}
