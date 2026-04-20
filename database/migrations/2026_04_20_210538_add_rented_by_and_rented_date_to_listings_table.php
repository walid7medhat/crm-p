<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->string('rented_by')->nullable()->after('sold_by'); // me, oia, another_agent, other_company
            $table->foreignId('rented_by_agent_id')->nullable()->after('rented_by')->constrained('users')->nullOnDelete();
            $table->date('rented_date')->nullable()->after('rented_by_agent_id');
              $table->timestamp('rented_at')->nullable()->after('rented_date');
            $table->foreignId('rented_owner_id')->nullable()->after('rented_at')->constrained('owners')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->dropForeign(['rented_by_agent_id']);
            $table->dropForeign(['rented_owner_id']);
            $table->dropColumn(['rented_by', 'rented_by_agent_id', 'rented_date','rented_date', 'rented_owner_id']);
        });
    }
};
