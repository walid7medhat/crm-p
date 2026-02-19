<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            //
             if (!Schema::hasColumn('stages', 'stage_type')) {
                $table->enum('stage_type', ['lead', 'deal'])->default('lead')->after('name');
            }
            
            if (!Schema::hasColumn('stages', 'deal_type')) {
                $table->enum('deal_type', ['primary', 'secondary', 'rental'])->nullable()->after('stage_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            //
              $table->dropColumn(['stage_type', 'deal_type']);
        });
    }
};
