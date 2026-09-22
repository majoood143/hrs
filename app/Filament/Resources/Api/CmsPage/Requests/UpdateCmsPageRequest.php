<?php

namespace App\Filament\Resources\Api\CmsPage\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes',
            'slug' => 'sometimes',
            'excerpt' => 'sometimes',
            'content' => 'sometimes',
            'status' => 'sometimes',
            'published_at' => 'sometimes',
            'template' => 'sometimes',
            'layout' => 'sometimes',
            'is_homepage' => 'sometimes',
            'is_system' => 'sometimes',
            'container_width' => 'sometimes',
            'show_title' => 'sometimes',
            'custom_css' => 'sometimes|string',
            'custom_head_scripts' => 'sometimes|string',
            'custom_body_scripts' => 'sometimes|string',
            'meta_title' => 'sometimes',
            'meta_description' => 'sometimes',
            'canonical_url' => 'sometimes',
            'noindex' => 'sometimes',
            'nofollow' => 'sometimes',
            'og_type' => 'sometimes',
        ];
    }
}
