<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Brand\BrandCrmScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: explain why a user does / does not see their leads on a brand host.
 *
 *   php artisan brand:diagnose-user 123
 */
class BrandDiagnoseUserLeadsCommand extends Command
{
    protected $signature = 'brand:diagnose-user {user : User id}';

    protected $description = 'Show why a user sees or does not see their leads (brand, revert, stage visibility)';

    public function handle(): int
    {
        $user = User::query()->find((int) $this->argument('user'));
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $inTree = BrandCrmScope::userInConfiguredCrmBranch((int) $user->id);
        $roles = $user->getRoleNames()->implode(', ');

        $this->info("User #{$user->id} {$user->name}");
        $this->table(['Field', 'Value'], [
            ['brand_key', $user->brand_key ?? 'NULL'],
            ['In Admin Luxvant tree', $inTree ? 'yes' : 'no'],
            ['Roles', $roles ?: '-'],
            ['Signs in on', $user->brand_key === 'luxvant' || $inTree ? 'Luxvant' : 'OIA'],
        ]);

        $base = DB::table('leads')->where('responsible_person_id', $user->id)->whereNull('deleted_at');

        $this->info('Leads where this user is the responsible person:');
        $rows = (clone $base)
            ->leftJoin('stages', 'stages.id', '=', 'leads.stage_id')
            ->selectRaw("COALESCE(leads.brand_key, 'NULL') as brand, IF(leads.revert IS NULL, 'no', 'yes') as reverted, stages.name as stage, COUNT(*) as total")
            ->groupBy('brand', 'reverted', 'stage')
            ->orderBy('brand')
            ->get()
            ->map(fn ($r) => [$r->brand, $r->reverted, $r->stage ?? '-', $r->total])
            ->all();
        $this->table(['brand_key', 'revert set', 'Stage', 'Leads'], $rows ?: [['-', '-', '-', 0]]);

        $luxVisible = (clone $base)->where('brand_key', 'luxvant')->whereNull('revert')->count();
        $wrongBrand = (clone $base)->where(function ($q) {
            $q->whereNull('brand_key')->orWhere('brand_key', '<>', 'luxvant');
        })->count();

        if ($user->brand_key === 'luxvant' || $inTree) {
            $this->line("Visible to this user on Luxvant (brand luxvant, not reverted): <info>{$luxVisible}</info>");
            if ($wrongBrand > 0) {
                $this->warn("{$wrongBrand} of this Luxvant user's leads are NOT marked luxvant, so the Luxvant board hides them.");
                $this->line('Fix: php artisan brand:classify-luxvant-crm --dry-run   (then without --dry-run)');
            }
        }

        $roleName = $user->getRoleNames()->first();
        $visibility = $roleName && \Illuminate\Support\Facades\Schema::hasTable('stage_visibility')
            ? DB::table('stage_visibility')->where('role_name', $roleName)->value('visible_stages')
            : null;
        $this->line('Stage visibility for role "'.($roleName ?? '-').'": '.($visibility ?: 'all lead stages'));

        return self::SUCCESS;
    }
}
