<!DOCTYPE html>
<html>
<head>
    <title>Interview Scheduled</title>
</head>
<body>
    <h2>Dear {{ $applicant->full_name }},</h2>
    
    <p>Your interview for the position <strong>{{ $applicant->job->title }}</strong> has been scheduled.</p>
    
    <h3>Interview Details:</h3>
    <ul>
        <li><strong>Date:</strong> {{ $interview->scheduled_at->format('F j, Y') }}</li>
        <li><strong>Time:</strong> {{ $interview->scheduled_at->format('h:i A') }}</li>
        <li><strong>Type:</strong> {{ ucfirst($interview->type) }}</li>
        @if($interview->location)
            <li><strong>Location:</strong> {{ $interview->location }}</li>
        @endif
        @if($interview->meeting_link)
            <li><strong>Meeting Link:</strong> <a href="{{ $interview->meeting_link }}">{{ $interview->meeting_link }}</a></li>
        @endif
    </ul>
    
    <p>Please arrive 10 minutes before the scheduled time.</p>
    
    <p>Best regards,<br>HR Team</p>
</body>
</html>