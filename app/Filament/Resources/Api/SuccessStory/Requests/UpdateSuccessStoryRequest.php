<?php

namespace App\Filament\Resources\Api\SuccessStory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSuccessStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'horse_id' => 'sometimes',
            'owner_name' => 'sometimes',
            'en_route' => 'sometimes',
            'ar_route' => 'sometimes',
            'en_quote' => 'sometimes|string',
            'ar_quote' => 'sometimes|string',
            'photo' => 'sometimes',
            'is_published' => 'sometimes',
            'order' => 'sometimes',
        ];
    }
}
