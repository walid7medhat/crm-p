<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('deal_properties', 'mou_documents')) {
                $table->json('mou_documents')->nullable()->after('booking_documents');
            }
            if (!Schema::hasColumn('deal_properties', 'noc_documents')) {
                $table->json('noc_documents')->nullable()->after('mou_documents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            if (Schema::hasColumn('deal_properties', 'noc_documents')) {
                $table->dropColumn('noc_documents');
            }
            if (Schema::hasColumn('deal_properties', 'mou_documents')) {
                $table->dropColumn('mou_documents');
            }
        });
    }
};
