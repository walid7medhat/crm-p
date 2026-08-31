{{--
    Subject line (use in Mailable): Happy Birthday, {name}!
    Production-ready HTML email for birthday celebrations. Email-safe inline styles.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Happy Birthday, {{ $userName }}!</title>
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
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 24px rgba(11, 7, 54, 0.12);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 48px 40px; text-align: center; background: linear-gradient(135deg, #0B0736 0%, #733E87 100%);">
                            <div style="font-size: 44px; line-height: 1; margin: 0 0 12px 0;">🎉🎂🎈</div>
                            <h1 style="margin: 0; font-size: 26px; font-weight: 700; color: #ffffff; letter-spacing: -0.02em;">Happy Birthday, {{ $userName }}!</h1>
                            <p style="margin: 10px 0 0 0; font-size: 14px; color: rgba(255, 255, 255, 0.85);">From all of us at Oia Properties</p>
                        </td>
                    </tr>
                    <!-- Main content -->
                    <tr>
                        <td style="padding: 36px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Dear {{ $userName }},</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Today is all about you! The entire team at <strong style="color: #0B0736;">Oia Properties</strong> is wishing you a birthday filled with joy, laughter, and everything that makes you happy.</p>
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #374151;">Thank you for being such a valued part of our team. We hope you get to celebrate in style and take a moment today just for yourself.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td style="text-align: center; padding: 20px; background: #f3ecf7; border-radius: 12px;">
                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #733E87;">🥳 Have a wonderful day! 🥳</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!-- Optional small print -->
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
