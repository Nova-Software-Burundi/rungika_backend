<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Currency::orderBy('code')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:5'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $currency = Currency::create($data);

        return response()->json($currency, 201);
    }

    public function show(Currency $currency): JsonResponse
    {
        return response()->json($currency);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:3', 'unique:currencies,code,' . $currency->id],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:5'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $currency->update($data);

        return response()->json($currency);
    }

    public function destroy(Currency $currency): JsonResponse
    {
        if ($currency->exchangeRatesAsBase()->exists() || $currency->exchangeRatesAsTarget()->exists()) {
            return response()->json(['message' => 'Cannot delete currency with associated exchange rates.'], 422);
        }

        $currency->delete();

        return response()->json(['message' => 'Currency deleted.']);
    }

    public function exchangeRates(Currency $currency): JsonResponse
    {
        $rates = ExchangeRate::where('base_currency_id', $currency->id)
            ->orWhere('target_currency_id', $currency->id)
            ->with('creator:id,name')
            ->orderByDesc('valid_from')
            ->get();

        return response()->json($rates);
    }

    public function storeExchangeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_currency_id' => ['required', 'exists:currencies,id'],
            'target_currency_id' => ['required', 'exists:currencies,id', 'different:base_currency_id'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'valid_from' => ['required', 'date'],
        ]);

        $rate = DB::transaction(function () use ($data, $request) {
            $base = Currency::findOrFail($data['base_currency_id']);
            $target = Currency::findOrFail($data['target_currency_id']);

            return ExchangeRate::create([
                'base_currency_id' => $base->id,
                'target_currency_id' => $target->id,
                'base_currency' => $base->code,
                'target_currency' => $target->code,
                'rate' => $data['rate'],
                'valid_from' => $data['valid_from'],
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($rate->load('creator:id,name'), 201);
    }

    public function destroyExchangeRate(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        return response()->json(['message' => 'Exchange rate deleted.']);
    }
}
