<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AdResource;
use App\Http\Resources\V1\BlogPostListResource;
use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\HorseForSaleListResource;
use App\Http\Resources\V1\ToolForSaleListResource;
use App\Http\Resources\V1\TransferPostResource;
use App\Models\Ad;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\Event;
use App\Models\HorseSalePost;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use Illuminate\Support\Collection;

/**
 * The app's own Home feed: the newest of each section, not the website's CMS homepage blocks.
 */
class HomeController extends Controller
{
    private const PER_SECTION = 6;

    /**
     * Home feed
     *
     * Promos (as on the website's homepage), the newest horses and tools for sale, the soonest
     * transfers, upcoming events and the latest blog posts.
     *
     * @unauthenticated
     */
    public function __invoke(): array
    {
        return [
            'data' => [
                'ads' => AdResource::collection($this->ads()),
                'horses' => HorseForSaleListResource::collection(
                    HorseSalePost::visible()->with(['type', 'gender', 'city', 'country'])->latest('id')->take(self::PER_SECTION)->get()
                ),
                'tools' => ToolForSaleListResource::collection(
                    ToolSalePost::visible()->with(['city', 'country'])->latest('id')->take(self::PER_SECTION)->get()
                ),
                'transfers' => TransferPostResource::collection(
                    TransferPost::visible()->with(['fromCountry', 'fromCity', 'toCountry', 'toCity'])->orderBy('transfer_date')->take(self::PER_SECTION)->get()
                ),
                'events' => EventResource::collection(
                    Event::query()->upcoming()->with('category')->orderBy('date')->orderBy('start_time')->take(self::PER_SECTION)->get()
                ),
                'posts' => BlogPostListResource::collection(
                    CmsPost::query()->published()->with(['category', 'media'])->latest('published_at')->take(4)->get()
                ),
            ],
        ];
    }

    /**
     * The promos of the website's homepage: its first "ads area" block's zone and count. No such
     * block, no promos (the app shows ads where the site does).
     *
     * @return Collection<int, Ad>
     */
    private function ads(): Collection
    {
        $page = CmsPage::query()->where('is_homepage', true)->published()->first()
            ?? CmsPage::query()->where('is_homepage', true)->first();

        $block = collect($page?->content ?? [])->first(fn ($block) => is_array($block) && ($block['type'] ?? null) === 'ads_area');

        if (! $block) {
            return collect();
        }

        $data = $block['data'] ?? [];

        return Ad::query()->active()
            ->when(! empty($data['zone_id']), fn ($q) => $q->where('zone_id', $data['zone_id']))
            ->where(fn ($q) => $q->whereNotNull('image_path')->orWhereNotNull('video_path'))
            ->orderBy('order')
            ->take((int) ($data['count'] ?? 8))
            ->get();
    }
}
