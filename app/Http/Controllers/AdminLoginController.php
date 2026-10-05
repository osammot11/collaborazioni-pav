<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AdminLoginController extends Controller
{
    public function show()
    {
        return view('integrations.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:1024']);
        $key = 'admin-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Troppi tentativi. Riprova tra un minuto.']);
        }
        if (! Auth::attempt([...$data, 'is_admin' => true])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Credenziali non valide.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('collaborations_authorized', true);

        return redirect()->intended(route('integrations.index'));
    }
}
