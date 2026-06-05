<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Provision Bitrix24 users into the local users table.
 *
 * Pulls the full user list from Bitrix24 (user.get) and, for each user not
 * already in our DB (matched by users.bitrix24_id, then by email), INSERTs them
 * and downloads their profile photo. Existing/linked users are left in place
 * (the photo is only backfilled when missing). New accounts are created
 * `in_active` with a default password. Every step is logged to the
 * `bitrix_users` channel and echoed to the console so insert progress is
 * visible.
 *
 * The provisioned users.bitrix24_id is what the lead "Activity" tile uses to
 * resolve LAST_ACTIVITY_BY to a real local user (see ResolvesLeadLastActivity).
 */
class ProvisionBitrix24ActivityUsers extends Command
{
    protected $signature = 'bitrix24:provision-users
        {--dry-run : Show what would happen without writing anything}
        {--limit=0 : Only process the first N Bitrix24 users (0 = all)}
        {--active-only : Only fetch active Bitrix24 users (default also includes inactive)}
        {--no-photo : Skip downloading profile photos}';

    protected $description = 'Insert all Bitrix24 users into the local DB (with photo) so the lead Activity person shows from saved data';

    /** Default password for provisioned Bitrix24 accounts (created as in_active). */
    private const DEFAULT_PASSWORD = '123123123';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $withPhoto = ! $this->option('no-photo');
        $activeOnly = (bool) $this->option('active-only');

        $this->logBoth('info', 'START provisioning Bitrix24 users', [
            'dry_run' => $dryRun,
            'limit' => $limit ?: 'all',
            'with_photo' => $withPhoto,
            'active_only' => $activeOnly,
        ]);

