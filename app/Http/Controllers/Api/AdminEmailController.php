<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FeatureAnnouncementMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
    }

    /**
     * GET /api/agents-emails
     * Returns a list of agent users (id, name, email).
     * SUPER_ADMIN only.
     */
    public function agentsEmails(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // "Agents" in this system are commonly users with role "sales".
        // Keep payload small and stable for multi-select UI.
        $agents = User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'sales');
            })
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents,
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

        // Send a dark SaaS styled email using our Blade template.
        // Uses a lightweight anonymous mailable to keep it modular.
        foreach ($recipients as $email) {
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
                            'footerNote' => 'You’re receiving this message from OIA Properties Listing Portal.',
                        ]);
                }
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Email sent successfully',
            'sent' => count($recipients),
        ]);
    }
}

