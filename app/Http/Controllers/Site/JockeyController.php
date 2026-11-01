<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Jockey;
use App\Models\Region;
use App\Models\SiteSetting;
use App\Services\PublicPosts\JockeyCreator;
use App\Support\Seo;
use App\Support\ViewCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use MarcoGermani87\FilamentCaptcha\Rules\Captcha;

/** The jockeys board, a jockey's page and the "add yourself" form (captcha like the farriers' one). */
class JockeyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(Jockey::TYPES)],
            'region_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
        ]);

        $countries = Country::query()->public()->with('region.city')->orderBy('en_name')->get();

        return view('site.jockeys.index', Seo::forSlug('jockeys', __('jockeys.board_title').' — '.SiteSetting::siteName()) + [
            'jockeys' => Jockey::query()->visible()->with(['city', 'country'])->filteredBy($filters)->latest('id')->paginate(9)->withQueryString(),
            'countries' => $countries,
            'regions' => $countries->flatMap->region,
            'filters' => $filters,
            'filterCity' => isset($filters['city_id']) ? City::find($filters['city_id']) : null,
        ]);
    }

    public function show(int $id): View
    {
        $jockey = Jockey::visible()->with(['city', 'country'])->findOrFail($id);

        ViewCounter::record($jockey);

        return view('site.jockeys.show', [
            'jockey' => $jockey,
            'seoTitle' => $jockey->name.' — '.SiteSetting::siteName(),
            'seoDescription' => $jockey->description ? Str::limit($jockey->description, 160) : null,
            'seoImage' => $jockey->cover_photo_url,
        ]);
    }

    public function create(): View
    {
        return view('site.jockeys.create', [
            'seoTitle' => __('jockeys.post_page_title').' — '.SiteSetting::siteName(),
            'locationTree' => $this->locationTree(),
        ]);
    }

    /** Country → region → city for the form's three selects (like the tools and transfer forms). */
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
                        'cities' => $region->city->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name])->values(),
                    ])
                    ->filter(fn (array $region) => $region['cities']->isNotEmpty())
                    ->values(),
            ])
            ->filter(fn (array $country) => $country['regions']->isNotEmpty())
            ->values()
            ->toArray();
    }

    public function store(Request $request, JockeyCreator $creator): RedirectResponse
    {
        // the racing profile link is set by an admin for website posts (the app has the search for it)
        $validated = $request->validate(collect($creator->rules())->except('racing_jockey_id')->all() + [
            'contact_number' => ['required', 'string', 'max:30'],
            'captcha' => ['required', new Captcha],
        ], [], $creator->attributes() + [
            'contact_number' => __('jockeys.contact_number'),
            'captcha' => __('farriers.captcha_label'),
        ]);

        $creator->create($validated, ['cover_photo' => $request->file('cover_photo')], $validated['contact_number']);

        return redirect()->route('jockeys.index')->with('status', __('jockeys.posted_success_title'));
    }
}
