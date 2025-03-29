<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Admin\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Admin\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\Auth\VerifyEmailController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HorseTypeController;
use App\Http\Controllers\Admin\HorseGenderController;
use App\Http\Controllers\Admin\HorseStatusController;
use App\Http\Controllers\Admin\HorsePollinationController;
use App\Http\Controllers\Admin\HorseColorController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\RegionController;
use Illuminate\Support\Facades\Route;

Route::group(["middleware" => "guest", "prefix" => "admin", "as" => "admin."], function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::group(["middleware" => "auth:admin", "prefix" => "admin", "as" => "admin."], function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');


    /** Horse Types Routs */
    Route::resource('horse-type', HorseTypeController::class);
    /** Horse Gender Routs */
    Route::resource('horse-gender', HorseGenderController::class);
    /** Horse Status Routs */
    Route::resource('horse-status', HorseStatusController::class);
    /** Horse Pollination Routs */
    Route::resource('horse-pollination', HorsePollinationController::class);
    /** Horse Color Routs */
    Route::resource('horse-color', HorseColorController::class);
    /**  Country Routs */
    Route::resource('country', CountryController::class);
    /**  Country Routs */
    Route::get('/{country}/region', RegionController::class,'index')->name('region');

});
