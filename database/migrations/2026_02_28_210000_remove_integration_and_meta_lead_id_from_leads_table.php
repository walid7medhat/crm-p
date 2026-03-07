<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Removes integration columns added for Meta lead sync.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'integration_id')) {
            return;
        }
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['integration_id', 'meta_lead_id']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['integration_id', 'meta_lead_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('integration_id')->nullable()->after('id')->constrained('integrations')->nullOnDelete();
            $table->string('meta_lead_id')->nullable()->after('integration_id');
            $table->index(['integration_id', 'meta_lead_id']);
        });
    }
};
