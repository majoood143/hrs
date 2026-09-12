<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Color;
use App\Models\Country;
use App\Models\Gender;
use App\Models\HorseSalePost;
use App\Models\SiteSetting;
use App\Models\Type;
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

class HorseForSaleController extends Controller
{
    public function index(): View
    {
        return view('site.horses-for-sale.index', Seo::forSlug(
            'horses-for-sale',
            __('horses-for-sale.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $post = HorseSalePost::visible()
            ->with(['type', 'gender', 'color', 'city', 'country'])
            ->findOrFail($id);

        return view('site.horses-for-sale.show', [
            'post' => $post,
            'seoTitle' => $post->name . ' — ' . SiteSetting::siteName(),
            'seoDescription' => $post->description ? Str::limit($post->description, 160) : null,
            'seoImage' => $post->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.horses-for-sale.create', [
            'seoTitle' => __('horses-for-sale.post_page_title') . ' — ' . SiteSetting::siteName(),
            'types' => Type::query()->orderBy('en_name')->get(),
            'genders' => Gender::query()->orderBy('en_name')->get(),
            'colors' => Color::query()->orderBy('en_name')->get(),
            'countries' => Country::query()->public()->with('region.city')->orderBy('en_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'en_name' => ['required', 'string', 'max:255'],
            'ar_name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'exists:types,id'],
            'gender_id' => ['required', 'exists:genders,id'],
            'color_id' => ['nullable', 'exists:colors,id'],
            'breed' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'dam' => ['nullable', 'string', 'max:255'],
            'sire' => ['nullable', 'string', 'max:255'],
            'birth_country_id' => ['nullable', 'exists:countries,id'],
            'passport_number' => ['nullable', 'string', 'max:255'],
            'passport_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'city_id' => ['required', 'exists:cities,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cover_photo' => ['required', 'image', 'max:4096'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:4096'],
            'description_en' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], [
            'en_name' => __('horses-for-sale.name_en'),
            'ar_name' => __('horses-for-sale.name_ar'),
            'type_id' => __('horses-for-sale.type'),
            'gender_id' => __('horses-for-sale.gender'),
            'color_id' => __('horses-for-sale.color'),
            'breed' => __('horses-for-sale.breed'),
            'dob' => __('horses-for-sale.dob'),
            'dam' => __('horses-for-sale.dam'),
            'sire' => __('horses-for-sale.sire'),
            'birth_country_id' => __('horses-for-sale.birth_country'),
            'passport_number' => __('horses-for-sale.passport_number'),
            'passport_document' => __('horses-for-sale.passport_document'),
            'city_id' => __('horses-for-sale.city'),
            'price' => __('horses-for-sale.price'),
            'cover_photo' => __('horses-for-sale.cover_photo'),
            'images' => __('horses-for-sale.gallery_photos'),
            'contact_number' => __('horses-for-sale.contact_number'),
            'captcha' => __('horses-for-sale.captcha_label'),
        ]);

        $city = City::with('region')->findOrFail($validated['city_id']);

        $coverPhotoPath = $request->file('cover_photo')->store('horse-sale/covers', 'public');

        ImageCompressor::compress(Storage::disk('public')->path($coverPhotoPath));

        $galleryPaths = [];
        foreach ($request->file('images', []) as $image) {
            $path = $image->store('horse-sale/gallery', 'public');
            ImageCompressor::compress(Storage::disk('public')->path($path));
            $galleryPaths[] = $path;
        }

        $passportDocumentPath = null;
        if ($request->hasFile('passport_document')) {
            $passportDocumentPath = $request->file('passport_document')->store('horse-sale/passports', 'public');
            ImageCompressor::compress(Storage::disk('public')->path($passportDocumentPath));
        }

        HorseSalePost::create([
            'en_name' => $validated['en_name'],
            'ar_name' => $validated['ar_name'],
            'type_id' => $validated['type_id'],
            'gender_id' => $validated['gender_id'],
            'color_id' => $validated['color_id'] ?? null,
            'breed' => $validated['breed'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'dam' => $validated['dam'] ?? null,
            'sire' => $validated['sire'] ?? null,
            'birth_country_id' => $validated['birth_country_id'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'passport_document' => $passportDocumentPath,
            'country_id' => $city->region->country_id,
            'region_id' => $city->region_id,
            'city_id' => $city->id,
            'price' => $validated['price'],
            'cover_photo' => $coverPhotoPath,
            'images' => $galleryPaths,
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'contact_number' => $validated['contact_number'],
            'status' => 'active',
        ]);

        return redirect()
            ->route('horses-for-sale.index')
            ->with('status', __('horses-for-sale.posted_success_title'));
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
