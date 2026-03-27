<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
    }

    /**
     * GET /api/agents-emails
     * Returns a list of users (id, name, email).
     * SUPER_ADMIN only.
     */
    public function agentsEmails(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Return all users with valid emails for admin email targeting.
        $users = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * POST /api/send-email
     * Body: { subject: string, body: string, recipients: string[] }
     * SUPER_ADMIN only.
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:160',
            'body' => 'required|string|max:20000',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'cta_url' => 'nullable|url|max:2048',
            'subtitle' => 'nullable|string|max:80',
        ]);

        $subject = $validated['subject'];
        $body = $validated['body'];
        $recipients = array_values(array_unique($validated['recipients']));
        $ctaUrl = $validated['cta_url'] ?? url('/');
        $subtitle = $validated['subtitle'] ?? 'Notification';

        $sent = [];
        $failed = [];

        // Send a dark SaaS styled email using our Blade template.
        // Uses a lightweight anonymous mailable to keep it modular.
        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new class($subject, $body, $ctaUrl, $subtitle) extends \Illuminate\Mail\Mailable {
                    public function __construct(
                        public string $subjectLine,
                        public string $bodyText,
                        public string $ctaUrl,
                        public string $subtitle,
                    ) {}

                    public function build()
                    {
                        $lines = preg_split("/\r\n|\n|\r/", trim($this->bodyText)) ?: [];
                        $lines = array_values(array_filter(array_map('trim', $lines)));

                        // Template already renders "Hello, {{ name }}", so remove duplicate
                        // greeting when admin starts the body with "Hello".
                        if (!empty($lines) && preg_match('/^hello[\s,!.\-]*$/i', $lines[0])) {
                            array_shift($lines);
                        }

                        return $this->subject($this->subjectLine)
                            ->view('emails.saas-notification-dark')
                            ->with([
                                'userName' => null,
                                'subtitle' => $this->subtitle,
                                'headline' => $this->subjectLine,
                                'bodyLines' => $lines,
                                'ctaText' => 'Start Chatting Now',
                                'ctaUrl' => $this->ctaUrl,
                                'fallbackUrl' => $this->ctaUrl,
                                'footerNote' => 'You’re receiving this message from Oia Properties Listing Portal.',
                            ]);
                    }
                });
                $sent[] = $email;
            } catch (Throwable $e) {
                $failed[] = [
                    'email' => $email,
                    'reason' => $e->getMessage(),
                ];
                Log::warning('Admin email send failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $hasFailures = count($failed) > 0;

        return response()->json([
            'success' => ! $hasFailures,
            'message' => $hasFailures ? 'Email sent partially' : 'Email sent successfully',
            'sent' => count($sent),
            'failed' => $failed,
            'failed_count' => count($failed),
            'total' => count($recipients),
        ], $hasFailures ? 207 : 200);
    }
}

