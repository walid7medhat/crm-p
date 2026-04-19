<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_settings', 'simple_mode_enabled')) {
                    $table->boolean('simple_mode_enabled')->default(true);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'simple_rotation_index')) {
                    $table->unsignedInteger('simple_rotation_index')->default(0);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'simple_last_assignment_label')) {
                    $table->string('simple_last_assignment_label', 512)->nullable();
                }
            });
        }

        if (Schema::hasTable('lead_assignment_logs') && Schema::hasColumn('lead_assignment_logs', 'method')) {
            $driver = DB::getDriverName();
            try {
                if ($driver === 'mysql') {
                    DB::statement('ALTER TABLE lead_assignment_logs MODIFY method VARCHAR(48) NOT NULL DEFAULT "auto"');
                } elseif ($driver === 'pgsql') {
                    DB::statement('ALTER TABLE lead_assignment_logs ALTER COLUMN method TYPE VARCHAR(48)');
                }
            } catch (\Throwable) {
                // ignore if not supported
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                foreach (['simple_last_assignment_label', 'simple_rotation_index', 'simple_mode_enabled'] as $col) {
                    if (Schema::hasColumn('lead_assignment_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
