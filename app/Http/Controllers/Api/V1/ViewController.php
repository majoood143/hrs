<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Clinic;
use App\Models\CmsPost;
use App\Models\Event;
use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\Shop;
use App\Models\ToolSalePost;
use App\Models\TransferPost;
use App\Models\Video;
use App\Support\ViewCounter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The app's side of the website's view counters (`Support\ViewCounter`). A GET never counts (the
 * app may refetch or prefetch); the app posts here once per listing per launch instead, which is
 * what the website's once-per-session rule amounts to. Crawlers are ignored as on the site.
 */
class ViewController extends Controller
{
    /**
     * Section => [the query a visitor may see, the key in the URL].
     *
     * @return array<string, array{0: Builder, 1: string}>
     */
    private function sections(): array
    {
        return [
            'horses-for-sale' => [HorseSalePost::visible(), 'id'],
            'tools-for-sale' => [ToolSalePost::visible(), 'id'],
            'farriers' => [Farrier::visible(), 'id'],
            'transfer-board' => [TransferPost::visible(), 'id'],
            'clinics' => [Clinic::active(), 'slug'],
            'centers' => [Center::active(), 'slug'],
            'shops' => [Shop::active(), 'slug'],
            'events' => [Event::query(), 'id'],
            'blog' => [CmsPost::query()->published(), 'slug'],
            'videos' => [Video::active(), 'slug'],
        ];
    }

    /**
     * Count a view
     *
     * `section` is one of horses-for-sale, tools-for-sale, farriers, transfer-board, clinics, centers,
     * shops, events (by `id`), blog, videos (by `slug`). Stables have no counter.
     *
     * @unauthenticated
     */
    public function store(Request $request, string $section, string $key): array
    {
        [$query, $column] = $this->sections()[$section] ?? abort(404);

        $model = $query->where($column, $key)->firstOrFail();

        $counted = ViewCounter::record($model, $request);

        return [
            /** False for crawlers. */
            'counted' => $counted,
            'views_count' => (int) $model->getAttribute('views_count'),
        ];
    }
}
