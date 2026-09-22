<?php

namespace App\Filament\Resources\Api\StableService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStableServiceRequest extends FormRequest
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
