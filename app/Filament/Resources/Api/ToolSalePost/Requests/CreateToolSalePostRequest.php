<?php

namespace App\Filament\Resources\Api\ToolSalePost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateToolSalePostRequest extends FormRequest
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
            'category' => 'sometimes',
            'condition' => 'sometimes',
            'brand' => 'sometimes',
            'country_id' => 'sometimes',
            'region_id' => 'sometimes',
            'city_id' => 'sometimes',
            'price' => 'sometimes|numeric',
            'price_negotiable' => 'sometimes|boolean',
            'cover_photo' => 'sometimes',
            'description_en' => 'sometimes|string',
            'description_ar' => 'sometimes|string',
            'contact_number' => 'sometimes',
            'status' => 'sometimes',
        ];
    }
}
