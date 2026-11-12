<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete marketing cards into a per-board Archive stage.
 * Archive columns are visible only to admin / super_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_stages', function (Blueprint $table) {
            $table->boolean('is_archive')->default(false)->after('is_done');
        });

        $now = now();

        foreach (['production', 'design', 'content'] as $category) {
            $existing = DB::table('marketing_stages')
                ->where('category', $category)
                ->where('is_archive', true)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                continue;
            }

            $sortOrder = (int) DB::table('marketing_stages')
                ->where('category', $category)
                ->whereNull('deleted_at')
                ->max('sort_order') + 1;

            DB::table('marketing_stages')->insert([
                'category' => $category,
                'name' => 'Archive',
                'color' => '#64748b',
                'icon' => 'lucide:archive',
                'sort_order' => $sortOrder,
                'is_done' => true,
                'is_archive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $archives = DB::table('marketing_stages')
            ->where('is_archive', true)
            ->whereNull('deleted_at')
            ->get();

        foreach ($archives as $stage) {
            $taskCount = DB::table('marketing_tasks')
                ->where('stage_id', $stage->id)
                ->whereNull('deleted_at')
                ->count();

            if ($taskCount > 0) {
                continue;
            }

            DB::table('marketing_stages')->where('id', $stage->id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('marketing_stages', function (Blueprint $table) {
            $table->dropColumn('is_archive');
        });
    }
};
