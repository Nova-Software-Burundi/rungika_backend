<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->input('role')));
        }

        if ($request->filled('kyc_status')) {
            $query->where('kyc_status', $request->input('kyc_status'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate((int) $request->get('per_page', 15))
        );
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles');
        $user->loadCount('ratingsReceived');
        $user->average_rating = round($user->averageRating() ?? 0, 1);
        return response()->json($user);
    }

    public function approveKyc(Request $request, User $user): JsonResponse
    {
        $user->update([
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ]);

        return response()->json(['message' => 'User KYC approved.', 'user' => $user->fresh()->load('roles')]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $user->update([
            'kyc_status' => 'suspended',
            'kyc_verified_at' => null,
        ]);

        return response()->json(['message' => 'User suspended.', 'user' => $user->fresh()->load('roles')]);
    }

    public function setKycTier(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['kyc_tier' => 'required|integer|min:0|max:3']);

        $user->update(['kyc_tier' => $data['kyc_tier']]);

        return response()->json(['message' => "KYC tier set to {$data['kyc_tier']}.", 'user' => $user->fresh()->load('roles')]);
    }

    public function flag(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'flagged'        => 'required|boolean',
            'flagged_reason' => 'nullable|string|max:500',
        ]);

        $user->update([
            'flagged'        => $data['flagged'],
            'flagged_reason' => $data['flagged_reason'] ?? null,
        ]);

        return response()->json([
            'message' => $data['flagged'] ? 'User flagged.' : 'User unflagged.',
            'user'    => $user->fresh()->load('roles'),
        ]);
    }

    public function toggleTrading(Request $request, User $user): JsonResponse
    {
        $user->update(['trading_enabled' => !$user->trading_enabled]);

        return response()->json([
            'message' => $user->trading_enabled ? 'Trading enabled.' : 'Trading disabled.',
            'user'    => $user->fresh()->load('roles'),
        ]);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['Customer', 'Agent', 'Driver', 'Admin', 'Operator', 'super_admin'])],
        ]);

        $user->syncRoles([$data['role']]);

        return response()->json(['message' => "Role assigned: {$data['role']}", 'user' => $user->fresh()->load('roles')]);
    }

    public function kycStats(): JsonResponse
    {
        return response()->json([
            'total' => User::count(),
            'pending' => User::where('kyc_status', 'pending')->count(),
            'verified' => User::where('kyc_status', 'verified')->count(),
            'suspended' => User::where('kyc_status', 'suspended')->count(),
            'customers' => User::whereHas('roles', fn($q) => $q->where('name', 'Customer'))->count(),
            'agents' => User::whereHas('roles', fn($q) => $q->where('name', 'Agent'))->count(),
        ]);
    }
}
