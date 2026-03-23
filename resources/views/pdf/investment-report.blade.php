<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investment Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 700; color: #1e3a8a; }
        .meta { color: #475569; font-size: 11px; margin-top: 4px; }
        .title { font-size: 18px; font-weight: 700; margin: 0; }
        .section { margin-top: 18px; }
        .section h3 { margin: 0 0 8px; font-size: 14px; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #eff6ff; font-size: 11px; text-transform: uppercase; color: #1e3a8a; }
        .kpi-grid { margin-top: 10px; }
        .kpi { width: 48%; display: inline-block; margin-bottom: 8px; border: 1px solid #dbeafe; background: #f8fbff; padding: 8px; }
        .kpi .label { color: #475569; font-size: 11px; }
        .kpi .value { font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        .risk-low { color: #059669; font-weight: 700; }
        .risk-medium { color: #d97706; font-weight: 700; }
        .risk-high { color: #dc2626; font-weight: 700; }
        ul { padding-left: 16px; margin: 8px 0 0; }
        li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Oia Properties - Investment Intelligence</div>
        <div class="meta">Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
    </div>

    <h1 class="title">{{ $investment->name }}</h1>
    <div class="meta">
        Scenario: {{ $scenario?->scenario_name ?? 'Current Scenario' }}
        @if($scenario?->version)
            (v{{ $scenario->version }})
        @endif
    </div>

    <div class="section">
        <h3>Key Performance Indicators</h3>
        <div class="kpi-grid">
            @foreach(($calculation['metricCards'] ?? []) as $card)
                <div class="kpi">
                    <div class="label">{{ $card['label'] ?? '-' }}</div>
                    <div class="value">{{ $card['value'] ?? '-' }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h3>Risk Analysis</h3>
        @php
            $risk = $calculation['risk'] ?? [];
            $riskClass = 'risk-medium';
            if (($risk['color'] ?? null) === 'success') { $riskClass = 'risk-low'; }
            if (($risk['color'] ?? null) === 'danger') { $riskClass = 'risk-high'; }
        @endphp
        <p class="{{ $riskClass }}">
            {{ $risk['label'] ?? 'Unknown Risk' }} ({{ number_format($risk['value'] ?? 0, 0) }}/100)
        </p>
    </div>

    <div class="section">
        <h3>Scenario Comparison</h3>
        <table>
            <thead>
                <tr>
                    <th>Scenario</th>
                    <th>ROI</th>
                    <th>Cash Flow</th>
                    <th>Risk</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($calculation['scenarioRows'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['name'] ?? '-' }}</td>
                        <td>{{ isset($row['roi']) ? number_format($row['roi'], 2).'%' : '-' }}</td>
                        <td>{{ isset($row['cashFlow']) ? '$'.number_format($row['cashFlow'], 0) : '-' }}</td>
                        <td>{{ $row['risk'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Cash Flow Projection</h3>
        <table>
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Rent</th>
                    <th>Expenses</th>
                    <th>Cash Flow</th>
                    <th>ROI</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($calculation['annualProjection'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['year'] ?? '-' }}</td>
                        <td>{{ isset($row['rent']) ? '$'.number_format($row['rent'], 0) : '-' }}</td>
                        <td>{{ isset($row['expenses']) ? '$'.number_format($row['expenses'], 0) : '-' }}</td>
                        <td>{{ isset($row['cashFlow']) ? '$'.number_format($row['cashFlow'], 0) : '-' }}</td>
                        <td>{{ isset($row['roi']) ? number_format($row['roi'], 2).'%' : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>AI Insights</h3>
        <ul>
            @foreach(($calculation['aiInsights'] ?? []) as $insight)
                <li>{{ $insight['title'] ?? 'Insight' }}: {{ $insight['message'] ?? '-' }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
