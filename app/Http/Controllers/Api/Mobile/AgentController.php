<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::role('Agent')
            ->with('country')
            ->withCount(['assignedMoneyTransfers as total_jobs'])
            ->withAvg('ratingsReceived as average_rating')
            ->where('kyc_status', 'verified')
            ->where('flagged', false);

        if ($request->boolean('available')) {
            $query->where('is_agent_available', true);
        }

        if ($request->boolean('online')) {
            $query->where('last_activity_at', '>=', now()->subMinutes(5));
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'average_rating');
        $sortDir = $request->input('sort_dir', 'desc');

        $allowedSorts = ['average_rating', 'total_jobs', 'name', 'last_activity_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $agents = $query->paginate(20);

        $agents->getCollection()->transform(function ($agent) {
            $completedJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->where('status', MoneyTransfer::STATUS_COMPLETED)
                ->count();

            $totalJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->whereIn('status', [
                    MoneyTransfer::STATUS_COMPLETED,
                    MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED,
                    MoneyTransfer::STATUS_USDT_RECEIVED,
                ])
                ->count();

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'photo_url' => $agent->agent_photo_path
                    ? url('storage/' . $agent->agent_photo_path)
                    : null,
                'country' => $agent->country ? [
                    'id' => $agent->country->id,
                    'code' => $agent->country->code,
                    'name' => $agent->country->name,
                    'flag_url' => $agent->country->flag_url,
                ] : null,
                'is_available' => (bool) $agent->is_agent_available,
                'is_online' => $agent->last_activity_at && $agent->last_activity_at->gt(now()->subMinutes(5)),
                'last_activity_at' => $agent->last_activity_at,
                'average_rating' => round((float) ($agent->average_rating ?? 0), 1),
                'total_jobs' => (int) ($agent->total_jobs ?? 0),
                'completed_jobs' => $completedJobs,
                'completion_rate' => $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 100,
                'agent_location' => $agent->agent_location,
                'agent_bio' => $agent->agent_bio,
                'agent_verified_at' => $agent->agent_verified_at,
            ];
        });

        return response()->json($agents);
    }

    public function show(User $user): JsonResponse
    {
        if (!$user->hasRole('Agent')) {
            return response()->json(['message' => 'User is not an agent.'], 404);
        }

        $completedJobs = MoneyTransfer::where('assigned_agent_id', $user->id)
            ->where('status', MoneyTransfer::STATUS_COMPLETED)
            ->count();

        $totalJobs = MoneyTransfer::where('assigned_agent_id', $user->id)
            ->whereIn('status', [
                MoneyTransfer::STATUS_COMPLETED,
                MoneyTransfer::STATUS_USDT_PROOF_SUBMITTED,
                MoneyTransfer::STATUS_USDT_RECEIVED,
            ])
            ->count();

        $ratingsCount = $user->ratingsReceived()->count();
        $avgRating = round((float) ($user->ratingsReceived()->avg('rating') ?? 0), 1);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'photo_url' => $user->agent_photo_path
                ? url('storage/' . $user->agent_photo_path)
                : null,
            'country' => $user->country ? [
                'id' => $user->country->id,
                'code' => $user->country->code,
                'name' => $user->country->name,
                'flag_url' => $user->country->flag_url,
            ] : null,
            'is_available' => (bool) $user->is_agent_available,
            'is_online' => $user->last_activity_at && $user->last_activity_at->gt(now()->subMinutes(5)),
            'last_activity_at' => $user->last_activity_at,
            'average_rating' => $avgRating,
            'total_ratings' => $ratingsCount,
            'total_jobs' => $totalJobs,
            'completed_jobs' => $completedJobs,
            'completion_rate' => $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 100,
            'agent_location' => $user->agent_location,
            'agent_bio' => $user->agent_bio,
            'agent_verified_at' => $user->agent_verified_at,
        ]);
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json(['message' => 'Only agents can toggle availability.'], 403);
        }

        $user->update([
            'is_agent_available' => !$user->is_agent_available,
            'agent_available_since' => $user->is_agent_available ? now() : null,
        ]);

        return response()->json([
            'message' => $user->is_agent_available ? 'You are now available.' : 'You are now unavailable.',
            'is_agent_available' => $user->is_agent_available,
            'agent_available_since' => $user->agent_available_since,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('Agent')) {
            return response()->json(['message' => 'Only agents can update agent profile.'], 403);
        }

        $data = $request->validate([
            'agent_location' => ['nullable', 'string', 'max:255'],
            'agent_bio' => ['nullable', 'string', 'max:2000'],
            'agent_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $updateData = [];

        if ($request->filled('agent_location')) {
            $updateData['agent_location'] = $data['agent_location'];
        }

        if ($request->filled('agent_bio')) {
            $updateData['agent_bio'] = $data['agent_bio'];
        }

        if ($request->hasFile('agent_photo')) {
            $path = $request->file('agent_photo')->store('agent-photos', 'public');
            $updateData['agent_photo_path'] = $path;
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return response()->json([
            'message' => 'Agent profile updated.',
            'agent_location' => $user->agent_location,
            'agent_bio' => $user->agent_bio,
            'photo_url' => $user->agent_photo_path
                ? url('storage/' . $user->agent_photo_path)
                : null,
        ]);
    }

    public function ping(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'last_activity_at' => now(),
        ])->save();

        return response()->json([
            'last_activity_at' => $request->user()->last_activity_at,
        ]);
    }
}
