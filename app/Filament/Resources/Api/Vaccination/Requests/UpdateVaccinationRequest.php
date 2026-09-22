<?php

namespace App\Filament\Resources\Api\Vaccination\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vaccine_type' => 'sometimes',
            'date_administered' => 'sometimes|date',
            'horse_id' => 'sometimes',
            'manufacturer' => 'sometimes',
            'batch_number' => 'sometimes',
            'veterinarian_name' => 'sometimes',
            'veterinarian_license' => 'sometimes',
            'dosage' => 'sometimes|numeric',
            'administration_route' => 'sometimes',
            'adverse_reaction' => 'sometimes',
            'reaction_details' => 'sometimes|string',
            'notes' => 'sometimes|string',
        ];
    }
}
