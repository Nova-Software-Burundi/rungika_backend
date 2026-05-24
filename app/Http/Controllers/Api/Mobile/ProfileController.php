<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => $user,
            'kyc_approved' => $user->kyc_status === 'verified',
            'is_agent' => $user->hasRole('Agent'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($data);

        return response()->json(['user' => $user->fresh()->load('roles')]);
    }
}
