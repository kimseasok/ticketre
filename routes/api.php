<?php

use App\Http\Controllers\Api\HealthcheckController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [HealthcheckController::class, 'show'])->name('api.health');
