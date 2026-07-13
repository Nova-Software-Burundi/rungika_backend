<?php

namespace App\Exports;

use App\Models\MoneyTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AgentPerformanceExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = User::role('Agent')
            ->with('country')
            ->withCount(['assignedMoneyTransfers as total_jobs']);

        if (!empty($this->filters['country_id'])) {
            $query->where('country_id', $this->filters['country_id']);
        }

        if (!empty($this->filters['agent_id'])) {
            $query->where('id', $this->filters['agent_id']);
        }

        $agents = $query->get();

        return $agents->map(function ($agent) {
            $completedJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->where('status', MoneyTransfer::STATUS_COMPLETED)
                ->when(!empty($this->filters['from']), fn($q) => $q->where('created_at', '>=', $this->filters['from']))
                ->when(!empty($this->filters['to']), fn($q) => $q->where('created_at', '<=', $this->filters['to']))
                ->count();

            $totalJobs = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->when(!empty($this->filters['from']), fn($q) => $q->where('created_at', '>=', $this->filters['from']))
                ->when(!empty($this->filters['to']), fn($q) => $q->where('created_at', '<=', $this->filters['to']))
                ->count();

            $activeDebts = MoneyTransfer::where('assigned_agent_id', $agent->id)
                ->where('executor_debt', true)
                ->count();

            $avgRating = round((float) ($agent->ratingsReceived()->avg('rating') ?? 0), 1);

            return (object) [
                'name' => $agent->name,
                'country' => $agent->country?->name ?? 'N/A',
                'total_jobs' => $totalJobs,
                'completed_jobs' => $completedJobs,
                'completion_rate' => $totalJobs > 0 ? round(($completedJobs / $totalJobs) * 100, 1) : 100,
                'active_debts' => $activeDebts,
                'avg_rating' => $avgRating,
                'is_available' => $agent->is_agent_available ? 'Yes' : 'No',
                'last_active' => $agent->last_activity_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name', 'Country', 'Total Jobs', 'Completed Jobs',
            'Completion Rate (%)', 'Active Debts', 'Avg Rating',
            'Available', 'Last Active',
        ];
    }

    public function map($agent): array
    {
        return [
            $agent->name,
            $agent->country,
            $agent->total_jobs,
            $agent->completed_jobs,
            $agent->completion_rate,
            $agent->active_debts,
            $agent->avg_rating,
            $agent->is_available,
            $agent->last_active,
        ];
    }
}
