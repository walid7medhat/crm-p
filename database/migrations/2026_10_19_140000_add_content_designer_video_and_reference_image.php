<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content cards can name a designer and/or a video handoff. Those choices
 * place a linked card on the Design or Production board (source_task_id).
 * Reference can also be an uploaded image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_tasks', function (Blueprint $table) {
            $table->foreignId('designed_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->boolean('also_video')->default(false)->after('designed_by');
            $table->string('reference_image')->nullable()->after('reference');
            $table->foreignId('source_task_id')->nullable()->after('created_by')->constrained('marketing_tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designed_by');
            $table->dropColumn('also_video');
            $table->dropColumn('reference_image');
            $table->dropConstrainedForeignId('source_task_id');
        });
    }
};
