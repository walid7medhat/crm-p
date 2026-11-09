{{-- Sent by `php artisan users:send-account-email` (App\Mail\AccountAccessMail). Email-safe inline styles. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your OIA Properties CRM Account</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <tr>
                        <td style="padding: 40px 40px 24px 40px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #111827;">OIA Properties CRM</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #6b7280;">Your account details</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Hello{{ $userName ? ' ' . e($userName) : '' }},</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">You have an active account on the <strong style="color: #111827;">OIA Properties CRM</strong>. Use the details below to sign in.</p>

                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px 0; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 15px; color: #374151;">
                                        <span style="color: #6b7280;">Login email:</span>
                                        <strong style="color: #111827;">{{ $userEmail }}</strong>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 12px 0; font-size: 16px; line-height: 1.6; color: #374151;">If you don't know your password, set a new one here:</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 20px 0 8px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ $resetUrl }}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff !important; text-decoration: none; background-color: #0f172a; border-radius: 8px;">Set / Reset Password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 24px 0; font-size: 13px; color: #6b7280; text-align: center;">This link works for {{ $expiresInDays }} days. After that, use "Forgot password" on the sign-in page.</p>

                            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6b7280; text-align: center;">Sign in:</p>
                            <p style="margin: 0 0 8px 0; font-size: 14px; text-align: center;">
                                <a href="{{ $loginUrl }}" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; word-break: break-all;">{{ $loginUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 24px auto 0 auto;">
                    <tr>
                        <td style="text-align: center; padding: 0 20px;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">If you didn't expect this email, you can ignore it. &copy; {{ date('Y') }} OIA Properties.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
