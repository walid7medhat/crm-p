<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ngram FULLTEXT for CRM lead identity contains-search.
 *
 * Why ngram (not standard FULLTEXT):
 * - Standard InnoDB FULLTEXT cannot match mid-token substrings (e.g. "hass" → "Hassan").
 * - Existing LeadTextSearch semantics use leading-wildcard LIKE '%term%'.
 * - MySQL 8 ngram parser (ngram_token_size=2) preserves 2+ char partial matching
 *   for English and Arabic identity fields without replacing phone/email paths.
 *
 * Why two indexes:
 * - lead_name is utf8mb4_general_ci while the other identity columns are
 *   utf8mb4_unicode_ci. MySQL requires identical collations inside one FULLTEXT index.
 * - Splitting avoids a collation rewrite on lead_name.
 *
 * Hosting note:
 * - MariaDB and MySQL builds without the ngram plugin raise SQLSTATE 1128
 *   ("Function 'ngram' is not defined"). This migration detects the active ngram
 *   parser and skips index creation when unsupported. LeadTextSearch keeps the
 *   LIKE identity fallback when these indexes are absent.
 * - Never create parser-less FULLTEXT under these index names — that would enable
 *   MATCH with incorrect contains semantics.
 *
 * Do not run against production without staging EXPLAIN + correctness checks.
 * Online DDL: MySQL 8 InnoDB FULLTEXT add typically rebuilds the table — schedule
 * a maintenance window on large leads tables.
 */
return new class extends Migration
{
    private const INDEX_LEAD_NAME = 'leads_lead_name_ngram_ft';

    private const INDEX_IDENTITY = 'leads_identity_ngram_ft';

    private const IDENTITY_COLUMNS = [
        'first_name',
        'last_name',
        'email',
        'lead_source',
        'lead_number',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        if (! $this->ngramParserAvailable()) {
            Log::warning(
                'Skipping leads ngram FULLTEXT indexes: MySQL ngram parser is not available/active on this database. '.
                'Lead identity search will continue using the LIKE fallback. '.
                'Indexes not created: '.self::INDEX_LEAD_NAME.', '.self::INDEX_IDENTITY.'.'
            );

            return;
        }

        if (Schema::hasColumn('leads', 'lead_name') && ! $this->indexExists(self::INDEX_LEAD_NAME)) {
            DB::statement(
                'ALTER TABLE leads ADD FULLTEXT INDEX '.self::INDEX_LEAD_NAME.' (lead_name) WITH PARSER ngram'
            );
        }

        foreach (self::IDENTITY_COLUMNS as $column) {
            if (! Schema::hasColumn('leads', $column)) {
                return;
            }
        }

        if (! $this->indexExists(self::INDEX_IDENTITY)) {
            $cols = implode(', ', self::IDENTITY_COLUMNS);
            DB::statement(
                'ALTER TABLE leads ADD FULLTEXT INDEX '.self::INDEX_IDENTITY." ({$cols}) WITH PARSER ngram"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        foreach ([self::INDEX_IDENTITY, self::INDEX_LEAD_NAME] as $index) {
            if ($this->indexExists($index)) {
                DB::statement('ALTER TABLE leads DROP INDEX '.$index);
            }
        }
    }

    /**
     * True only when the MySQL ngram full-text parser plugin is present and ACTIVE.
     * MariaDB and hosts without ngram return false (safe skip).
     */
    public function ngramParserAvailable(): bool
    {
        try {
            $row = DB::selectOne(
                "SELECT PLUGIN_STATUS AS status
                 FROM INFORMATION_SCHEMA.PLUGINS
                 WHERE PLUGIN_NAME = 'ngram'
                 LIMIT 1"
            );

            if ($row === null) {
                return false;
            }

            $status = strtoupper(trim((string) ($row->status ?? '')));

            return $status === 'ACTIVE';
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExists(string $name): bool
    {
        foreach (Schema::getIndexes('leads') as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
