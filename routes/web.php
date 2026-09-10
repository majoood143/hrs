<?php

<<<<<<< HEAD
<<<<<<< HEAD
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
=======
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorseQRController;
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
=======
use App\Http\Controllers\Site\ClinicController;
use App\Http\Controllers\Site\FarrierController;
use App\Http\Controllers\Site\HorseForSaleController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\StableController;
use App\Http\Controllers\Site\ToolSaleController;
use App\Http\Controllers\Site\TransferBoardController;
use App\Models\CmsRedirect;
use Illuminate\Support\Facades\Route;
>>>>>>> bbd33618 (Add transfer board creation and listing views)

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [PostController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [PostController::class, 'tag'])->name('blog.tag');
Route::get('/blog/{slug}', [PostController::class, 'show'])->name('blog.show');

Route::get('/transfer-board', [TransferBoardController::class, 'index'])->name('transfer-board.index');
Route::get('/transfer-board/post', [TransferBoardController::class, 'create'])->name('transfer-board.create');
Route::post('/transfer-board/post', [TransferBoardController::class, 'store'])->name('transfer-board.store');
Route::get('/transfer-board/captcha', [TransferBoardController::class, 'captcha'])->name('transfer-board.captcha');

Route::get('/horses-for-sale', [HorseForSaleController::class, 'index'])->name('horses-for-sale.index');
Route::get('/horses-for-sale/post', [HorseForSaleController::class, 'create'])->name('horses-for-sale.create');
Route::post('/horses-for-sale/post', [HorseForSaleController::class, 'store'])->name('horses-for-sale.store');
Route::get('/horses-for-sale/captcha', [HorseForSaleController::class, 'captcha'])->name('horses-for-sale.captcha');

Route::get('/stables', [StableController::class, 'index'])->name('stables.index');
Route::get('/stables/{slug}', [StableController::class, 'show'])->name('stables.show');

Route::get('/clinics', [ClinicController::class, 'index'])->name('clinics.index');
Route::get('/clinics/{slug}', [ClinicController::class, 'show'])->name('clinics.show');

Route::get('/farriers', [FarrierController::class, 'index'])->name('farriers.index');
Route::get('/farriers/post', [FarrierController::class, 'create'])->name('farriers.create');
Route::post('/farriers/post', [FarrierController::class, 'store'])->name('farriers.store');
Route::get('/farriers/captcha', [FarrierController::class, 'captcha'])->name('farriers.captcha');

Route::get('/tools-for-sale', [ToolSaleController::class, 'index'])->name('tools-for-sale.index');
Route::get('/tools-for-sale/post', [ToolSaleController::class, 'create'])->name('tools-for-sale.create');
Route::post('/tools-for-sale/post', [ToolSaleController::class, 'store'])->name('tools-for-sale.store');
Route::get('/tools-for-sale/captcha', [ToolSaleController::class, 'captcha'])->name('tools-for-sale.captcha');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|transportation|blog|transfer-board|horses-for-sale|stables|clinics|farriers|tools-for-sale).*$')
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
<<<<<<< HEAD

<<<<<<< HEAD
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/admin/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth:admin', 'verified'])->name('admin.dashboard');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
=======
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
=======
>>>>>>> bbd33618 (Add transfer board creation and listing views)
