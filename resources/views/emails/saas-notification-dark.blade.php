{{--
  Dark SaaS notification email template.

  Variables:
    - $userName (string|null)
    - $subtitle (string|null)
    - $headline (string|null)
    - $bodyLines (array<string>) OR $bodyHtml (string|null)
    - $ctaText (string|null)
    - $ctaUrl (string|null)
    - $fallbackUrl (string|null)
    - $footerNote (string|null)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{ $subtitle ?? 'Notification' }} — OIA Properties Listing Portal</title>
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
<body style="margin:0; padding:0; background:#0b1220; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; -webkit-font-smoothing:antialiased;">
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#0b1220;">
    <tr>
      <td style="padding:40px 16px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:620px; margin:0 auto;">
          <!-- Top brand -->
          <tr>
            <td style="padding:0 0 14px 0; text-align:center;">
              <div style="display:inline-block; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); color:#e5e7eb; font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;">
                OIA Properties Listing Portal
              </div>
              @if(!empty($subtitle))
                <div style="margin-top:10px; color:#93c5fd; font-size:13px; font-weight:600;">
                  {{ $subtitle }}
                </div>
              @endif
            </td>
          </tr>

          <!-- Card -->
          <tr>
            <td style="background:#0f172a; border-radius:16px; border:1px solid rgba(255,255,255,0.10); box-shadow:0 16px 40px rgba(0,0,0,0.35); overflow:hidden;">
              <!-- Header gradient strip -->
              <div style="height:6px; background:linear-gradient(90deg,#2563eb,#22c55e,#f59e0b);"></div>

              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td style="padding:28px 26px 8px 26px;">
                    @php($safeName = isset($userName) && $userName ? trim($userName) : '')
                    <p style="margin:0 0 14px 0; color:#cbd5e1; font-size:14px; line-height:1.6;">
                      Hello{{ $safeName ? ' ' . e($safeName) : '' }},
                    </p>
                    @if(!empty($headline))
                      <h1 style="margin:0 0 12px 0; color:#f8fafc; font-size:20px; line-height:1.25; letter-spacing:-0.02em;">
                        {{ $headline }}
                      </h1>
                    @endif
                  </td>
                </tr>

                <tr>
                  <td style="padding:0 26px 18px 26px;">
                    @if(!empty($bodyHtml))
                      <div style="color:#cbd5e1; font-size:14px; line-height:1.75;">
                        {!! $bodyHtml !!}
                      </div>
                    @else
                      @php($lines = isset($bodyLines) && is_array($bodyLines) ? $bodyLines : [])
                      @foreach($lines as $line)
                        <p style="margin:0 0 12px 0; color:#cbd5e1; font-size:14px; line-height:1.75;">
                          {{ $line }}
                        </p>
                      @endforeach
                    @endif
                  </td>
                </tr>

                @if(!empty($ctaText) && !empty($ctaUrl))
                  <tr>
                    <td style="padding:0 26px 10px 26px;">
                      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        <tr>
                          <td style="text-align:center; padding:10px 0 6px 0;">
                            <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer"
                               style="display:inline-block; padding:12px 22px; border-radius:12px; background:#2563eb; color:#ffffff !important; text-decoration:none; font-size:14px; font-weight:700;">
                              {{ $ctaText }}
                            </a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                @endif

                @php($fb = $fallbackUrl ?? $ctaUrl ?? null)
                @if(!empty($fb))
                  <tr>
                    <td style="padding:0 26px 22px 26px; text-align:center;">
                      <p style="margin:6px 0 0 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                        If the button doesn’t work, copy and paste this link:
                      </p>
                      <p style="margin:6px 0 0 0; font-size:12px; line-height:1.6;">
                        <a href="{{ $fb }}" target="_blank" rel="noopener noreferrer" style="color:#93c5fd; text-decoration:underline; word-break:break-all;">
                          {{ $fb }}
                        </a>
                      </p>
                    </td>
                  </tr>
                @endif

              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:16px 6px 0 6px; text-align:center;">
              @if(!empty($footerNote))
                <p style="margin:0 0 8px 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                  {{ $footerNote }}
                </p>
              @endif
              <p style="margin:0; color:#64748b; font-size:12px;">
                &copy; {{ date('Y') }} OIA Properties Listing Portal. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
