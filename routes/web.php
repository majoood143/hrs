<?php

use App\Http\Controllers\Site\AccountAuthController;
use App\Http\Controllers\Site\AccountOrderController;
use App\Http\Controllers\Site\AdClickController;
use App\Http\Controllers\Site\CenterController;
use App\Http\Controllers\Site\ClinicController;
use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\FarrierController;
use App\Http\Controllers\Site\HorseForSaleController;
use App\Http\Controllers\Site\OrderController;
use App\Http\Controllers\Site\OrderDocumentController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PaymentController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\RacingCalendarController;
use App\Http\Controllers\Site\RacingController;
use App\Http\Controllers\Site\RacingHandicapController;
use App\Http\Controllers\Site\RacingPdfController;
use App\Http\Controllers\Site\RacingRaceController;
use App\Http\Controllers\Site\RacingWidgetController;
use App\Http\Controllers\Site\ShopController;
use App\Http\Controllers\Site\StableController;
use App\Http\Controllers\Site\ToolSaleController;
use App\Http\Controllers\Site\TransferBoardController;
use App\Http\Controllers\Site\VideoLibraryController;
use App\Models\CmsRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [PostController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [PostController::class, 'tag'])->name('blog.tag');
Route::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');

Route::get('/transfer-board', [TransferBoardController::class, 'index'])->name('transfer-board.index');
Route::get('/transfer-board/post', [TransferBoardController::class, 'create'])->name('transfer-board.create');
Route::post('/transfer-board/post', [TransferBoardController::class, 'store'])->middleware('throttle:10,1')->name('transfer-board.store');
Route::get('/transfer-board/captcha', [TransferBoardController::class, 'captcha'])->name('transfer-board.captcha');
Route::get('/transfer-board/{transferPost}', [TransferBoardController::class, 'show'])->name('transfer-board.show');

Route::get('/horses-for-sale', [HorseForSaleController::class, 'index'])->name('horses-for-sale.index');
Route::get('/horses-for-sale/post', [HorseForSaleController::class, 'create'])->name('horses-for-sale.create');
Route::post('/horses-for-sale/post', [HorseForSaleController::class, 'store'])->middleware('throttle:10,1')->name('horses-for-sale.store');
Route::get('/horses-for-sale/captcha', [HorseForSaleController::class, 'captcha'])->name('horses-for-sale.captcha');
Route::get('/horses-for-sale/{horseSalePost}', [HorseForSaleController::class, 'show'])->name('horses-for-sale.show');

Route::get('/stables', [StableController::class, 'index'])->name('stables.index');
Route::get('/stables/{slug}', [StableController::class, 'show'])->name('stables.show');

Route::get('/clinics', [ClinicController::class, 'index'])->name('clinics.index');
Route::get('/clinics/{slug}', [ClinicController::class, 'show'])->name('clinics.show');

Route::get('/centers', [CenterController::class, 'index'])->name('centers.index');
Route::get('/centers/{slug}', [CenterController::class, 'show'])->name('centers.show');

Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
Route::get('/shops/{slug}', [ShopController::class, 'show'])->name('shops.show');

Route::get('/farriers', [FarrierController::class, 'index'])->name('farriers.index');
Route::get('/farriers/post', [FarrierController::class, 'create'])->name('farriers.create');
Route::post('/farriers/post', [FarrierController::class, 'store'])->middleware('throttle:10,1')->name('farriers.store');
Route::get('/farriers/captcha', [FarrierController::class, 'captcha'])->name('farriers.captcha');
Route::get('/farriers/{farrier}', [FarrierController::class, 'show'])->name('farriers.show');

Route::get('/tools-for-sale', [ToolSaleController::class, 'index'])->name('tools-for-sale.index');
Route::get('/tools-for-sale/post', [ToolSaleController::class, 'create'])->name('tools-for-sale.create');
Route::post('/tools-for-sale/post', [ToolSaleController::class, 'store'])->middleware('throttle:10,1')->name('tools-for-sale.store');
Route::get('/tools-for-sale/captcha', [ToolSaleController::class, 'captcha'])->name('tools-for-sale.captcha');
Route::get('/tools-for-sale/{toolSalePost}', [ToolSaleController::class, 'show'])->name('tools-for-sale.show');

Route::get('/events', [EventController::class, 'index'])->name('events.index');

Route::get('/video-library', [VideoLibraryController::class, 'index'])->name('video-library.index');
Route::get('/video-library/watch/{slug}', [VideoLibraryController::class, 'show'])->name('video-library.show');
Route::get('/video-library/{slug}', [VideoLibraryController::class, 'folder'])->name('video-library.folder');

