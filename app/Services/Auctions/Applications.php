<?php

namespace App\Services\Auctions;

use App\Jobs\SendAuctionMessage;
use App\Models\Auction;
use App\Models\AuctionApplication;
use App\Models\AuctionLot;
use App\Models\Customer;
use App\Models\Stallion;
use App\Support\ImageCompressor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Seller applications: the website's form (rules, storing its files), and the admin's approval into a
 * draft lot (the details, photos and documents copied, the customer as the lot's seller) or rejection.
 * The seller is told either way by SMS.
 */
class Applications
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'horse_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(AuctionLot::GENDERS)],
            'born_on' => ['nullable', 'date', 'before:today', 'after:1990-01-01'],
            'breed' => ['nullable', 'string', 'max:255'],
            'colour' => ['nullable', Rule::in(Stallion::COLOURS)],
            'height_cm' => ['nullable', 'integer', 'min:50', 'max:220'],
            'sire' => ['nullable', 'string', 'max:255'],
            'dam' => ['nullable', 'string', 'max:255'],
            'ueln' => ['nullable', 'string', 'max:64'],
            'microchip' => ['nullable', 'string', 'max:64'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_town' => ['nullable', 'string', 'max:255'],
            'breeder_name' => ['nullable', 'string', 'max:255'],
            'asking_reserve' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'videos' => ['nullable', 'array', 'max:5'],
            'videos.*' => ['nullable', 'url', 'max:500'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'declare' => ['accepted'],
        ];
    }

    /** @return array<string, string> the field names in the request's language, for the error messages */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->reject(fn ($key) => str_contains($key, '*'))
            ->mapWithKeys(fn (string $key) => [$key => __('auctions.sell.fields.'.$key)])->all();
    }

    /**
     * @param  array<string, mixed>  $data  validated
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, UploadedFile>  $documents
     */
    public function submit(Customer $customer, array $data, array $photos, array $documents): AuctionApplication
    {
        if (AuctionApplication::query()->where('customer_id', $customer->id)->where('status', 'pending')->count() >= AuctionApplication::MAX_PENDING) {
            throw new AuctionBidException('too_many_applications', ['max' => (string) AuctionApplication::MAX_PENDING]);
        }

        $stored = [];
        try {
            $photoPaths = array_map(function (UploadedFile $file) use (&$stored) {
                $stored[] = ['public', $path = $file->store('auctions/applications', 'public')];
                ImageCompressor::compress(Storage::disk('public')->path($path));

                return $path;
            }, $photos);
            $documentPaths = array_map(function (UploadedFile $file) use (&$stored) {
                $stored[] = ['local', $path = $file->storeAs('auctions/documents', uniqid().'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()), 'local')];

                return $path;
            }, $documents);

            return AuctionApplication::query()->create(collect($data)->except(['photos', 'documents', 'declare', 'videos'])->all() + [
                'customer_id' => $customer->id,
                'locale' => app()->getLocale(),
                'videos' => array_values(array_filter($data['videos'] ?? [])),
                'photos' => $photoPaths,
                'documents' => $documentPaths,
                'declared_at' => now(),
            ]);
        } catch (Throwable $e) {
            // nothing half-saved: the files of a failed application go too
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }

    /** Into a draft lot of the auction; the admin finishes the catalogue page (pedigree, prices, status) there. */
    public function approve(AuctionApplication $application, Auction $auction, int $lotNumber, string $startingPrice, ?string $reserve, Authenticatable $by): AuctionLot
    {
        return DB::transaction(function () use ($application, $auction, $lotNumber, $startingPrice, $reserve, $by) {
            $application = AuctionApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') {
                throw new AuctionBidException('already_reviewed');
            }

            $lot = AuctionLot::query()->create([
                'auction_id' => $auction->id,
                'lot_number' => $lotNumber,
                'en_name' => $application->horse_name,
                'ar_name' => $application->horse_name,
                'gender' => $application->gender,
                'born_on' => $application->born_on,
                'breed' => $application->breed,
                'colour' => $application->colour,
                'height_cm' => $application->height_cm,
                'sire_en' => $application->sire,
                'dam_en' => $application->dam,
                'ueln' => $application->ueln,
                'microchip' => $application->microchip,
                'owner_name' => $application->owner_name,
                'owner_town' => $application->owner_town,
                'breeder_name' => $application->breeder_name,
                'photos' => $application->photos,
                'videos' => $application->videos,
                'documents' => $application->documents,
                'en_note' => null,
                'seller_customer_id' => $application->customer_id,
                'starting_price' => $startingPrice,
                'reserve_price' => $reserve,
                'status' => 'draft',
            ]);

            $application->update(['status' => 'approved', 'auction_lot_id' => $lot->id, 'reviewed_at' => now(), 'reviewed_by' => $by->getAuthIdentifier()]);
            SendAuctionMessage::dispatch(SendAuctionMessage::APPLICATION_APPROVED, $application->id)->afterCommit();

            return $lot;
        });
    }

    public function reject(AuctionApplication $application, string $reason, Authenticatable $by): void
    {
        if ($application->status !== 'pending') {
            throw new AuctionBidException('already_reviewed');
        }

        $application->update(['status' => 'rejected', 'reject_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $by->getAuthIdentifier()]);
        SendAuctionMessage::dispatch(SendAuctionMessage::APPLICATION_REJECTED, $application->id);
    }
}
