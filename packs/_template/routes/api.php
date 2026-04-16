<?php

declare(strict_types=1);

use Fynla\Packs\XX\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/xx')->group(function () {
    Route::get('/health', HealthController::class);
});
