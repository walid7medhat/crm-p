<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The background each user picked. Null falls back to the default background.
     * If the chosen background is deleted, this is set back to null (default).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('background_id')->nullable()->after('avatar');
            $table->foreign('background_id')->references('id')->on('backgrounds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['background_id']);
            $table->dropColumn('background_id');
        });
    }
};
