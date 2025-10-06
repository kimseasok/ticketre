<?php

use App\Http\Controllers\Api\HealthcheckController;
use App\Http\Controllers\Api\TicketMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [HealthcheckController::class, 'show'])->name('api.health');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/tickets/{ticket}/messages', [TicketMessageController::class, 'index'])
        ->name('api.tickets.messages.index');
    Route::post('/v1/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])
        ->name('api.tickets.messages.store');
});
