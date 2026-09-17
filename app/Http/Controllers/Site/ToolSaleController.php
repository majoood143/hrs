<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\SiteSetting;
use App\Models\ToolSalePost;
use App\Support\ImageCompressor;
use App\Support\Seo;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use MarcoGermani87\FilamentCaptcha\Rules\Captcha;

class ToolSaleController extends Controller
{
    public function index(): View
    {
        return view('site.tools-for-sale.index', Seo::forSlug(
            'tools-for-sale',
            __('tools-for-sale.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $tool = ToolSalePost::visible()
            ->with(['city', 'country'])
            ->findOrFail($id);

        return view('site.tools-for-sale.show', [
            'tool' => $tool,
            'seoTitle' => $tool->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $tool->description ? Str::limit($tool->description, 160) : null,
            'seoImage' => $tool->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.tools-for-sale.create', [
            'seoTitle' => __('tools-for-sale.post_page_title') . ' — ' . SiteSetting::siteName(),
            'categories' => ToolSalePost::CATEGORIES,
            'locationTree' => $this->locationTree(),
        ]);
    }

    private function locationTree(): array
    {
        return Country::query()->public()->with('region.city')->orderBy('en_name')->get()
            ->map(fn (Country $country) => [
                'id' => $country->id,
                'name' => $country->name,
                'regions' => $country->region
                    ->map(fn (\App\Models\Region $region) => [
                        'id' => $region->id,
                        'name' => $region->name,
                        'cities' => $region->city
                            ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name])
                            ->values(),
                    ])
                    ->filter(fn (array $region) => $region['cities']->isNotEmpty())
                    ->values(),
            ])
            ->filter(fn (array $country) => $country['regions']->isNotEmpty())
            ->values()
            ->toArray();
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:' . implode(',', ToolSalePost::CATEGORIES)],
            'condition' => ['required', 'in:' . implode(',', ToolSalePost::CONDITIONS)],
            'brand' => ['nullable', 'string', 'max:255'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], [
            'en_name' => __('tools-for-sale.name_en'),
            'ar_name' => __('tools-for-sale.name_ar'),
            'category' => __('tools-for-sale.category'),
            'condition' => __('tools-for-sale.condition'),
            'brand' => __('tools-for-sale.brand'),
            'city_id' => __('tools-for-sale.city'),
            'price' => __('tools-for-sale.price'),
            'cover_photo' => __('tools-for-sale.cover_photo'),
            'contact_number' => __('tools-for-sale.contact_number'),
            'captcha' => __('tools-for-sale.captcha_label'),
        ]);

        $city = City::with('region')->findOrFail($validated['city_id']);

        $coverPhotoPath = $request->file('cover_photo')->store('tools-for-sale/covers', 'public');

        ImageCompressor::compress(Storage::disk('public')->path($coverPhotoPath));

        ToolSalePost::create([
            'en_name' => $validated['en_name'],
            'ar_name' => $validated['ar_name'],
            'category' => $validated['category'],
            'condition' => $validated['condition'],
            'brand' => $validated['brand'] ?? null,
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
            ->route('tools-for-sale.index')
            ->with('status', __('tools-for-sale.posted_success_title'));
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
