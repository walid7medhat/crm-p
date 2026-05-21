<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (! Schema::hasColumn('listings', 'handover_date')) {
                $table->date('handover_date')->nullable();
            }
            if (! Schema::hasColumn('listings', 'noc_percentage')) {
                $table->unsignedTinyInteger('noc_percentage')->nullable()->after('handover_date');
            }
            if (! Schema::hasColumn('listings', 'payment_breakdown')) {
                $table->json('payment_breakdown')->nullable()->after('noc_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'payment_breakdown')) {
                $table->dropColumn('payment_breakdown');
            }
            if (Schema::hasColumn('listings', 'noc_percentage')) {
                $table->dropColumn('noc_percentage');
            }
            if (Schema::hasColumn('listings', 'handover_date')) {
                $table->dropColumn('handover_date');
            }
        });
    }
};
