<?php

namespace App\Filament\Resources\Api\Stable\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'en_name' => 'sometimes',
            'ar_name' => 'sometimes',
            'slug' => 'sometimes',
            'country_id' => 'sometimes',
            'region_id' => 'sometimes',
            'city_id' => 'sometimes',
            'address' => 'sometimes',
            'map_link' => 'sometimes',
            'en_description' => 'sometimes|string',
            'ar_description' => 'sometimes|string',
            'cover_photo' => 'sometimes',
            'gallery' => 'sometimes',
            'opening_hours' => 'sometimes',
            'is_active' => 'sometimes',
        ];
    }
}
