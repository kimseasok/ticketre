<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\HealthcheckController;
use App\Http\Controllers\Api\KbArticleController;
use App\Http\Controllers\Api\KbCategoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [HealthcheckController::class, 'show'])->name('api.health');

Route::middleware(['auth', 'tenant'])->prefix('v1')->name('api.')->group(function () {
    Route::scopeBindings()->group(function () {
        Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('tickets/{ticket}/events', [TicketController::class, 'events'])->name('tickets.events.index');
        Route::post('tickets/{ticket}/events', [TicketController::class, 'storeEvent'])->name('tickets.events.store');

        Route::apiResource('tickets.messages', MessageController::class)->parameters([
            'tickets' => 'ticket',
            'messages' => 'message',
        ]);
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::apiResource('kb-categories', KbCategoryController::class)->except(['create', 'edit']);
    Route::apiResource('kb-articles', KbArticleController::class)->except(['create', 'edit']);
    Route::apiResource('roles', RoleController::class)->except(['create', 'edit']);
});
