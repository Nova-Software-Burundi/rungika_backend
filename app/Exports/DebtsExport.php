<?php

namespace App\Exports;

use App\Models\MoneyTransfer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DebtsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = MoneyTransfer::with(['initiator', 'agent'])
            ->where(function ($q) {
                $q->where('requester_debt', true)->orWhere('executor_debt', true);
            });

        if (!empty($this->filters['from'])) {
            $query->where('created_at', '>=', $this->filters['from']);
        }
        if (!empty($this->filters['to'])) {
            $query->where('created_at', '<=', $this->filters['to']);
        }
        if (!empty($this->filters['side'])) {
            if ($this->filters['side'] === 'my_debts') {
                $query->where('requester_debt', true);
            } elseif ($this->filters['side'] === 'owed_to_me') {
                $query->where('executor_debt', true);
            }
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Reference', 'Requester', 'Agent', 'Amount', 'Currency',
            'Debt Side', 'Status', 'Days Outstanding', 'Created At',
        ];
    }

    public function map($transfer): array
    {
        $debtSide = '';
        if ($transfer->requester_debt && $transfer->executor_debt) {
            $debtSide = 'Both';
        } elseif ($transfer->requester_debt) {
            $debtSide = 'Requester';
        } elseif ($transfer->executor_debt) {
            $debtSide = 'Executor';
        }

        $daysOutstanding = $transfer->completed_at
            ? now()->diffInDays($transfer->completed_at)
            : now()->diffInDays($transfer->created_at);

        return [
            $transfer->reference,
            $transfer->initiator?->name ?? 'N/A',
            $transfer->agent?->name ?? 'N/A',
            $transfer->send_amount,
            $transfer->send_currency ?? 'USD',
            $debtSide,
            $transfer->status,
            $daysOutstanding,
            $transfer->created_at,
        ];
    }
}
