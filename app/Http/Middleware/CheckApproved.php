<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Admins bypass approval checks
        if ($user->hasAnyRole(['super_admin', 'Admin', 'Operator'])) {
            return $next($request);
        }

        if ($user->kyc_status !== 'verified') {
            return response()->json([
                'message' => 'Your account is pending approval. Please wait for an administrator to verify your account.',
            ], 403);
        }

        if ($user->flagged) {
            return response()->json([
                'message' => $user->flagged_reason
                    ? "Account restricted: {$user->flagged_reason}"
                    : 'Your account has been restricted. Contact support for more information.',
            ], 403);
        }

        if (!$user->trading_enabled) {
            return response()->json([
                'message' => 'Trading is currently disabled for your account.',
            ], 403);
        }

        return $next($request);
    }
}
