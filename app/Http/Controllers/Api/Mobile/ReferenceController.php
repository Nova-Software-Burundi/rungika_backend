<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Currency;
use App\Models\PaymentMethod;

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
}
