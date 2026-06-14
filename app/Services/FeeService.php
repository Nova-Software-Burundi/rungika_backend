<?php

namespace App\Services;

use App\Models\PlatformFee;

class FeeService
{
    public function calculateForTrade(int $assetId, int $fiatCurrencyId, float $fiatAmount): array
    {
        $feeConfig = PlatformFee::where('asset_id', $assetId)
            ->where('fiat_currency_id', $fiatCurrencyId)
            ->where('enabled', true)
            ->first();

        if (!$feeConfig) {
            return ['buyer' => 0, 'seller' => 0];
        }

        $buyerFee = $this->calculateFee(
            $fiatAmount, $feeConfig->buyer_fee_type, $feeConfig->buyer_fee_value,
            $feeConfig->min_fee, $feeConfig->max_fee
        );

        $sellerFee = $this->calculateFee(
            $fiatAmount, $feeConfig->seller_fee_type, $feeConfig->seller_fee_value,
            $feeConfig->min_fee, $feeConfig->max_fee
        );

        return ['buyer' => $buyerFee, 'seller' => $sellerFee];
    }

    private function calculateFee(float $amount, string $type, float $value, ?float $min, ?float $max): float
    {
        $fee = $type === 'percentage' ? ($amount * $value / 100) : $value;

        if ($min !== null && $fee < $min) {
            $fee = $min;
        }
        if ($max !== null && $fee > $max) {
            $fee = $max;
        }

        return round($fee, 8);
    }
}
