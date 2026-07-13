<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Exports\AgentPerformanceExport;
use App\Exports\DebtsExport;
use App\Exports\RemittancesExport;
use App\Models\MoneyTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Remittance report with filters and summary cards.
     */
    public function remittances(Request $request): JsonResponse
    {
        $query = MoneyTransfer::with(['initiator:id,name,phone', 'agent:id,name,phone']);

        $this->applyCommonFilters($request, $query);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query->orderByDesc('created_at')->paginate($request->get('per_page', 25));

        $summary = $this->remittanceSummary(clone $query);

        return response()->json(array_merge($summary, [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]));
    }

    /**
     * Export remittances as xlsx, csv, or pdf.
     */
    public function exportRemittances(Request $request)
    {
        $filters = $request->only(['from', 'to', 'status', 'agent_id', 'initiated_by', 'has_debt', 'country_id']);
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            $query = MoneyTransfer::with(['initiator', 'agent', 'paymentMethod']);
            $this->applyCommonFilters($request, $query);
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            $remittances = $query->orderByDesc('created_at')->get();

            $html = view('exports.remittances-pdf', compact('remittances'))->render();
            $pdf = \Barryvdh\DomPDF\Facade::Pdf::loadHTML($html);
            return $pdf->download('remittances-report.pdf');
        }

        $fileName = 'remittances-export.' . ($format === 'csv' ? 'csv' : 'xlsx');
        return Excel::download(new RemittancesExport($filters), $fileName);
    }

    /**
     * Debt report with filters.
     */
    public function debts(Request $request): JsonResponse
    {
        $query = MoneyTransfer::with(['initiator:id,name,phone', 'agent:id,name,phone'])
            ->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            });

        $this->applyCommonFilters($request, $query);

        if ($request->filled('side')) {
            if ($request->input('side') === 'my_debts') {
                $query->where('requester_debt', true);
            } elseif ($request->input('side') === 'owed_to_me') {
                $query->where('executor_debt', true);
            }
        }

        $paginated = $query->orderByDesc('created_at')->paginate($request->get('per_page', 25));

        $debtsSummary = $this->debtsSummary(clone $query);

        return response()->json(array_merge($debtsSummary, [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]));
    }

    /**
     * Export debts as xlsx, csv, or pdf.
     */
    public function exportDebts(Request $request)
    {
        $filters = $request->only(['from', 'to', 'side']);
        $format = $request->input('format', 'xlsx');

        $fileName = 'debts-export.' . ($format === 'csv' ? 'csv' : 'xlsx');
        return Excel::download(new DebtsExport($filters), $fileName);
    }

    /**
     * Agent performance report.
     */
    public function agentPerformance(Request $request): JsonResponse
    {
        $query = User::role('Agent')
            ->with('country')
            ->withCount(['assignedMoneyTransfers as total_jobs']);

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        if ($request->filled('agent_id')) {
            $query->where('id', $request->integer('agent_id'));
        }

        $paginated = $query->orderByDesc('total_jobs')->paginate($request->get('per_page', 25));

        $paginated->getCollection()->transform(function ($agent) use ($request) {
            $completedJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->where('status', MoneyTransfer::STATUS_COMPLETED)
                ->when($request->filled('from'), fn($q) => $q->where('created_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn($q) => $q->where('created_at', '<=', $request->input('to')))
                ->count();

            $totalJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->when($request->filled('from'), fn($q) => $q->where('created_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn($q) => $q->where('created_at', '<=', $request->input('to')))
                ->count();

            $activeDebts = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->where('executor_debt', true)
                ->count();

            $avgRating = round((float) ($agent->ratingsReceived()->avg('rating') ?? 0), 1);

            $agent->completed_jobs = $completedJobs;
            $agent->total_jobs = $totalJobs;
            $agent->completion_rate = $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 100;
            $agent->active_debts = $activeDebts;
            $agent->average_rating = $avgRating;

            return $agent;
        });

        $summary = $this->agentPerformanceSummary();

        return response()->json(array_merge($summary, [
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]));
    }

    /**
     * Export agent performance as xlsx, csv, or pdf.
     */
    public function exportAgentPerformance(Request $request)
    {
        $filters = $request->only(['from', 'to', 'country_id', 'agent_id']);
        $format = $request->input('format', 'xlsx');

        $fileName = 'agent-performance-export.' . ($format === 'csv' ? 'csv' : 'xlsx');
        return Excel::download(new AgentPerformanceExport($filters), $fileName);
    }

    /**
     * Platform summary — aggregate stats across all remittances.
     */
    public function platformSummary(Request $request): JsonResponse
    {
        $from = $request->input('from');
        $to = $request->input('to');

        $baseQuery = MoneyTransfer::query()->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));

        $totalCount = (clone $baseQuery)->count();
        $totalVolume = (clone $baseQuery)->sum('send_amount');
        $completedCount = (clone $baseQuery)->where('status', MoneyTransfer::STATUS_COMPLETED)->count();

        $debtsCount = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            })
            ->count();

        $requesterDebtVolume = (clone $baseQuery)->where('requester_debt', true)->sum('send_amount');
        $executorDebtVolume = (clone $baseQuery)->where('executor_debt', true)->sum('send_amount');

        $statusBreakdown = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(send_amount), 0) as volume'))
            ->groupBy('status')
            ->get();

        $activeAgents = User::role('Agent')->where('is_agent_available', true)->count();
        $totalAgents = User::role('Agent')->count();

        return response()->json([
            'total_remittances' => $totalCount,
            'total_volume' => round($totalVolume, 2),
            'completed_count' => $completedCount,
            'completion_rate' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0,
            'debts_count' => $debtsCount,
            'requester_debt_volume' => round($requesterDebtVolume, 2),
            'executor_debt_volume' => round($executorDebtVolume, 2),
            'active_agents' => $activeAgents,
            'total_agents' => $totalAgents,
            'status_breakdown' => $statusBreakdown,
            'period' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Download a single remittance detail as PDF.
     */
    public function downloadRemittancePdf(int $id)
    {
        $remittance = MoneyTransfer::with([
            'initiator', 'agent', 'paymentMethod', 'events.user',
        ])->findOrFail($id);

        $html = view('exports.remittance-detail-pdf', compact('remittance'))->render();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        return $pdf->download("remittance-{$remittance->reference}.pdf");
    }

    // -- Helpers --

    protected function applyCommonFilters(Request $request, $query): void
    {
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('agent_id')) {
            $query->where('assigned_agent_id', $request->integer('agent_id'));
        }
        if ($request->filled('initiated_by')) {
            $query->where('initiated_by', $request->integer('initiated_by'));
        }
        if ($request->filled('country_id')) {
            $query->whereHas('agent', fn($q) => $q->where('country_id', $request->integer('country_id')));
        }
        if ($request->filled('has_debt')) {
            $query->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            });
        }
    }

    protected function remittanceSummary($query): array
    {
        $total = (clone $query)->count();
        $volume = (clone $query)->sum('send_amount');
        $completed = (clone $query)->where('status', MoneyTransfer::STATUS_COMPLETED)->count();
        $debts = (clone $query)->where(function ($q) {
            $q->where('requester_debt', true)->orWhere('executor_debt', true);
        })->count();

        return [
            'summary_total' => $total,
            'summary_volume' => round($volume, 2),
            'summary_completed' => $completed,
            'summary_completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'summary_debts' => $debts,
        ];
    }

    protected function debtsSummary($query): array
    {
        $total = (clone $query)->count();
        $volume = (clone $query)->sum('send_amount');
        $requesterDebtVolume = (clone $query)->where('requester_debt', true)->sum('send_amount');
        $executorDebtVolume = (clone $query)->where('executor_debt', true)->sum('send_amount');

        return [
            'summary_total' => $total,
            'summary_volume' => round($volume, 2),
            'summary_requester_debt_volume' => round($requesterDebtVolume, 2),
            'summary_executor_debt_volume' => round($executorDebtVolume, 2),
        ];
    }

    protected function agentPerformanceSummary(): array
    {
        $totalAgents = User::role('Agent')->count();
        $activeAgents = User::role('Agent')->where('is_agent_available', true)->count();

        $allCompleted = MoneyTransfer::where('status', MoneyTransfer::STATUS_COMPLETED)->count();
        $allExecuted = MoneyTransfer::whereIn('status', [
            MoneyTransfer::STATUS_COMPLETED, MoneyTransfer::STATUS_EXECUTED,
        ])->count();

        return [
            'summary_total_agents' => $totalAgents,
            'summary_active_agents' => $activeAgents,
            'summary_overall_completion_rate' => $allExecuted > 0 ? round(($allCompleted / $allExecuted) * 100, 1) : 0,
        ];
    }
}
