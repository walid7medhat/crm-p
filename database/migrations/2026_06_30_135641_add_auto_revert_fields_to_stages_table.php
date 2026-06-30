<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->boolean('auto_revert')->default(false);
            $table->integer('revert_after_hours')->nullable();
            $table->integer('notify_before_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn([
                'auto_revert',
                'revert_after_hours',
                'notify_before_minutes'
            ]);
        });
    }
};
