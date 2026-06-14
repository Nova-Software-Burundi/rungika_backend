<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserRating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(Request $request)
    {
        return UserRating::with(['trade', 'rater', 'ratedUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
    }
}
