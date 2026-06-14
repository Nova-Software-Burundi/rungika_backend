<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token'    => 'required|string',
            'platform' => 'nullable|string|max:20',
        ]);

        $deviceToken = DeviceToken::updateOrCreate(
            ['user_id' => auth()->id(), 'token' => $data['token']],
            ['platform' => $data['platform'] ?? null]
        );

        return response()->json($deviceToken, 201);
    }

    public function destroy($token)
    {
        DeviceToken::where('user_id', auth()->id())->where('token', $token)->delete();
        return response()->noContent();
    }
}
