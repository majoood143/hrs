<?php

namespace App\Filament\Resources\Api\CenterService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCenterServiceRequest extends FormRequest
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
            'applies_to' => 'sometimes',
        ];
    }
}
