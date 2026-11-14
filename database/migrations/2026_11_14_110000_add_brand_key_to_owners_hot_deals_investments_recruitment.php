<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-owned (non shared) data: owners, hot deals, investments, recruitment.
 * Additive nullable column only — classify rows with `php artisan brand:backfill-legacy`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'owners',
        'owner_additional_documents',
        'hot_deal_requests',
        'investments',
        'investment_overrides',
        'investment_scenario_versions',
        'job_openings',
        'applicants',
        'interviews',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
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
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_brand_key_index');
                $t->dropColumn('brand_key');
            });
        }
    }
};
