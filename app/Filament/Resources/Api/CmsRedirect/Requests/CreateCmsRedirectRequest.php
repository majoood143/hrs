<?php

namespace App\Filament\Resources\Api\CmsRedirect\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCmsRedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_path' => 'sometimes',
            'to_path' => 'sometimes',
            'status_code' => 'sometimes',
            'hits' => 'sometimes',
        ];
    }
}
