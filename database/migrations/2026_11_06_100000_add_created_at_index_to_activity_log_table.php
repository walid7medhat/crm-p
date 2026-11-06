<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'activity_log_created_at_index';

    // System Logs date filters. INPLACE / LOCK=NONE so logging keeps working on a large table.
    public function up(): void
    {
        if (! $this->hasIndex()) {
            DB::statement('ALTER TABLE activity_log ADD INDEX ' . self::INDEX . ' (created_at), ALGORITHM=INPLACE, LOCK=NONE');
        }
    }

    public function down(): void
    {
        if ($this->hasIndex()) {
            DB::statement('ALTER TABLE activity_log DROP INDEX ' . self::INDEX);
        }
    }

    private function hasIndex(): bool
    {
        return DB::select('SHOW INDEX FROM activity_log WHERE Key_name = ?', [self::INDEX]) !== [];
    }
};
