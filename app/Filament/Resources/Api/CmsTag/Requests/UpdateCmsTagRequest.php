<?php

namespace App\Filament\Resources\Api\CmsTag\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsTagRequest extends FormRequest
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
            'type' => 'sometimes',
            'order_column' => 'sometimes',
        ];
    }
}
