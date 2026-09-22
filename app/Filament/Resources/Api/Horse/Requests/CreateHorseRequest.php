<?php

namespace App\Filament\Resources\Api\Horse\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHorseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'en_name' => 'sometimes',
            'is_featured' => 'sometimes',
            'cover_photo' => 'sometimes',
            'public_story_en' => 'sometimes|string',
            'public_story_ar' => 'sometimes|string',
            'ar_name' => 'sometimes',
            'city_id' => 'sometimes',
            'region_id' => 'sometimes',
            'country_id' => 'sometimes',
            'type_id' => 'sometimes',
            'gender_id' => 'sometimes',
            'user_id' => 'sometimes',
            'attachment' => 'sometimes',
            'dob' => 'sometimes|date',
            'color_id' => 'sometimes',
            'breed' => 'sometimes',
            'microship' => 'sometimes',
            'registration_number' => 'sometimes',
            'dam_id' => 'sometimes',
            'sire_id' => 'sometimes',
        ];
    }
}
