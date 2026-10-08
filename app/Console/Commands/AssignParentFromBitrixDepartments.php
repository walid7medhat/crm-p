<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;

class AssignParentFromBitrixDepartments extends Command
{
    protected $signature = 'bitrix24:assign-department-parents
                            {--apply : Save parent_id changes (default is a dry run)}
                            {--all : Include inactive users}';

    protected $description = 'For users without parent_id, resolve their manager from the Bitrix24 department head (UF_HEAD)';

    public function handle()
    {
        $client = new Bitrix24Client();
        $apply = (bool) $this->option('apply');

        // department ID => ['name', 'parent', 'head']
        $departments = collect($client->listDepartments())
            ->mapWithKeys(fn($d) => [(int) $d['ID'] => [
                'name' => $d['NAME'] ?? '',
                'parent' => !empty($d['PARENT']) ? (int) $d['PARENT'] : null,
                'head' => !empty($d['UF_HEAD']) ? (int) $d['UF_HEAD'] : null,
            ]])
            ->all();

        $this->info('Bitrix departments loaded: ' . count($departments));

        // bitrix24_id => local user id
        $localByBitrix = User::whereNotNull('bitrix24_id')->pluck('id', 'bitrix24_id')->all();

        $remoteUsers = collect($client->listUsers($this->option('all') ? [] : ['ACTIVE' => true]))
            ->keyBy(fn($u) => (int) $u['ID']);

        $users = User::where(fn($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->whereNotNull('bitrix24_id')
            ->when(!$this->option('all'), fn($q) => $q->where('status', 'active'))
            ->get();

        $assigned = [];
        $unresolved = [];

        foreach ($users as $user) {
            $remote = $remoteUsers->get((int) $user->bitrix24_id);

            if (!$remote) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, '-', 'Not found in Bitrix'];
                continue;
            }

            $deptIds = array_map('intval', (array) ($remote['UF_DEPARTMENT'] ?? []));
            $deptNames = collect($deptIds)->map(fn($id) => $departments[$id]['name'] ?? "#{$id}")->implode(', ');

            [$headBitrixId, $deptName] = $this->resolveHead($deptIds, (int) $user->bitrix24_id, $departments);

            if (!$headBitrixId) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, $deptNames ?: '-', 'No department head'];
                continue;
            }

            $parentId = $localByBitrix[$headBitrixId] ?? null;

            if (!$parentId) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, $deptNames, "Head (Bitrix #{$headBitrixId}) not in local users"];
                continue;
            }

            $parentName = User::find($parentId)?->name;
            $assigned[] = [$user->id, $user->bitrix24_id, $user->name, $deptName, "{$parentId} ({$parentName})"];

            // Never overwrite an existing parent
            if ($apply && empty($user->parent_id)) {
                $user->parent_id = $parentId;
                $user->save();
            }
        }

        $this->info("\n===== " . ($apply ? 'ASSIGNED' : 'WILL ASSIGN (dry run)') . ' =====');
        $this->table(['User ID', 'Bitrix ID', 'Name', 'Department', 'Parent'], $assigned);

        $this->info("\n===== UNRESOLVED =====");
        $this->table(['User ID', 'Bitrix ID', 'Name', 'Departments', 'Reason'], $unresolved);

        $this->info('Users without parent: ' . $users->count()
            . ' | Resolved: ' . count($assigned)
            . ' | Unresolved: ' . count($unresolved));

        if (!$apply && $assigned) {
            $this->warn('Dry run — re-run with --apply to save.');
        }

        return self::SUCCESS;
    }

    /**
     * Find the head of the user's department. If the user is the head of
     * their own department, walk up to the parent department's head.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveHead(array $deptIds, int $userBitrixId, array $departments): array
    {
        foreach ($deptIds as $deptId) {
            $current = $deptId;
            $guard = 0;

            while ($current && isset($departments[$current]) && $guard++ < 20) {
                $head = $departments[$current]['head'];

                if ($head && $head !== $userBitrixId) {
                    return [$head, $departments[$current]['name']];
                }

                $current = $departments[$current]['parent'];
            }
        }

        return [null, null];
    }
}
