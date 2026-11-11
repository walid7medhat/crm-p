<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dubai leads that are still in the CRM "New Lead" stage: compare each one with Bitrix24
 * (stage, responsible, source, name, phone, email) and list the ones that need an update.
 *
 *   php artisan leads:check-dubai-new                  (read-only)
 *   php artisan leads:check-dubai-new --user=224       (only one responsible user)
 *   php artisan leads:check-dubai-new --update         (apply Bitrix data to the listed leads)
 *
 * --update only refreshes the lead itself (stage + fields, same as the Bitrix sync);
 * comments/activities are not touched — use leads:check-dubai-engagement --import for those.
 */
class CheckDubaiNewLeads extends Command
{
    protected $signature = 'leads:check-dubai-new
        {--branch=Dubai : Branch name}
        {--stage=3 : Local stage id to check (3 = New Lead)}
        {--user= : Only leads of this responsible user (id or email)}
        {--show=50 : How many leads to list in the table}
        {--update : Update the listed leads from Bitrix24 (stage, responsible, source, contact fields)}
        {--force : With --update: do not ask for confirmation}';

    protected $description = 'Check Dubai leads in the New Lead stage against Bitrix24 (stage/data) and optionally update them';

    public function handle(Bitrix24Client $client): int
    {
        $branchName = (string) $this->option('branch');
        $stageId = (int) $this->option('stage');
        $stageName = DB::table('stages')->where('id', $stageId)->value('name') ?? "#{$stageId}";

        $userId = null;
        if ($u = $this->option('user')) {
            $userId = is_numeric($u) ? (int) $u : User::where('email', $u)->value('id');
            if (! $userId) {
                $this->error("User not found: {$u}");
                return self::FAILURE;
            }
        }

        $leads = $this->branchLeads($branchName, $stageId, $userId);
        $this->info("{$branchName} leads in \"{$stageName}\" with a Bitrix24 ID".($userId ? " (user #{$userId})" : '').": {$leads->count()}");
        if ($leads->isEmpty()) {
            return self::SUCCESS;
        }

        $importer = new Bitrix24LeadImporter($client, 1);
        $stageNames = DB::table('stages')->pluck('name', 'id');
        $userNames = [];
        $userName = function ($id) use (&$userNames) {
            if (! $id) {
                return '—';
            }
            return $userNames[$id] ??= (User::whereKey($id)->value('name') ?? "#{$id}");
        };

        $rows = [];
        $toUpdate = [];
        $notInBitrix = [];
        $counts = ['stage' => 0, 'responsible' => 0, 'source' => 0, 'name' => 0, 'phone' => 0, 'email' => 0];

        $bar = $this->output->createProgressBar($leads->count());
        foreach ($leads->chunk(50) as $chunk) {
            $byB24 = $chunk->keyBy('bitrix24_id');
            $r = $client->call('crm.lead.list', [
                'filter' => ['ID' => $byB24->keys()->all()],
                'select' => ['ID', 'TITLE', 'NAME', 'LAST_NAME', 'STATUS_ID', 'ASSIGNED_BY_ID', 'SOURCE_ID', 'PHONE', 'EMAIL'],
            ]);
            $found = [];
            foreach ($r['result'] ?? [] as $b) {
                $b24Id = (int) $b['ID'];
                $found[$b24Id] = true;
                $lead = $byB24[$b24Id] ?? null;
                if (! $lead) {
                    continue;
                }

                $diff = [];
                $b24Stage = $importer->resolveStageId($b['STATUS_ID'] ?? null);
                if ($b24Stage && (int) $b24Stage !== (int) $lead->stage_id) {
                    $diff[] = 'stage';
                }
                $b24Resp = $importer->mapBitrixUser($b['ASSIGNED_BY_ID'] ?? null);
                if ($b24Resp && (int) $b24Resp !== (int) $lead->responsible_person_id) {
                    $diff[] = 'responsible';
                }
                $b24Source = $importer->resolveLeadSource($b['SOURCE_ID'] ?? null) ?? 'Bitrix24';
                if ((string) $lead->lead_source !== $b24Source) {
                    $diff[] = 'source';
                }
                $title = trim((string) ($b['TITLE'] ?? ''));
                $b24Name = $title !== '' ? $title : trim(trim((string) ($b['NAME'] ?? '')).' '.trim((string) ($b['LAST_NAME'] ?? '')));
                if ($b24Name !== '' && $b24Name !== (string) $lead->lead_name) {
                    $diff[] = 'name';
                }
                $b24Phone = $this->firstValue($b['PHONE'] ?? null);
                if ($b24Phone !== '' && $this->digits($b24Phone) !== $this->digits($lead->work_phone)) {
                    $diff[] = 'phone';
                }
                $b24Email = strtolower($this->firstValue($b['EMAIL'] ?? null));
                if ($b24Email !== '' && $b24Email !== strtolower((string) $lead->email)) {
                    $diff[] = 'email';
                }

                if ($diff) {
                    foreach ($diff as $d) {
                        $counts[$d]++;
                    }
                    $toUpdate[] = $lead;
                    $rows[] = [
                        $b24Id,
                        $lead->id,
                        mb_strimwidth((string) $lead->lead_name, 0, 28, '…'),
                        in_array('stage', $diff, true) ? ($stageNames[$b24Stage] ?? $b24Stage) : '(same)',
                        in_array('responsible', $diff, true)
                            ? mb_strimwidth($userName($lead->responsible_person_id), 0, 14, '…').' → '.mb_strimwidth($userName($b24Resp), 0, 14, '…')
                            : mb_strimwidth($userName($lead->responsible_person_id), 0, 14, '…'),
                        implode(', ', $diff),
                    ];
                }
            }
            foreach ($byB24 as $b24Id => $lead) {
                if (! isset($found[$b24Id])) {
                    $notInBitrix[] = [$b24Id, $lead->id, mb_strimwidth((string) $lead->lead_name, 0, 28, '…')];
                }
            }
            $bar->advance($chunk->count());
        }
        $bar->finish();
        $this->newLine(2);

        $ok = $leads->count() - count($rows) - count($notInBitrix);
        $this->table(['Result', 'Leads'], [
            ['Up to date', $ok],
            ['Need update', count($rows)],
            ['  – stage different in Bitrix', $counts['stage']],
            ['  – responsible different', $counts['responsible']],
            ['  – source different', $counts['source']],
            ['  – name / phone / email different', $counts['name'].' / '.$counts['phone'].' / '.$counts['email']],
            ['Not found in Bitrix24 (deleted there?)', count($notInBitrix)],
        ]);

        $show = max(1, (int) $this->option('show'));
        if ($rows) {
            $this->warn(count($rows).' lead(s) need an update:');
            $this->table(['Bitrix ID', 'CRM ID', 'Lead', 'Bitrix stage', 'Responsible', 'Different'], array_slice($rows, 0, $show));
            if (count($rows) > $show) {
                $this->line('… and '.(count($rows) - $show).' more (use --show=N).');
            }
            $path = storage_path('app/dubai-new-leads-to-update.txt');
            file_put_contents($path, implode(PHP_EOL, array_column($rows, 0)).PHP_EOL);
            $this->info("Bitrix lead IDs saved to: {$path}");
        }
        if ($notInBitrix) {
            $this->warn(count($notInBitrix).' lead(s) not found in Bitrix24:');
            $this->table(['Bitrix ID', 'CRM ID', 'Lead'], array_slice($notInBitrix, 0, $show));
        }

        if (! $rows) {
            $this->info("✓ All {$branchName} \"{$stageName}\" leads match Bitrix24.");
            return self::SUCCESS;
        }
        if (! $this->option('update')) {
            $this->line('To apply the Bitrix24 data to these leads run again with --update');
            return self::SUCCESS;
        }

        return $this->applyUpdates($client, $importer, $toUpdate);
    }

