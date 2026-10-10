<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Farrier;
use App\Models\SiteSetting;
use App\Services\PublicPosts\FarrierCreator;
use App\Support\Seo;
use App\Support\ViewCounter;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use MarcoGermani87\FilamentCaptcha\Rules\Captcha;

class FarrierController extends Controller
{
    public function index(): View
    {
        return view('site.farriers.index', Seo::forSlug(
            'farriers',
            __('farriers.board_title').' — '.SiteSetting::siteName(),
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
            'seoTitle' => $farrier->name.' — '.SiteSetting::siteName(),
            'seoDescription' => $farrier->description ? Str::limit($farrier->description, 160) : null,
            'seoImage' => $farrier->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.farriers.create', [
            'seoTitle' => __('farriers.post_page_title').' — '.SiteSetting::siteName(),
            'countries' => Country::query()->public()->with('region.city')->orderBy('en_name')->get(),
        ]);
    }

    public function store(Request $request, FarrierCreator $creator): RedirectResponse
    {
        $validated = $request->validate($creator->rules() + [
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], $creator->attributes() + [
            'contact_number' => __('farriers.contact_number'),
            'captcha' => __('farriers.captcha_label'),
        ]);

        $creator->create($validated, ['cover_photo' => $request->file('cover_photo')], $validated['contact_number']);

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
            'Content-Type' => 'image/'.$captcha->getImageType(),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
