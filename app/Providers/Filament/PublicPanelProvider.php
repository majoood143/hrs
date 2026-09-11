<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use App\Models\SiteSetting;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MarcoGermani87\FilamentCaptcha\FilamentCaptcha;

class PublicPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('public')
            ->path('transportation')
            ->brandName('Horse Transfer Service')
            ->favicon(fn () => SiteSetting::faviconUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverPages(in: app_path('Filament/Public/Pages'), for: 'App\\Filament\\Public\\Pages')
            ->discoverResources(in: app_path('Filament/Public/Resources'), for: 'App\\Filament\\Public\\Resources')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->plugins([
                FilamentCaptcha::make(),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.public.language-switcher')->render(),
            );
    }
}
