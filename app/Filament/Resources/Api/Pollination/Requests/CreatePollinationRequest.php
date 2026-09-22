<?php

namespace App\Filament\Resources\Api\Pollination\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePollinationRequest extends FormRequest
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
