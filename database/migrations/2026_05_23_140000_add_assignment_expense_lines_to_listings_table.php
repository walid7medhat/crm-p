<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (! Schema::hasColumn('listings', 'assignment_expense_lines')) {
                $after = Schema::hasColumn('listings', 'payment_breakdown') ? 'payment_breakdown' : 'noc_percentage';
                $table->json('assignment_expense_lines')->nullable()->after($after);
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'assignment_expense_lines')) {
                $table->dropColumn('assignment_expense_lines');
            }
        });
    }
};
