<?php

namespace App\Filament\Resources\Api\Region\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRegionRequest extends FormRequest
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
            'country_id' => 'sometimes',
        ];
    }
}
