<?php

use App\Http\Controllers\Portal\TicketController as PortalTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::middleware(['tenant', 'ability:portal.submit,allow-guest'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('tickets/create', [PortalTicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [PortalTicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/confirmation/{submission}', [PortalTicketController::class, 'confirmation'])
        ->name('tickets.confirmation');
});
