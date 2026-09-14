<?php

use App\Http\Controllers\Site\CenterController;
use App\Http\Controllers\Site\ClinicController;
use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\FarrierController;
use App\Http\Controllers\Site\HorseForSaleController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\ShopController;
use App\Http\Controllers\Site\StableController;
use App\Http\Controllers\Site\ToolSaleController;
use App\Http\Controllers\Site\TransferBoardController;
use App\Models\CmsRedirect;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [PostController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [PostController::class, 'tag'])->name('blog.tag');
Route::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');

Route::get('/transfer-board', [TransferBoardController::class, 'index'])->name('transfer-board.index');
Route::get('/transfer-board/post', [TransferBoardController::class, 'create'])->name('transfer-board.create');
Route::post('/transfer-board/post', [TransferBoardController::class, 'store'])->name('transfer-board.store');
Route::get('/transfer-board/captcha', [TransferBoardController::class, 'captcha'])->name('transfer-board.captcha');
Route::get('/transfer-board/{transferPost}', [TransferBoardController::class, 'show'])->name('transfer-board.show');

Route::get('/horses-for-sale', [HorseForSaleController::class, 'index'])->name('horses-for-sale.index');
Route::get('/horses-for-sale/post', [HorseForSaleController::class, 'create'])->name('horses-for-sale.create');
Route::post('/horses-for-sale/post', [HorseForSaleController::class, 'store'])->name('horses-for-sale.store');
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
Route::post('/farriers/post', [FarrierController::class, 'store'])->name('farriers.store');
Route::get('/farriers/captcha', [FarrierController::class, 'captcha'])->name('farriers.captcha');
Route::get('/farriers/{farrier}', [FarrierController::class, 'show'])->name('farriers.show');

Route::get('/tools-for-sale', [ToolSaleController::class, 'index'])->name('tools-for-sale.index');
Route::get('/tools-for-sale/post', [ToolSaleController::class, 'create'])->name('tools-for-sale.create');
Route::post('/tools-for-sale/post', [ToolSaleController::class, 'store'])->name('tools-for-sale.store');
Route::get('/tools-for-sale/captcha', [ToolSaleController::class, 'captcha'])->name('tools-for-sale.captcha');
Route::get('/tools-for-sale/{toolSalePost}', [ToolSaleController::class, 'show'])->name('tools-for-sale.show');

Route::get('/events', [EventController::class, 'index'])->name('events.index');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|transportation|blog|transfer-board|horses-for-sale|stables|clinics|centers|shops|farriers|tools-for-sale|events).*$')
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
