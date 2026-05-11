<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            $table->json('eoi_documents')->nullable()->after('spa_document');
            $table->json('booking_documents')->nullable()->after('eoi_documents');
        });
    }

    public function down(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            $table->dropColumn(['eoi_documents', 'booking_documents']);
        });
    }
};
