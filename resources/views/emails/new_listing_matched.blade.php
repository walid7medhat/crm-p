{{-- resources/views/emails/new_listing_matched.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $subtitle ?? 'Notification' }} — Oia Properties Listing Portal</title>
</head>
<body style="margin:0; padding:0; background:#0b1220; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" style="background:#0b1220;">
    <tr>
      <td style="padding:40px 16px;">
        <table width="100%" style="max-width:620px; margin:0 auto;">
          <tr>
            <td style="padding:28px 26px;">
              @php($safeName = $userName ?? '')
              <p style="color:#cbd5e1;">Hello{{ $safeName ? ' ' . e($safeName) : '' }},</p>
              <h1 style="color:#f8fafc;">{{ $headline }}</h1>

              @foreach($bodyLines as $line)
                <p style="color:#cbd5e1;">{{ $line }}</p>
              @endforeach

              <a href="{{ $ctaUrl }}" style="display:inline-block; padding:12px 22px; border-radius:12px; background:#2563eb; color:#fff; text-decoration:none; font-weight:700;">
                {{ $ctaText }}
              </a>

              @if(!empty($fallbackUrl))
                <p style="color:#94a3b8; font-size:12px; word-break:break-all;">
                  If the button doesn’t work, copy and paste this link: <a href="{{ $fallbackUrl }}" style="color:#93c5fd;">{{ $fallbackUrl }}</a>
                </p>
              @endif

              <p style="color:#64748b; font-size:12px;">&copy; {{ date('Y') }} Oia Properties Listing Portal. All rights reserved.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>