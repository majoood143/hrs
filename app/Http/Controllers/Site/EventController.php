<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\Seo;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        return view('site.events.index', Seo::forSlug(
            'events',
            __('events.calendar.title').' — '.SiteSetting::siteName(),
        ));
    }
}
