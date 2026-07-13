<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->apiKeys()->select('id', 'name', 'last_used_at', 'expires_at', 'is_active', 'created_at')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $plaintext = 'ml_' . Str::random(48);
        $hashed = hash('sha256', $plaintext);

        $apiKey = $request->user()->apiKeys()->create([
            'name' => $request->name,
            'key' => $hashed,
        ]);

        return response()->json([
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'plain_text_key' => $plaintext,
            'message' => 'Store this key securely — it will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $apiKey->update(['is_active' => false]);

        return response()->json(['message' => 'API key revoked.']);
    }
}
