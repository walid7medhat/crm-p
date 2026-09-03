{{--
    Subject line (use in Mailable): Evaluation due: {employee} ({milestone}-month review)
    Production-ready HTML email. Email-safe inline styles.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Evaluation due: {{ $employeeName }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(11, 7, 54, 0.12);">
                    <tr>
                        <td style="padding: 40px 40px 24px 40px; text-align: center; background: linear-gradient(135deg, #0B0736 0%, #733E87 100%);">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">Employee Evaluation Due</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">{{ $milestoneMonths }}-month review</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Hi {{ $managerName }},</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;"><strong style="color: #0B0736;">{{ $employeeName }}</strong> has reached their {{ $milestoneMonths }}-month milestone. Please complete their evaluation form so we can share the results with them.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ $formUrl }}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff !important; text-decoration: none; background-color: #0f172a; border-radius: 8px;">Complete Evaluation</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6b7280; text-align: center;">Or copy and paste this link into your browser:</p>
                            <p style="margin: 0 0 24px 0; font-size: 14px; text-align: center;">
                                <a href="{{ $formUrl }}" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; word-break: break-all;">{{ $formUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 24px auto 0 auto;">
                    <tr>
                        <td style="text-align: center; padding: 0 20px;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {{ date('Y') }} Oia Properties Listing Portal. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
