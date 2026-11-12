<?php

namespace Tests\Unit\Migration;

use App\Models\Lead;
use App\Services\LeadTextSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Compatibility coverage for the optional ngram FULLTEXT migration.
 * Does not create parser-less FULLTEXT indexes and does not touch production.
 */
class LeadsIdentityNgramFulltextMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_12_210000_add_leads_identity_ngram_fulltext_index.php';

    public function test_migration_source_never_creates_parser_less_fulltext(): void
    {
        $source = file_get_contents(base_path(self::MIGRATION));
        $this->assertNotFalse($source);

        $this->assertStringContainsString('WITH PARSER ngram', $source);
        $this->assertStringContainsString('ngramParserAvailable', $source);
        $this->assertStringContainsString('Skipping leads ngram FULLTEXT indexes', $source);
        $this->assertStringContainsString('LIKE fallback', $source);

        preg_match_all('/ADD FULLTEXT INDEX[^;]+/i', $source, $matches);
        $this->assertNotEmpty($matches[0]);
        foreach ($matches[0] as $statement) {
            $this->assertStringContainsString(
                'WITH PARSER ngram',
                $statement,
                'FULLTEXT ADD must always specify WITH PARSER ngram; parser-less indexes are forbidden under these names.'
            );
        }
    }

    public function test_supported_ngram_parser_issues_original_ngram_alter_sql(): void
    {
        $migration = $this->loadMigration();
        $statements = [];

        Schema::shouldReceive('hasTable')->with('leads')->andReturn(true);
        Schema::shouldReceive('hasColumn')->andReturn(true);
        Schema::shouldReceive('getIndexes')->with('leads')->andReturn([]);

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(function ($sql) {
                return is_string($sql)
                    && str_contains($sql, 'INFORMATION_SCHEMA.PLUGINS')
                    && str_contains($sql, "'ngram'");
            })
            ->andReturn((object) ['status' => 'ACTIVE']);

        DB::shouldReceive('statement')
            ->twice()
            ->withArgs(function ($sql) use (&$statements) {
                $statements[] = $sql;

                return is_string($sql)
                    && str_contains($sql, 'ALTER TABLE leads ADD FULLTEXT INDEX')
                    && str_contains($sql, 'WITH PARSER ngram');
            })
            ->andReturn(true);

        Log::shouldReceive('warning')->never();

        $migration->up();

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('leads_lead_name_ngram_ft', $statements[0]);
        $this->assertStringContainsString('(lead_name) WITH PARSER ngram', $statements[0]);
        $this->assertStringContainsString('leads_identity_ngram_ft', $statements[1]);
        $this->assertStringContainsString('WITH PARSER ngram', $statements[1]);

        foreach ($statements as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/ADD FULLTEXT INDEX\s+\w+\s+\([^)]+\)\s*$/i',
                trim($sql),
                'Parser-less FULLTEXT CREATE is not allowed.'
            );
        }
    }

    public function test_unsupported_ngram_parser_skips_alter_and_logs_warning(): void
    {
        $migration = $this->loadMigration();

        Schema::shouldReceive('hasTable')->with('leads')->andReturn(true);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        DB::shouldReceive('statement')->never();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return is_string($message)
                    && str_contains($message, 'Skipping leads ngram FULLTEXT indexes')
                    && str_contains($message, 'LIKE fallback')
                    && str_contains($message, 'leads_lead_name_ngram_ft')
                    && str_contains($message, 'leads_identity_ngram_ft');
            });

        $migration->up();
    }

    public function test_inactive_ngram_plugin_is_treated_as_unsupported(): void
    {
        $migration = $this->loadMigration();

        Schema::shouldReceive('hasTable')->with('leads')->andReturn(true);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['status' => 'DISABLED']);

        DB::shouldReceive('statement')->never();

        Log::shouldReceive('warning')->once()->withArgs(function ($message) {
            return is_string($message) && str_contains($message, 'Skipping leads ngram FULLTEXT indexes');
        });

        $migration->up();
    }

    public function test_search_uses_like_when_ngram_indexes_are_absent(): void
    {
        config([
            'lead_search.use_fulltext' => true,
            'lead_search.fulltext_indexes' => [
                'missing_leads_lead_name_ngram_ft',
                'missing_leads_identity_ngram_ft',
            ],
        ]);
        LeadTextSearch::clearFulltextCache();

        $this->assertFalse(LeadTextSearch::fulltextEnabled());

        $sql = LeadTextSearch::apply(Lead::query(), 'SearchAlice', [
            'admin' => true,
            'lean' => true,
            'relations' => false,
        ])->toSql();

        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertStringNotContainsString('MATCH(', $sql);
    }

    private function loadMigration(): object
    {
        // include (not require_once): anonymous migration files return true on
        // subsequent require_once after the first load in the same process.
        $migration = include base_path(self::MIGRATION);
        $this->assertIsObject($migration);
        $this->assertTrue(method_exists($migration, 'up'));
        $this->assertTrue(method_exists($migration, 'down'));
        $this->assertTrue(method_exists($migration, 'ngramParserAvailable'));

        return $migration;
    }
}
