<?php

namespace App\Http\Middleware;

use App\Http\Controllers\OAuthDiscoveryController;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class OAuthAuthorization
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET')) {
            if ($request->input('resource') !== config('integrations.resource') ||
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
        $location = $response->headers->get('Location');
        if ($location) {
            parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $query);
            if (isset($query['code']) || isset($query['error'])) {
                $separator = str_contains($location, '?') ? '&' : '?';
                $response->headers->set('Location', $location.$separator.'iss='.rawurlencode(OAuthDiscoveryController::issuer()));
            }
        }
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