        try {
            $client = new Bitrix24Client();
        } catch (\Throwable $e) {
            $this->logBoth('error', 'Cannot build Bitrix24 client', ['error' => $e->getMessage()]);
            $this->error('Bitrix24 is not configured: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Fetching users from Bitrix24...');
        try {
            $remoteUsers = $this->fetchAllBitrixUsers($client, $activeOnly);
        } catch (\Throwable $e) {
            $this->logBoth('error', 'Failed to list Bitrix24 users', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch users from Bitrix24: '.$e->getMessage());
            return self::FAILURE;
        }

        if ($limit > 0) {
            $remoteUsers = array_slice($remoteUsers, 0, $limit);
        }

        $total = count($remoteUsers);
        if ($total === 0) {
            $this->warn('Bitrix24 returned no users — nothing to do.');
            return self::SUCCESS;
        }

        $stats = ['created' => 0, 'linked' => 0, 'exists' => 0, 'photo' => 0, 'skipped' => 0, 'errors' => 0];
        $this->info("Processing {$total} Bitrix24 user(s).");

        foreach (array_values($remoteUsers) as $i => $remote) {
            $position = $i + 1;
            $b24Id = (int) ($remote['ID'] ?? 0);
            if ($b24Id <= 0) {
                $stats['skipped']++;
                continue;
            }

            try {
                $stats = $this->provisionUser($remote, $b24Id, $dryRun, $withPhoto, $position, $total, $stats);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->logBoth('error', 'Failed provisioning user', [
                    'bitrix24_id' => $b24Id,
                    'error' => $e->getMessage(),
                ]);
                $this->line("  [{$position}/{$total}] b24#{$b24Id} ❌ error: {$e->getMessage()}");
            }
        }

        $this->logBoth('info', 'FINISHED provisioning Bitrix24 users', $stats);
        $this->newLine();
        $this->info(sprintf(
            'Done. created=%d linked=%d already=%d photos=%d skipped=%d errors=%d%s',
            $stats['created'], $stats['linked'], $stats['exists'], $stats['photo'],
            $stats['skipped'], $stats['errors'], $dryRun ? ' (dry-run, nothing written)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Fetch every Bitrix24 user. By default merges active + inactive users
     * (deduped by ID); --active-only restricts to active.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllBitrixUsers(Bitrix24Client $client, bool $activeOnly): array
    {
        $byId = [];
        foreach ($client->listUsers(['ACTIVE' => true]) as $user) {
            $id = (int) ($user['ID'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $user;
            }
        }

        if (! $activeOnly) {
            foreach ($client->listUsers(['ACTIVE' => false]) as $user) {
                $id = (int) ($user['ID'] ?? 0);
                if ($id > 0 && ! isset($byId[$id])) {
                    $byId[$id] = $user;
                }
            }
        }

        return array_values($byId);
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  array<string, int>  $stats
     * @return array<string, int>
     */
    private function provisionUser(
        array $remote,
        int $b24Id,
        bool $dryRun,
        bool $withPhoto,
        int $position,
        int $total,
        array $stats
    ): array {
        $prefix = "  [{$position}/{$total}] b24#{$b24Id}";

        $name = $this->remoteName($remote, $b24Id);
        $email = $this->remoteEmail($remote);
        $photoUrl = $this->remotePhotoUrl($remote);

        // 1) Already provisioned by Bitrix24 id → only backfill a missing photo.
        $existing = User::where('bitrix24_id', $b24Id)->first();
        if ($existing) {
            $stats['exists']++;
            $this->line("{$prefix} ✓ exists (user #{$existing->id} {$existing->name})");
            if ($withPhoto && $this->avatarIsEmpty($existing) && $photoUrl && $this->applyPhoto($existing, $photoUrl, $b24Id, $dryRun)) {
                $stats['photo']++;
                $this->line("{$prefix}    ↳ photo backfilled");
            }
            return $stats;
        }

        // 2) Match an existing local user by email OR name → link (set bitrix24_id).
        $matchName = $this->remoteRealName($remote); // real name only, never the placeholder
        $match = ($email || $matchName)
            ? User::query()
                ->where(function ($q) use ($email, $matchName) {
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($matchName) {
                        $q->orWhere('name', $matchName);
                    }
                })
                ->first()
            : null;
        if ($match) {
            $by = ($email && strcasecmp((string) $match->email, $email) === 0) ? "email {$email}" : "name {$match->name}";
            $this->logBoth('info', 'Linking existing local user to Bitrix24 id', [
                'bitrix24_id' => $b24Id, 'user_id' => $match->id, 'matched_by' => $by,
            ]);
            $this->line("{$prefix} 🔗 linked to existing user #{$match->id} (by {$by})");
            if (! $dryRun) {
                $match->bitrix24_id = $b24Id;
                if ($withPhoto && $this->avatarIsEmpty($match) && $photoUrl) {
                    $match->avatar = $this->downloadPhoto($photoUrl, $b24Id);
                }
                $match->save();
            }
            $stats['linked']++;
            return $stats;
        }

        // 3) Create a new (in_active) local user from the Bitrix24 profile.
        $avatarPath = ($withPhoto && $photoUrl) ? $this->downloadPhoto($photoUrl, $b24Id, $dryRun) : null;

        $this->logBoth('info', 'Creating local user from Bitrix24', [
            'bitrix24_id' => $b24Id, 'name' => $name, 'email' => $email, 'has_photo' => (bool) $avatarPath,
        ]);

        if (! $dryRun) {
            $user = new User();
            $user->name = $name;
            $user->email = $email ?: $this->placeholderEmail($b24Id);
            $user->password = self::DEFAULT_PASSWORD; // hashed via the model cast
            $user->status = 'in_active';
            $user->bitrix24_id = $b24Id;
            if ($avatarPath) {
                $user->avatar = $avatarPath;
            }
            $user->save();
            $this->line("{$prefix} ➕ created user #{$user->id} ({$user->name})".($avatarPath ? ' +photo' : ''));
        } else {
            $this->line("{$prefix} ➕ would create user ({$name})".($photoUrl ? ' +photo' : ''));
        }

        $stats['created']++;
        if ($avatarPath) {
            $stats['photo']++;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function remoteName(array $remote, int $b24Id): string
    {
        return $this->remoteRealName($remote)
            ?? $this->remoteEmail($remote)
            ?? "Bitrix24 user #{$b24Id}";
    }

    /**
     * The real Bitrix24 display name (NAME + LAST_NAME), or null if absent.
     * Used for matching — never returns the "Bitrix24 user #id" placeholder.
     *
     * @param  array<string, mixed>  $remote
     */
    private function remoteRealName(array $remote): ?string
    {
        $name = trim(($remote['NAME'] ?? '').' '.($remote['LAST_NAME'] ?? ''));
        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function remoteEmail(array $remote): ?string
    {
        $email = $remote['EMAIL'] ?? null;
        return is_string($email) && trim($email) !== '' ? trim($email) : null;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function remotePhotoUrl(array $remote): ?string
    {
        $photo = $remote['PERSONAL_PHOTO'] ?? $remote['personal_photo'] ?? null;
        return is_string($photo) && trim($photo) !== '' ? trim($photo) : null;
    }

    private function placeholderEmail(int $b24Id): string
    {
        return "bitrix24.user.{$b24Id}@bitrix.local";
    }

    private function avatarIsEmpty(User $user): bool
    {
        $raw = $user->getRawOriginal('avatar');
        return $raw === null || $raw === '' || $raw === 'users/user.png';
    }

    private function applyPhoto(User $user, string $photoUrl, int $b24Id, bool $dryRun): bool
    {
        $path = $this->downloadPhoto($photoUrl, $b24Id, $dryRun);
        if (! $path) {
            return false;
        }
        if (! $dryRun) {
            $user->avatar = $path;
            $user->save();
        }
        return true;
    }

    /**
     * Download a Bitrix24 personal photo to the public disk under users/avatars.
     * Returns the stored relative path, or null on failure.
     */
    private function downloadPhoto(string $url, int $b24Id, bool $dryRun = false): ?string
    {
        if ($dryRun) {
            return null;
        }
        try {
            $response = Http::timeout(30)->get($url);
            if (! $response->ok() || $response->body() === '') {
                $this->logBoth('warning', 'Photo download failed', ['bitrix24_id' => $b24Id, 'status' => $response->status()]);
                return null;
            }

            $extension = $this->guessExtension($response->header('Content-Type'), $url);
            $path = 'users/avatars/bitrix24-'.$b24Id.'-'.Str::random(8).'.'.$extension;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            $this->logBoth('warning', 'Photo download error', ['bitrix24_id' => $b24Id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function guessExtension(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $type = explode(';', strtolower(trim((string) $contentType)))[0];
        if (isset($map[$type])) {
            return $map[$type];
        }
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logBoth(string $level, string $message, array $context = []): void
    {
        Log::channel('bitrix_users')->{$level}($message, $context);
    }
}
