<?php

namespace App\Filament\Resources\Api\CmsCategory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCmsCategoryRequest extends FormRequest
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
            'description' => 'sometimes',
            'parent_id' => 'sometimes',
            'order' => 'sometimes',
        ];
    }
}