    /** --update: crm.lead.get each lead and refresh it (stage + fields), like the Bitrix sync does. */
    private function applyUpdates(Bitrix24Client $client, Bitrix24LeadImporter $importer, array $leads): int
    {
        $this->newLine();
        $this->warn('Will update '.count($leads).' lead(s) from Bitrix24: stage, responsible, source and contact fields.');
        $answer = $this->option('force') ? 'yes' : (string) $this->ask('Continue? Type yes to update', 'yes');
        if (! in_array(strtolower(preg_replace('/[^\p{L}]/u', '', $answer)), ['y', 'yes'], true)) {
            $this->line('Cancelled — nothing updated.');
            return self::SUCCESS;
        }

        $updated = 0;
        $moved = 0;
        $errors = 0;
        $bar = $this->output->createProgressBar(count($leads));
        foreach ($leads as $row) {
            try {
                $b24 = $client->call('crm.lead.get', ['id' => $row->bitrix24_id])['result'] ?? null;
                $lead = Lead::find($row->id);
                if ($b24 && $lead) {
                    $before = (int) $lead->stage_id;
                    $importer->syncLeadData($lead, $b24);
                    $updated++;
                    if ((int) $lead->fresh()->stage_id !== $before) {
                        $moved++;
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->warn("Lead {$row->id} (Bitrix {$row->bitrix24_id}): {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Updated {$updated} lead(s) — {$moved} moved to another stage. Errors: {$errors}");

        return self::SUCCESS;
    }

    /** Branch leads in one stage: lead.branch = branch, or no branch and (branch source / responsible in that branch). */
    private function branchLeads(string $branchName, int $stageId, ?int $userId)
    {
        $branchUser = User::role('admin')->whereRaw('LOWER(name) = ?', [mb_strtolower($branchName)])->first();
        $teamIds = $branchUser ? $branchUser->getAllSubordinatesIds() : [];

        return DB::table('leads')
            ->whereNull('deleted_at')
            ->whereNotNull('bitrix24_id')
            ->where('stage_id', $stageId)
            ->when($userId, fn ($q) => $q->where('responsible_person_id', $userId))
            ->where(function ($q) use ($branchName, $teamIds) {
                $q->where('branch', $branchName)
                    ->orWhere(function ($q2) use ($branchName, $teamIds) {
                        $q2->where(fn ($b) => $b->whereNull('branch')->orWhere('branch', ''))
                            ->where(function ($q3) use ($branchName, $teamIds) {
                                $q3->where('lead_branch_source', 'like', '%'.$branchName.'%');
                                if ($teamIds) {
                                    $q3->orWhereIn('responsible_person_id', $teamIds);
                                }
                            });
                    });
            })
            ->get(['id', 'bitrix24_id', 'lead_name', 'stage_id', 'responsible_person_id', 'lead_source', 'work_phone', 'email'])
            ->map(function ($l) {
                $l->bitrix24_id = (int) $l->bitrix24_id;
                return $l;
            });
    }

    /** First VALUE of a Bitrix multifield (PHONE / EMAIL). */
    private function firstValue($field): string
    {
        if (is_array($field)) {
            foreach ($field as $item) {
                $v = trim((string) (is_array($item) ? ($item['VALUE'] ?? '') : $item));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return '';
    }

    private function digits($v): string
    {
        $d = preg_replace('/\D/', '', (string) $v);

        return ltrim(substr($d, -9), '0'); // compare last 9 digits: ignores +971 / 00971 / 0 prefixes
    }
}
