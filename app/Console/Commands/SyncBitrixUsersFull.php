<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;

class SyncBitrixUsersFull extends Command
{
    protected $signature = 'bitrix24:full-sync';

    protected $description = 'Full sync users (status + manager + roles + inactive list)';

    public function handle()
    {
        $client = new Bitrix24Client();

        $remoteUsers = $client->listUsers([
            'SELECT' => ['ID', 'NAME', 'LAST_NAME', 'ACTIVE', 'UF_HEAD']
        ]);

        $inactiveUsers = [];
        $activeNoManager = [];

        foreach ($remoteUsers as $remote) {

            $b24Id = (int)($remote['ID'] ?? 0);
            if (!$b24Id) continue;

            $user = User::where('bitrix24_id', $b24Id)->first();
            if (!$user) continue;

            /*
            |-----------------------------
            | STATUS
            |-----------------------------
            */
            $value = $remote['ACTIVE'] ?? false;
            $isActive = $value === true || $value === 'Y';

            $user->status = $isActive ? 'active' : 'in_active';

            /*
            |-----------------------------
            | NAME
            |-----------------------------
            */
            $name = trim(($remote['NAME'] ?? '') . ' ' . ($remote['LAST_NAME'] ?? ''));

            /*
            |-----------------------------
            | MANAGER
            |-----------------------------
            */
            $managerId = $remote['UF_HEAD'] ?? null;

            if ($isActive && ($user->parent_id==null || !$user->parent_id)) {
                $activeNoManager[] = [
                    'id' => $user->id,

                    'bitrix_id' => $user->bitrix24_id,
                    'name' => $name,
                ];
            }

            /*
            |-----------------------------
            | INACTIVE USERS ARRAY
            |-----------------------------
            */
            if (!$isActive) {
                $inactiveUsers[] = [
                    'id' => $user->id,
                    'bitrix_id' => $user->bitrix24_id,
                    'name' => $name,
                ];
            }

            /*
            |-----------------------------
            | ROLE
            |-----------------------------
            */
           if ($user->roles->isEmpty()) {
                $user->assignRole('sales');
            }

            $user->save();
        }

        /*
        |-----------------------------
        | OUTPUT
        |-----------------------------
        */

        $this->info("===== INACTIVE USERS =====");

        foreach ($inactiveUsers as $u) {
            $this->line("ID: {$u['id']} | Name: {$u['name']}");
        }

        $this->info("Total Inactive: " . count($inactiveUsers));

        $this->info("\n===== ACTIVE USERS WITH NO MANAGER =====");

        foreach ($activeNoManager as $u) {
            $this->line("ID: {$u['id']} | Name: {$u['name']}");
        }

        $this->info("Total Active without manager " . count($activeNoManager));

        return self::SUCCESS;
    }
}