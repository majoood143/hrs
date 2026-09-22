<?php

namespace App\Filament\Resources\Api\Authority\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAuthorityRequest extends FormRequest
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
        ];
    }
}
