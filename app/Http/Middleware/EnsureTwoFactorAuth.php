<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at) {
            if (!$request->session()->get('two_factor_passed', false)) {
                return response()->json([
                    'message' => 'Two-factor authentication challenge required.',
                    'requires_2fa_challenge' => true,
                ], 403);
            }
            return $next($request);
        }

        return response()->json([
            'message' => 'Two-factor authentication is required. Please set up 2FA before accessing the portal.',
            'requires_2fa_setup' => true,
        ], 403);
    }
}
