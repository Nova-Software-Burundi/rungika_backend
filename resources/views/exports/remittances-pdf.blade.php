<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Remittances Report</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #64748b; font-size: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: white; padding: 8px 6px; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
        .badge-pending { background: #dbeafe; color: #1d4ed8; }
        .badge-accepted { background: #fef3c7; color: #b45309; }
        .badge-executed { background: #e0e7ff; color: #4338ca; }
        .badge-completed { background: #d1fae5; color: #059669; }
        .badge-disputed { background: #fce7f3; color: #be185d; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .footer { margin-top: 20px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>Remittances Report</h1>
    <p class="subtitle">Generated {{ now()->format('Y-m-d H:i') }} &middot; {{ count($remittances) }} records</p>
    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Requester</th>
                <th>Agent</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Debt</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($remittances as $r)
                <tr>
                    <td>{{ $r->reference }}</td>
                    <td>{{ $r->initiator?->name ?? $r->sender_name }}</td>
                    <td>{{ $r->agent?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($r->send_amount, 2) }} {{ $r->send_currency ?? 'USD' }}</td>
                    <td><span class="badge badge-{{ $r->status }}">{{ $r->status }}</span></td>
                    <td>{{ $r->hasDebt() ? ($r->requester_debt ? 'Requester' : '') . ($r->requester_debt && $r->executor_debt ? ' + ' : '') . ($r->executor_debt ? 'Executor' : '') : 'None' }}</td>
                    <td>{{ $r->created_at->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">{{ config('app-custom.brand_name') }} Platform &mdash; Remittance Report</div>
</body>
</html>
