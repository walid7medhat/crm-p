<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proposal only. This file is outside database/migrations so artisan migrate
 * cannot run it. Do not move it until the production checklist is signed off.
 *
 * It does not drop any index. It does not add a functional index or a
 * generated column. Those need a confirmed production engine:
 *   MySQL 8.0.13+ can keep the existing functional sort index. Its expression
 *   is COALESCE of bitrix24_last_activity_at and created_at. The current board
 *   orders by bitrix24_last_activity_at, id, which does not match that expression.
 *   MariaDB rejects that parenthesized expression. A stored generated column
 *   is not added here because the current SQL would not read it.
 *
 * The only additive index is the covering form of the query StageController
 * runs today. It is skipped when that index, or a same-named one, already exists.
 */
return new class extends Migration
{
    private const INDEX = 'leads_stage_activity_live_idx';

    public function up(): void
    {
        if (! filter_var(env('KANBAN_INDEX_PROPOSAL_APPROVED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'Refusing to change schema. Approve the Kanban index checklist, then set KANBAN_INDEX_PROPOSAL_APPROVED=true.'
            );
        }

        if ($this->hasIndex(self::INDEX)) {
            return;
        }

        $this->assertOnlineDdlSupported();

        DB::statement(
            'ALTER TABLE leads ADD INDEX '.self::INDEX
            .' (stage_id, deleted_at, bitrix24_last_activity_at, id), ALGORITHM=INPLACE, LOCK=NONE'
        );
    }

    public function down(): void
    {
        if (! filter_var(env('KANBAN_INDEX_PROPOSAL_APPROVED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'Refusing to change schema. Set KANBAN_INDEX_PROPOSAL_APPROVED=true to roll this proposal back.'
            );
        }

        if ($this->hasIndex(self::INDEX)) {
            DB::statement(
                'ALTER TABLE leads DROP INDEX '.self::INDEX.', ALGORITHM=INPLACE, LOCK=NONE'
            );
        }
    }

    private function hasIndex(string $name): bool
    {
        $rows = DB::select('SHOW INDEX FROM leads WHERE Key_name = ?', [$name]);

        return $rows !== [];
    }

    private function assertOnlineDdlSupported(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS version, @@version_comment AS comment');
        $comment = strtolower((string) ($version->comment ?? ''));
        $number = $this->serverVersion((string) $version->version);

        $mysql8 = ! str_contains($comment, 'mariadb') && version_compare($number, '8.0.13', '>=');
        $mariaOnline = str_contains($comment, 'mariadb') && version_compare($number, '10.6.0', '>=');

        if (! $mysql8 && ! $mariaOnline) {
            throw new RuntimeException(
                'Online ADD INDEX is not confirmed for '.($version->comment ?? '').' '.($version->version ?? '')
                .'. Do not apply this proposal until VERSION() is checked.'
            );
        }
    }

    private function serverVersion(string $version): string
    {
        if (preg_match('/\d+\.\d+\.\d+/', $version, $match) === 1) {
            return $match[0];
        }

        return '0.0.0';
    }
};
