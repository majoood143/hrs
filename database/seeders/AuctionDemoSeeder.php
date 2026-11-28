<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\Customer;
use App\Services\Auctions\Bidding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * A live demo auction to click through (dev only): 6 lots closing from 2 hours from now, photos from
 * the stallion catalogue, a few bidders and bids. The demo customer of StableBookingDemoSeeder
 * (90000002) is registered and approved; run it again to start over.
 *
 *   php artisan db:seed --class=AuctionDemoSeeder
 */
class AuctionDemoSeeder extends Seeder
{
    public const SLUG = 'demo-winter-sale';

    private const HORSES = [
        ['Bahar Muscat', 'بحر مسقط', 'stallion', 'Amer (SA)', 'Nisaee (FR)', 'achour.jpg', 2500],
        ['Najm Al Sharq', 'نجم الشرق', 'mare', 'Wafi (SA)', 'Reem (OM)', 'ajs-moaddie.jpg', 1800],
        ['Sahm', 'سهم', 'colt', 'Al Mouhannad (FR)', 'Layla (OM)', 'al-mishkhas.jpg', 1200],
        ['Reem Al Barr', 'ريم البر', 'filly', 'Amer (SA)', 'Hamsa (OM)', 'al-murabitoon.jpg', 900],
        ['Qous', 'قوس', 'gelding', 'Manganate (FR)', 'Ghazal (OM)', 'al-qous.jpg', 700],
        ['Dana', 'دانة', 'mare', 'Wafi (SA)', 'Sama (OM)', 'achour.jpg', 1500],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('The auction demo is for development only.');

            return;
        }

        Auction::query()->where('slug', self::SLUG)->delete();

        $auction = Auction::query()->create([
            'slug' => self::SLUG,
            'en_title' => 'Demo winter sale',
            'ar_title' => 'مزاد الشتاء التجريبي',
            'en_description' => 'Six Arabian horses from Omani breeders, online.',
            'ar_description' => 'ستة خيول عربية من مربين عمانيين، عبر الإنترنت.',
            'en_conditions' => '<p>Bids are binding. The buyer pays the hammer price plus a 5% buyer\'s premium and VAT within 3 days.</p>',
            'ar_conditions' => '<p>المزايدات ملزمة. يدفع المشتري سعر البيع مع عمولة المشتري ٥٪ والضريبة خلال ٣ أيام.</p>',
            'status' => 'published',
            'preview_at' => now()->subDays(2),
            'bidding_starts_at' => now()->subDay(),
            'ends_at' => now()->addHours(2),
            'lot_gap_seconds' => 300,
            'auto_approve' => true,
            'buyer_premium_percent' => 5,
            'seller_commission_percent' => 5,
        ]);

        foreach (self::HORSES as $i => [$en, $ar, $gender, $sire, $dam, $photo, $start]) {
            AuctionLot::query()->create([
                'auction_id' => $auction->id,
                'lot_number' => $i + 1,
                'en_name' => $en,
                'ar_name' => $ar,
                'en_tagline' => 'By '.$sire,
                'ar_tagline' => 'من نسل '.$sire,
                'gender' => $gender,
                'born_on' => now()->subYears(3 + $i)->startOfYear()->addMonths($i),
                'breed' => 'Purebred Arabian',
                'colour' => ['grey', 'bay', 'chestnut'][$i % 3],
                'height_cm' => 150 + $i,
                'owner_name' => 'Demo Stud',
                'owner_town' => 'Seeb',
                'sire_en' => $sire,
                'dam_en' => $dam,
                'pedigree' => ['ss' => 'Wafi (SA)', 'sd' => 'Reem (OM)', 'ds' => 'Manganate (FR)', 'dd' => 'Hamsa (OM)'],
                'photos' => [$this->photo($photo)],
                'starting_price' => $start,
                'reserve_price' => $i === 0 ? 4000 : null,
                'status' => 'ready',
            ]);
        }

        $demo = Customer::query()->firstOrCreate(['phone' => StableBookingDemoSeeder::CUSTOMER_PHONE], ['name' => 'Demo Customer', 'locale' => 'en']);
        $bidders = collect([$demo->phone, '96890000101', '96890000102'])->map(function (string $phone, int $i) use ($auction) {
            $customer = Customer::query()->firstOrCreate(['phone' => $phone], ['name' => 'Demo Bidder '.($i + 1), 'locale' => 'en']);

            return AuctionBidder::query()->create(['auction_id' => $auction->id, 'customer_id' => $customer->id, 'status' => 'approved', 'approved_at' => now(), 'accepted_terms_at' => now()]);
        });

        $bidding = app(Bidding::class);
        $lots = $auction->lots()->get();
        $bidding->place($lots[0], $bidders[1], 2_500_000, 3_500_000);
        $bidding->place($lots[0], $bidders[2], 3_000_000);
        $bidding->place($lots[1], $bidders[2], 1_800_000);
        $bidding->place($lots[2], $bidders[0], 1_200_000);
        $bidding->place($lots[2], $bidders[1], 1_300_000);

        $this->command?->info('Demo auction: '.route('auctions.show', self::SLUG).' (sign in with 90000002)');
    }

    private function photo(string $file): string
    {
        $path = 'auctions/lots/demo-'.$file;
        Storage::disk('public')->put($path, file_get_contents(database_path('seeders/data/stallions/'.$file)));

        return $path;
    }
}
