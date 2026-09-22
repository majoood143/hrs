<?php

namespace App\Filament\Resources\Api\Event\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes',
            'description' => 'sometimes',
            'date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'category_id' => 'sometimes',
            'link' => 'sometimes',
        ];
    }
}
