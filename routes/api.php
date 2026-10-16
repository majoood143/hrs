<?php

use App\Http\Controllers\Api\V1\AccountBookingController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AccountPackageController;
use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AppStatusController;
use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CenterController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FarrierController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\HorseForSaleController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\Owner\OwnerAuthController;
use App\Http\Controllers\Api\V1\Owner\OwnerBookingController;
use App\Http\Controllers\Api\V1\Owner\OwnerScheduleController;
use App\Http\Controllers\Api\V1\Owner\OwnerStableController;
use App\Http\Controllers\Api\V1\RacingController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SilksController;
use App\Http\Controllers\Api\V1\StableController;
use App\Http\Controllers\Api\V1\ToolForSaleController;
use App\Http\Controllers\Api\V1\TransferBoardController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\VideoLibraryController;
use App\Http\Controllers\Api\V1\ViewController;
use App\Http\Middleware\EnsureMobileFeature;
use App\Http\Middleware\EnsureStableOwner;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// The mobile app's API (public reads; posting with a verified phone; bookings with a signed-in customer). Never the admin API (/api/{panel}/*).
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([SetApiLocale::class, 'throttle:api-public'])
    ->group(function () {
        Route::get('app-config', AppConfigController::class)->name('app-config');
        // Maintenance, updates, feature switches and popups (admin → Mobile app), per platform/version.
        Route::get('app-status', AppStatusController::class)->name('app-status');
        Route::get('lookups', LookupController::class)->name('lookups');
        Route::get('home', HomeController::class)->name('home');

        Route::get('horses-for-sale', [HorseForSaleController::class, 'index'])->name('horses-for-sale.index');
        Route::get('horses-for-sale/{id}', [HorseForSaleController::class, 'show'])->whereNumber('id')->name('horses-for-sale.show');

        Route::get('tools-for-sale', [ToolForSaleController::class, 'index'])->name('tools-for-sale.index');
        Route::get('tools-for-sale/{id}', [ToolForSaleController::class, 'show'])->whereNumber('id')->name('tools-for-sale.show');

        Route::get('farriers', [FarrierController::class, 'index'])->name('farriers.index');
        Route::get('farriers/{id}', [FarrierController::class, 'show'])->whereNumber('id')->name('farriers.show');

        Route::get('transfer-board', [TransferBoardController::class, 'index'])->name('transfer-board.index');
        Route::get('transfer-board/{id}', [TransferBoardController::class, 'show'])->whereNumber('id')->name('transfer-board.show');

        foreach (['stables' => StableController::class, 'clinics' => ClinicController::class, 'centers' => CenterController::class, 'shops' => ShopController::class] as $section => $controller) {
            Route::get($section, [$controller, 'index'])->name("{$section}.index");
            Route::get("{$section}/{slug}", [$controller, 'show'])->name("{$section}.show");
        }

        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::get('events/categories', [EventController::class, 'categories'])->name('events.categories');

        Route::get('blog/posts', [BlogController::class, 'index'])->name('blog.posts.index');
        Route::get('blog/posts/{slug}', [BlogController::class, 'show'])->name('blog.posts.show');
        Route::get('blog/categories', [BlogController::class, 'categories'])->name('blog.categories');

        Route::get('videos/folders', [VideoLibraryController::class, 'folders'])->name('videos.folders');
        Route::get('videos/folders/{slug}', [VideoLibraryController::class, 'folder'])->name('videos.folder');
        Route::get('videos/{slug}', [VideoLibraryController::class, 'show'])->name('videos.show');

        Route::get('silks', SilksController::class)->name('silks');

        // The racing database (scraped and cached, like the website's /racing pages).
        Route::prefix('racing')->name('racing.')->controller(RacingController::class)->group(function () {
            // every search is a request to the source with a visitor-typed name: a tighter limit
            Route::get('search', 'search')->middleware('throttle:api-racing-search')->name('search');
            Route::get('calendar', 'calendar')->name('calendar');
            Route::get('days/{date}', 'day')->where('date', '\d{4}-\d{2}-\d{2}')->name('day');
            Route::get('handicap', 'handicap')->name('handicap');
            Route::get('races/{page}/{race?}', 'meeting')->whereIn('page', ['results', 'entries', 'card', 'form-guide'])->whereNumber('race')->name('meeting');
            Route::get('{entity}/{id}', 'profile')->whereIn('entity', ['horse', 'owner', 'jockey', 'trainer'])->whereNumber('id')->name('profile');
        });

        // Posting from the app: a verified phone (code by WhatsApp, else SMS) instead of the site's captcha.
        // Each refused while its feature is off, the app is in maintenance or below the minimum version.
        $anyPosting = EnsureMobileFeature::class.':post_horse,post_tool,post_farrier,post_transfer';
        Route::post('verification/request', [VerificationController::class, 'request'])->middleware([$anyPosting, 'throttle:api-codes'])->name('verification.request');
        Route::post('verification/verify', [VerificationController::class, 'verify'])->middleware([$anyPosting, 'throttle:api-codes'])->name('verification.verify');
        Route::post('horses-for-sale', [HorseForSaleController::class, 'store'])->middleware([EnsureMobileFeature::class.':post_horse', 'throttle:api-posting'])->name('horses-for-sale.store');
        Route::post('tools-for-sale', [ToolForSaleController::class, 'store'])->middleware([EnsureMobileFeature::class.':post_tool', 'throttle:api-posting'])->name('tools-for-sale.store');
        Route::post('farriers', [FarrierController::class, 'store'])->middleware([EnsureMobileFeature::class.':post_farrier', 'throttle:api-posting'])->name('farriers.store');
        Route::post('transfer-board', [TransferBoardController::class, 'store'])->middleware([EnsureMobileFeature::class.':post_transfer', 'throttle:api-posting'])->name('transfer-board.store');

        // Stable bookings: the website's booking engine. Reading is open; booking, paying for a package,
        // cancelling and reviewing need a signed-in customer and the `bookings` feature.
        Route::prefix('bookings')->name('bookings.')->controller(BookingController::class)->group(function () {
            Route::get('search', 'search')->name('search');
            Route::get('offerings/{id}', 'offering')->whereNumber('id')->name('offering');
            Route::get('slots/{id}', 'slot')->whereNumber('id')->name('slot');
        });

        // Customer sign-in (phone + code, like the website's /account/login): a bearer token for `customer_api`.
        Route::middleware('throttle:api-codes')->group(function () {
            Route::post('auth/request', [CustomerAuthController::class, 'request'])->name('auth.request');
            Route::post('auth/verify', [CustomerAuthController::class, 'verify'])->name('auth.verify');
        });

        Route::middleware('auth:customer_api')->group(function () {
            Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('auth.logout');
            Route::get('account', [AccountController::class, 'show'])->name('account.show');
            Route::post('account', [AccountController::class, 'update'])->middleware('throttle:api-account')->name('account.update');
            Route::delete('account', [AccountController::class, 'destroy'])->middleware('throttle:api-account')->name('account.destroy');
            Route::get('account/bookings', [AccountBookingController::class, 'index'])->name('account.bookings.index');
            Route::get('account/bookings/{reference}', [AccountBookingController::class, 'show'])->name('account.bookings.show');
            Route::get('account/packages', [AccountPackageController::class, 'index'])->name('account.packages.index');

            Route::middleware([EnsureMobileFeature::class.':bookings', 'throttle:api-account'])->group(function () {
                Route::post('bookings/slots/{id}', [BookingController::class, 'store'])->whereNumber('id')->name('bookings.store');
                Route::post('account/bookings/{reference}/cancel', [AccountBookingController::class, 'cancel'])->name('account.bookings.cancel');
                Route::post('account/bookings/{reference}/review', [AccountBookingController::class, 'review'])->name('account.bookings.review');
                Route::post('packages/{id}/buy', [AccountPackageController::class, 'buy'])->whereNumber('id')->name('packages.buy');
            });
        });

        // Stable owners (the website's /stable panel, day to day): phone + code or email + password, then a
        // token with the `stable_owner` ability, good for these endpoints only (AppServiceProvider).
        Route::prefix('owner')->name('owner.')->group(function () {
            Route::middleware([EnsureMobileFeature::class.':stable_owner', 'throttle:api-codes'])->group(function () {
                Route::post('auth/request', [CustomerAuthController::class, 'request'])->name('auth.request');
                Route::post('auth/verify', [OwnerAuthController::class, 'verify'])->name('auth.verify');
                Route::post('auth/login', [OwnerAuthController::class, 'login'])->name('auth.login');
            });

            Route::middleware(['auth:owner_api', EnsureStableOwner::class])->group(function () {
                Route::post('auth/logout', [OwnerAuthController::class, 'logout'])->name('auth.logout');
                Route::get('me', [OwnerAuthController::class, 'me'])->name('me');

                Route::prefix('stables/{slug}')->name('stables.')->group(function () {
                    Route::get('/', [OwnerStableController::class, 'show'])->name('show');
                    Route::get('income', [OwnerStableController::class, 'income'])->name('income');
                    Route::get('bookings', [OwnerBookingController::class, 'index'])->name('bookings.index');
                    Route::get('bookings/{reference}', [OwnerBookingController::class, 'show'])->name('bookings.show');
                    Route::get('slots', [OwnerScheduleController::class, 'slots'])->name('slots.index');
                    Route::get('closures', [OwnerScheduleController::class, 'closures'])->name('closures.index');

                    Route::middleware([EnsureMobileFeature::class.':stable_owner', 'throttle:api-owner'])->group(function () {
                        Route::post('bookings/{reference}/attended', [OwnerBookingController::class, 'attended'])->name('bookings.attended');
                        Route::post('bookings/{reference}/no-show', [OwnerBookingController::class, 'noShow'])->name('bookings.no-show');
                        Route::post('bookings/{reference}/cancel', [OwnerBookingController::class, 'cancel'])->name('bookings.cancel');
                        Route::post('slots/{id}/close', [OwnerScheduleController::class, 'close'])->whereNumber('id')->name('slots.close');
                        Route::post('slots/{id}/open', [OwnerScheduleController::class, 'open'])->whereNumber('id')->name('slots.open');
                        Route::post('closures', [OwnerScheduleController::class, 'storeClosure'])->name('closures.store');
                        Route::delete('closures/{id}', [OwnerScheduleController::class, 'destroyClosure'])->whereNumber('id')->name('closures.destroy');
                    });
                });
            });
        });

        Route::post('views/{section}/{key}', [ViewController::class, 'store'])->middleware('throttle:api-views')->name('views.store');

        // An unknown API path is a JSON 404 here, never the website's fallback (CMS redirects, HTML 404).
        Route::any('{path}', fn () => abort(404))->where('path', '.*')->name('not-found');
    });
