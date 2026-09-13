<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Support\Seo;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(): View
    {
        return view('site.shops.index', Seo::forSlug(
            'shops',
            __('shops.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(string $slug): View
    {
        $shop = Shop::active()
            ->with(['country', 'region', 'city', 'services'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.shops.show', [
            'shop' => $shop,
            'seoTitle' => $shop->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $shop->description ? Str::limit($shop->description, 160) : null,
            'seoImage' => $shop->cover_photo_url,
        ]);
    }
}
