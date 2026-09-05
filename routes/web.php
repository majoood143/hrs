<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HorseQRController;

Route::get('/', function () {
    return view('welcome');
});

