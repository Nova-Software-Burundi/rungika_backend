<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\TradeEvent;
use App\Models\Advertisement;
use App\Services\TradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TradeController extends Controller
{
    public function __construct(protected TradeService $tradeService) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        $trades = Trade::with(['ad', 'asset', 'fiatCurrency', 'paymentMethod'])
            ->where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return $trades;
    }

    public function show(Trade $trade)
    {
        $user = auth()->user();
        if ($trade->buyer_id !== $user->id && $trade->seller_id !== $user->id) {
            abort(403);
        }

        $trade->load(['ad', 'asset', 'fiatCurrency', 'paymentMethod', 'events' => function ($q) {
            $q->with('actor')->orderBy('created_at', 'asc');
        }]);

        return $trade;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ad_id'             => 'required|exists:advertisements,id',
            'asset_amount'      => 'required|numeric|min:0.00000001',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_details'   => 'nullable|string|max:500',
            'price'             => 'nullable|numeric|min:0',
        ]);

        $data['buyer_id'] = auth()->id();

        try {
            $trade = $this->tradeService->create($data);
            return response()->json($trade, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request, Trade $trade)
    {
        try {
            $trade = $this->tradeService->confirmBySeller($trade, auth()->id());
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function markPaid(Request $request, Trade $trade)
    {
        $data = $request->validate([
            'payment_details' => 'nullable|string|max:500',
            'proof'           => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('trade-proofs', 'public');
        }

        try {
            $trade = $this->tradeService->markAsPaid(
                $trade, auth()->id(), $proofPath, $data['payment_details'] ?? null
            );
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function release(Request $request, Trade $trade)
    {
        try {
            $trade = $this->tradeService->release($trade, auth()->id());
            $trade = $this->tradeService->complete($trade);
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Trade $trade)
    {
        $data = $request->validate([
            'cancelled_by' => 'required|in:buyer,seller',
        ]);

        try {
            $trade = $this->tradeService->cancel($trade, auth()->id(), $data['cancelled_by']);
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dispute(Request $request, Trade $trade)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $trade = $this->tradeService->openDispute($trade, auth()->id(), $data['reason']);
            return response()->json($trade);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function messages(Trade $trade)
    {
        $user = auth()->user();
        if ($trade->buyer_id !== $user->id && $trade->seller_id !== $user->id) {
            abort(403);
        }

        return $trade->events()->with('actor')->orderBy('created_at', 'desc')->get();
    }
}
