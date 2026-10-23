<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_action_tour_campaigns', function (Blueprint $table) {
            $table->string('feature')->default('next_action')->after('launch_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('next_action_tour_campaigns', function (Blueprint $table) {
            $table->dropIndex(['feature']);
            $table->dropColumn('feature');
        });
    }
};
