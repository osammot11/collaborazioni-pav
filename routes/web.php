<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PipelineController;
use App\Http\Middleware\OAuthAuthorization;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

Route::get('/login', [AdminLoginController::class, 'show'])->name('login');
Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:10,1');
Route::middleware(['auth:web', RequireAdmin::class])->group(function (): void {
    Route::get('/integrazioni', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::delete('/integrazioni/{client}', [IntegrationController::class, 'revoke'])->name('integrations.revoke');
    Route::middleware(OAuthAuthorization::class)->group(function (): void {
        Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])->name('passport.authorizations.authorize');
        Route::post('/oauth/authorize', [ApproveAuthorizationController::class, 'approve'])->name('passport.authorizations.approve');
        Route::delete('/oauth/authorize', [DenyAuthorizationController::class, 'deny'])->name('passport.authorizations.deny');
    });
});

Route::get('/access', [AccessController::class, 'show'])->name('access.show');
Route::post('/access', [AccessController::class, 'authenticate'])->name('access.authenticate');

Route::middleware('access.code')->group(function (): void {
    Route::get('/', [CollaborationController::class, 'index'])->name('dashboard');
    Route::get('/pipeline', [PipelineController::class, 'index'])->name('pipeline');
    Route::patch('/pipeline/{collaboration}', [PipelineController::class, 'update'])->name('pipeline.update');
    Route::post('/logout', [AccessController::class, 'logout'])->name('logout');

    Route::get('/collaborazioni/nuova', [CollaborationController::class, 'create'])->name('collaborations.create');
    Route::post('/collaborazioni', [CollaborationController::class, 'store'])->name('collaborations.store');
    Route::get('/collaborazioni/{collaboration}/modifica', [CollaborationController::class, 'edit'])->name('collaborations.edit');
    Route::put('/collaborazioni/{collaboration}', [CollaborationController::class, 'update'])->name('collaborations.update');
    Route::delete('/collaborazioni/{collaboration}', [CollaborationController::class, 'destroy'])->name('collaborations.destroy');
});
