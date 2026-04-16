<?php

declare(strict_types=1);

use Fynla\Packs\XXSmoke\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('api/xx/health', HealthController::class);
