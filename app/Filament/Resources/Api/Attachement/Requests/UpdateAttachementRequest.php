<?php

namespace App\Filament\Resources\Api\Attachement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttachementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            'description' => 'sometimes',
            'file_path' => 'sometimes',
            'type' => 'sometimes',
            'horse_id' => 'sometimes',
            'user_id' => 'sometimes',
            'is_visible_to_client' => 'sometimes',
            'is_active' => 'sometimes',
            'is_approved' => 'sometimes',
            'transaction_id' => 'sometimes',
        ];
    }
}
