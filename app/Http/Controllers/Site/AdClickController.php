<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Support\Ads\AdStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdClickController extends Controller
{
    public function redirect(Request $request, Ad $ad): RedirectResponse
    {
        abort_if(! $ad->target_url, 404);

        $ad->increment('clicks');
        AdStats::click($ad, $request->query('src') === 'app' ? 'app' : 'web');

        return redirect()->away($ad->target_url);
    }
}
