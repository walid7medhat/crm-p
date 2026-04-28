<!DOCTYPE html>
<html>
<head>
    <title>Application Status</title>
</head>
<body>
    <h2>Dear {{ $applicant->full_name }},</h2>
    
    @if($status == 'shortlisted')
        <p>Congratulations! You have been <strong>shortlisted</strong> for the position <strong>{{ $applicant->job->title }}</strong>.</p>
        <p>We will contact you soon for the interview schedule.</p>
    
    @elseif($status == 'rejected')
        <p>Thank you for your interest in the position <strong>{{ $applicant->job->title }}</strong>.</p>
        <p>After careful review, we regret to inform you that you have not been selected.</p>
        @if($applicant->rejection_reason)
            <p><strong>Reason:</strong> {{ $applicant->rejection_reason }}</p>
        @endif
    
    @elseif($status == 'hired')
        <p>Congratulations! We are pleased to offer you the position <strong>{{ $applicant->job->title }}</strong>.</p>
        <p>Our HR team will contact you shortly with the offer details.</p>
    @endif
    
    <p>Best regards,<br>HR Team</p>
</body>
</html>