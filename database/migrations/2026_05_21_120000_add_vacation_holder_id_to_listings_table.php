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
            // Stores the original agent_id while the listing is temporarily delegated
            // because that agent is on vacation. NULL means the listing is not currently delegated.
            $table->unsignedBigInteger('vacation_holder_id')->nullable()->after('agent_id');

            $table->foreign('vacation_holder_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->index('vacation_holder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['vacation_holder_id']);
            $table->dropIndex(['vacation_holder_id']);
            $table->dropColumn('vacation_holder_id');
        });
    }
};
