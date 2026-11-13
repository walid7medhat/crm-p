<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead comment replies: a manager / any parent in the hierarchy (and the comment's
 * author) can reply on a lead comment. One level only — parent_id always points
 * at a top-level comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lead_comments', 'parent_id')) {
            return;
        }

        Schema::table('lead_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('lead_comments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lead_comments', 'parent_id')) {
            return;
        }

        Schema::table('lead_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
