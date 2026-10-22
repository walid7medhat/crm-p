{{-- Subject (set in Mailable): Attendance Notice – No Attendance Record --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Attendance Notice</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 24px 40px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #111827; letter-spacing: -0.02em;">Attendance Notice</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #6b7280;">Human Resources Department</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Dear {{ $userName }},</p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">We noticed that there is no attendance record for you for the past two days, and we hope everything is okay.</p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">In case you are unwell, we kindly request you to apply for sick leave as per company policy and keep the HR department informed.</p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Moving forward, please ensure that all business-related attendance requests, such as meetings and viewings, are communicated to the HR Department in a timely manner on a daily basis. For annual leave, please ensure that requests are submitted via email to the HR Department in advance of your planned vacation, with your line manager copied, to avoid any misunderstandings.</p>

                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;"><strong style="color: #111827;">Kindly update us on your current status at your earliest convenience.</strong></p>

                            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #374151;">Wishing you good health. Please take care and stay safe.</p>
                            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #374151;">Kind regards,<br>
                            <strong style="color: #111827;">Human Resources Department</strong></p>
                        </td>
                    </tr>
                </table>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 24px auto 0 auto;">
                    <tr>
                        <td style="text-align: center; padding: 0 20px;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {{ date('Y') }} Oia Properties. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>