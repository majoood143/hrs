<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\Stallion;
use App\Support\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The breeding stallions list and a stallion's page (pedigree, race record, contact). */
class StallionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'colour' => ['nullable', Rule::in(Stallion::COLOURS)],
        ]);

        return view('site.stallions.index', [
            'stallions' => Stallion::query()->visible()->with('city')->filteredBy($filters)->byName()->paginate(12)->withQueryString(),
            'filters' => $filters,
            'seoTitle' => __('stallions.board_title').' — '.SiteSetting::siteName(),
            'seoDescription' => __('stallions.board_subtitle'),
        ]);
    }

    public function show(int $id): View
    {
        $stallion = Stallion::visible()->with('city')->findOrFail($id);

        ViewCounter::record($stallion);

        return view('site.stallions.show', [
            'stallion' => $stallion,
            'seoTitle' => $stallion->name.' — '.SiteSetting::siteName(),
            'seoDescription' => Str::limit(trim(__('stallions.by', ['sire' => $stallion->sire, 'dam' => $stallion->dam]).'. '.$stallion->highlights), 160),
            'seoImage' => $stallion->photo_url,
        ]);
    }
}
