<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AreaSearchController;
use App\Http\Controllers\GeographyAssetController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'map')->name('map');
Route::get('/area-search', AreaSearchController::class)->name('areas.search');
Route::get('/areas/{area:geometry_key}', AreaController::class)->name('areas.show');
Route::get('/geography/{filename}', GeographyAssetController::class)->name('geography.show');
