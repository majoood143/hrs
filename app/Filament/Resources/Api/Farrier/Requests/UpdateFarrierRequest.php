<?php

namespace App\Filament\Resources\Api\Farrier\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFarrierRequest extends FormRequest
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
            'specialty' => 'sometimes',
            'years_experience' => 'sometimes',
            'country_id' => 'sometimes',
            'region_id' => 'sometimes',
            'city_id' => 'sometimes',
            'price' => 'sometimes|numeric',
            'cover_photo' => 'sometimes',
            'description_en' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'contact_number' => 'sometimes',
            'status' => 'sometimes',
        ];
    }
}
