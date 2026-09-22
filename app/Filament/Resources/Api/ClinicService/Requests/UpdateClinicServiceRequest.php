<?php

namespace App\Filament\Resources\Api\ClinicService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicServiceRequest extends FormRequest
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
