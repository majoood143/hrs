<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fixed choice: `value` is what filters send back, `label` is in the request's language.
 */
class OptionResource extends JsonResource
{
    public static function of(string $value, string $label): self
    {
        return new self(['value' => $value, 'label' => $label]);
    }

    /**
     * @return array{value: string, label: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => (string) $this->resource['value'],
            'label' => (string) $this->resource['label'],
        ];
    }
}
