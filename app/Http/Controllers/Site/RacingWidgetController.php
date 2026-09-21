<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * The races widget on its own, for the page's JavaScript to swap in when a visitor picks a date or a
 * month, so the page does not reload. It is not a page: `?from=/path` says which page the widget sits on
 * (the links it renders point back there), and everything else in the query is the widget's own.
 */
class RacingWidgetController extends Controller
{
    public function __invoke(): Response
    {
        return response(view('site.racing.widget-fragment'))
            ->header('Cache-Control', 'private, max-age=30')
            ->header('X-Robots-Tag', 'noindex');
    }
}
