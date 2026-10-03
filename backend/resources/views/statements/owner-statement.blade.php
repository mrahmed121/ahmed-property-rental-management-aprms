<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; }
  .header { border-bottom: 3px solid #d4af37; padding-bottom: 12px; margin-bottom: 20px; }
  .header h1 { margin: 0; font-size: 22px; }
  .header .tagline { color: #b87333; font-size: 11px; }
  .meta { width: 100%; margin-bottom: 16px; }
  .meta td { padding: 4px 8px 4px 0; vertical-align: top; }
  .label { color: #666; font-size: 10px; text-transform: uppercase; }
  table.lines { width: 100%; border-collapse: collapse; margin: 12px 0; }
  table.lines th { background: #12161d; color: #d4af37; text-align: left; padding: 8px; font-size: 11px; }
  table.lines td { border-bottom: 1px solid #ddd; padding: 8px; font-size: 11px; }
  .right { text-align: right; }
  .totals { width: 60%; margin-left: auto; margin-top: 12px; }
  .totals td { padding: 5px 8px; }
  .totals .net { font-size: 15px; font-weight: bold; border-top: 2px solid #d4af37; }
  .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 10px; color: #666; }
</style>
</head>
<body>
<div class="header">
  <h1>Owner Statement</h1>
  <div class="tagline">Ahmed — Own Every Square Foot. · Developed by Ahmed</div>
</div>

<table class="meta">
  <tr>
    <td><div class="label">Statement No.</div><strong>{{ $statement->statement_number }}</strong></td>
    <td><div class="label">Owner</div>{{ $statement->owner?->name }}</td>
    <td><div class="label">Status</div>{{ ucfirst($statement->status) }}</td>
  </tr>
  <tr>
    <td><div class="label">Period</div>{{ $statement->period?->start_date->toDateString() }} → {{ $statement->period?->end_date->toDateString() }}</td>
    <td><div class="label">Currency</div>{{ $statement->currency }}</td>
    <td><div class="label">Generated</div>{{ $statement->created_at->toDateTimeString() }}</td>
  </tr>
</table>

<table class="lines">
  <thead>
    <tr><th>Date</th><th>Description</th><th>Property</th><th>Reference</th><th class="right">Amount</th></tr>
  </thead>
  <tbody>
    @foreach($statement->lines as $line)
    <tr>
      <td>{{ $line->line_date->toDateString() }}</td>
      <td>{{ $line->description }}</td>
      <td>{{ $line->property?->name ?? '—' }}</td>
      <td>{{ $line->reference ?? '—' }}</td>
      <td class="right">{{ number_format($line->amount, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<table class="totals">
  <tr><td>Gross Income</td><td class="right">{{ number_format($statement->gross_income, 2) }}</td></tr>
  <tr><td>Management Fee ({{ number_format($statement->management_fee_percent, 2) }}%)</td><td class="right">({{ number_format($statement->management_fee, 2) }})</td></tr>
  <tr><td>Owner Expenses</td><td class="right">({{ number_format($statement->owner_expenses, 2) }})</td></tr>
  <tr><td>Owner Maintenance</td><td class="right">({{ number_format($statement->owner_maintenance, 2) }})</td></tr>
  <tr><td>Owner Utility Absorption</td><td class="right">({{ number_format($statement->owner_utility_absorption, 2) }})</td></tr>
  <tr><td>Adjustments</td><td class="right">{{ number_format($statement->adjustments_total, 2) }}</td></tr>
  <tr class="net"><td>Net Amount</td><td class="right">{{ number_format($statement->net_amount, 2) }} {{ $statement->currency }}</td></tr>
</table>

<div class="footer">
  @if($statement->approved_by)
  Approved by {{ $statement->approvedBy?->name }} on {{ $statement->approved_at?->toDateTimeString() }}.<br>
  @endif
  @if($statement->finalized_by)
  Finalized by {{ $statement->finalizedBy?->name }} on {{ $statement->finalized_at?->toDateTimeString() }}.<br>
  @endif
  This statement is generated from APRMS financial records. Income is accrual-based (rent invoices charged).
</div>
</body>
</html>
