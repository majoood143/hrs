<?php

namespace App\Filament\Resources\Api\Color\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateColorRequest extends FormRequest
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
            'hex_code' => 'sometimes',
        ];
    }
}
