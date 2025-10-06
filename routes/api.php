<?php

use App\Http\Controllers\Api\HealthcheckController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [HealthcheckController::class, 'show'])->name('api.health');

Route::middleware(['auth', 'tenant'])->prefix('v1')->name('api.')->group(function () {
    Route::scopeBindings()->group(function () {
        Route::apiResource('tickets.messages', MessageController::class)->parameters([
            'tickets' => 'ticket',
            'messages' => 'message',
        ]);
    });
});
