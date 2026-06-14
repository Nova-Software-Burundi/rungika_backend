<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    public function trades(Request $request)
    {
        $query = Trade::with(['asset', 'fiatCurrency', 'buyer', 'seller', 'paymentMethod']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->where('created_at', '<=', $request->to);
        }

        $trades = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="trades-export.csv"',
        ];

        $callback = function () use ($trades) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference', 'Status', 'Asset', 'Fiat', 'Asset Amount', 'Fiat Amount',
                'Price', 'Buyer', 'Seller', 'Payment Method', 'Buyer Fee', 'Seller Fee',
                'Created At', 'Completed At',
            ]);

            foreach ($trades as $trade) {
                fputcsv($handle, [
                    $trade->reference,
                    $trade->status,
                    $trade->asset?->code,
                    $trade->fiatCurrency?->code,
                    $trade->asset_amount,
                    $trade->fiat_amount,
                    $trade->price,
                    $trade->buyer?->email,
                    $trade->seller?->email,
                    $trade->paymentMethod?->name,
                    $trade->fee_buyer,
                    $trade->fee_seller,
                    $trade->created_at,
                    $trade->completed_at,
                ]);
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }
}
