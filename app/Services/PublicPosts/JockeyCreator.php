<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Models\City;
use App\Models\Jockey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** A jockey adding themselves from the app (no website form yet). */
class JockeyCreator extends PublicPostCreator
{
    public function rules(): array
    {
        return [
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', Rule::in(Jockey::TYPES)],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'city_id' => ['required', 'exists:cities,id'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            /** Their racing club profile, if they race; an admin checks it before it shows. */
            'racing_jockey_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'en_name' => __('jockeys.name_en'),
            'ar_name' => __('jockeys.name_ar'),
            'types' => __('jockeys.types_label'),
            'years_experience' => __('jockeys.years_experience'),
            'city_id' => __('jockeys.city'),
            'cover_photo' => __('jockeys.cover_photo'),
            'racing_jockey_id' => __('jockeys.racing_jockey_id'),
        ];
    }

    public function create(array $data, array $files, string $contactNumber, string $status = 'active'): Model
    {
        $city = City::with('region')->findOrFail($data['city_id']);

        $post = Jockey::create([
            'en_name' => $data['en_name'],
            'ar_name' => $data['ar_name'],
            'types' => array_values(array_unique($data['types'])),
            'years_experience' => $data['years_experience'] ?? null,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'cover_photo' => isset($files['cover_photo']) ? $this->store($files['cover_photo'], 'jockeys/covers') : null,
            'description_en' => $data['description_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'contact_number' => $contactNumber,
            'racing_jockey_id' => $data['racing_jockey_id'] ?? null,
            'status' => $status,
        ]);

        PublicPostSubmitted::dispatch($post);

        return $post;
    }
}
