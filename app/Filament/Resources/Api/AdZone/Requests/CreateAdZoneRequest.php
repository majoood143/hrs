<?php

namespace App\Filament\Resources\Api\AdZone\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            'slug' => 'sometimes',
            'order' => 'sometimes',
            'is_active' => 'sometimes',
        ];
    }
}
