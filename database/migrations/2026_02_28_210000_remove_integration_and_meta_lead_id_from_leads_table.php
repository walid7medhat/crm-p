<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Removes integration columns added for Meta lead sync.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'integration_id') && !Schema::hasColumn('leads', 'meta_lead_id')) {
            return;
        }

        // Drop FK only if it exists in this environment.
        if (Schema::hasColumn('leads', 'integration_id')) {
            try {
                Schema::table('leads', function (Blueprint $table) {
                    $table->dropForeign(['integration_id']);
                });
            } catch (\Throwable $e) {
                // Ignore when FK name differs or is already missing.
            }
        }

        // Drop composite index only when present.
        $indexExists = collect(DB::select("SHOW INDEX FROM `leads`"))
            ->pluck('Key_name')
            ->contains('leads_integration_id_meta_lead_id_index');

        if ($indexExists) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex('leads_integration_id_meta_lead_id_index');
            });
        }

        $columnsToDrop = [];
        if (Schema::hasColumn('leads', 'integration_id')) {
            $columnsToDrop[] = 'integration_id';
        }
        if (Schema::hasColumn('leads', 'meta_lead_id')) {
            $columnsToDrop[] = 'meta_lead_id';
        }

        if (!empty($columnsToDrop)) {
            Schema::table('leads', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
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
