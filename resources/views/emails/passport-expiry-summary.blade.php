<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Passport Expiry Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 0 0 8px 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #4F46E5;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .urgent {
            color: #dc2626;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Passport Expiry Report</h1>
            <p>{{ $date }}</p>
        </div>
        <div class="content">
            <p>Dear <strong>{{ $hrManager->name }}</strong>,</p>
            
            <p>Please find below the list of employees whose passports are expiring within the next <strong>{{ $daysThreshold }} days</strong>.</p>
            
            <h3>⚠️ Action Required</h3>
            <p>Please coordinate with the following employees to renew their passports:</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Employee Code</th>
                        <th>Passport Number</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $emp)
                    <tr>
                        <td>{{ $emp['Name'] }}</td>
                        <td>{{ $emp['Employee Code'] }}</td>
                        <td>{{ $emp['Passport No.'] }}</td>
                        <td>{{ $emp['Expiry Date'] }}</td>
                        <td class="{{ str_contains($emp['Urgency'], 'URGENT') ? 'urgent' : (str_contains($emp['Urgency'], 'Warning') ? 'warning' : '') }}">
                            {{ $emp['Days Left'] }} days
                            @if(str_contains($emp['Urgency'], 'URGENT'))
                                (URGENT)
                            @elseif(str_contains($emp['Urgency'], 'Warning'))
                                (Warning)
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <p><strong>Total employees affected:</strong> {{ count($employees) }}</p>
            
            <hr>
            
            <h3>📌 Next Steps</h3>
            <ul>
                <li>Contact employees whose passports expire within 7 days immediately</li>
                <li>Schedule renewal appointments for passports expiring within 30 days</li>
                <li>Update the HR system once passports are renewed</li>
            </ul>
            
            <p>For any questions, please contact the HR department.</p>
        </div>
        <div class="footer">
            <p>This is an automated notification from the HR Management System.</p>
            <p>© {{ date('Y') }} Your Company Name. All rights reserved.</p>
        </div>
    </div>
</body>
</html>