<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Models\City;
use App\Models\ToolSalePost;
use Illuminate\Database\Eloquent\Model;

class ToolSalePostCreator extends PublicPostCreator
{
    public function rules(): array
    {
        return [
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', ToolSalePost::CATEGORIES)],
            'condition' => ['required', 'in:'.implode(',', ToolSalePost::CONDITIONS)],
            'brand' => ['nullable', 'string', 'max:255'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_negotiable' => ['nullable', 'boolean'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'en_name' => __('tools-for-sale.name_en'),
            'ar_name' => __('tools-for-sale.name_ar'),
            'category' => __('tools-for-sale.category'),
            'condition' => __('tools-for-sale.condition'),
            'brand' => __('tools-for-sale.brand'),
            'city_id' => __('tools-for-sale.city'),
            'price' => __('tools-for-sale.price'),
            'price_negotiable' => __('tools-for-sale.price_negotiable'),
            'cover_photo' => __('tools-for-sale.cover_photo'),
        ];
    }

    public function create(array $data, array $files, string $contactNumber): Model
    {
        $city = City::with('region')->findOrFail($data['city_id']);

        $post = ToolSalePost::create([
            'en_name' => $data['en_name'],
            'ar_name' => $data['ar_name'],
            'category' => $data['category'],
            'condition' => $data['condition'],
            'brand' => $data['brand'] ?? null,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'price' => $data['price'],
            'price_negotiable' => filter_var($data['price_negotiable'] ?? false, FILTER_VALIDATE_BOOL),
            'cover_photo' => $this->store($files['cover_photo'], 'tools-for-sale/covers'),
            'description_en' => $data['description_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'contact_number' => $contactNumber,
            'status' => 'active',
        ]);

        PublicPostSubmitted::dispatch($post);

        return $post;
    }
}
