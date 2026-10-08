<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;

class AssignParentFromBitrixDepartments extends Command
{
    protected $signature = 'bitrix24:assign-department-parents
                            {--apply : Save parent_id changes (default is a dry run)}
                            {--all : Include inactive users}
                            {--map=* : Bitrix department → local parent user, e.g. --map=107:59 (repeatable)}';

    protected $description = 'For users without parent_id, set their manager from their Bitrix24 department (a --map, or the department head when department.get is allowed)';

    /**
     * Built-in mappings: Bitrix department ID => local parent user ID.
     * --map values are added on top (and win on the same department).
     */
    private const DEFAULT_MAP = [
        107 => 59,
    ];

    public function handle()
    {
        $client = new Bitrix24Client();
        $apply = (bool) $this->option('apply');

        $map = $this->departmentMap();
        $this->info('Department → parent map: ' . collect($map)->map(fn ($p, $d) => "{$d} → #{$p}")->implode(', '));

        // department.get needs the "department" scope on the webhook. Without it we can
        // still use the map (user.get returns each user's UF_DEPARTMENT ids).
        $departments = [];
        try {
            $departments = collect($client->listDepartments())
                ->mapWithKeys(fn ($d) => [(int) $d['ID'] => [
                    'name' => $d['NAME'] ?? '',
                    'parent' => !empty($d['PARENT']) ? (int) $d['PARENT'] : null,
                    'head' => !empty($d['UF_HEAD']) ? (int) $d['UF_HEAD'] : null,
                ]])
                ->all();
            $this->info('Bitrix departments loaded: ' . count($departments));
        } catch (\Throwable $e) {
            $this->warn('department.get not allowed for this webhook (' . $e->getMessage() . ') — using the --map only; department names / heads unknown.');
        }

        // bitrix24_id => local user id
        $localByBitrix = User::whereNotNull('bitrix24_id')->pluck('id', 'bitrix24_id')->all();

        $remoteUsers = collect($client->listUsers($this->option('all') ? [] : ['ACTIVE' => true]))
            ->keyBy(fn ($u) => (int) $u['ID']);

        $users = User::where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->whereNotNull('bitrix24_id')
            ->when(!$this->option('all'), fn ($q) => $q->where('status', 'active'))
            ->get();

        $assigned = [];
        $unresolved = [];
        $unresolvedByDept = []; // dept id => [user ids]

        foreach ($users as $user) {
            $remote = $remoteUsers->get((int) $user->bitrix24_id);

            if (!$remote) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, '-', 'Not found in Bitrix'];
                continue;
            }

            $deptIds = array_map('intval', (array) ($remote['UF_DEPARTMENT'] ?? []));
            $deptLabel = collect($deptIds)
                ->map(fn ($id) => isset($departments[$id]['name']) ? "{$id} ({$departments[$id]['name']})" : (string) $id)
                ->implode(', ');

            // 1) A mapped department wins.
            $parentId = null;
            $via = null;
            foreach ($deptIds as $deptId) {
                if (isset($map[$deptId])) {
                    $parentId = $map[$deptId];
                    $via = "dept {$deptId} (map)";
                    break;
                }
            }

            // 2) Otherwise the Bitrix department head (only when department.get worked).
            if (!$parentId && $departments) {
                [$headBitrixId, $deptName] = $this->resolveHead($deptIds, (int) $user->bitrix24_id, $departments);
                if ($headBitrixId && isset($localByBitrix[$headBitrixId])) {
                    $parentId = $localByBitrix[$headBitrixId];
                    $via = "head of {$deptName}";
                }
            }

            if (!$parentId) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, $deptLabel ?: '-', 'No mapping for this department'];
                foreach ($deptIds ?: [0] as $deptId) {
                    $unresolvedByDept[$deptId][] = $user->id;
                }
                continue;
            }

            $parent = User::find($parentId);
            if (!$parent) {
                $unresolved[] = [$user->id, $user->bitrix24_id, $user->name, $deptLabel, "Parent #{$parentId} not in local users"];
                continue;
            }

            $assigned[] = [$user->id, $user->bitrix24_id, $user->name, $deptLabel, $via, "{$parentId} ({$parent->name})"];

            // Never overwrite an existing parent
            if ($apply && empty($user->parent_id)) {
                $user->parent_id = $parentId;
                $user->save();
            }
        }

        $this->info("\n===== " . ($apply ? 'ASSIGNED' : 'WILL ASSIGN (dry run)') . ' =====');
        $this->table(['User ID', 'Bitrix ID', 'Name', 'Departments', 'Via', 'Parent'], $assigned);

        $this->info("\n===== UNRESOLVED =====");
        $this->table(['User ID', 'Bitrix ID', 'Name', 'Departments', 'Reason'], $unresolved);

        if ($unresolvedByDept) {
            $this->info("\n===== STILL WITHOUT PARENT, BY BITRIX DEPARTMENT (add --map=DEPT:PARENT_ID) =====");
            $rows = collect($unresolvedByDept)
                ->map(fn ($ids, $dept) => [
                    $dept ?: '(none)',
                    $departments[$dept]['name'] ?? '-',
                    count($ids),
                    implode(', ', $ids),
                ])
                ->sortByDesc(fn ($row) => $row[2])
                ->values()
                ->all();
            $this->table(['Bitrix dept', 'Name', 'Users', 'Local user IDs'], $rows);
        }

        $this->info('Users without parent: ' . $users->count()
            . ' | Resolved: ' . count($assigned)
            . ' | Unresolved: ' . count($unresolved));

        if (!$apply && $assigned) {
            $this->warn('Dry run — re-run with --apply to save.');
        }

        return self::SUCCESS;
    }

    /** DEFAULT_MAP + --map=DEPT:PARENT (or DEPT=PARENT) options. */
    private function departmentMap(): array
    {
        $map = self::DEFAULT_MAP;
        foreach ((array) $this->option('map') as $entry) {
            foreach (preg_split('/\s*,\s*/', (string) $entry) as $pair) {
                if (preg_match('/^\s*(\d+)\s*[:=]\s*(\d+)\s*$/', $pair, $m)) {
                    $map[(int) $m[1]] = (int) $m[2];
                } elseif (trim($pair) !== '') {
                    $this->warn("Ignored --map value '{$pair}' (use DEPT:PARENT_ID)");
                }
            }
        }

        return $map;
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
