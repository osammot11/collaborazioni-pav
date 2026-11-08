<?php

namespace App\Http\Middleware;

use App\Http\Controllers\OAuthDiscoveryController;
use App\Services\OAuthResourceBindings;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class OAuthAuthorization
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            if (! in_array($request->input('resource'), OAuthDiscoveryController::resources(), true) ||
                $request->input('code_challenge_method') !== 'S256' ||
                ! is_string($request->input('code_challenge')) ||
                ! preg_match('/^[A-Za-z0-9_-]{43}$/D', $request->input('code_challenge')) ||
                ! is_string($request->input('state')) || $request->input('state') === '') {
                return response()->json(['error' => 'invalid_request', 'error_description' => 'Resource, state e PKCE S256 sono obbligatori.'], 400);
            }
            // Always show the permissions to the administrator, even on relinking.
            $request->query->set('prompt', 'consent');
        }
        try {
            $response = $next($request);
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
        }
        if ($request->isMethod('GET') && $response instanceof Response
            && $response->original instanceof View) {
            $authToken = $response->original->getData()['authToken'] ?? null;
            if (is_string($authToken)) {
                $request->session()->put('mcp_oauth_resource', [
                    'auth_token_hash' => hash('sha256', $authToken), 'resource' => $request->input('resource'),
                ]);
            }
        }
        $location = $response->headers->get('Location');
        if ($location) {
            parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $query);
            if (isset($query['code']) && $request->isMethod('POST')) {
                $binding = $request->session()->pull('mcp_oauth_resource');
                if (is_array($binding) && hash_equals($binding['auth_token_hash'], hash('sha256', (string) $request->input('auth_token')))) {
                    OAuthResourceBindings::remember('code', $query['code'], $binding['resource'], now()->addMinutes(10));
                }
            }
            if (isset($query['code']) || isset($query['error'])) {
                $separator = str_contains($location, '?') ? '&' : '?';
                $response->headers->set('Location', $location.$separator.'iss='.rawurlencode(OAuthDiscoveryController::issuer()));
            }
        }
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
