<?php

namespace App\Services\PublicPosts;

use App\Events\PublicPostSubmitted;
use App\Models\City;
use App\Models\TransferPost;
use Illuminate\Database\Eloquent\Model;

class TransferPostCreator extends PublicPostCreator
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:offer,request'],
            'from_city_id' => ['required', 'different:to_city_id', 'exists:cities,id'],
            'to_city_id' => ['required', 'exists:cities,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date', 'after_or_equal:today'],
            'price' => ['nullable', 'required_if:type,offer', 'numeric', 'min:0'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => __('transportation.step_type'),
            'from_city_id' => __('transportation.filter_from'),
            'to_city_id' => __('transportation.filter_to'),
            'capacity' => __('transportation.capacity'),
            'transfer_date' => __('transportation.transfer_date'),
            'price' => __('transportation.price'),
            'cover_photo' => __('transportation.cover_photo'),
        ];
    }

    public function create(array $data, array $files, string $contactNumber, string $status = 'active'): Model
    {
        $fromCity = City::with('region')->findOrFail($data['from_city_id']);
        $toCity = City::with('region')->findOrFail($data['to_city_id']);

        $post = TransferPost::create([
            'type' => $data['type'],
            'from_country_id' => $fromCity->region->country_id,
            'from_region_id' => $fromCity->region_id,
            'from_city_id' => $fromCity->id,
            'to_country_id' => $toCity->region->country_id,
            'to_region_id' => $toCity->region_id,
            'to_city_id' => $toCity->id,
            'capacity' => $data['capacity'],
            'transfer_date' => $data['transfer_date'],
            'price' => $data['type'] === 'offer' ? $data['price'] : null,
            'cover_photo' => ($files['cover_photo'] ?? null) ? $this->store($files['cover_photo'], 'transfer-board/covers') : null,
            'contact_number' => $contactNumber,
            'status' => $status,
        ]);

        PublicPostSubmitted::dispatch($post);

        return $post;
    }
}
