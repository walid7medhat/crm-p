
<!DOCTYPE html>
<html>
<head>
    <title>New Application</title>
</head>
<body>
    <h2>New Job Application</h2>
    
    <p><strong>Position:</strong> {{ $job->title }}</p>
    
    <h3>Applicant Details:</h3>
    <ul>
        <li><strong>Name:</strong> {{ $applicant->full_name }}</li>
        <li><strong>Email:</strong> {{ $applicant->email }}</li>
        <li><strong>Phone:</strong> {{ $applicant->phone }}</li>
        <li><strong>Nationality:</strong> {{ $applicant->nationality ?? 'Not specified' }}</li>
        <li><strong>Expected Salary:</strong> {{ $applicant->expected_salary ?? 'Not specified' }}</li>
    </ul>
    
    <h3>Answers:</h3>
    @if($applicant->answers)
        @foreach($applicant->answers as $question => $answer)
            <p><strong>{{ $question }}:</strong> {{ $answer }}</p>
        @endforeach
    @endif
    
    <p>
        <a href="{{ url('/api/recruitment/admin/applicants/' . $applicant->id) }}">
            View Full Application
        </a>
    </p>
    
    <p>Best regards,<br>HR System</p>
</body>
</html>