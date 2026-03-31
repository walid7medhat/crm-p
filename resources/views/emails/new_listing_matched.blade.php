{{-- resources/views/emails/new_listing_matched.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $subtitle ?? 'Notification' }} — Oia Properties Listing Portal</title>
</head>
<body style="margin:0; padding:0; background:#050c1f; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
  @php($safeName = $userName ?? '')
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050c1f;">
    <tr>
      <td align="center" style="padding:30px 14px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;">
          <tr>
            <td align="center" style="padding-bottom:12px;">
              <span style="display:inline-block; background:#19263e; border:1px solid #2a3957; color:#e2e8f0; font-size:13px; font-weight:600; line-height:1; border-radius:999px; padding:10px 16px;">
                Oia Properties Listing Portal
              </span>
            </td>
          </tr>
          @if(!empty($subtitle))
            <tr>
              <td align="center" style="padding-bottom:18px; color:#93c5fd; font-size:22px; font-weight:700;">
                {{ $subtitle }}
              </td>
            </tr>
          @endif
          <tr>
            <td style="background:#091633; border:1px solid #23365d; border-radius:16px; padding:28px;">
              <p style="margin:0 0 16px; color:#dbeafe; font-size:18px; line-height:1.5;">
                Hello{{ $safeName ? ' ' . e($safeName) : '' }},
              </p>

              <h1 style="margin:0 0 18px; color:#ffffff; font-size:44px; line-height:1.15; font-weight:800;">
                {{ $headline }}
              </h1>

              @foreach($bodyLines as $line)
                <p style="margin:0 0 14px; color:#cbd5e1; font-size:18px; line-height:1.65;">
                  {{ $line }}
                </p>
              @endforeach

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 20px;">
                <tr>
                  <td align="center" style="border-radius:14px; background:#2f6ef6;">
                    <a href="{{ $ctaUrl }}" style="display:inline-block; padding:16px 34px; color:#ffffff; font-size:34px; font-weight:700; text-decoration:none; border-radius:14px;">
                      {{ $ctaText }}
                    </a>
                  </td>
                </tr>
              </table>

              @if(!empty($fallbackUrl))
                <p style="margin:0; color:#94a3b8; font-size:30px; line-height:1.6;">
                  If the button doesn’t work, copy and paste this link:
                  <a href="{{ $fallbackUrl }}" style="color:#93c5fd; word-break:break-all;">{{ $fallbackUrl }}</a>
                </p>
              @endif
            </td>
          </tr>
          <tr>
            <td style="padding-top:16px; color:#64748b; font-size:12px; text-align:left;">
              &copy; {{ date('Y') }} Oia Properties Listing Portal. All rights reserved.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>