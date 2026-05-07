<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GB Pack API Routes
|--------------------------------------------------------------------------
|
| UK module routes mounted by GbPackServiceProvider::boot() under the same
| /api prefix and api middleware group as routes/api.php. Per the R-9
| URL-strategy decision, GB routes are mounted WITHOUT a /api/gb/ prefix
| so URL paths stay identical and feature tests keep passing. The Option X
| prefix + redirect layer ships in R-14.
|
*/

use Fynla\Packs\Gb\Http\Controllers\SavingsController;

// Savings module routes
Route::middleware('auth:sanctum')->prefix('savings')->group(function () {
    // Main savings data and analysis
    Route::get('/', [SavingsController::class, 'index']);
    Route::post('/analyze', [SavingsController::class, 'analyze']);
    Route::get('/recommendations', [SavingsController::class, 'recommendations']);
    Route::post('/scenarios', [SavingsController::class, 'scenarios']);

    // ISA allowance tracking
    Route::get('/isa-allowance/{taxYear}', [SavingsController::class, 'isaAllowance'])->where('taxYear', '.*');

    // Savings accounts
    Route::prefix('accounts')->group(function () {
        Route::post('/', [SavingsController::class, 'storeAccount']);
        Route::get('/{id}', [SavingsController::class, 'showAccount']);
        Route::put('/{id}', [SavingsController::class, 'updateAccount']);
        Route::delete('/{id}', [SavingsController::class, 'destroyAccount']);
        Route::patch('/{id}/toggle-retirement', [SavingsController::class, 'toggleRetirementInclusion']);
    });

    // Legacy savings goals - DEPRECATED since v0.7.0
    // Goals are now managed via unified Goals module: /api/goals?module=savings
    // See GoalsController for the unified API. Legacy routes removed in v0.8.1.
});
