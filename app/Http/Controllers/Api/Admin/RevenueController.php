<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function summary(Request $request)
    {
        $query = Trade::whereIn('status', ['completed', 'released'])
            ->select(
                DB::raw('DATE(completed_at) as date'),
                DB::raw('SUM(fee_buyer + fee_seller) as total_fees'),
                DB::raw('SUM(fee_buyer) as buyer_fees'),
                DB::raw('SUM(fee_seller) as seller_fees'),
                DB::raw('COUNT(*) as trade_count'),
                DB::raw('SUM(fiat_amount) as volume')
            );

        if ($request->from) {
            $query->where('completed_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->where('completed_at', '<=', $request->to);
        }

        $groupBy = $request->group_by ?? 'day';
        if ($groupBy === 'month') {
            $query->select(
                DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as date"),
                DB::raw('SUM(fee_buyer + fee_seller) as total_fees'),
                DB::raw('SUM(fee_buyer) as buyer_fees'),
                DB::raw('SUM(fee_seller) as seller_fees'),
                DB::raw('COUNT(*) as trade_count'),
                DB::raw('SUM(fiat_amount) as volume')
            )->groupBy(DB::raw("DATE_FORMAT(completed_at, '%Y-%m')"));
        } else {
            $query->groupBy(DB::raw('DATE(completed_at)'));
        }

        return $query->orderBy('date', 'desc')->paginate($request->per_page ?? 31);
    }

    public function byPair()
    {
        return Trade::whereIn('status', ['completed', 'released'])
            ->select(
                'asset_id',
                'fiat_currency_id',
                DB::raw('COUNT(*) as trade_count'),
                DB::raw('SUM(fiat_amount) as volume'),
                DB::raw('SUM(fee_buyer + fee_seller) as total_fees'),
            )
            ->with(['asset', 'fiatCurrency'])
            ->groupBy('asset_id', 'fiat_currency_id')
            ->orderBy('total_fees', 'desc')
            ->get();
    }

    public function totals()
    {
        $totals = Trade::whereIn('status', ['completed', 'released'])
            ->select(
                DB::raw('COUNT(*) as total_trades'),
                DB::raw('COALESCE(SUM(fiat_amount), 0) as total_volume'),
                DB::raw('COALESCE(SUM(fee_buyer + fee_seller), 0) as total_fees'),
                DB::raw('COALESCE(SUM(fee_buyer), 0) as buyer_fees'),
                DB::raw('COALESCE(SUM(fee_seller), 0) as seller_fees'),
            )
            ->first();

        return response()->json($totals);
    }
}
