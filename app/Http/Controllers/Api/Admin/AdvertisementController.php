<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function index(Request $request)
    {
        $query = Advertisement::with(['user', 'asset', 'fiatCurrency']);

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
    }

    public function show(Advertisement $advertisement)
    {
        return $advertisement->load(['user', 'asset', 'fiatCurrency']);
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $data = $request->validate([
            'status' => 'in:active,paused,closed',
            'price' => 'numeric|min:0',
            'min_order' => 'numeric|min:0',
            'max_order' => 'numeric|min:0',
            'available_quantity' => 'numeric|min:0',
        ]);

        $advertisement->update($data);
        return $advertisement;
    }

    public function destroy(Advertisement $advertisement)
    {
        $advertisement->delete();
        return response()->noContent();
    }
}
