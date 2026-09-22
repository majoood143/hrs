<?php

namespace App\Filament\Resources\Api\Partner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerRequest extends FormRequest
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
            'logo' => 'sometimes',
            'website_url' => 'sometimes',
            'en_description' => 'sometimes|string',
            'ar_description' => 'sometimes|string',
            'type' => 'sometimes',
            'is_active' => 'sometimes',
            'order' => 'sometimes',
        ];
    }
}
