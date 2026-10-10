<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Country;
use App\Models\Gender;
use App\Models\HorseSalePost;
use App\Models\SiteSetting;
use App\Models\Type;
use App\Services\PublicPosts\HorseSalePostCreator;
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

class HorseForSaleController extends Controller
{
    public function index(): View
    {
        return view('site.horses-for-sale.index', Seo::forSlug(
            'horses-for-sale',
            __('horses-for-sale.board_title').' — '.SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $post = HorseSalePost::visible()
            ->with(['type', 'gender', 'color', 'city', 'country'])
            ->findOrFail($id);

        ViewCounter::record($post);

        return view('site.horses-for-sale.show', [
            'post' => $post,
            'seoTitle' => $post->name.' — '.SiteSetting::siteName(),
            'seoDescription' => $post->description ? Str::limit($post->description, 160) : null,
            'seoImage' => $post->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.horses-for-sale.create', [
            'seoTitle' => __('horses-for-sale.post_page_title').' — '.SiteSetting::siteName(),
            'types' => Type::query()->orderBy('en_name')->get(),
            'genders' => Gender::query()->orderBy('en_name')->get(),
            'colors' => Color::query()->orderBy('en_name')->get(),
            'countries' => Country::query()->public()->with('region.city')->orderBy('en_name')->get(),
        ]);
    }

    public function store(Request $request, HorseSalePostCreator $creator): RedirectResponse
    {
        $validated = $request->validate($creator->rules() + [
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], $creator->attributes() + [
            'contact_number' => __('horses-for-sale.contact_number'),
            'captcha' => __('horses-for-sale.captcha_label'),
        ]);

        $creator->create($validated, ['cover_photo' => $request->file('cover_photo'), 'images' => $request->file('images', []), 'passport_document' => $request->file('passport_document')], $validated['contact_number']);

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
            'Content-Type' => 'image/'.$captcha->getImageType(),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
