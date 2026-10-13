<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Models\City;
use App\Models\Farrier;
use Illuminate\Database\Eloquent\Model;

class FarrierCreator extends PublicPostCreator
{
    public function rules(): array
    {
        return [
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'en_name' => __('farriers.name_en'),
            'ar_name' => __('farriers.name_ar'),
            'specialty' => __('farriers.specialty'),
            'years_experience' => __('farriers.years_experience'),
            'city_id' => __('farriers.city'),
            'price' => __('farriers.price'),
            'cover_photo' => __('farriers.cover_photo'),
        ];
    }

    public function create(array $data, array $files, string $contactNumber, string $status = 'active'): Model
    {
        $city = City::with('region')->findOrFail($data['city_id']);

        $post = Farrier::create([
            'en_name' => $data['en_name'],
            'ar_name' => $data['ar_name'],
            'specialty' => $data['specialty'] ?? null,
            'years_experience' => $data['years_experience'] ?? null,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'price' => $data['price'],
            'cover_photo' => $this->store($files['cover_photo'], 'farriers/covers'),
            'description_en' => $data['description_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'contact_number' => $contactNumber,
            'status' => $status,
        ]);

        PublicPostSubmitted::dispatch($post);

        return $post;
    }
}
