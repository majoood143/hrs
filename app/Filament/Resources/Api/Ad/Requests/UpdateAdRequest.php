<?php

namespace App\Filament\Resources\Api\Ad\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id' => 'sometimes',
            'customer_name' => 'sometimes',
            'media_type' => 'sometimes',
            'image_path' => 'sometimes',
            'video_path' => 'sometimes',
            'alt_text' => 'sometimes',
            'target_url' => 'sometimes',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date',
            'is_active' => 'sometimes',
            'order' => 'sometimes',
            'clicks' => 'sometimes',
        ];
    }
}
