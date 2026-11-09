<?php

namespace App\Services;

use App\Mail\AccountAccessMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Password;

/**
 * "Your CRM account" email (login email + 7-day set/reset password link).
 * Used by the users:send-account-email command and the Account Emails settings page.
 */
class AccountAccessMailer
{
    public const BROKER = 'account_setup';

    /**
     * Cities (admins directly under the root) with their branch offices.
     *
     * @return array<int, array{id:int, name:string, parent_id:?int, level:string}>
     */
    public function branches(): array
    {
        $cities = User::role('admin')
            ->whereHas('parent', fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'parent_id']);

        $offices = User::role('admin')
            ->whereIn('parent_id', $cities->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'parent_id']);

        $out = [];
        foreach ($cities as $city) {
            $out[] = ['id' => $city->id, 'name' => User::resolveDisplayName($city) ?? $city->name, 'parent_id' => null, 'level' => 'city'];
            foreach ($offices->where('parent_id', $city->id) as $office) {
                $out[] = ['id' => $office->id, 'name' => User::resolveDisplayName($office) ?? $office->name, 'parent_id' => $city->id, 'level' => 'office'];
            }
        }

        return $out;
    }

    public function isBranch(int $id): bool
    {
        return collect($this->branches())->contains('id', $id);
    }

    /**
     * Active users with a valid email under the branch (the branch account itself excluded).
     *
     * @param  int[]|null  $onlyIds
     * @return Collection<int, User>
     */
    public function branchUsers(User $branch, ?array $onlyIds = null): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->whereIn('id', $branch->getAllSubordinatesIds())
            ->where('id', '!=', $branch->id)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($onlyIds, fn ($q) => $q->whereIn('id', array_map('intval', $onlyIds)))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'email', 'status', 'parent_id', 'avatar'])
            ->filter(fn (User $u) => filter_var($u->email, FILTER_VALIDATE_EMAIL))
            ->values();
    }

    /**
     * Create a fresh 7-day reset token for the user and email them. Replaces any older reset link.
     * $deliverTo sends the same email (for this user's account) to another address — used for tests.
     */
    public function send(User $user, ?string $deliverTo = null): void
    {
        $base = rtrim(config('app.frontend_url', config('app.url')), '/');
        $to = $deliverTo ?: $user->email;

        try {
            $token = Password::broker(self::BROKER)->createToken($user);

            Mail::to($to)->send(new AccountAccessMail(
                $user->email,
                User::resolveDisplayName($user) ?? $user->name,
                $base . '/sign-in',
                $base . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email),
                $this->expiresInDays(),
            ));
        } catch (\Throwable $e) {
            $this->log($user, $to, $deliverTo !== null, 'failed', $e->getMessage());
            throw $e;
        }

        $this->log($user, $to, $deliverTo !== null, 'sent');
    }

    /**
     * Last email per user (non-test), keyed by user id.
     *
     * @param  int[]  $userIds
     * @return array<int, array{status:string, sent_at:string, error:?string}>
     */
    public function lastSent(array $userIds): array
    {
        if (!$userIds || !Schema::hasTable('account_access_emails')) {
            return [];
        }

        $latestIds = DB::table('account_access_emails')
            ->whereIn('user_id', $userIds)
            ->where('is_test', false)
            ->groupBy('user_id')
            ->selectRaw('MAX(id) as id');

        return DB::table('account_access_emails')
            ->whereIn('id', $latestIds)
            ->get(['user_id', 'status', 'error', 'created_at'])
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => [
                'status' => $row->status,
                'sent_at' => $row->created_at,
                'error' => $row->error,
            ]])
            ->all();
    }

    private function log(User $user, string $to, bool $isTest, string $status, ?string $error = null): void
    {
        if (!Schema::hasTable('account_access_emails')) {
            return;
        }
        try {
            DB::table('account_access_emails')->insert([
                'user_id' => $user->id,
                'sent_by' => auth()->id(),
                'sent_to' => $to,
                'is_test' => $isTest,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 2000) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e); // history must never block sending
        }
    }

    public function expiresInDays(): int
    {
        return (int) ceil(config('auth.passwords.' . self::BROKER . '.expire') / 1440);
    }
}
