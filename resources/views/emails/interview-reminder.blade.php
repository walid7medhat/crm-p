<!DOCTYPE html>
<html>
<head>
    <title>Interview Reminder</title>
</head>
<body>
    <h2>Dear {{ $interview->interviewer->name }},</h2>
    
    <p>This is a reminder for the upcoming interview.</p>
    
    <h3>Interview Details:</h3>
    <ul>
        <li><strong>Candidate:</strong> {{ $applicant->full_name }}</li>
        <li><strong>Position:</strong> {{ $applicant->job->title }}</li>
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
    
    <p>Please be prepared and review the candidate's resume before the interview.</p>
    
    <p>Best regards,<br>HR System</p>
</body>
</html>