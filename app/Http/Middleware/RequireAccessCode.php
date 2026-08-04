<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccessCode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('collaborations_authorized', false)) {
            return redirect()->route('access.show');
        }

        return $next($request);
    }
}
