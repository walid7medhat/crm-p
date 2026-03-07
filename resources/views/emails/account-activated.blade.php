{{--
    Subject line (use in Mailable): Your OIA Properties Listing Portal Account Is Now Active
    Production-ready HTML email for account activation. Email-safe inline styles.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Your Account Has Been Activated</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f5; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 24px 40px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 700; color: #111827; letter-spacing: -0.02em;">OIA Properties Listing Portal</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #6b7280;">Your account is now active</p>
                        </td>
                    </tr>
                    <!-- Main content -->
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Hello{{ isset($userName) && $userName ? ' ' . e($userName) : '' }},</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Great news — your account on the <strong style="color: #111827;">OIA Properties Listing Portal</strong> has been successfully activated by our team.</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">You can now sign in using the email address and password you used when you signed up. Once logged in, you’ll be able to browse all available property listings on the portal.</p>
                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="https://listings.oiaproperties.com/" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 32px; font-size: 16px; font-weight: 600; color: #ffffff !important; text-decoration: none; background-color: #0f172a; border-radius: 8px;">Sign In to Your Account</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 8px 0; font-size: 14px; color: #6b7280; text-align: center;">Or copy and paste this link into your browser:</p>
                            <p style="margin: 0 0 24px 0; font-size: 14px; text-align: center;">
                                <a href="https://listings.oiaproperties.com/" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; word-break: break-all;">https://listings.oiaproperties.com/</a>
                            </p>
                            <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #374151;">We’re glad to have you on board. If you have any questions, feel free to reach out to our team.</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px 40px 40px; background-color: #f9fafb; border-radius: 0 0 12px 12px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.5;">Best regards,<br><strong style="color: #111827;">The OIA Properties Team</strong></p>
                        </td>
                    </tr>
                </table>
                <!-- Optional small print -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 24px auto 0 auto;">
                    <tr>
                        <td style="text-align: center; padding: 0 20px;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {{ date('Y') }} OIA Properties Listing Portal. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
