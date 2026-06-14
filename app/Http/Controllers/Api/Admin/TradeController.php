<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Services\TradeService;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(protected TradeService $tradeService) {}

    public function index(Request $request)
    {
        $query = Trade::with(['ad', 'asset', 'fiatCurrency', 'buyer', 'seller', 'paymentMethod']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->buyer_id) {
            $query->where('buyer_id', $request->buyer_id);
        }
        if ($request->seller_id) {
            $query->where('seller_id', $request->seller_id);
        }
        if ($request->reference) {
            $query->where('reference', 'like', "%{$request->reference}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 20);
    }

    public function show(Trade $trade)
    {
        return $trade->load([
            'ad', 'asset', 'fiatCurrency', 'buyer', 'seller', 'paymentMethod',
            'events' => fn($q) => $q->with('actor')->orderBy('created_at', 'asc'),
        ]);
    }

    public function cancel(Trade $trade, Request $request)
    {
        $data = $request->validate(['reason' => 'nullable|string']);

        try {
            $trade = $this->tradeService->cancel($trade, auth()->id(), 'admin');
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function resolveDispute(Trade $trade, Request $request)
    {
        $data = $request->validate([
            'resolution' => 'required|string|max:1000',
            'outcome'    => 'required|in:released,cancelled',
        ]);

        try {
            $trade = $this->tradeService->resolveDispute(
                $trade, auth()->id(), $data['resolution'], $data['outcome']
            );
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
