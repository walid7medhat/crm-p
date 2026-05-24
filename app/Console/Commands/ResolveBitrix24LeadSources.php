<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveBitrix24LeadSources extends Command
{
    protected $signature = 'bitrix24:resolve-sources
        {--dry-run : Show what would change without writing}
        {--fresh : Bust the source cache before fetching}
        {--only-bitrix24 : Only update leads imported from Bitrix24}';

    protected $description = 'Resolve lead_source + clean comments for Bitrix24 leads';

    public function handle(): int
    {
        $processed = 0;
        $commentUpdated = 0;

        Log::channel('bitrix_resolve')->info('START command', [
            'time' => now(),
        ]);

        if ($this->option('fresh')) {
            Cache::forget('bitrix_lead_sources');
            $this->line('🗑 Cleared cache');
        }

        try {
            $client   = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, 1);
            $sources  = $importer->getLeadSources();
        } catch (\Throwable $e) {
            Log::channel('bitrix_resolve')->error('Failed to load sources', [
                'error' => $e->getMessage()
            ]);

            return self::FAILURE;
        }

        if (empty($sources)) {
            return self::FAILURE;
        }

        $dryRun  = (bool) $this->option('dry-run');
        $onlyB24 = (bool) $this->option('only-bitrix24');

        /**
         * =========================
         * 1️⃣ BUILD SQL MAP
         * =========================
         */
        $cases = '';
        $ids   = [];

        // foreach ($sources as $id => $name) {
        //     $safeId   = addslashes($id);
        //     $safeName = addslashes($name);

        //     $cases .= "WHEN '{$safeId}' THEN '{$safeName}' ";
        //     $ids[] = "'{$safeId}'";
        // }

        // $where = "lead_source IN (" . implode(',', $ids) . ")";

        // if ($onlyB24) {
        //     $where .= " AND bitrix24_id IS NOT NULL";
        // }

        /**
         * =========================
         * 2️⃣ UPDATE lead_source
         * =========================
         */
        // if (!$dryRun) {

        //     $updated = DB::update("
        //         UPDATE leads
        //         SET lead_source = CASE lead_source
        //             {$cases}
        //             ELSE lead_source
        //         END
        //         WHERE {$where}
        //     ");

        //     Log::channel('bitrix_resolve')->info('lead_source updated', [
        //         'count' => $updated
        //     ]);

        //     $this->info("✅ lead_source updated: {$updated}");

        // } else {

        //     $count = DB::selectOne("
        //         SELECT COUNT(*) as total
        //         FROM leads
        //         WHERE {$where}
        //     ");

        //     Log::channel('bitrix_resolve')->info('dry-run lead_source', [
        //         'would_update' => $count->total
        //     ]);

        //     $this->info("🔎 Would update: {$count->total}");
        // }

        /**
         * =========================
         * 3️⃣ CLEAN COMMENTS
         * =========================
         */
        Lead::whereNotNull('bitrix24_id')
        ->where('id','>',24816)
    ->chunkById(500, function ($leads) use (
        $dryRun,
        &$commentUpdated,
        &$processed,
        $importer
    ) {

        $ids = $leads->pluck('id')->toArray();

        $processed += $leads->count();

        Log::channel('bitrix_resolve')->info('Processing batch', [
            'batch_size' => $leads->count(),
            'first_id' => $ids[0] ?? null,
            'last_id' => $ids[array_key_last($ids)] ?? null,
            'processed_so_far' => $processed,
            'updated_comments' => $commentUpdated,
        ]);

        foreach ($leads as $lead) {

            try {
                $bitrixComment = $importer->getLeadComment($lead->bitrix24_id);
            } catch (\Throwable $e) {
                Log::channel('bitrix_resolve')->warning('Bitrix error', [
                    'lead_id' => $lead->id,
                    'bitrix24_id' => $lead->bitrix24_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (!empty($bitrixComment) && !$dryRun) {
Log::channel('bitrix_resolve')->warning('Bitrix error', ['$bitrixComment'=>$bitrixComment]);
                $lead->update([
                    'more_information' => $this->cleanRichText($bitrixComment)
                ]);

                $commentUpdated++;
            }
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    });

        /**
         * =========================
         * 4️⃣ FINISH LOG
         * =========================
         */
        Log::channel('bitrix_resolve')->info('FINISHED command', [
            'processed' => $processed,
            'comments_updated' => $commentUpdated,
        ]);

        $this->info("🧹 Comments updated: {$commentUpdated}");

        return self::SUCCESS;
    }

    /**
     * =========================
     * CLEAN COMMENTS
     * =========================
     */
private function cleanRichText($value): ?string
{
    if (empty($value)) {
        return null;
    }

    $original = (string) $value;

    $clean = html_entity_decode($original, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // فك روابط Bitrix
    $clean = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '$2', $clean);
    $clean = preg_replace('/\[url\](.*?)\[\/url\]/i', '$1', $clean);

    // تحويل br
    $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);

    // إزالة HTML
    $clean = strip_tags($clean);

    // تنظيف المسافات
    $clean = trim($clean);

    /**
     * 🚨 منع N/A أو قيم meaningless
     */
    $invalidValues = ['n/a', 'na', '-', '--', 'null', 'undefined'];

    if (
        $clean === '' ||
        $clean === null ||
        in_array(strtolower($clean), $invalidValues, true)
    ) {
        return $original; // 👈 حافظ على الأصل
    }

    return $clean;
}
}