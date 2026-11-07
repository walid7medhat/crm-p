<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner Financial Intelligence report filters deals by deal date
 * (won_date, else created_at) and lead source. Indexes only — no data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasIndex('deals', 'deals_won_date_idx')) {
                $table->index('won_date', 'deals_won_date_idx');
            }
            if (! Schema::hasIndex('deals', 'deals_created_at_idx')) {
                $table->index('created_at', 'deals_created_at_idx');
            }
            if (! Schema::hasIndex('deals', 'deals_source_idx')) {
                $table->index('source', 'deals_source_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_source_idx');
            $table->dropIndex('deals_created_at_idx');
            $table->dropIndex('deals_won_date_idx');
        });
    }
};
