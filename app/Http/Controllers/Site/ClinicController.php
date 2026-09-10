<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\SiteSetting;
use App\Support\Seo;
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

        return view('site.clinics.show', [
            'clinic' => $clinic,
            'seoTitle' => $clinic->name . ' — ' . SiteSetting::siteName(),
        ]);
    }
}
