<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MoneyTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Current user's activity report — remittances summary.
     */
    public function myActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = $request->input('from');
        $to = $request->input('to');

        $base = MoneyTransfer::where('initiated_by', $user->id)
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));

        $totalSent = (clone $base)->count();
        $totalVolume = (clone $base)->sum('send_amount');
        $completed = (clone $base)->where('status', MoneyTransfer::STATUS_COMPLETED)->count();
        $pending = (clone $base)->where('status', MoneyTransfer::STATUS_PENDING)->count();
        $disputed = (clone $base)->where('status', MoneyTransfer::STATUS_DISPUTED)->count();
        $myDebts = (clone $base)->where('requester_debt', true)->count();

        // Agent stats if user is an agent
        $agentStats = null;
        if ($user->hasRole('Agent')) {
            $agentBase = MoneyTransfer::where('assigned_agent_id', $user->id)
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to));

            $agentStats = [
                'total_orders' => (clone $agentBase)->count(),
                'accepted_orders' => (clone $agentBase)->whereIn('status', [
                    MoneyTransfer::STATUS_ACCEPTED, MoneyTransfer::STATUS_EXECUTED, MoneyTransfer::STATUS_COMPLETED,
                ])->count(),
                'completed_orders' => (clone $agentBase)->where('status', MoneyTransfer::STATUS_COMPLETED)->count(),
                'executor_debts' => (clone $agentBase)->where('executor_debt', true)->count(),
            ];
        }

        return response()->json([
            'requester' => [
                'total_remittances' => $totalSent,
                'total_volume' => round($totalVolume, 2),
                'completed' => $completed,
                'pending' => $pending,
                'disputed' => $disputed,
                'my_debts' => $myDebts,
            ],
            'agent' => $agentStats,
            'period' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Export user activity as CSV.
     */
    public function exportMyActivity(Request $request)
    {
        $user = $request->user();
        $from = $request->input('from');
        $to = $request->input('to');

        $remittances = MoneyTransfer::with(['agent:id,name,phone', 'paymentMethod:id,name'])
            ->where('initiated_by', $user->id)
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="my-activity.csv"',
        ];

        $callback = function () use ($remittances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference', 'Agent', 'Status', 'Amount', 'Currency',
                'Destinator', 'Payment Method', 'Requester Debt', 'Executor Debt',
                'Created At', 'Completed At',
            ]);

            foreach ($remittances as $r) {
                fputcsv($handle, [
                    $r->reference,
                    $r->agent?->name ?? 'N/A',
                    $r->status,
                    $r->send_amount,
                    $r->send_currency ?? 'USD',
                    $r->destinator_name,
                    $r->paymentMethod?->name ?? 'N/A',
                    $r->requester_debt ? 'Yes' : 'No',
                    $r->executor_debt ? 'Yes' : 'No',
                    $r->created_at,
                    $r->completed_at,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
