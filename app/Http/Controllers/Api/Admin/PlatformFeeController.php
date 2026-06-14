<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFee;
use Illuminate\Http\Request;

class PlatformFeeController extends Controller
{
    public function index(Request $request)
    {
        $query = PlatformFee::with(['asset', 'fiatCurrency']);

        if ($request->asset_id) {
            $query->where('asset_id', $request->asset_id);
        }
        if ($request->enabled !== null) {
            $query->where('enabled', filter_var($request->enabled, FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'          => 'required|exists:assets,id',
            'fiat_currency_id'  => 'required|exists:currencies,id',
            'buyer_fee_type'    => 'required|in:percentage,fixed',
            'buyer_fee_value'   => 'required|numeric|min:0',
            'seller_fee_type'   => 'required|in:percentage,fixed',
            'seller_fee_value'  => 'required|numeric|min:0',
            'min_fee'           => 'nullable|numeric|min:0',
            'max_fee'           => 'nullable|numeric|min:0',
            'enabled'           => 'boolean',
        ]);

        $fee = PlatformFee::create($data);
        return response()->json($fee->load(['asset', 'fiatCurrency']), 201);
    }

    public function show(PlatformFee $platformFee)
    {
        return $platformFee->load(['asset', 'fiatCurrency']);
    }

    public function update(Request $request, PlatformFee $platformFee)
    {
        $data = $request->validate([
            'buyer_fee_type'   => 'in:percentage,fixed',
            'buyer_fee_value'  => 'numeric|min:0',
            'seller_fee_type'  => 'in:percentage,fixed',
            'seller_fee_value' => 'numeric|min:0',
            'min_fee'          => 'nullable|numeric|min:0',
            'max_fee'          => 'nullable|numeric|min:0',
            'enabled'          => 'boolean',
        ]);

        $platformFee->update($data);
        return $platformFee->load(['asset', 'fiatCurrency']);
    }

    public function destroy(PlatformFee $platformFee)
    {
        $platformFee->delete();
        return response()->noContent();
    }
}