Route::get('/racing/search', [RacingController::class, 'search'])->name('racing.search');
Route::get('/racing/image', [RacingController::class, 'image'])->name('racing.image');
Route::get('/racing/calendar', [RacingCalendarController::class, 'index'])->name('racing.calendar');
Route::get('/racing/widget', RacingWidgetController::class)->middleware('throttle:120,1')->name('racing.widget');
Route::get('/racing/calendar/{date}', [RacingCalendarController::class, 'day'])->where('date', '\d{4}-\d{2}-\d{2}')->name('racing.calendar.day');
Route::get('/racing/handicap-ratings', [RacingHandicapController::class, 'index'])->name('racing.handicap');
Route::get('/racing/{entity}/{id}/pdf', [RacingPdfController::class, 'profile'])
    ->whereIn('entity', ['horse', 'owner', 'jockey', 'trainer'])
    ->whereNumber('id')
    ->middleware('throttle:30,1')
    ->name('racing.profile.pdf');
Route::get('/racing/{page}/{race}/pdf', [RacingPdfController::class, 'race'])
    ->whereIn('page', ['results', 'entries', 'card', 'form-guide'])
    ->whereNumber('race')
    ->middleware('throttle:30,1')
    ->name('racing.meeting.pdf');
Route::get('/racing/race/{race}', [RacingRaceController::class, 'race'])->whereNumber('race')->name('racing.race');
Route::get('/racing/{page}/{race?}', [RacingRaceController::class, 'show'])
    ->whereIn('page', ['results', 'entries', 'card', 'form-guide'])
    ->whereNumber('race')
    ->name('racing.meeting');
Route::get('/racing/{entity}/{id}', [RacingController::class, 'profile'])
    ->whereIn('entity', ['horse', 'owner', 'jockey', 'trainer'])
    ->whereNumber('id')
    ->name('racing.profile');

// Paying for a service order. Every path here has at least two segments, so none can be
// shadowed by the /{slug} CMS page route below.
Route::get('/pay/{order}', [PaymentController::class, 'show'])->middleware('throttle:60,1')->name('payment.start');
Route::post('/pay/{order}', [PaymentController::class, 'begin'])->middleware('throttle:20,1')->name('payment.begin');

Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('thawani/return', [PaymentController::class, 'thawaniReturn'])->name('thawani.return');
    Route::get('thawani/cancel', [PaymentController::class, 'thawaniCancel'])->name('thawani.cancel');
    // Server-to-server callbacks below are excluded from CSRF in bootstrap/app.php.
    Route::post('thawani/webhook', [PaymentController::class, 'thawaniWebhook'])->name('thawani.webhook');
    Route::post('nbo/callback', [PaymentController::class, 'nboCallback'])->name('nbo.callback');
    Route::post('ccavenue/callback', [PaymentController::class, 'ccavenueCallback'])->name('ccavenue.callback');
    Route::get('demo/{order}', [PaymentController::class, 'demo'])->name('demo');
    Route::post('demo/{order}', [PaymentController::class, 'demoDecide'])->name('demo.decide');
});

// Customers sign in with their phone and an SMS code. There is deliberately no single-segment
// /account route: the /{slug} CMS page route below would compete with it, so every path has two segments.
Route::prefix('account')->name('account.')->group(function () {
    Route::get('login', [AccountAuthController::class, 'login'])->name('login');
    Route::post('login', [AccountAuthController::class, 'send'])->middleware('throttle:10,1')->name('send');
    Route::get('verify', [AccountAuthController::class, 'verifyForm'])->name('verify');
    Route::post('verify', [AccountAuthController::class, 'verify'])->middleware('throttle:10,1')->name('verify.check');
    Route::post('verify/resend', [AccountAuthController::class, 'resend'])->middleware('throttle:10,1')->name('resend');

    Route::middleware('auth:customer')->group(function () {
        Route::post('logout', [AccountAuthController::class, 'logout'])->name('logout');
        Route::get('orders', [AccountOrderController::class, 'index'])->name('orders');
        Route::get('orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/receipt', [AccountOrderController::class, 'receipt'])->middleware('throttle:30,1')->name('orders.receipt');
        Route::get('orders/{order}/documents/{document}', [OrderDocumentController::class, 'customer'])->middleware('throttle:30,1')->whereNumber('document')->name('orders.document');
    });
});

// An admin's link to a result document (signed, and only for people who may see orders).
Route::get('/order-documents/{document}', [OrderDocumentController::class, 'admin'])->whereNumber('document')->middleware('signed')->name('orders.documents.admin');

Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('throttle:60,1')->name('orders.show');

Route::get('/promo/{ad}/go', [AdClickController::class, 'redirect'])->name('promo.click');

// `[^/]+` (not `.*`) keeps this to a single path segment: `.*` also matches `/`, so it was
// swallowing every two-or-more-segment path (e.g. /docs/api) that isn't defined above it in
// this file, before packages that register their own routes later (in a booted() callback,
// like dedoc/scramble's /docs/api) ever got a chance to match.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|transportation|blog|transfer-board|horses-for-sale|stables|clinics|centers|shops|farriers|tools-for-sale|events|video-library|racing)[^/]+$')
    ->name('page.show');

Route::fallback(function () {
    $path = ltrim(request()->path(), '/');

    $redirect = CmsRedirect::query()->where('from_path', $path)->first();

    if ($redirect) {
        $redirect->increment('hits');

        return redirect($redirect->to_path, $redirect->status_code);
    }

    abort(404);
});
