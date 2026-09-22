<?php

namespace App\Filament\Resources\Api\HorseSalePost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHorseSalePostRequest extends FormRequest
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
            'type_id' => 'sometimes',
            'gender_id' => 'sometimes',
            'color_id' => 'sometimes',
            'breed' => 'sometimes',
            'dam' => 'sometimes',
            'sire' => 'sometimes',
            'birth_country_id' => 'sometimes',
            'passport_number' => 'sometimes',
            'passport_document' => 'sometimes',
            'dob' => 'sometimes|date',
            'country_id' => 'sometimes',
            'region_id' => 'sometimes',
            'city_id' => 'sometimes',
            'price' => 'sometimes|numeric',
            'cover_photo' => 'sometimes',
            'images' => 'sometimes',
            'description_en' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'contact_number' => 'sometimes',
            'status' => 'sometimes',
        ];
    }
}
