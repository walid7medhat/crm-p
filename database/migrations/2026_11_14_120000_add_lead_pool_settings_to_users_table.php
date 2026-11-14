<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-agent Lead Pool control (LeadPoolSettingsController):
 *  - lead_pool_enabled       may the agent see / take from the Lead Pool
 *  - lead_pool_daily_limit   "Assign To Me" leads per day   (null → 40)
 *  - lead_pool_hourly_limit  "Assign To Me" leads per hour  (null → 10)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'lead_pool_enabled')) {
                $table->boolean('lead_pool_enabled')->default(true);
            }
            if (! Schema::hasColumn('users', 'lead_pool_daily_limit')) {
                $table->unsignedSmallInteger('lead_pool_daily_limit')->nullable();
            }
            if (! Schema::hasColumn('users', 'lead_pool_hourly_limit')) {
                $table->unsignedSmallInteger('lead_pool_hourly_limit')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['lead_pool_enabled', 'lead_pool_daily_limit', 'lead_pool_hourly_limit'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
