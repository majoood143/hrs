<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\SiteSetting;
use App\Models\TransferPost;
use App\Support\ImageCompressor;
use App\Support\Seo;
use App\Support\ViewCounter;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use MarcoGermani87\FilamentCaptcha\Rules\Captcha;

class TransferBoardController extends Controller
{
    public function index(): View
    {
        return view('site.transfer-board.index', Seo::forSlug(
            'transfer-board',
            __('transportation.board_title') . ' — ' . SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $post = TransferPost::visible()
            ->with(['fromCity', 'fromCountry', 'toCity', 'toCountry'])
            ->findOrFail($id);

        $title = $post->fromCity?->name . ' → ' . $post->toCity?->name . ' — ' . SiteSetting::siteName();

        ViewCounter::record($post);

        return view('site.transfer-board.show', [
            'post' => $post,
            'seoTitle' => $title,
        ]);
    }

    public function create(): View
    {
        return view('site.transfer-board.create', [
            'seoTitle' => __('transportation.post_page_title') . ' — ' . SiteSetting::siteName(),
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
            'type' => ['required', 'in:offer,request'],
            'from_city_id' => ['required', 'different:to_city_id', 'exists:cities,id'],
            'to_city_id' => ['required', 'exists:cities,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date', 'after_or_equal:today'],
            'price' => ['nullable', 'required_if:type,offer', 'numeric', 'min:0'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], [
            'type' => __('transportation.step_type'),
            'from_city_id' => __('transportation.filter_from'),
            'to_city_id' => __('transportation.filter_to'),
            'capacity' => __('transportation.capacity'),
            'transfer_date' => __('transportation.transfer_date'),
            'price' => __('transportation.price'),
            'cover_photo' => __('transportation.cover_photo'),
            'contact_number' => __('transportation.contact_number'),
            'captcha' => __('transportation.captcha_label'),
        ]);

        $fromCity = City::with('region')->findOrFail($validated['from_city_id']);
        $toCity = City::with('region')->findOrFail($validated['to_city_id']);

        $coverPhotoPath = null;

        if ($request->hasFile('cover_photo')) {
            $coverPhotoPath = $request->file('cover_photo')->store('transfer-board/covers', 'public');

            ImageCompressor::compress(Storage::disk('public')->path($coverPhotoPath));
        }

        TransferPost::create([
            'type' => $validated['type'],
            'from_country_id' => $fromCity->region->country_id,
            'from_region_id' => $fromCity->region_id,
            'from_city_id' => $fromCity->id,
            'to_country_id' => $toCity->region->country_id,
            'to_region_id' => $toCity->region_id,
            'to_city_id' => $toCity->id,
            'capacity' => $validated['capacity'],
            'transfer_date' => $validated['transfer_date'],
            'price' => $validated['type'] === 'offer' ? $validated['price'] : null,
            'cover_photo' => $coverPhotoPath,
            'contact_number' => $validated['contact_number'],
            'status' => 'active',
        ]);

        return redirect()
            ->route('transfer-board.index')
            ->with('status', __('transportation.posted_success_title'));
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
