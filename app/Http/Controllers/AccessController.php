<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AccessController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('collaborations_authorized', false)) {
            return redirect()->route('dashboard');
        }

        return view('access');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:64'],
        ], [
            'access_code.required' => 'Inserisci il codice di accesso.',
        ]);

        $key = 'collaboration-access:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()
                ->withInput()
                ->withErrors(['access_code' => "Troppi tentativi. Riprova tra {$seconds} secondi."]);
        }

        $expectedCode = (string) config('collaborations.access_code');

        if (! hash_equals($expectedCode, (string) $request->input('access_code'))) {
            RateLimiter::hit($key, 60);

            return back()
                ->withInput()
                ->withErrors(['access_code' => 'Il codice inserito non è corretto.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('collaborations_authorized', true);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('access.show')->with('success', 'Accesso terminato.');
    }
}
