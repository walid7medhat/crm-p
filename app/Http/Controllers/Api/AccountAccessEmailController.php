<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountAccessMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings → Account Emails (super_admin only): pick a branch, see its active users,
 * send them the "your account + set/reset password" email (one user as a test, or many).
 */
class AccountAccessEmailController extends Controller
{
    public function __construct(private AccountAccessMailer $mailer)
    {
    }

    public function branches(): JsonResponse
    {
        return ApiResponse::success([
            'branches' => $this->mailer->branches(),
            'mailer' => config('mail.default'),
            'expires_in_days' => $this->mailer->expiresInDays(),
            'me' => ['id' => auth()->id(), 'email' => auth()->user()?->email],
        ]);
    }

    public function users(int $branch): JsonResponse
    {
        if (!$this->mailer->isBranch($branch)) {
            return ApiResponse::error('Unknown branch', 404);
        }

        $users = $this->mailer->branchUsers(User::findOrFail($branch))->map(fn (User $u) => [
            'id' => $u->id,
            'name' => User::resolveDisplayName($u) ?? $u->name,
            'email' => $u->email,
            'role' => $u->roles->pluck('name')->first(),
            'avatar' => $u->avatar ? asset('storage/' . $u->avatar) : null,
        ]);

        return ApiResponse::success($users->values());
    }

    /**
     * Send to the given users of the branch (ids not in the branch / not active are skipped),
     * or with `to_me` send the email to the signed-in admin's own account as a preview.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_me' => ['sometimes', 'boolean'],
            // Test: deliver the signed-in admin's own account email to this address instead.
            'test_email' => ['nullable', 'email:rfc', 'max:191'],
            'branch_id' => ['required_unless:to_me,true', 'nullable', 'integer'],
            'user_ids' => ['required_unless:to_me,true', 'array', 'max:500'],
            'user_ids.*' => ['integer'],
        ]);

        if (!empty($data['to_me'])) {
            $me = auth()->user();
            try {
                // The email shows (and resets) the signed-in admin's own account — never another user's.
                $this->mailer->send($me, $data['test_email'] ?? null);
                return ApiResponse::success(['sent' => [$me->id], 'failed' => [], 'to' => $data['test_email'] ?? $me->email], '1 sent');
            } catch (\Throwable $e) {
                report($e);
                return ApiResponse::success(['sent' => [], 'failed' => [['id' => $me->id, 'email' => $data['test_email'] ?? $me->email, 'error' => $e->getMessage()]]], 'Failed');
            }
        } else {
            if (!$this->mailer->isBranch((int) $data['branch_id'])) {
                return ApiResponse::error('Unknown branch', 404);
            }
            $users = $this->mailer->branchUsers(User::findOrFail($data['branch_id']), $data['user_ids']);
        }

        $sent = [];
        $failed = [];
        foreach ($users as $user) {
            try {
                $this->mailer->send($user);
                $sent[] = $user->id;
                if ($users->count() > 1) {
                    usleep(200000); // be gentle with the SMTP server
                }
            } catch (\Throwable $e) {
                report($e);
                $failed[] = ['id' => $user->id, 'email' => $user->email, 'error' => $e->getMessage()];
            }
        }

        return ApiResponse::success(
            ['sent' => $sent, 'failed' => $failed],
            count($sent) . ' sent' . ($failed ? ', ' . count($failed) . ' failed' : '')
        );
    }
}
