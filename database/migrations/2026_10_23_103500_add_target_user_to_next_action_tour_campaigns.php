<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_action_tour_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('target_user_id')->nullable()->after('feature')->index();
        });
    }

    public function down(): void
    {
        Schema::table('next_action_tour_campaigns', function (Blueprint $table) {
            $table->dropIndex(['target_user_id']);
            $table->dropColumn('target_user_id');
        });
    }
};
