<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\SiteSetting;
use App\Models\TransferPost;
use App\Services\PublicPosts\TransferPostCreator;
use App\Support\Seo;
use App\Support\ViewCounter;
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
            __('transportation.board_title').' — '.SiteSetting::siteName(),
        ));
    }

    public function show(int $id): View
    {
        $post = TransferPost::visible()
            ->with(['fromCity', 'fromCountry', 'toCity', 'toCountry'])
            ->findOrFail($id);

        $title = $post->fromCity?->name.' → '.$post->toCity?->name.' — '.SiteSetting::siteName();

        ViewCounter::record($post);

        return view('site.transfer-board.show', [
            'post' => $post,
            'seoTitle' => $title,
        ]);
    }

    public function create(): View
    {
        return view('site.transfer-board.create', [
            'seoTitle' => __('transportation.post_page_title').' — '.SiteSetting::siteName(),
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
                    ->map(fn (Region $region) => [
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

    public function store(Request $request, TransferPostCreator $creator): RedirectResponse
    {
        $validated = $request->validate($creator->rules() + [
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], $creator->attributes() + [
            'contact_number' => __('transportation.contact_number'),
            'captcha' => __('transportation.captcha_label'),
        ]);

        $creator->create($validated, ['cover_photo' => $request->file('cover_photo')], $validated['contact_number']);

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
            'Content-Type' => 'image/'.$captcha->getImageType(),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
