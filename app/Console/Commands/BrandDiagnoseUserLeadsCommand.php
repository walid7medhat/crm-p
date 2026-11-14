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

        $this->explainBoard($user, $base, $visibility, $inTree);

        return self::SUCCESS;
    }

    /**
     * Same rules as StageController::getStagesWithLeads for a sales-level user:
     * each lead is counted under the FIRST rule that hides it.
     */
    private function explainBoard(User $user, $base, ?string $visibility, bool $inTree): void
    {
        $isAdminLevel = $user->hasAnyRole(['super_admin', 'admin', 'branch_admin', 'manager', 'team_lead']);
        if ($isAdminLevel) {
            $this->line('This user is admin level — the board uses their team, not only their own leads.');

            return;
        }

        $visibleIds = array_map('intval', array_filter((array) json_decode((string) $visibility, true)));
        if ($visibleIds === []) {
            $visibleIds = DB::table('stages')->where('stage_type', 'lead')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $newLeadId = (int) DB::table('stages')->where('stage_type', 'lead')->orderBy('order')->value('id');
        if ($user->hasAnyRole(['manager', 'sales']) && $user->is_listing_team && $newLeadId && ! in_array($newLeadId, $visibleIds, true)) {
            $visibleIds[] = $newLeadId;
        }
        $poolIds = DB::table('stages')->where('stage_type', 'lead')->whereRaw('LOWER(name) = ?', ['lead pool'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $isLuxvantUser = $user->brand_key === 'luxvant' || $inTree;

        $reasons = [];
        $shown = 0;
        foreach ((clone $base)->get(['stage_id', 'revert', 'brand_key']) as $lead) {
            $stageId = (int) $lead->stage_id;
            $reason = match (true) {
                $isLuxvantUser && $lead->brand_key !== 'luxvant' => 'Not marked luxvant (hidden on Luxvant host)',
                ! $isLuxvantUser && $lead->brand_key === 'luxvant' => 'Marked luxvant (hidden on OIA host)',
                in_array($stageId, $poolIds, true) => 'In Lead Pool (shown in the Lead Pool tab, not the board)',
                ! in_array($stageId, $visibleIds, true) => 'Stage hidden for role "'.($user->getRoleNames()->first() ?? '-').'"'
                    .($stageId === $newLeadId ? ' (New Lead)' : ''),
                $lead->revert !== null => 'Reverted (revert set — hidden from sales)',
                default => null,
            };

            if ($reason === null) {
                $shown++;
            } else {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }

        $this->newLine();
        $this->info("On their own Leads board this user sees: {$shown} lead(s)");
        if ($reasons !== []) {
            arsort($reasons);
            $this->table(['Why the rest are hidden', 'Leads'], collect($reasons)->map(fn ($n, $r) => [$r, $n])->values()->all());
        }
    }
}
