<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Support\Seo;
use Illuminate\View\View;

class StableController extends Controller
{
    public function index(): View
    {
        return view('site.stables.index', Seo::forSlug(
            'stables',
            __('stables.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(string $slug): View
    {
        $stable = Stable::active()
            ->with(['country', 'region', 'city', 'services'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.stables.show', [
            'stable' => $stable,
            'seoTitle' => $stable->name . ' — ' . SiteSetting::siteName(),
        ]);
    }
}
