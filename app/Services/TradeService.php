<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\TradeEvent;
use App\Models\Advertisement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeService
{
    const STATUSES = [
        'pending', 'awaiting_payment', 'payment_sent',
        'released', 'completed', 'cancelled', 'disputed', 'resolved',
    ];

    const TRANSITIONS = [
        'pending'          => ['awaiting_payment' => ['seller'], 'cancelled' => ['buyer']],
        'awaiting_payment' => ['payment_sent' => ['buyer'], 'cancelled' => ['buyer']],
        'payment_sent'     => ['released' => ['seller'], 'disputed' => ['buyer', 'seller']],
        'released'         => ['completed' => ['system']],
        'disputed'         => ['resolved' => ['admin']],
    ];

    public function create(array $data): Trade
    {
        return DB::transaction(function () use ($data) {
            $ad = Advertisement::lockForUpdate()->findOrFail($data['ad_id']);

            if ($ad->status !== 'active') {
                throw new \Exception('Ad is not active.');
            }

            if ($ad->user_id === $data['buyer_id']) {
                throw new \Exception('You cannot trade with your own ad.');
            }

            $assetAmount = $data['asset_amount'];
            $price = $ad->price_type === 'fixed' ? $ad->price : ($data['price'] ?? 0);
            $fiatAmount = $assetAmount * $price;

            if ($fiatAmount < $ad->min_order || $fiatAmount > $ad->max_order) {
                throw new \Exception('Amount outside ad limits.');
            }

            if ($assetAmount > $ad->available_quantity) {
                throw new \Exception('Insufficient available quantity.');
            }

            $reference = 'P2P-' . strtoupper(Str::random(8));

            $trade = Trade::create([
                'reference'          => $reference,
                'ad_id'              => $ad->id,
                'asset_id'           => $ad->asset_id,
                'fiat_currency_id'   => $ad->fiat_currency_id,
                'buyer_id'           => $data['buyer_id'],
                'seller_id'          => $ad->user_id,
                'status'             => 'pending',
                'asset_amount'       => $assetAmount,
                'fiat_amount'        => $fiatAmount,
                'price'              => $price,
                'payment_method_id'  => $data['payment_method_id'],
                'payment_details'    => $data['payment_details'] ?? null,
                'fee_buyer'          => 0,
                'fee_seller'         => 0,
            ]);

            $this->logEvent($trade, 'system', null, 'pending', 'Trade created');

            $ad->decrement('available_quantity', $assetAmount);

            return $trade->fresh()->load(['ad', 'asset', 'fiatCurrency', 'buyer', 'seller', 'paymentMethod']);
        });
    }

    public function confirmBySeller(Trade $trade, int $userId): Trade
    {
        return DB::transaction(function () use ($trade, $userId) {
            if ($trade->seller_id !== $userId) {
                throw new \Exception('Only the seller can confirm this trade.');
            }
            $this->assertTransition($trade, 'awaiting_payment', 'seller');

            $trade->update(['status' => 'awaiting_payment']);
            $this->logEvent($trade, 'seller', 'pending', 'awaiting_payment', 'Seller confirmed the order');

            return $trade->fresh();
        });
    }

    public function markAsPaid(Trade $trade, int $userId, ?string $proofPath = null, ?string $paymentDetails = null): Trade
    {
        return DB::transaction(function () use ($trade, $userId, $proofPath, $paymentDetails) {
            if ($trade->buyer_id !== $userId) {
                throw new \Exception('Only the buyer can mark as paid.');
            }
            $this->assertTransition($trade, 'payment_sent', 'buyer');

            $updates = ['status' => 'payment_sent', 'proof_uploaded_at' => now()];
            if ($proofPath) {
                $updates['proof_path'] = $proofPath;
            }
            if ($paymentDetails) {
                $updates['payment_details'] = $paymentDetails;
            }
            $trade->update($updates);

            $this->logEvent($trade, 'buyer', 'awaiting_payment', 'payment_sent', 'Buyer marked as paid');

            return $trade->fresh();
        });
    }

    public function release(Trade $trade, int $userId): Trade
    {
        return DB::transaction(function () use ($trade, $userId) {
            if ($trade->seller_id !== $userId) {
                throw new \Exception('Only the seller can release the asset.');
            }
            $this->assertTransition($trade, 'released', 'seller');

            $trade->update([
                'status' => 'released',
                'seller_confirmed_at' => now(),
            ]);
            $this->logEvent($trade, 'seller', 'payment_sent', 'released', 'Seller confirmed fiat receipt and released asset');

            return $trade->fresh();
        });
    }

    public function complete(Trade $trade): Trade
    {
        return DB::transaction(function () use ($trade) {
            $this->assertTransition($trade, 'completed', 'system');

            $trade->update(['status' => 'completed', 'completed_at' => now()]);
            $this->logEvent($trade, 'system', 'released', 'completed', 'Trade completed');

            return $trade->fresh();
        });
    }

    public function cancel(Trade $trade, int $userId, string $cancelledBy): Trade
    {
        return DB::transaction(function () use ($trade, $userId, $cancelledBy) {
            $allowedFrom = ['pending', 'awaiting_payment'];
            if (!in_array($trade->status, $allowedFrom)) {
                throw new \Exception('Trade cannot be cancelled in its current state.');
            }

            if ($cancelledBy === 'buyer' && $trade->buyer_id !== $userId) {
                throw new \Exception('Unauthorized.');
            }
            if ($cancelledBy === 'seller' && $trade->seller_id !== $userId) {
                throw new \Exception('Unauthorized.');
            }

            $fromStatus = $trade->status;
            $trade->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
            ]);

            $trade->ad->increment('available_quantity', $trade->asset_amount);

            $this->logEvent($trade, $cancelledBy, $fromStatus, 'cancelled', 'Trade cancelled');

            return $trade->fresh();
        });
    }

    public function openDispute(Trade $trade, int $userId, string $reason): Trade
    {
        return DB::transaction(function () use ($trade, $userId, $reason) {
            if ($trade->buyer_id !== $userId && $trade->seller_id !== $userId) {
                throw new \Exception('Only buyer or seller can open a dispute.');
            }
            $this->assertTransition($trade, 'disputed', $trade->buyer_id === $userId ? 'buyer' : 'seller');

            $fromStatus = $trade->status;
            $trade->update([
                'status' => 'disputed',
                'dispute_reason' => $reason,
                'dispute_opened_at' => now(),
            ]);

            $this->logEvent($trade, $trade->buyer_id === $userId ? 'buyer' : 'seller', $fromStatus, 'disputed', $reason);

            return $trade->fresh();
        });
    }

    public function resolveDispute(Trade $trade, int $adminId, string $resolution, string $outcome): Trade
    {
        return DB::transaction(function () use ($trade, $adminId, $resolution, $outcome) {
            if ($trade->status !== 'disputed') {
                throw new \Exception('Trade is not in dispute.');
            }

            if (!in_array($outcome, ['released', 'cancelled'])) {
                throw new \Exception('Resolution outcome must be released or cancelled.');
            }

            $trade->update([
                'status' => 'resolved',
                'dispute_resolved_at' => now(),
                'dispute_resolved_by' => $adminId,
                'dispute_resolution' => $resolution,
            ]);

            $this->logEvent($trade, 'admin', 'disputed', 'resolved', "Dispute resolved: $resolution (outcome: $outcome)");

            if ($outcome === 'released') {
                $trade->update(['status' => 'released', 'seller_confirmed_at' => now()]);
                $this->logEvent($trade, 'system', 'resolved', 'released', 'Released per admin decision');
                $this->complete($trade);
            } else {
                $trade->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'admin']);
                $trade->ad->increment('available_quantity', $trade->asset_amount);
                $this->logEvent($trade, 'system', 'resolved', 'cancelled', 'Cancelled per admin decision');
            }

            return $trade->fresh();
        });
    }

    private function assertTransition(Trade $trade, string $target, string $actorType): void
    {
        $allowed = self::TRANSITIONS[$trade->status] ?? null;
        if (!$allowed || !isset($allowed[$target]) || !in_array($actorType, $allowed[$target])) {
            throw new \Exception("Cannot transition from '{$trade->status}' to '$target' as '$actorType'.");
        }
    }

    private function logEvent(Trade $trade, string $actorType, ?string $fromStatus, string $toStatus, ?string $notes = null): void
    {
        TradeEvent::create([
            'trade_id'    => $trade->id,
            'actor_id'    => match ($actorType) {
                'buyer'  => $trade->buyer_id,
                'seller' => $trade->seller_id,
                'admin'  => $trade->dispute_resolved_by ?? auth()->id(),
                default  => auth()->id() ?? $trade->seller_id,
            },
            'actor_type'  => $actorType,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'notes'       => $notes,
        ]);
    }
}
