<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\SiteSetting;
use App\Models\TransferPost;
use App\Support\Seo;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function create(): View
    {
        return view('site.transfer-board.create', [
            'seoTitle' => __('transportation.post_page_title') . ' — ' . SiteSetting::siteName(),
            'countries' => Country::query()->public()->with('region.city')->orderBy('en_name')->get(),
        ]);
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
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], [
            'type' => __('transportation.step_type'),
            'from_city_id' => __('transportation.filter_from'),
            'to_city_id' => __('transportation.filter_to'),
            'capacity' => __('transportation.capacity'),
            'transfer_date' => __('transportation.transfer_date'),
            'price' => __('transportation.price'),
            'contact_number' => __('transportation.contact_number'),
            'captcha' => __('transportation.captcha_label'),
        ]);

        $fromCity = City::with('region')->findOrFail($validated['from_city_id']);
        $toCity = City::with('region')->findOrFail($validated['to_city_id']);

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
