<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * After signing in or registering on a panel: back to the page that asked for it only when that page
 * is in the same panel, else the panel's home. Both panels share the web session, so a page remembered
 * from /admin (url.intended) would otherwise send a new stable owner to an admin page.
 */
class PanelHomeResponse implements LoginResponse, RegistrationResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $intended = session()->pull('url.intended');
        $base = rtrim(url(Filament::getCurrentPanel()->getPath()), '/');

        $inPanel = is_string($intended) && ($intended === $base || str_starts_with($intended, $base.'/') || str_starts_with($intended, $base.'?'));

        return redirect()->to($inPanel ? $intended : Filament::getUrl());
    }
}
