<?php

namespace App\Filament\Resources\Api\VideoFolder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVideoFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'sometimes',
            'name' => 'sometimes',
            'slug' => 'sometimes',
            'order' => 'sometimes',
            'is_active' => 'sometimes',
        ];
    }
}
