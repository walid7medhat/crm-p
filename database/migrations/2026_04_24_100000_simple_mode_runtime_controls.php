<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_assignment_settings', 'simple_mode_batch_size')) {
                $table->unsignedSmallInteger('simple_mode_batch_size')
                    ->default(25)
                    ->after('simple_mode_enabled');
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'simple_mode_auto_interval_seconds')) {
                $table->unsignedSmallInteger('simple_mode_auto_interval_seconds')
                    ->default(10)
                    ->after('simple_mode_batch_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            if (Schema::hasColumn('lead_assignment_settings', 'simple_mode_auto_interval_seconds')) {
                $table->dropColumn('simple_mode_auto_interval_seconds');
            }
            if (Schema::hasColumn('lead_assignment_settings', 'simple_mode_batch_size')) {
                $table->dropColumn('simple_mode_batch_size');
            }
        });
    }
};
