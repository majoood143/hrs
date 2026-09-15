<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;

class AdClickController extends Controller
{
    public function redirect(Ad $ad): RedirectResponse
    {
        abort_if(! $ad->target_url, 404);

        $ad->increment('clicks');

        return redirect()->away($ad->target_url);
    }
}
