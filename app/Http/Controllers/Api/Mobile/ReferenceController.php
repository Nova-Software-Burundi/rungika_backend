<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\ReferencePrice;

class ReferenceController extends Controller
{
    public function assets()
    {
        return Asset::where('enabled', true)->orderBy('code')->get();
    }

    public function fiatCurrencies()
    {
        return Currency::where('enabled', true)->orderBy('code')->get();
    }

    public function paymentMethods()
    {
        return PaymentMethod::where('enabled', true)->orderBy('name')->get();
    }

    public function referencePrices()
    {
        $latest = \DB::table('reference_prices')
            ->selectRaw('asset_id, fiat_currency_id, MAX(valid_at) as valid_at')
            ->groupBy('asset_id', 'fiat_currency_id')
            ->get();

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
