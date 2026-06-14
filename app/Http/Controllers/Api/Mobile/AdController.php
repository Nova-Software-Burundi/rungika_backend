<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request)
    {
        $query = Advertisement::with(['user', 'asset', 'fiatCurrency'])
            ->where('status', 'active');

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->asset_id) {
            $query->where('asset_id', $request->asset_id);
        }
        if ($request->fiat_currency_id) {
            $query->where('fiat_currency_id', $request->fiat_currency_id);
        }

        return $query->orderByRaw('price IS NULL, price ASC')->paginate($request->per_page ?? 20);
    }

    public function show(Advertisement $advertisement)
    {
        if ($advertisement->status !== 'active' && $advertisement->user_id !== auth()->id()) {
            abort(404);
        }
        return $advertisement->load(['user', 'asset', 'fiatCurrency']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:buy,sell',
            'asset_id' => 'required|exists:assets,id',
            'fiat_currency_id' => 'required|exists:currencies,id',
            'price_type' => 'required|in:fixed,floating',
            'price' => 'required_if:price_type,fixed|numeric|min:0|nullable',
            'margin' => 'required_if:price_type,floating|numeric|nullable',
            'min_order' => 'required|numeric|min:0',
            'max_order' => 'required|numeric|min:0',
            'available_quantity' => 'required|numeric|min:0',
            'payment_methods' => 'required|array',
            'payment_methods.*' => 'exists:payment_methods,id',
            'terms' => 'nullable|string',
            'auto_reply' => 'nullable|string',
        ]);

        $data['user_id'] = auth()->id();
        $data['payment_methods'] = json_encode($data['payment_methods']);

        return Advertisement::create($data);
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        if ($advertisement->user_id !== auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'price_type' => 'in:fixed,floating',
            'price' => 'numeric|min:0|nullable',
            'margin' => 'numeric|nullable',
            'min_order' => 'numeric|min:0',
            'max_order' => 'numeric|min:0',
            'available_quantity' => 'numeric|min:0',
            'payment_methods' => 'array',
            'payment_methods.*' => 'exists:payment_methods,id',
            'terms' => 'nullable|string',
            'status' => 'in:active,paused,closed',
            'auto_reply' => 'nullable|string',
        ]);

        if (isset($data['payment_methods'])) {
            $data['payment_methods'] = json_encode($data['payment_methods']);
        }

        $advertisement->update($data);
        return $advertisement;
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->user_id !== auth()->id()) {
            abort(403);
        }
        $advertisement->delete();
        return response()->noContent();
    }

    public function myAds(Request $request)
    {
        return Advertisement::with(['asset', 'fiatCurrency'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
    }
}
