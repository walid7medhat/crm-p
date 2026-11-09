<?php

namespace App\Services;

use App\Mail\AccountAccessMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
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
        $token = Password::broker(self::BROKER)->createToken($user);

        Mail::to($deliverTo ?: $user->email)->send(new AccountAccessMail(
            $user->email,
            User::resolveDisplayName($user) ?? $user->name,
            $base . '/sign-in',
            $base . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email),
            $this->expiresInDays(),
        ));
    }

    public function expiresInDays(): int
    {
        return (int) ceil(config('auth.passwords.' . self::BROKER . '.expire') / 1440);
    }
}
