<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountAccessMailer;
use Illuminate\Console\Command;

/**
 * Email every active user under a branch their login email + a 7-day "set / reset password" link.
 * Same as the Settings → Account Emails page.
 *
 *   php artisan users:send-account-email --dry-run            # list who would get it (Dubai)
 *   php artisan users:send-account-email --only=123           # send to one user first (test)
 *   php artisan users:send-account-email                      # send to all active Dubai users
 *   php artisan users:send-account-email --branch="Abu Dhabi" # another branch (name or id)
 */
class SendAccountAccessEmails extends Command
{
    protected $signature = 'users:send-account-email
        {--branch=Dubai : Branch account name or id; everyone under it is included}
        {--only=* : Send only to these user ids (must be in the branch)}
        {--dry-run : List recipients without sending}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Email active users of a branch their login email and a link to set/reset their password';

    public function handle(AccountAccessMailer $mailer): int
    {
        $branch = $this->resolveBranch((string) $this->option('branch'));
        if (!$branch) {
            $this->error('Branch not found: ' . $this->option('branch'));
            return self::FAILURE;
        }

        $users = $mailer->branchUsers($branch, $this->option('only') ?: null);

        $branchName = User::resolveDisplayName($branch) ?? $branch->name;
        $this->info("Branch: {$branchName} (#{$branch->id}) — {$users->count()} active user(s) with a valid email");
        $this->table(['ID', 'Name', 'Email'], $users->map(fn (User $u) => [
            $u->id, User::resolveDisplayName($u) ?? $u->name, $u->email,
        ])->all());

        if ($users->isEmpty() || $this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("Send the account email to {$users->count()} user(s) via mailer [" . config('mail.default') . ']?')) {
            $this->warn('Cancelled.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($users as $user) {
            try {
                $mailer->send($user);
                $sent++;
                $this->line("  <info>sent</info>   #{$user->id} {$user->email}");
                usleep(300000); // be gentle with the SMTP server
            } catch (\Throwable $e) {
                $failed++;
                $this->line("  <error>failed</error> #{$user->id} {$user->email}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Done. Sent: {$sent}, failed: {$failed}");
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function resolveBranch(string $branch): ?User
    {
        if ($branch === '') {
            return null;
        }
        if (ctype_digit($branch)) {
            return User::find((int) $branch);
        }

        return User::role('admin')
            ->where(fn ($q) => $q->where('name', $branch)->orWhere('display_name', $branch))
            ->orderBy('id')
            ->first();
    }
}
