<!-- resources/views/emails/user-invitation.blade.php -->
<x-mail::message>
# Join Our System

You have been invited to join our system. 

<x-mail::button :url="$invitationUrl">
Create Account
</x-mail::button>

This invitation expires at: {{ $expiresAt }}

Thank you,<br>
{{ config('app.name') }}
</x-mail::message>