<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\SiteSetting;
use App\Support\Seo;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CenterController extends Controller
{
    public function index(): View
    {
        return view('site.centers.index', Seo::forSlug(
            'centers',
            __('centers.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(string $slug): View
    {
        $center = Center::active()
            ->with(['country', 'region', 'city', 'services'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.centers.show', [
            'center' => $center,
            'seoTitle' => $center->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $center->description ? Str::limit($center->description, 160) : null,
            'seoImage' => $center->cover_photo_url,
        ]);
    }
}
