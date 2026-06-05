<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProvisionBitrix24ActivityUsers extends Command
{
    protected $signature = 'bitrix24:provision-users
        {--dry-run : Show what would happen without writing anything}
        {--limit=0 : Only process the first N Bitrix24 users (0 = all)}
        {--active-only : Only fetch active Bitrix24 users}
        {--no-photo : Skip downloading profile photos}';

    protected $description = 'Sync Bitrix24 users into local DB';

    private const DEFAULT_PASSWORD = '123123123';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $withPhoto = ! $this->option('no-photo');
        $activeOnly = (bool) $this->option('active-only');

        $client = new Bitrix24Client();

        $remoteUsers = $this->fetchAllBitrixUsers($client, $activeOnly);

        if ($limit > 0) {
            $remoteUsers = array_slice($remoteUsers, 0, $limit);
        }

        $stats = [
            'created' => 0,
            'linked' => 0,
            'exists' => 0,
            'photo' => 0,
            'skipped' => 0,
        ];

        foreach ($remoteUsers as $i => $remote) {

            $b24Id = (int)($remote['ID'] ?? 0);
            if (!$b24Id) {
                $stats['skipped']++;
                continue;
            }

            $stats = $this->provisionUser(
                $remote,
                $b24Id,
                $dryRun,
                $withPhoto,
                $stats,
                $i + 1,
                count($remoteUsers)
            );
        }

        $this->info(json_encode($stats));

        return self::SUCCESS;
    }

    private function fetchAllBitrixUsers(Bitrix24Client $client, bool $activeOnly): array
    {
        $byId = [];

        foreach ($client->listUsers(['ACTIVE' => true]) as $user) {
            $id = (int)($user['ID'] ?? 0);
            if ($id) $byId[$id] = $user;
        }

        if (!$activeOnly) {
            foreach ($client->listUsers(['ACTIVE' => false]) as $user) {
                $id = (int)($user['ID'] ?? 0);
                if ($id && !isset($byId[$id])) {
                    $byId[$id] = $user;
                }
            }
        }

        return array_values($byId);
    }

    private function provisionUser(
        array $remote,
        int $b24Id,
        bool $dryRun,
        bool $withPhoto,
        array $stats,
        int $pos,
        int $total
    ): array {

        $prefix = "[{$pos}/{$total}] b24#{$b24Id}";

        $name = $this->remoteName($remote, $b24Id);
        $email = $this->remoteEmail($remote);
        $photoUrl = $this->remotePhotoUrl($remote);

        /*
        |--------------------------------------
        | 1) MATCH BY BITRIX ID
        |--------------------------------------
        */
        $user = User::where('bitrix24_id', $b24Id)->first();

        if ($user) {
            $stats['exists']++;

            $this->line("$prefix exists");

            if ($withPhoto && $photoUrl && $this->avatarIsEmpty($user)) {
                $this->applyPhoto($user, $photoUrl, $b24Id, $dryRun);
                $stats['photo']++;
            }

            return $stats;
        }

        /*
        |--------------------------------------
        | 2) MATCH BY EMAIL OR NAME
        |--------------------------------------
        */

        $normalizedName = $this->normalize($name);

        $existingByEmail = $email
            ? User::where('email', $email)->first()
            : null;

        $existingByName = User::whereRaw(
            'LOWER(TRIM(name)) = ?',
            [$normalizedName]
        )->first();

        $existingUser = $existingByEmail ?? $existingByName;

        if ($existingUser) {

            $this->line("$prefix update existing user");

            if (!$dryRun) {

                $existingUser->bitrix24_id = $b24Id;

                if (empty($existingUser->name)) {
                    $existingUser->name = $name;
                }

                $existingUser->save();

                if ($withPhoto && $photoUrl) {
                    $this->applyPhoto($existingUser, $photoUrl, $b24Id, $dryRun);
                }
            }

            $stats['linked']++;
            return $stats;
        }

        /*
        |--------------------------------------
        | 3) CREATE NEW USER
        |--------------------------------------
        */

        $avatarPath = ($withPhoto && $photoUrl)
            ? $this->downloadPhoto($photoUrl, $b24Id, $dryRun)
            : null;

        if (!$dryRun) {

            $user = new User();
            $user->name = $name;
            $user->email = $email ?: $this->placeholderEmail($b24Id);
            $user->password = self::DEFAULT_PASSWORD;
            $user->status = 'in_active';
            $user->bitrix24_id = $b24Id;

            if ($avatarPath) {
                $user->avatar = $avatarPath;
            }

            $user->save();

            $this->line("$prefix created user #{$user->id}");
        }

        $stats['created']++;

        if ($avatarPath) {
            $stats['photo']++;
        }

        return $stats;
    }

    private function remoteName($remote, $id)
    {
        return trim(($remote['NAME'] ?? '').' '.($remote['LAST_NAME'] ?? ''))
            ?: ($remote['EMAIL'] ?? "Bitrix #$id");
    }

    private function remoteEmail($remote)
    {
        return $remote['EMAIL'] ?? null;
    }

    private function remotePhotoUrl($remote)
    {
        return $remote['PERSONAL_PHOTO'] ?? null;
    }

    private function normalize(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    private function placeholderEmail(int $id): string
    {
        return "bitrix24.user.$id@local.test";
    }

    private function avatarIsEmpty(User $user): bool
    {
        return empty($user->avatar);
    }

    private function applyPhoto(User $user, string $url, int $id, bool $dryRun): bool
    {
        if ($dryRun) return false;

        $response = Http::get($url);

        if (!$response->ok()) return false;

        $path = "users/avatars/b24-$id-" . Str::random(6) . ".jpg";

        Storage::disk('public')->put($path, $response->body());

        $user->avatar = $path;
        $user->save();

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