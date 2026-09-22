<?php

namespace App\Filament\Resources\Api\Country\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCountryRequest extends FormRequest
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
            'country_code' => 'sometimes',
            'phone_code' => 'sometimes',
            'currency_code' => 'sometimes',
            'nationality_en' => 'sometimes',
            'nationality_ar' => 'sometimes',
            'order' => 'sometimes',
            'is_public' => 'sometimes',
        ];
    }
}
