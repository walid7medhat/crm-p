<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24FieldLabels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permanently rewrites raw Bitrix24 field codes (UF_CRM_*) inside a lead's
 * stored raw_meta_data into their human labels, using Bitrix24FieldLabels —
 * so already-imported leads read correctly even if Bitrix24 later becomes
 * unreachable, instead of relying on live/runtime resolution forever.
 */
class BakeBitrixFieldLabels extends Command
{
    protected $signature = 'bitrix24:bake-field-labels
        {--dry-run : Show what would change without writing}';

    protected $description = 'Bake resolved Bitrix24 field labels into raw_meta_data for existing leads (permanent, no live Bitrix dependency after this)';

    private const BATCH_SIZE = 200;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Lead::select(['id', 'raw_meta_data'])->whereNotNull('raw_meta_data');

        $this->info('Scanning leads with raw_meta_data...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        $updated = 0;
        $unchanged = 0;
        $fieldsRewritten = 0;

        $query->orderBy('id')->chunkById(self::BATCH_SIZE, function ($leads) use (
            $dryRun, $bar, &$updated, &$unchanged, &$fieldsRewritten
        ) {
            foreach ($leads as $lead) {
                $raw = is_string($lead->raw_meta_data)
                    ? json_decode($lead->raw_meta_data, true)
                    : $lead->raw_meta_data;

                if (empty($raw['field_data']) || !is_array($raw['field_data'])) {
                    $unchanged++;
                    $bar->advance();
                    continue;
                }

                $changed = false;

                foreach ($raw['field_data'] as &$field) {
                    if (!is_array($field) || empty($field['name'])) {
                        continue;
                    }

                    // Already baked in a previous run — leave it alone.
                    if (isset($field['code'])) {
                        continue;
                    }

                    $code = (string) $field['name'];
                    $label = Bitrix24FieldLabels::resolve($code);

                    if ($label && $label !== $code) {
                        $field['name'] = $label;
                        $field['code'] = $code;
                        $changed = true;
                        $fieldsRewritten++;
                    }
                }
                unset($field);

                if ($changed) {
                    $updated++;

                    if (!$dryRun) {
                        // Raw query builder update targeting only this one column —
                        // guarantees nothing else (bitrix24_last_activity_at,
                        // updated_at, any model event) is touched by this bake.
                        DB::table('leads')
                            ->where('id', $lead->id)
                            ->update(['raw_meta_data' => json_encode($raw, JSON_UNESCAPED_UNICODE)]);
                    }
                } else {
                    $unchanged++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Leads updated: {$updated}");
        $this->info("Fields rewritten: {$fieldsRewritten}");
        $this->info("Leads unchanged: {$unchanged}");

        if ($dryRun) {
            $this->info('Dry run — no changes written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
