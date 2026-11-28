<?php

namespace App\Http\Resources\V1\Auctions;

use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\Stallion;
use App\Services\Orders\OrderPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** A lot's whole page: the card, the horse, the pedigree, the documents (approved bidders) and the bid board. */
class LotResource extends LotListResource
{
    public function __construct(AuctionLot $lot, private readonly ?AuctionBidder $bidder = null, private readonly bool $watching = false)
    {
        parent::__construct($lot);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lot = $this->resource;
        $approved = $this->bidder?->status === 'approved';
        $pedigree = $lot->pedigree ?? [];

        return parent::toArray($request) + [
            'photos' => $lot->photoUrls(),
            'videos' => array_values($lot->videos ?? []),
            /** Label/value pairs in the request's language, in the order to show them. */
            'facts' => collect([
                'gender' => $lot->gender ? __('auctions.genders.'.$lot->gender) : null,
                'born' => $lot->born_on?->translatedFormat('j M Y'),
                'breed' => $lot->breed,
                'colour' => $lot->colour ? (Stallion::colourOptions()[$lot->colour] ?? null) : null,
                'height' => $lot->height_cm ? __('auctions.site.height_value', ['cm' => $lot->height_cm, 'hands' => number_format($lot->height_cm / 10.16, 1)]) : null,
                'ueln' => $lot->ueln,
                'microchip' => $lot->microchip,
                'genetic_tests' => $lot->genetic_tests,
                'owner' => trim($lot->owner_name.($lot->owner_town ? ', '.$lot->owner_town : '')) ?: null,
                'breeder' => trim($lot->breeder_name.($lot->breeder_town ? ', '.$lot->breeder_town : '')) ?: null,
                'clinic' => $lot->clinic,
                'vat' => $lot->vat_rate !== null ? rtrim(rtrim((string) $lot->vat_rate, '0'), '.').'%' : null,
            ])->filter()->map(fn (string $value, string $key) => ['label' => __('auctions.site.facts.'.$key), 'value' => $value])->values(),
            /** sire, dam, then AuctionLot::PEDIGREE_KEYS (s/d per generation, 4 generations); null where unknown. */
            'pedigree' => ['s' => $lot->sire, 'd' => $lot->dam] + collect(AuctionLot::PEDIGREE_KEYS)->mapWithKeys(fn ($key) => [$key => $pedigree[$key] ?? null])->all(),
            'dam_line' => $lot->dam_line,
            'note' => $lot->note,
            'racing_horse_id' => $lot->racing_horse_id,
            /** Vet report and X-rays: links that work for 30 minutes, for approved bidders only. */
            'documents' => $approved ? collect($lot->documents ?? [])->values()->map(fn (string $path, int $i) => [
                'name' => basename($path),
                'url' => URL::temporarySignedRoute('api.v1.auctions.document', now()->addMinutes(30), ['slug' => $lot->auction->slug, 'number' => $lot->lot_number, 'index' => $i]),
            ]) : [],
            'documents_locked' => ! $approved && ! empty($lot->documents),
            /** For "if you win you pay": hammer + premium + hammer × vat + premium × fee_vat. */
            'costs' => [
                'buyer_premium_percent' => (float) $lot->auction->buyer_premium_percent,
                'vat_percent' => (float) $lot->vat_rate,
                'fee_vat_percent' => app(OrderPricing::class)->vatRate(),
            ],
            'watching' => $this->watching,
            'state' => new LotStateResource($lot, $this->bidder),
        ];
    }
}
