<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Marketing Daily Report — {{ $r['date'] }}</title>
    <style>
        @page { margin: 22px 26px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f1a2e; font-size: 11px; line-height: 1.45; margin: 0; }

        .head-wrap { border-bottom: 3px solid #733E87; margin-bottom: 14px; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: bottom; padding: 0 0 10px; }
        .eyebrow { font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase; color: #733E87; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; margin-top: 2px; }
        .meta { text-align: right; font-size: 9px; color: #6b6580; }

        .totals { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 16px; }
        .totals td { width: 20%; padding: 9px 10px; border: 1px solid #e7e2ee; border-radius: 6px; background: #f8f6fb; }
        .totals .num { font-size: 18px; font-weight: bold; color: #1f1a2e; line-height: 1.1; }
        .totals .lbl { font-size: 9px; color: #6b6580; text-transform: uppercase; letter-spacing: .5px; }
        .totals td.alert { background: #fdf0f0; border-color: #f3c9c9; }
        .totals td.alert .num { color: #b42318; }

        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #e7e2ee; color: #733E87; }
        h2 .count { float: right; font-size: 9.5px; font-weight: normal; color: #6b6580; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th { font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b6580; text-align: left; padding: 5px 6px; background: #f3effa; border-bottom: 1px solid #e0d8eb; }
        table.data td { padding: 6px; border-bottom: 1px solid #efebf4; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        table.data .n { text-align: center; width: 9%; }
        table.data .strong { font-weight: bold; }
        table.data tfoot td { font-weight: bold; background: #f8f6fb; border-top: 1px solid #e0d8eb; }
        .late { color: #b42318; white-space: nowrap; }
        .muted { color: #8a8499; }
        .none { padding: 9px 10px; background: #f8f6fb; border-radius: 6px; color: #6b6580; }

        .foot { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 8.5px; color: #8a8499; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">OIA Properties · Marketing daily report · {{ $r['date_label'] }}</div>

    <div class="head-wrap">
    <table class="head">
        <tr>
            <td>
                <div class="eyebrow">Marketing · Daily report</div>
                <div class="title">{{ $r['date_label'] }}</div>
            </td>
            <td class="meta">
                Generated {{ $r['generated_at'] }}@if($r['generated_by'])<br>by {{ $r['generated_by'] }}@endif
            </td>
        </tr>
    </table>
    </div>

    @php $t = $r['totals']; @endphp
    <table class="totals">
        <tr>
            <td><div class="num">{{ $t['created'] }}</div><div class="lbl">Created</div></td>
            <td><div class="num">{{ $t['finished'] }}</div><div class="lbl">Finished</div></td>
            <td><div class="num">{{ $t['posts_posted'] }}/{{ $t['posts_scheduled'] }}</div><div class="lbl">Posted</div></td>
            <td><div class="num">{{ count($r['team']) }}</div><div class="lbl">Active members</div></td>
            <td class="{{ $t['overdue'] ? 'alert' : '' }}"><div class="num">{{ $t['overdue'] }}</div><div class="lbl">Overdue</div></td>
        </tr>
    </table>

    <h2>Team activity <span class="count">{{ count($r['team']) }} active</span></h2>
    @if(count($r['team']))
        <table class="data">
            <thead>
                <tr>
                    <th>Member</th>
                    <th class="n">Created</th>
                    <th class="n">Moved</th>
                    <th class="n">Finished</th>
                    <th class="n">Links</th>
                    <th class="n">Comments</th>
                    <th class="n">Posted</th>
                    <th class="n">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($r['team'] as $m)
                    <tr>
                        <td class="strong">{{ $m['name'] }}</td>
                        <td class="n">{{ $m['created'] }}</td>
                        <td class="n">{{ $m['moved'] }}</td>
                        <td class="n strong">{{ $m['finished'] }}</td>
                        <td class="n">{{ $m['links'] }}</td>
                        <td class="n">{{ $m['comments'] }}</td>
                        <td class="n">{{ $m['posted'] }}</td>
                        <td class="n strong">{{ $m['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="none">No marketing activity on this day.</div>
    @endif

    <h2>Finished <span class="count">{{ count($r['finished']) }}</span></h2>
    @if(count($r['finished']))
        <table class="data">
            <thead><tr><th>Card</th><th>Board</th><th>People</th><th>Time</th><th>Link</th></tr></thead>
            <tbody>
                @foreach($r['finished'] as $f)
                    <tr>
                        <td class="strong">{{ $f['title'] }}</td>
                        <td>{{ $f['board'] }}</td>
                        <td>{{ $f['people'] }}</td>
                        <td style="white-space:nowrap">{{ $f['time'] }}</td>
                        <td>{!! $f['has_link'] ? 'Yes' : '<span class="late">Missing</span>' !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="none">Nothing reached Done on this day.</div>
    @endif

    <h2>Overdue <span class="count">{{ $t['overdue'] }} open past due{{ $t['overdue'] > count($r['overdue']) ? ' · showing '.count($r['overdue']) : '' }}</span></h2>
    @if(count($r['overdue']))
        <table class="data">
            <thead><tr><th>Card</th><th>Board</th><th>Stage</th><th>Due</th><th>People</th></tr></thead>
            <tbody>
                @foreach($r['overdue'] as $o)
                    <tr>
                        <td class="strong">{{ $o['title'] }}</td>
                        <td>{{ $o['board'] }}</td>
                        <td style="white-space:nowrap">{{ $o['stage'] }}</td>
                        <td class="late">{{ $o['due'] }}<br><span class="muted">{{ $o['days_late'] }} day{{ $o['days_late'] === 1 ? '' : 's' }} late</span></td>
                        <td>{{ $o['people'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="none">Nothing overdue.</div>
    @endif
</body>
</html>
