<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('deal_properties', 'title_deed_documents')) {
                $table->json('title_deed_documents')->nullable()->after('noc_documents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            if (Schema::hasColumn('deal_properties', 'title_deed_documents')) {
                $table->dropColumn('title_deed_documents');
            }
        });
    }
};
