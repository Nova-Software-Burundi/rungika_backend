<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$key) {
            return response()->json(['message' => 'API key is missing'], 401);
        }

        $hashed = hash('sha256', $key);

        $apiKey = ApiKey::where('key', $hashed)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$apiKey) {
            return response()->json(['message' => 'Invalid or inactive API key'], 401);
        }

        if ($apiKey->allowed_ips) {
            if (!in_array($request->ip(), $apiKey->allowed_ips)) {
                return response()->json(['message' => 'IP not allowed'], 403);
            }
        }

        $apiKey->update(['last_used_at' => now()]);

        Auth::shouldUse('web');

        if ($apiKey->user_id) {
            Auth::loginUsingId($apiKey->user_id);
        }

        $request->attributes->set('auth.api_key', true);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
