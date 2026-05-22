<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'bitrix24_data')) {
                // Full Bitrix24 lead payload (every field returned by crm.lead.get
                // — including standard fields, UF_* customs, multi-fields, etc.)
                // — stored as a JSON column so downstream code can introspect
                // anything that wasn't promoted to a dedicated column.
                $table->json('bitrix24_data')->nullable()->after('bitrix24_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'bitrix24_data')) {
                $table->dropColumn('bitrix24_data');
            }
        });
    }
};
