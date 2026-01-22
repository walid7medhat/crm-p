<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_access_requests', function (Blueprint $table) {
            $table->text('review')->nullable()->after('conversion_notes');
            $table->timestamp('reviewed_at')->nullable()->after('review');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                  ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('listing_access_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['review', 'reviewed_at', 'reviewed_by']);
        });
    }
};