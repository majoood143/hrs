<?php

namespace App\Providers\Filament;

use App\Filament\Stable\Pages\Auth\Login;
use App\Filament\Stable\Pages\Auth\Register;
use App\Filament\Stable\Pages\Dashboard;
use App\Filament\Stable\Pages\Tenancy\EditStableProfile;
use App\Filament\Stable\Pages\Tenancy\RegisterStable;
use App\Http\Middleware\LogAdminStableAccess;
use App\Http\Middleware\SetLocale;
use App\Models\SiteSetting;
use App\Models\Stable;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
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

/**
 * /stable: where stable owners run their stables. Each stable is a tenant, so every screen works on
 * the one stable chosen in the menu, and its records are scoped to it. Owners are users of type
 * stable_owner; they cannot open /admin (User::canAccessPanel). Admins who manage stables can open
 * any stable here on its owner's behalf, under a warning strip, with every change logged.
 */
class StablePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('stable')
            ->path('stable')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->profile(isSimple: false)
            ->tenant(Stable::class, slugAttribute: 'slug')
            ->tenantProfile(EditStableProfile::class)
            ->tenantRegistration(RegisterStable::class)
            // admins who manage stables can open any of them: the menu gets long, so it searches
            ->searchableTenantMenu()
            ->tenantMiddleware([
                LogAdminStableAccess::class,
            ], isPersistent: true)
            ->brandName(fn () => SiteSetting::siteName())
            ->brandLogo(fn () => SiteSetting::appLogoUrl())
            ->favicon(fn () => SiteSetting::faviconUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Stable/Resources'), for: 'App\\Filament\\Stable\\Resources')
            ->discoverPages(in: app_path('Filament/Stable/Pages'), for: 'App\\Filament\\Stable\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Stable/Widgets'), for: 'App\\Filament\\Stable\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
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
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.stable.admin-banner')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.public.language-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => '<div class="flex justify-end">'.view('filament.public.language-switcher')->render().'</div>',
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
