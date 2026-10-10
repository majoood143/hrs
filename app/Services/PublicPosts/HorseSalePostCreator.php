<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Models\City;
use App\Models\HorseSalePost;
use Illuminate\Database\Eloquent\Model;

class HorseSalePostCreator extends PublicPostCreator
{
    public function rules(): array
    {
        return [
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'exists:types,id'],
            'gender_id' => ['required', 'exists:genders,id'],
            'color_id' => ['nullable', 'exists:colors,id'],
            'breed' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'dam' => ['nullable', 'string', 'max:255'],
            'sire' => ['nullable', 'string', 'max:255'],
            'birth_country_id' => ['nullable', 'exists:countries,id'],
            'passport_number' => ['nullable', 'string', 'max:255'],
            'passport_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_negotiable' => ['nullable', 'boolean'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'en_name' => __('horses-for-sale.name_en'),
            'ar_name' => __('horses-for-sale.name_ar'),
            'type_id' => __('horses-for-sale.type'),
            'gender_id' => __('horses-for-sale.gender'),
            'color_id' => __('horses-for-sale.color'),
            'breed' => __('horses-for-sale.breed'),
            'dob' => __('horses-for-sale.dob'),
            'dam' => __('horses-for-sale.dam'),
            'sire' => __('horses-for-sale.sire'),
            'birth_country_id' => __('horses-for-sale.birth_country'),
            'passport_number' => __('horses-for-sale.passport_number'),
            'passport_document' => __('horses-for-sale.passport_document'),
            'city_id' => __('horses-for-sale.city'),
            'price' => __('horses-for-sale.price'),
            'price_negotiable' => __('horses-for-sale.price_negotiable'),
            'cover_photo' => __('horses-for-sale.cover_photo'),
            'images' => __('horses-for-sale.gallery_photos'),
        ];
    }

    public function create(array $data, array $files, string $contactNumber): Model
    {
        $city = City::with('region')->findOrFail($data['city_id']);

        $post = HorseSalePost::create([
            'en_name' => $data['en_name'],
            'ar_name' => $data['ar_name'],
            'type_id' => $data['type_id'],
            'gender_id' => $data['gender_id'],
            'color_id' => $data['color_id'] ?? null,
            'breed' => $data['breed'] ?? null,
            'dob' => $data['dob'] ?? null,
            'dam' => $data['dam'] ?? null,
            'sire' => $data['sire'] ?? null,
            'birth_country_id' => $data['birth_country_id'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'passport_document' => ($files['passport_document'] ?? null) ? $this->store($files['passport_document'], 'horse-sale/passports') : null,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'price' => $data['price'],
            'price_negotiable' => filter_var($data['price_negotiable'] ?? false, FILTER_VALIDATE_BOOL),
            'cover_photo' => $this->store($files['cover_photo'], 'horse-sale/covers'),
            'images' => array_map(fn ($image) => $this->store($image, 'horse-sale/gallery'), $files['images'] ?? []),
            'description_en' => $data['description_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'contact_number' => $contactNumber,
            'status' => 'active',
        ]);

        PublicPostSubmitted::dispatch($post);

        return $post;
    }
}
