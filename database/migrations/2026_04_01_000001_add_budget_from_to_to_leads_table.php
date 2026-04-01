<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'budget_from')) {
                $table->decimal('budget_from', 15, 2)->nullable()->after('budget');
            }
            if (!Schema::hasColumn('leads', 'budget_to')) {
                $table->decimal('budget_to', 15, 2)->nullable()->after('budget_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'budget_to')) {
                $table->dropColumn('budget_to');
            }
            if (Schema::hasColumn('leads', 'budget_from')) {
                $table->dropColumn('budget_from');
            }
        });
    }
};
