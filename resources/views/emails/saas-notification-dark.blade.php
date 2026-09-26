{{--
  Oia Properties notification email.

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
  <title>{{ $subtitle ?? 'Notification' }} — Oia Properties</title>
</head>
<body style="margin:0; padding:0; background:#f4f2f8;">
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#f4f2f8" style="background:#f4f2f8; margin:0; padding:0;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="width:600px; max-width:600px;">
          <tr>
            <td bgcolor="#0B0736" style="background:#0B0736; border-radius:16px 16px 0 0; padding:0;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td height="4" bgcolor="#e8a317" style="background:#e8a317; font-size:0; line-height:0;">&nbsp;</td>
                </tr>
                <tr>
                  <td style="padding:26px 32px 22px 32px;">
                    <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#f6d48a;">
                      Oia Properties
                    </p>
                    @if(!empty($subtitle))
                      <p style="margin:8px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:700; color:#ffffff;">
                        {{ $subtitle }}
                      </p>
                    @endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td bgcolor="#ffffff" style="background:#ffffff; border:1px solid #efe6f5; border-top:none; border-radius:0 0 16px 16px; padding:0;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td style="padding:28px 32px 8px 32px; font-family:Arial, Helvetica, sans-serif;">
                    @php($safeName = isset($userName) && $userName ? mb_convert_case(trim($userName), MB_CASE_TITLE, 'UTF-8') : '')
                    <p style="margin:0 0 14px 0; color:#64748b; font-size:14px; line-height:1.6;">
                      Hello{{ $safeName ? ' ' . e($safeName) : '' }},
                    </p>
                    @if(!empty($headline))
                      <h1 style="margin:0 0 14px 0; color:#0B0736; font-size:20px; line-height:1.35; font-weight:700;">
                        {{ $headline }}
                      </h1>
                    @endif
                  </td>
                </tr>

                <tr>
                  <td style="padding:0 32px 8px 32px; font-family:Arial, Helvetica, sans-serif;">
                    @if(!empty($bodyHtml))
                      <div style="color:#334155; font-size:15px; line-height:1.7;">
                        {!! $bodyHtml !!}
                      </div>
                    @else
                      @php($lines = isset($bodyLines) && is_array($bodyLines) ? $bodyLines : [])
                      @foreach($lines as $line)
                        <p style="margin:0 0 12px 0; color:#334155; font-size:15px; line-height:1.7;">
                          {{ $line }}
                        </p>
                      @endforeach
                    @endif
                  </td>
                </tr>

                @if(!empty($ctaText) && !empty($ctaUrl))
                  <tr>
                    <td align="center" style="padding:18px 32px 8px 32px;">
                      <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                        <tr>
                          <td bgcolor="#733E87" style="background:#733E87; border-radius:999px;">
                            <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer"
                               style="display:inline-block; padding:13px 28px; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
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
                    <td style="padding:8px 32px 28px 32px; text-align:center; font-family:Arial, Helvetica, sans-serif;">
                      <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                        If the button doesn’t work, copy and paste this link:
                      </p>
                      <p style="margin:6px 0 0 0; font-size:12px; line-height:1.6;">
                        <a href="{{ $fb }}" target="_blank" rel="noopener noreferrer" style="color:#733E87; text-decoration:underline; word-break:break-all;">
                          {{ $fb }}
                        </a>
                      </p>
                    </td>
                  </tr>
                @endif
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 8px 0 8px; text-align:center; font-family:Arial, Helvetica, sans-serif;">
              @if(!empty($footerNote))
                <p style="margin:0 0 6px 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                  {{ $footerNote }}
                </p>
              @endif
              <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                &copy; {{ date('Y') }} Oia Properties. All rights reserved.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
