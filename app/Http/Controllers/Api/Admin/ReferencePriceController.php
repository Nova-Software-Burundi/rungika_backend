<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferencePrice;
use Illuminate\Http\Request;

class ReferencePriceController extends Controller
{
    public function index(Request $request)
    {
        $query = ReferencePrice::with(['asset', 'fiatCurrency']);

        if ($request->asset_id) {
            $query->where('asset_id', $request->asset_id);
        }
        if ($request->fiat_currency_id) {
            $query->where('fiat_currency_id', $request->fiat_currency_id);
        }

        return $query->orderBy('valid_at', 'desc')->paginate($request->per_page ?? 20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'         => 'required|exists:assets,id',
            'fiat_currency_id' => 'required|exists:currencies,id',
            'price'            => 'required|numeric|min:0',
            'source'           => 'nullable|string|max:50',
            'valid_at'         => 'nullable|date',
        ]);

        if (!isset($data['source'])) {
            $data['source'] = 'manual';
        }

        $price = ReferencePrice::create($data);
        return response()->json($price->load(['asset', 'fiatCurrency']), 201);
    }

    public function show(ReferencePrice $referencePrice)
    {
        return $referencePrice->load(['asset', 'fiatCurrency']);
    }

    public function update(Request $request, ReferencePrice $referencePrice)
    {
        $data = $request->validate([
            'price'    => 'numeric|min:0',
            'source'   => 'string|max:50',
            'valid_at' => 'date',
        ]);

        $referencePrice->update($data);
        return $referencePrice->load(['asset', 'fiatCurrency']);
    }

    public function destroy(ReferencePrice $referencePrice)
    {
        $referencePrice->delete();
        return response()->noContent();
    }

    public function latest(Request $request)
    {
        $query = ReferencePrice::selectRaw('asset_id, fiat_currency_id, MAX(valid_at) as valid_at')
            ->groupBy('asset_id', 'fiat_currency_id');

        if ($request->asset_id) {
            $query->where('asset_id', $request->asset_id);
        }

        $latest = $query->get();

        $prices = [];
        foreach ($latest as $row) {
            $price = ReferencePrice::where('asset_id', $row->asset_id)
                ->where('fiat_currency_id', $row->fiat_currency_id)
                ->where('valid_at', $row->valid_at)
                ->with(['asset', 'fiatCurrency'])
                ->first();
            if ($price) {
                $prices[] = $price;
            }
        }

        return response()->json($prices);
    }
}
