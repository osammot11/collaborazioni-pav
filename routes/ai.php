<?php

use App\Http\Controllers\OAuthDiscoveryController;
use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\BindOAuthResource;
use App\Mcp\PipelineServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use Laravel\Passport\Http\Controllers\AccessTokenController;

Route::get('/.well-known/oauth-protected-resource', [OAuthDiscoveryController::class, 'resource']);
Route::get('/.well-known/oauth-protected-resource/mcp', [OAuthDiscoveryController::class, 'resource']);
Route::get('/.well-known/oauth-authorization-server', [OAuthDiscoveryController::class, 'server']);
Route::post('/oauth/register', [OAuthDiscoveryController::class, 'register'])->middleware('throttle:10,1');
Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware(['throttle:30,1', BindOAuthResource::class])->name('passport.token');
Mcp::web('/mcp', PipelineServer::class)
    ->withoutMiddleware(AddWwwAuthenticateHeader::class)
    ->middleware(['throttle:120,1', AuthenticateMcp::class]);
