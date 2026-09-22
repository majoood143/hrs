<?php

namespace App\Filament\Resources\Api\Clinic\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicRequest extends FormRequest
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
            'type' => 'sometimes',
            'slug' => 'sometimes',
            'country_id' => 'sometimes',
            'region_id' => 'sometimes',
            'city_id' => 'sometimes',
            'address' => 'sometimes',
            'map_link' => 'sometimes',
            'phone' => 'sometimes',
            'website_url' => 'sometimes',
            'instagram_url' => 'sometimes',
            'en_description' => 'sometimes|string',
            'ar_description' => 'sometimes|string',
            'cover_photo' => 'sometimes',
            'gallery' => 'sometimes',
            'opening_hours' => 'sometimes',
            'is_active' => 'sometimes',
        ];
    }
}
