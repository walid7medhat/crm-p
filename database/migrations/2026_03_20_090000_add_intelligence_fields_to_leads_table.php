<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('budget');
            $table->string('priority', 20)->nullable()->after('score');
            $table->string('intent', 20)->nullable()->after('priority');
            $table->string('next_action', 255)->nullable()->after('intent');
            $table->timestamp('last_scored_at')->nullable()->after('next_action');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'score',
                'priority',
                'intent',
                'next_action',
                'last_scored_at',
            ]);
        });
    }
};
