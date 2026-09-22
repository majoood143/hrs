<?php

namespace App\Filament\Resources\Api\Service\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            'ar_name' => 'sometimes',
            'description' => 'sometimes|string',
            'ar_description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'is_active' => 'sometimes',
        ];
    }
}
