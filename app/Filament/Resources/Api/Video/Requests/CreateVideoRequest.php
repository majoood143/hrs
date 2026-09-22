<?php

namespace App\Filament\Resources\Api\Video\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => 'sometimes',
            'title' => 'sometimes',
            'description' => 'sometimes',
            'slug' => 'sometimes',
            'youtube_url' => 'sometimes',
            'order' => 'sometimes',
            'is_active' => 'sometimes',
        ];
    }
}
