<?php

namespace App\Http\Middleware;

use App\Http\Controllers\OAuthDiscoveryController;
use App\Services\OAuthResourceBindings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

class BindOAuthResource
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->input('resource'), OAuthDiscoveryController::resources(), true)) {
            return response()->json(['error' => 'invalid_target'], 400);
        }
        if (! in_array($request->input('grant_type'), ['authorization_code', 'refresh_token'], true)) {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
        }

        $kind = $request->input('grant_type') === 'refresh_token' ? 'refresh' : 'code';
        $credential = $request->input($kind === 'refresh' ? 'refresh_token' : 'code');
        if (! is_string($credential) || $credential === '') {
            return response()->json(['error' => 'invalid_request'], 400);
        }
        $resource = OAuthResourceBindings::resource($kind, $credential);
        // Old ChatGPT grants predate these bindings: retain them only on the original endpoint.
        // New Claude grants must always be bound and cannot switch audiences on refresh.
        if (($resource !== null && $resource !== $request->input('resource')) ||
            ($resource === null && $request->input('resource') !== config('integrations.resource'))) {
            return response()->json(['error' => $kind === 'refresh' ? 'invalid_grant' : 'invalid_target'], 400);
        }

        return DB::transaction(function () use ($request, $next) {
            $response = $next($request);
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
                // Decode only our freshly issued, signed token to persist its resource binding.
                // Passport independently verifies the signature, expiry and revocation on every use.
                $payload = explode('.', $data['access_token'])[1];
                $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
                DB::table('mcp_token_resources')->insert([
                    'token_id' => $claims['jti'], 'resource' => $request->input('resource'), 'created_at' => now(),
                ]);
                if (isset($data['refresh_token'])) {
                    OAuthResourceBindings::remember('refresh', $data['refresh_token'], $request->input('resource'), now()->add(Passport::refreshTokensExpireIn()));
                }
            }

            return $response;
        });
    }
}
