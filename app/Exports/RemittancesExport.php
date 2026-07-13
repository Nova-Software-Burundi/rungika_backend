<?php

namespace App\Exports;

use App\Models\MoneyTransfer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RemittancesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = MoneyTransfer::with(['initiator', 'agent', 'paymentMethod']);

        if (!empty($this->filters['from'])) {
            $query->where('created_at', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->where('created_at', '<=', $this->filters['to']);
        }
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['agent_id'])) {
            $query->where('assigned_agent_id', $this->filters['agent_id']);
        }
        if (!empty($this->filters['initiated_by'])) {
            $query->where('initiated_by', $this->filters['initiated_by']);
        }
        if (!empty($this->filters['has_debt'])) {
            $query->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            });
        }
        if (!empty($this->filters['country_id'])) {
            $query->whereHas('agent', function ($q) {
                $q->where('country_id', $this->filters['country_id']);
            });
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Reference', 'Requester', 'Agent', 'Status', 'Send Amount', 'Send Currency',
            'Destinator Name', 'Destinator Phone', 'Payment Method',
            'Requester Debt', 'Executor Debt', 'Agent Notes', 'Payout Reference',
            'Created At', 'Accepted At', 'Executed At', 'Completed At',
        ];
    }

    public function map($transfer): array
    {
        return [
            $transfer->reference,
            $transfer->initiator?->name ?? $transfer->sender_name,
            $transfer->agent?->name ?? 'N/A',
            $transfer->status,
            $transfer->send_amount,
            $transfer->send_currency ?? 'USD',
            $transfer->destinator_name,
            $transfer->destinator_phone ?? 'N/A',
            $transfer->paymentMethod?->name ?? 'N/A',
            $transfer->requester_debt ? 'Yes' : 'No',
            $transfer->executor_debt ? 'Yes' : 'No',
            $transfer->agent_notes ?? '',
            $transfer->payout_reference ?? '',
            $transfer->created_at,
            $transfer->accepted_at,
            $transfer->executed_at,
            $transfer->completed_at,
        ];
    }
}
