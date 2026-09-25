<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Farrier;
use App\Models\SiteSetting;
use App\Support\ImageCompressor;
use App\Support\Seo;
use App\Support\ViewCounter;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use MarcoGermani87\FilamentCaptcha\Rules\Captcha;

class FarrierController extends Controller
{
    public function index(): View
    {
        return view('site.farriers.index', Seo::forSlug(
            'farriers',
            __('farriers.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $farrier = Farrier::visible()
            ->with(['city', 'country'])
            ->findOrFail($id);

        ViewCounter::record($farrier);

        return view('site.farriers.show', [
            'farrier' => $farrier,
            'seoTitle' => $farrier->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $farrier->description ? Str::limit($farrier->description, 160) : null,
            'seoImage' => $farrier->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.farriers.create', [
            'seoTitle' => __('farriers.post_page_title') . ' — ' . SiteSetting::siteName(),
            'countries' => Country::query()->public()->with('region.city')->orderBy('en_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], [
            'en_name' => __('farriers.name_en'),
            'ar_name' => __('farriers.name_ar'),
            'specialty' => __('farriers.specialty'),
            'years_experience' => __('farriers.years_experience'),
            'city_id' => __('farriers.city'),
            'price' => __('farriers.price'),
            'cover_photo' => __('farriers.cover_photo'),
            'contact_number' => __('farriers.contact_number'),
            'captcha' => __('farriers.captcha_label'),
        ]);

        $city = City::with('region')->findOrFail($validated['city_id']);

        $coverPhotoPath = $request->file('cover_photo')->store('farriers/covers', 'public');

        ImageCompressor::compress(Storage::disk('public')->path($coverPhotoPath));

        Farrier::create([
            'en_name' => $validated['en_name'],
            'ar_name' => $validated['ar_name'],
            'specialty' => $validated['specialty'] ?? null,
            'years_experience' => $validated['years_experience'] ?? null,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'price' => $validated['price'],
            'cover_photo' => $coverPhotoPath,
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'contact_number' => $validated['contact_number'],
            'status' => 'active',
        ]);

        return redirect()
            ->route('farriers.index')
            ->with('status', __('farriers.posted_success_title'));
    }

    public function captcha(): Response
    {
        $charset = config('filament-captcha.charset', 'abcdefghijklmnpqrstuvwxyz123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $phraseBuilder = new PhraseBuilder(config('filament-captcha.length', 5), $charset);
        $captcha = new CaptchaBuilder(null, $phraseBuilder);

        session(['filament_captcha_code' => $captcha->getPhrase()]);

        $background = config('filament-captcha.background_color', [255, 255, 255]);

        $captcha->setBackgroundColor($background[0] ?? 255, $background[1] ?? 255, $background[2] ?? 255)
            ->build(config('filament-captcha.width', 180), config('filament-captcha.height', 50));

        return response($captcha->get(), 200, [
            'Content-Type' => 'image/' . $captcha->getImageType(),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
