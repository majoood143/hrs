<?php

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AppStatusController;
use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\CenterController;
use App\Http\Controllers\Api\V1\ClinicController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FarrierController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\HorseForSaleController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\StableController;
use App\Http\Controllers\Api\V1\ToolForSaleController;
use App\Http\Controllers\Api\V1\TransferBoardController;
use App\Http\Controllers\Api\V1\VerificationController;
use App\Http\Controllers\Api\V1\VideoLibraryController;
use App\Http\Controllers\Api\V1\ViewController;
use App\Http\Middleware\EnsureMobileFeature;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// The mobile app's API (public, read-only in v1). Never the admin API (/api/{panel}/*).
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

        // Posting from the app: a verified phone (code by WhatsApp, else SMS) instead of the site's captcha.
        Route::middleware('throttle:10,1')->group(function () {
            // Each refused while its feature is off, the app is in maintenance or below the minimum version.
            $anyPosting = EnsureMobileFeature::class.':post_horse,post_tool,post_farrier,post_transfer';
            Route::post('verification/request', [VerificationController::class, 'request'])->middleware($anyPosting)->name('verification.request');
            Route::post('verification/verify', [VerificationController::class, 'verify'])->middleware($anyPosting)->name('verification.verify');
            Route::post('horses-for-sale', [HorseForSaleController::class, 'store'])->middleware(EnsureMobileFeature::class.':post_horse')->name('horses-for-sale.store');
            Route::post('tools-for-sale', [ToolForSaleController::class, 'store'])->middleware(EnsureMobileFeature::class.':post_tool')->name('tools-for-sale.store');
            Route::post('farriers', [FarrierController::class, 'store'])->middleware(EnsureMobileFeature::class.':post_farrier')->name('farriers.store');
            Route::post('transfer-board', [TransferBoardController::class, 'store'])->middleware(EnsureMobileFeature::class.':post_transfer')->name('transfer-board.store');
        });

        Route::post('views/{section}/{key}', [ViewController::class, 'store'])->middleware('throttle:30,1')->name('views.store');

        // An unknown API path is a JSON 404 here, never the website's fallback (CMS redirects, HTML 404).
        Route::any('{path}', fn () => abort(404))->where('path', '.*')->name('not-found');
    });
