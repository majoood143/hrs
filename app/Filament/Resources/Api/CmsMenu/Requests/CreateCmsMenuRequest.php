<?php

namespace App\Filament\Resources\Api\CmsMenu\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCmsMenuRequest extends FormRequest
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
            'location' => 'sometimes',
        ];
    }
}
