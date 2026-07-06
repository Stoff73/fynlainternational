<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Serve Vue.js SPA for all routes (catch-all). Excludes api/* so unknown
// API endpoints 404 as JSON instead of silently returning the SPA shell
// with a 200 — that masked stale frontend calls during the pack relocation.
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api(?:/|$)).*');
