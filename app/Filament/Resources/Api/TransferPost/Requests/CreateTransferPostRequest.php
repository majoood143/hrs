<?php

namespace App\Filament\Resources\Api\TransferPost\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes',
            'from_country_id' => 'sometimes',
            'from_region_id' => 'sometimes',
            'from_city_id' => 'sometimes',
            'to_country_id' => 'sometimes',
            'to_region_id' => 'sometimes',
            'to_city_id' => 'sometimes',
            'capacity' => 'sometimes',
            'transfer_date' => 'sometimes|date',
            'price' => 'sometimes|numeric',
            'cover_photo' => 'sometimes',
            'contact_number' => 'sometimes',
            'status' => 'sometimes',
        ];
    }
}
