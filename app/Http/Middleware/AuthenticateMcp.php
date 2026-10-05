<?php

namespace App\Http\Middleware;

use App\Http\Controllers\OAuthDiscoveryController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthenticateMcp
{
    public function handle(Request $request, Closure $next)
    {
        // Prevent DNS-rebinding/browser-origin attacks. Server-to-server clients send no Origin.
        if ($request->header('Origin') && $request->header('Origin') !== OAuthDiscoveryController::issuer()) {
            return response()->json(['error' => 'origin_not_allowed'], 403);
        }
        $publicMethods = ['initialize', 'ping', 'tools/list', 'notifications/initialized'];
        if (! $request->bearerToken() && in_array($request->input('method'), $publicMethods, true)) {
            return $next($request);
        }
        $user = Auth::guard('api')->user();
        $token = $user?->currentAccessToken();
        if (! $user?->is_admin || ! $token || ! DB::table('mcp_token_resources')
            ->where('token_id', $token->id)->where('resource', config('integrations.resource'))->exists()) {
            return response()->json(['error' => 'invalid_token'], 401)->header('WWW-Authenticate',
                'Bearer resource_metadata="'.OAuthDiscoveryController::issuer().'/.well-known/oauth-protected-resource/mcp"');
        }
        Auth::shouldUse('api');
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
