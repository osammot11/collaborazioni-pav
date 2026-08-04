<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\CollaborationController;
use Illuminate\Support\Facades\Route;

Route::get('/access', [AccessController::class, 'show'])->name('access.show');
Route::post('/access', [AccessController::class, 'authenticate'])->name('access.authenticate');

Route::middleware('access.code')->group(function (): void {
    Route::get('/', [CollaborationController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AccessController::class, 'logout'])->name('logout');

    Route::get('/collaborazioni/nuova', [CollaborationController::class, 'create'])->name('collaborations.create');
    Route::post('/collaborazioni', [CollaborationController::class, 'store'])->name('collaborations.store');
    Route::get('/collaborazioni/{collaboration}/modifica', [CollaborationController::class, 'edit'])->name('collaborations.edit');
    Route::put('/collaborazioni/{collaboration}', [CollaborationController::class, 'update'])->name('collaborations.update');
    Route::delete('/collaborazioni/{collaboration}', [CollaborationController::class, 'destroy'])->name('collaborations.destroy');
});
