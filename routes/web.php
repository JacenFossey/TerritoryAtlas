<?php

use App\Http\Controllers\AreaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'map')->name('map');
Route::get('/areas/{area:geometry_key}', AreaController::class)->name('areas.show');
