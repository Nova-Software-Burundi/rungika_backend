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

        // Always allow 2FA setup/challenge endpoints
        if ($request->is('api/portal/2fa*')) {
            return $next($request);
        }

        // If 2FA setup is pending, block everything else
        if ($request->session()->get('2fa_pending_setup')) {
            return response()->json([
                'message' => 'Two-factor authentication setup required.',
                'requires_2fa_setup' => true,
            ], 403);
        }

        // If 2FA challenge is pending, block everything else
        if ($request->session()->get('2fa_pending_challenge')) {
            return response()->json([
                'message' => 'Two-factor authentication challenge required.',
                'requires_2fa_challenge' => true,
            ], 403);
        }

        return $next($request);
    }
}
