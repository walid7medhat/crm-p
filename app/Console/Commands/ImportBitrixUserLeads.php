<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Bring one user's leads from Bitrix24 (leads whose responsible = this user in Bitrix),
 * e.g. after a new CRM user was added and their bitrix24_id was set.
 *
 *   php artisan bitrix:import-user-leads 245                  (read-only report)
 *   php artisan bitrix:import-user-leads ali@oia.ae --import  (create missing + update existing)
 *   php artisan bitrix:import-user-leads 245 --import --only-missing   (create missing only)
 *
 * --import runs the normal Bitrix importer on every lead: a missing lead is created
 * (lead + comments + activities + timeline); a lead already in the CRM is updated
 * (responsible, stage, fields) and gets its new comments / activities / timeline.
 * Leads deleted in the CRM are skipped.
 */
class ImportBitrixUserLeads extends Command
{
    protected $signature = 'bitrix:import-user-leads
        {user : CRM user id or email}
        {--bitrix-id= : Bitrix24 user id (default: the user\'s bitrix24_id)}
        {--since= : Only Bitrix leads created on/after this date (Y-m-d)}
        {--show=30 : How many leads to list per table}
        {--import : Create missing leads and update the ones already in the CRM}
        {--only-missing : With --import: only create missing leads, do not update existing ones}
        {--dry-run : Show, lead by lead, what --import would create / change — writes nothing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Check / import the Bitrix24 leads of one user (responsible in Bitrix24)';

    public function handle(Bitrix24Client $client): int
    {
        $arg = (string) $this->argument('user');
        $user = is_numeric($arg) ? User::find((int) $arg) : User::where('email', $arg)->first();
        if (! $user) {
            $this->error("User not found: {$arg}");
            return self::FAILURE;
        }

        $b24UserId = (int) ($this->option('bitrix-id') ?: $user->bitrix24_id);
        if (! $b24UserId) {
            $this->error("User #{$user->id} ({$user->name}) has no bitrix24_id. Set it first or pass --bitrix-id=.");
            return self::FAILURE;
        }
        if ((int) $user->bitrix24_id !== $b24UserId) {
            $this->warn("Note: users.bitrix24_id of #{$user->id} is ".($user->bitrix24_id ?: 'empty')
                ." — imported leads are mapped by users.bitrix24_id, so set it to {$b24UserId} first.");
        }

        $this->info("User #{$user->id} {$user->name} — Bitrix24 user {$b24UserId}");

        // ---- 1) all Bitrix leads of this responsible -------------------------------
        $filter = ['ASSIGNED_BY_ID' => $b24UserId];
        if ($since = $this->option('since')) {
            $filter['>=DATE_CREATE'] = $since;
        }

        $b24Leads = [];
        $start = 0;
        do {
            $r = $client->call('crm.lead.list', [
                'filter' => $filter,
                'select' => ['ID', 'TITLE', 'NAME', 'LAST_NAME', 'STATUS_ID', 'SOURCE_ID', 'PHONE', 'DATE_CREATE'],
                'order' => ['ID' => 'DESC'],
                'start' => $start,
            ]);
            foreach ($r['result'] ?? [] as $b) {
                $b24Leads[(int) $b['ID']] = $b;
            }
            $start = $r['next'] ?? null;
        } while ($start !== null);

        $this->info('Leads in Bitrix24: '.count($b24Leads));
        if (! $b24Leads) {
            return self::SUCCESS;
        }

        // ---- 2) compare with the CRM ----------------------------------------------
        $local = Lead::withTrashed()
            ->whereIn('bitrix24_id', array_keys($b24Leads))
            ->get(['id', 'bitrix24_id', 'responsible_person_id', 'stage_id', 'lead_name', 'lead_source', 'work_phone', 'deleted_at'])
            ->keyBy('bitrix24_id');

        $importer = new Bitrix24LeadImporter($client, 1);
        $missing = $otherOwner = $deleted = $mine = [];
        foreach ($b24Leads as $b24Id => $b) {
            $lead = $local[$b24Id] ?? null;
            if (! $lead) {
                $missing[] = $b24Id;
            } elseif ($lead->deleted_at) {
                $deleted[] = $b24Id;
            } elseif ((int) $lead->responsible_person_id !== (int) $user->id) {
                $otherOwner[] = $b24Id;
            } else {
                $mine[] = $b24Id;
            }
        }

        $this->table(['Status', 'Leads'], [
            ['Already in CRM for this user', count($mine)],
            ['Missing in CRM', count($missing)],
            ['In CRM under another responsible', count($otherOwner)],
            ['In CRM but deleted (skipped)', count($deleted)],
        ]);

        $show = max(1, (int) $this->option('show'));
        $title = fn ($b) => trim((string) ($b['TITLE'] ?? '')) ?: trim(($b['NAME'] ?? '').' '.($b['LAST_NAME'] ?? ''));

        if ($missing) {
            $this->line('<comment>Missing in CRM:</comment>');
            $this->table(['Bitrix ID', 'Title', 'Bitrix status', 'Created'], collect($missing)->take($show)->map(fn ($id) => [
                $id, mb_substr($title($b24Leads[$id]), 0, 40), $b24Leads[$id]['STATUS_ID'] ?? '', substr((string) ($b24Leads[$id]['DATE_CREATE'] ?? ''), 0, 10),
            ])->all());
        }
        if ($otherOwner) {
            $names = User::whereIn('id', $local->only($otherOwner)->pluck('responsible_person_id')->unique())->pluck('name', 'id');
            $this->line('<comment>In CRM under another responsible:</comment>');
            $this->table(['Bitrix ID', 'CRM lead', 'Title', 'CRM responsible now'], collect($otherOwner)->take($show)->map(fn ($id) => [
                $id, $local[$id]->id, mb_substr($title($b24Leads[$id]), 0, 40),
                $names[$local[$id]->responsible_person_id] ?? ('#'.$local[$id]->responsible_person_id),
            ])->all());
        }

        $existing = $this->option('only-missing') ? [] : array_merge($otherOwner, $mine);

        if ($this->option('dry-run')) {
            $this->dryRun($importer, $user, $b24Leads, $local, $missing, $existing, $deleted, $title, $show);
            return self::SUCCESS;
        }

        if (! $this->option('import')) {
            $this->line('Run again with --import to create the missing leads and update the existing ones'
                .' (add --only-missing to skip the update).');
            return self::SUCCESS;
        }
        if (! $missing && ! $existing) {
            $this->info('Nothing to import.');
            return self::SUCCESS;
        }

        $plan = count($missing).' new lead(s) + '.count($existing).' existing lead(s) updated from Bitrix';
        if (! $this->option('force')) {
            $answer = strtolower(preg_replace('/[^a-z]/i', '', (string) $this->ask("Apply: {$plan}? Type yes", 'yes')));
            if (! in_array($answer, ['y', 'yes'], true)) {
                $this->warn('Cancelled — nothing changed.');
                return self::SUCCESS;
            }
        }

        // ---- 3) apply -------------------------------------------------------------
        $created = $fixed = $errors = 0;
        $work = array_merge($missing, $existing);
        $bar = $this->output->createProgressBar(count($work));
        $bar->start();
        foreach ($work as $b24Id) {
            try {
                $full = $client->call('crm.lead.get', ['id' => $b24Id])['result'] ?? null;
                if (! $full) {
                    throw new \RuntimeException('crm.lead.get returned nothing');
                }
                // importOne: creates a missing lead; for an existing one it re-syncs
                // responsible / stage / fields and adds new comments, activities, timeline.
                $res = $importer->importOne($full);
                $res['created'] ? $created++ : $fixed++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('bitrix:import-user-leads failed', ['bitrix_id' => $b24Id, 'error' => $e->getMessage()]);
                $this->newLine();
                $this->warn("Bitrix lead {$b24Id}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Created: {$created}   Updated: {$fixed}   Errors: {$errors}");

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lead-by-lead preview of --import: what is created, and for existing leads which
     * fields Bitrix would change (stage / responsible / source / name / phone).
     * Comments / activities are added on update too, but are not counted here.
     */
    private function dryRun(Bitrix24LeadImporter $importer, User $user, array $b24Leads, $local,
        array $missing, array $existing, array $deleted, callable $title, int $show): void
    {
        $stageNames = \App\Models\Stage::pluck('name', 'id');
        $userNames = [];
        $uname = function ($id) use (&$userNames) {
            if (! $id) {
                return '—';
            }
            return $userNames[$id] ??= (User::whereKey($id)->value('name') ?? "#{$id}");
        };
        $digits = fn ($v) => substr(preg_replace('/\D+/', '', (string) $v), -9);

        $rows = [];
        foreach ($missing as $id) {
            $b = $b24Leads[$id];
            $stage = $importer->resolveStageId($b['STATUS_ID'] ?? null);
            $rows[] = [$id, '—', 'CREATE', mb_substr($title($b), 0, 35),
                'stage: '.($stageNames[$stage] ?? ($b['STATUS_ID'] ?? '?')).', responsible: '.$user->name];
        }

        $noChange = 0;
        foreach ($existing as $id) {
            $b = $b24Leads[$id];
            $lead = $local[$id];
            $changes = [];

            $stage = $importer->resolveStageId($b['STATUS_ID'] ?? null);
            if ($stage && (int) $stage !== (int) $lead->stage_id) {
                $changes[] = 'stage: '.($stageNames[$lead->stage_id] ?? $lead->stage_id).' → '.($stageNames[$stage] ?? $stage);
            }
            $resp = $importer->mapBitrixUser($user->bitrix24_id) ?: $user->id;
            if ((int) $resp !== (int) $lead->responsible_person_id) {
                $changes[] = 'responsible: '.$uname($lead->responsible_person_id).' → '.$uname($resp);
            }
            $source = $importer->resolveLeadSource($b['SOURCE_ID'] ?? null) ?? 'Bitrix24';
            if ((string) $lead->lead_source !== $source) {
                $changes[] = 'source: '.($lead->lead_source ?: '—').' → '.$source;
            }
            $name = $title($b);
            if ($name !== '' && trim((string) $lead->lead_name) !== $name) {
                $changes[] = 'name: '.mb_substr((string) $lead->lead_name, 0, 20).' → '.mb_substr($name, 0, 20);
            }
            $phone = is_array($b['PHONE'] ?? null) ? ($b['PHONE'][0]['VALUE'] ?? '') : '';
            if ($phone !== '' && $digits($phone) !== $digits($lead->work_phone)) {
                $changes[] = 'phone: '.($lead->work_phone ?: '—').' → '.$phone;
            }

            if (! $changes) {
                $noChange++;
                continue;
            }
            $rows[] = [$id, $lead->id, 'UPDATE', mb_substr($name, 0, 35), implode("\n", $changes)];
        }

        $this->newLine();
        $this->line('<info>DRY RUN — nothing is written.</info>');
        $this->table(['Would', 'Leads'], [
            ['Create (missing in CRM)', count($missing)],
            ['Update (data differs from Bitrix)', count($rows) - count($missing)],
            ['Existing, data already same (only new comments/activities)', $noChange],
            ['Skip (deleted in CRM)', count($deleted)],
        ]);

        if ($rows) {
            $this->table(['Bitrix ID', 'CRM lead', 'Action', 'Title', 'Changes'], array_slice($rows, 0, $show));
            if (count($rows) > $show) {
                $this->line('… '.(count($rows) - $show).' more (use --show='.count($rows).' to list all).');
            }
        }

        $file = storage_path('app/bitrix-user-'.$user->id.'-dry-run.txt');
        file_put_contents($file, implode("\n", array_map(
            fn ($r) => implode("\t", [$r[0], $r[1], $r[2], $r[3], str_replace("\n", ' | ', $r[4])]),
            $rows
        ))."\n");
        $this->line("Full list saved to {$file}");
        $this->line('Looks right? Run again with --import (without --dry-run) to apply.');
    }
}
