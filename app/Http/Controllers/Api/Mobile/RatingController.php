<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\UserRating;
use App\Models\User;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request, Trade $trade)
    {
        $user = auth()->user();

        if ($trade->status !== 'completed') {
            return response()->json(['message' => 'Trade must be completed to rate.'], 422);
        }

        if ($trade->buyer_id !== $user->id && $trade->seller_id !== $user->id) {
            abort(403);
        }

        $ratedUserId = $trade->buyer_id === $user->id ? $trade->seller_id : $trade->buyer_id;

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $existing = UserRating::where('trade_id', $trade->id)
            ->where('rater_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already rated this trade.'], 422);
        }

        $rating = UserRating::create([
            'trade_id'      => $trade->id,
            'rater_id'      => $user->id,
            'rated_user_id' => $ratedUserId,
            'rating'        => $data['rating'],
            'comment'       => $data['comment'] ?? null,
        ]);

        return response()->json($rating->load('rater'), 201);
    }

    public function userRatings(User $user)
    {
        $ratings = $user->ratingsReceived()
            ->with('rater')
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 20));

        return response()->json([
            'ratings' => $ratings,
            'average_rating' => round($user->averageRating() ?? 0, 1),
            'total_ratings' => $user->ratingCount(),
        ]);
    }

    public function stats(User $user)
    {
        $completedTrades = Trade::where(function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        })->where('status', 'completed')->count();

        $totalTrades = Trade::where(function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        })->count();

        $cancelledTrades = Trade::where(function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        })->where('status', 'cancelled')->count();

        $volume = Trade::where(function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        })->whereIn('status', ['completed', 'released'])
            ->sum('fiat_amount');

        return response()->json([
            'total_trades'       => $totalTrades,
            'completed_trades'   => $completedTrades,
            'cancelled_trades'   => $cancelledTrades,
            'completion_rate'    => $totalTrades > 0 ? round(($completedTrades / $totalTrades) * 100, 1) : 0,
            'total_volume'       => round($volume, 2),
            'average_rating'     => round($user->averageRating() ?? 0, 1),
            'total_ratings'      => $user->ratingCount(),
            'kyc_tier'           => $user->kyc_tier,
            'kyc_status'         => $user->kyc_status,
            'trading_enabled'    => $user->trading_enabled,
        ]);
    }
}
