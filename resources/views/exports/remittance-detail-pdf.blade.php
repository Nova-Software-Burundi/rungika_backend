<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Remittance {{ $remittance->reference }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1e293b; padding: 20px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; margin: 0; }
        .header .ref { color: #64748b; font-size: 14px; }
        .section { margin-bottom: 16px; }
        .section h2 { font-size: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        td.label { font-weight: bold; width: 180px; color: #64748b; font-size: 11px; padding: 4px 8px 4px 0; }
        td.value { padding: 4px 0; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-pending { background: #dbeafe; color: #1d4ed8; }
        .badge-accepted { background: #fef3c7; color: #b45309; }
        .badge-executed { background: #e0e7ff; color: #4338ca; }
        .badge-completed { background: #d1fae5; color: #059669; }
        .badge-disputed { background: #fce7f3; color: #be185d; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .events { margin-top: 8px; }
        .event { padding: 4px 0; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Remittance Detail</h1>
        <div class="ref">{{ $remittance->reference }}</div>
    </div>

    <div class="section">
        <h2>Status</h2>
        <span class="badge badge-{{ $remittance->status }}">{{ $remittance->status }}</span>
        @if ($remittance->hasDebt())
            <span style="color: #b91c1c; font-size: 11px; margin-left: 8px;">
                Debt: {{ $remittance->requester_debt ? 'Requester ' : '' }}{{ $remittance->executor_debt ? 'Executor' : '' }}
            </span>
        @endif
    </div>

    <div class="section">
        <h2>Request Details</h2>
        <table>
            <tr><td class="label">Requester</td><td class="value">{{ $remittance->initiator?->name ?? $remittance->sender_name }}</td></tr>
            <tr><td class="label">Phone</td><td class="value">{{ $remittance->initiator?->phone ?? $remittance->sender_phone }}</td></tr>
            <tr><td class="label">Agent</td><td class="value">{{ $remittance->agent?->name ?? 'N/A' }}</td></tr>
            <tr><td class="label">Send Amount</td><td class="value">{{ number_format($remittance->send_amount, 2) }} {{ $remittance->send_currency ?? 'USD' }}</td></tr>
            <tr><td class="label">Notes</td><td class="value">{{ $remittance->notes ?? '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Destinator</h2>
        <table>
            <tr><td class="label">Name</td><td class="value">{{ $remittance->destinator_name }}</td></tr>
            <tr><td class="label">Phone</td><td class="value">{{ $remittance->destinator_phone ?? '—' }}</td></tr>
            <tr><td class="label">Address</td><td class="value">{{ $remittance->destinator_address ?? '—' }}</td></tr>
            <tr><td class="label">Payment Method</td><td class="value">{{ $remittance->paymentMethod?->name ?? '—' }}</td></tr>
            <tr><td class="label">Account Number</td><td class="value">{{ $remittance->destinator_account_number ?? '—' }}</td></tr>
        </table>
    </div>

    @if ($remittance->events->isNotEmpty())
        <div class="section">
            <h2>Event History</h2>
            <div class="events">
                @foreach ($remittance->events as $ev)
                    <div class="event">
                        <strong>{{ $ev->user?->name ?? 'System' }}</strong>
                        &mdash; {{ $ev->type }} 
                        @if ($ev->from_status) ({{ $ev->from_status }} &rarr; {{ $ev->to_status }}) @endif
                        <span style="float: right; color: #94a3b8;">{{ $ev->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        Rungika Platform &mdash; Generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
