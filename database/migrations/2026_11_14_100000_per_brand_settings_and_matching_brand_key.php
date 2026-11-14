<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-only changes for per-brand settings + brand-scoped matching.
 *
 * 1. Settings tables keyed by a natural key (kanban key, city, deal cost key)
 *    were UNIQUE across the whole table, so Luxvant could never store its own
 *    row. Uniqueness becomes (natural key, brand_key).
 * 2. lead_listing_matches + search_alerts get a nullable brand_key column.
 *
 * Data backfill is done separately by `php artisan brand:backfill-legacy`
 * (dry-run first) — never blind-assigned in a migration.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: list<string>}> table => [natural column, old unique index names] */
    private array $keyed = [
        'kanban_settings' => ['key', ['kanban_settings_key_unique']],
        'city_investment_settings' => ['city', ['city_investment_settings_city_unique']],
        'deal_cost_settings' => ['key', ['deal_cost_settings_key_unique']],
    ];

    /** @var list<string> */
    private array $newBrandColumns = ['lead_listing_matches', 'search_alerts'];

    public function up(): void
    {
        foreach ($this->keyed as $table => [$column, $oldIndexes]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            $newIndex = $table.'_'.$column.'_brand_key_unique';

            foreach ($oldIndexes as $old) {
                if ($this->indexExists($table, $old)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropUnique($old));
                }
            }

            if (! $this->indexExists($table, $newIndex)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique([$column, 'brand_key'], $newIndex));
            }
        }

        foreach ($this->newBrandColumns as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('brand_key', 32)->nullable()->after('id');
                $t->index('brand_key', $table.'_brand_key_index');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->newBrandColumns as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_brand_key_index');
                $t->dropColumn('brand_key');
            });
        }

        foreach ($this->keyed as $table => [$column, $oldIndexes]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $newIndex = $table.'_'.$column.'_brand_key_unique';
            if ($this->indexExists($table, $newIndex)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($newIndex));
            }

            // Restoring the global unique only works once per-brand duplicates are removed.
            $old = $oldIndexes[0];
            if (! $this->indexExists($table, $old)) {
                Schema::table($table, fn (Blueprint $t) => $t->unique($column, $old));
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            try {
                return collect(Schema::getIndexes($table))->contains(fn ($i) => ($i['name'] ?? '') === $index);
            } catch (\Throwable) {
                return false;
            }
        }

        return DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]) !== [];
    }
};
