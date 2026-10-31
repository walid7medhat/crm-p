<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that help SmartSearch / LeadTextSearch identity lookups.
 * Leading-wildcard LIKE still cannot use these; equality, prefix, and
 * responsible_person_id scoped scans benefit.
 */
return new class extends Migration
{
    private const INDEXES = [
        'leads' => [
            'leads_lead_name_index' => ['lead_name'],
            'leads_first_name_index' => ['first_name'],
            'leads_last_name_index' => ['last_name'],
        ],
        'users' => [
            'users_name_index' => ['name'],
            'users_display_name_index' => ['display_name'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $name => $columns) {
                    if ($this->indexExists($table, $name)) {
                        continue;
                    }
                    foreach ($columns as $column) {
                        if (! Schema::hasColumn($table, $column)) {
                            continue 2;
                        }
                    }
                    $blueprint->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach (array_keys($indexes) as $name) {
                    if ($this->indexExists($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
};
