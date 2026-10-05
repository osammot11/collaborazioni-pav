<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BindOAuthResource
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->input('resource') !== config('integrations.resource')) {
            return response()->json(['error' => 'invalid_target'], 400);
        }
        if (! in_array($request->input('grant_type'), ['authorization_code', 'refresh_token'], true)) {
            return response()->json(['error' => 'unsupported_grant_type'], 400);
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
                    'token_id' => $claims['jti'], 'resource' => config('integrations.resource'), 'created_at' => now(),
                ]);
            }

            return $response;
        });
    }
}
