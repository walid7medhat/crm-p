<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Owner Financial Intelligence</title>
    <style>
        @page { margin: 18px 22px 28px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f1a2e; font-size: 10px; line-height: 1.4; margin: 0; }

        .head-wrap { border-bottom: 3px solid #733E87; margin-bottom: 12px; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: bottom; padding: 0 0 8px; }
        .eyebrow { font-size: 8.5px; letter-spacing: 1.4px; text-transform: uppercase; color: #733E87; font-weight: bold; }
        .title { font-size: 16px; font-weight: bold; margin-top: 2px; color: #3f2148; }
        .meta { text-align: right; font-size: 8.5px; color: #6b6580; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 12px; }
        .kpis td { width: 20%; padding: 8px 9px; border: 1px solid #e7dcec; border-radius: 6px; background: #f7f2f8; vertical-align: top; }
        .kpis .num { font-size: 13px; font-weight: bold; color: #3f2148; line-height: 1.15; white-space: nowrap; }
        .kpis .lbl { font-size: 8px; color: #6b6580; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }
        .kpis td.hero { background: #efe6f3; border-color: #d4c0de; }
        .kpis td.hero .num { color: #733E87; }
        .kpis td.warn { background: #fdf0f2; border-color: #f5c6cd; }
        .kpis td.warn .num { color: #c2283a; }

        h2 { font-size: 11px; margin: 12px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e7dcec; color: #733E87; }
        h2 .count { float: right; font-size: 8.5px; font-weight: normal; color: #6b6580; }

        .filters { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .filters td { padding: 3px 6px 3px 0; font-size: 9px; vertical-align: top; }
        .filters .k { color: #6b6580; width: 22%; }
        .filters .v { color: #1f1a2e; font-weight: bold; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            font-size: 7.5px; text-transform: uppercase; letter-spacing: .35px; color: #3f2148;
            text-align: left; padding: 5px 4px; background: #f7f2f8; border-bottom: 1px solid #e7dcec;
        }
        table.data th.n, table.data td.n { text-align: right; white-space: nowrap; }
        table.data td { padding: 4px; border-bottom: 1px solid #efeaf3; vertical-align: top; font-size: 8px; }
        table.data tr { page-break-inside: avoid; }
        table.data .strong { font-weight: bold; }
        table.data .muted { color: #8a8499; }
        table.data .warn { color: #c2283a; font-weight: bold; }
        .note { margin: 6px 0 0; font-size: 8.5px; color: #6b6580; }
        .none { padding: 8px 10px; background: #f7f2f8; border-radius: 6px; color: #6b6580; }

        .foot {
            position: fixed; bottom: -16px; left: 0; right: 0;
            font-size: 8px; color: #8a8499; text-align: center;
        }
        .page-num:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="foot">
        OIA Properties · Owner Financial Intelligence · {{ $generated_at }}
        · Page <span class="page-num"></span>
    </div>

    <div class="head-wrap">
        <table class="head">
            <tr>
                <td>
                    <div class="eyebrow">OIA Properties</div>
                    <div class="title">Owner Financial Intelligence</div>
                </td>
                <td class="meta">
                    Generated {{ $generated_at }}<br>
                    Amounts in AED · Commission from stored deal data
                </td>
            </tr>
        </table>
    </div>

    <table class="kpis">
        <tr>
            <td class="hero">
                <div class="num">{{ number_format((float) $summary['net_revenue'], 2) }}</div>
                <div class="lbl">Net Company Revenue</div>
            </td>
            <td>
                <div class="num">{{ number_format((float) $summary['gross_commission'], 2) }}</div>
                <div class="lbl">Gross Commission</div>
            </td>
            <td>
                <div class="num">{{ number_format((float) $summary['agent_commission'], 2) }}</div>
                <div class="lbl">Agent Commission</div>
            </td>
            <td>
                <div class="num">{{ number_format((int) $summary['closed_deals']) }}</div>
                <div class="lbl">Closed Deals</div>
            </td>
            <td class="{{ ((int) $summary['incomplete_deals']) ? 'warn' : '' }}">
                <div class="num">{{ number_format((int) $summary['incomplete_deals']) }}</div>
                <div class="lbl">Missing Data</div>
            </td>
        </tr>
    </table>

    @if(count($filters))
        <h2>Selected filters</h2>
        <table class="filters">
            @foreach($filters as $label => $value)
                <tr>
                    <td class="k">{{ $label }}</td>
                    <td class="v">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>
        Deals
        <span class="count">
            @if($truncated)
                Showing {{ $shown_rows }} of {{ number_format($total_rows) }} · full set via Excel
            @else
                {{ number_format($total_rows) }} deals
            @endif
        </span>
    </h2>

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th>Deal #</th>
                    <th>Property</th>
                    <th>Client</th>
                    <th>Agent</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th class="n">Deal Amount</th>
                    <th class="n">Gross</th>
                    <th class="n">Agent</th>
                    <th class="n">External</th>
                    <th class="n">Net Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td class="strong">{{ $row['deal_number'] ?: ('#'.$row['id']) }}</td>
                        <td>{{ $row['property'] ?: '—' }}</td>
                        <td>
                            {{ $row['client']['name'] ?? '—' }}
                            @if(!empty($row['client']['phone']))
                                <br><span class="muted">{{ $row['client']['phone'] }}</span>
                            @endif
                        </td>
                        <td>{{ $row['agent']['name'] ?? '—' }}</td>
                        <td>{{ $row['branch'] ?: '—' }}</td>
                        <td>{{ $row['deal_type_label'] ?: '—' }}</td>
                        <td style="white-space:nowrap">{{ $row['deal_date'] ?: '—' }}</td>
                        <td class="n">{{ $row['deal_amount'] === null ? '—' : number_format((float) $row['deal_amount'], 2) }}</td>
                        <td class="n">{{ $row['gross_commission'] === null ? '—' : number_format((float) $row['gross_commission'], 2) }}</td>
                        <td class="n">{{ $row['agent_commission'] === null ? '—' : number_format((float) $row['agent_commission'], 2) }}</td>
                        <td class="n">{{ $row['external_commission'] === null ? '—' : number_format((float) $row['external_commission'], 2) }}</td>
                        <td class="n">
                            @if(!empty($row['is_incomplete']))
                                <span class="warn">Incomplete</span>
                            @else
                                <strong>{{ $row['net_revenue'] === null ? '—' : number_format((float) $row['net_revenue'], 2) }}</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($truncated)
            <p class="note">PDF shows the first {{ $shown_rows }} deals for readability. Export Excel for the complete filtered dataset ({{ number_format($total_rows) }} deals).</p>
        @endif
    @else
        <div class="none">No deals match these filters.</div>
    @endif

    <p class="note">
        Collected amount, outstanding amount and expenses are not shown because the CRM has no payment ledger.
        Agent / external / net totals exclude deals with incomplete financial data.
    </p>
</body>
</html>
