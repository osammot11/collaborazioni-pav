<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;

class OAuthDiscoveryController extends Controller
{
    public static function issuer(): string
    {
        return rtrim(config('app.url'), '/');
    }

    public function resource()
    {
        return response()->json([
            'resource' => config('integrations.resource'),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => ['pipeline:read', 'pipeline:write', 'offline_access'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Produce a Value · Pipeline',
        ]);
    }

    public function server()
    {
        $base = self::issuer();

        return response()->json([
            'issuer' => $base,
            'authorization_endpoint' => $base.'/oauth/authorize',
            'token_endpoint' => $base.'/oauth/token',
            'registration_endpoint' => $base.'/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['pipeline:read', 'pipeline:write', 'offline_access'],
            'authorization_response_iss_parameter_supported' => true,
        ]);
    }

    public function register(Request $request, ClientRepository $clients)
    {
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:100'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:3'],
            'redirect_uris.*' => ['required', 'url:https', Rule::in(config('integrations.redirect_uris'))],
            'token_endpoint_auth_method' => ['sometimes', Rule::in(['none'])],
            'grant_types' => ['sometimes', 'array'],
            'grant_types.*' => [Rule::in(['authorization_code', 'refresh_token'])],
            'response_types' => ['sometimes', 'array'],
            'response_types.*' => [Rule::in(['code'])],
        ]);
        $client = $clients->createAuthorizationCodeGrantClient($data['client_name'], $data['redirect_uris'], confidential: false);

        return response()->json([
            'client_id' => $client->id,
            'client_name' => $client->name,
            'redirect_uris' => $data['redirect_uris'],
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'scope' => 'pipeline:read pipeline:write offline_access',
        ], 201)->header('Cache-Control', 'no-store');
    }
}
