<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SiteSetting;
use App\Support\Seo;
use App\Support\ViewCounter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        return view('site.clinics.index', Seo::forSlug(
            'clinics',
            __('clinics.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(string $slug): View
    {
        $clinic = Clinic::active()
            ->with(['country', 'region', 'city', 'services'])
            ->where('slug', $slug)
            ->firstOrFail();

        ViewCounter::record($clinic);

        return view('site.clinics.show', [
            'clinic' => $clinic,
            'seoTitle' => $clinic->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $clinic->description ? Str::limit($clinic->description, 160) : null,
            'seoImage' => $clinic->cover_photo_url,
        ]);
    }
}
