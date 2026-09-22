<?php

namespace App\Filament\Resources\Api\EventCategory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventCategoryRequest extends FormRequest
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
            'color' => 'sometimes',
            'order' => 'sometimes',
        ];
    }
}
