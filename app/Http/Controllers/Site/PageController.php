<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Support\Seo;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function home(): View
    {
        $page = CmsPage::query()->where('is_homepage', true)->published()->first()
            ?? CmsPage::query()->where('is_homepage', true)->first();

        abort_unless($page, Response::HTTP_NOT_FOUND);

        return view('site.page', ['page' => $page] + Seo::forPage($page));
    }

    public function show(string $slug): View
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_homepage', false)
            ->published()
            ->firstOrFail();

        return view('site.page', ['page' => $page] + Seo::forPage($page));
    }
}
