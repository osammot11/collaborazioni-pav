<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        return view('integrations.index', [
            'connections' => $request->user()->tokens()->with('client')->latest()->get()->groupBy('client_id'),
            'operations' => DB::table('mcp_operations')->where('user_id', $request->user()->id)->latest('id')->paginate(20),
        ]);
    }

    public function revoke(Request $request, string $client)
    {
        DB::transaction(function () use ($request, $client): void {
            foreach ($request->user()->tokens()->where('client_id', $client)->get() as $token) {
                $token->refreshToken?->revoke();
                $token->revoke();
            }
            DB::table('oauth_auth_codes')->where('user_id', $request->user()->id)->where('client_id', $client)->update(['revoked' => true]);
        });

        return back()->with('success', 'Collegamento revocato, inclusi i token di rinnovo.');
    }
}
