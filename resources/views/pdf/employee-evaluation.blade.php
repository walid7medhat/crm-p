<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Evaluation</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .header { border-bottom: 2px solid #733E87; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 700; color: #0B0736; }
        .meta { color: #475569; font-size: 11px; margin-top: 4px; }
        .title { font-size: 18px; font-weight: 700; margin: 16px 0 4px; }
        .info-grid { margin-bottom: 16px; }
        .info-row { margin-bottom: 4px; }
        .info-label { display: inline-block; width: 140px; color: #475569; font-size: 11px; }
        .info-value { font-weight: 700; }
        .section { margin-top: 18px; }
        .section h3 { margin: 0 0 8px; font-size: 14px; color: #733E87; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; font-size: 11px; }
        th { background: #f3ecf7; text-transform: uppercase; color: #0B0736; }
        .q-col { width: 65%; }
        .a-col { width: 35%; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Oia Properties - Employee Evaluation</div>
        <div class="meta">Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
    </div>

    <h1 class="title">{{ $employeeName }}</h1>
    <div class="info-grid">
        <div class="info-row"><span class="info-label">Designation</span><span class="info-value">{{ $designationName ?? 'N/A' }}</span></div>
        <div class="info-row"><span class="info-label">Milestone</span><span class="info-value">{{ $milestoneMonths }} months</span></div>
        <div class="info-row"><span class="info-label">Evaluated by</span><span class="info-value">{{ $evaluatorName }}</span></div>
        <div class="info-row"><span class="info-label">Submitted on</span><span class="info-value">{{ $submittedAt->format('Y-m-d') }}</span></div>
    </div>

    @foreach($sections as $section)
        <div class="section">
            <h3>{{ $section['title'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th class="q-col">Question</th>
                        <th class="a-col">Answer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['questions'] as $question)
                        <tr>
                            <td class="q-col">{{ $question['question_text'] }}</td>
                            <td class="a-col">{{ $question['answer_value'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
