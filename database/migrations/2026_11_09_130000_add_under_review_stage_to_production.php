<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Production board: insert "Under Review" immediately before Done.
 * Flow becomes: Pending → Post Prod → Under Review → Done → Cancelled → Paused
 * Idempotent — safe if the stage was already added by an admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $category = 'production';

        $existing = DB::table('marketing_stages')
            ->where('category', $category)
            ->where('name', 'Under Review')
            ->whereNull('deleted_at')
            ->first();

        if (! $existing) {
            $done = DB::table('marketing_stages')
                ->where('category', $category)
                ->where('name', 'Done')
                ->whereNull('deleted_at')
                ->first();

            $sortOrder = $done
                ? (int) $done->sort_order
                : ((int) DB::table('marketing_stages')->where('category', $category)->whereNull('deleted_at')->max('sort_order') + 1);

            // Make room so Under Review sits where Done was.
            DB::table('marketing_stages')
                ->where('category', $category)
                ->whereNull('deleted_at')
                ->where('sort_order', '>=', $sortOrder)
                ->increment('sort_order');

            DB::table('marketing_stages')->insert([
                'category' => $category,
                'name' => 'Under Review',
                'color' => '#ea580c',
                'icon' => 'lucide:eye',
                'sort_order' => $sortOrder,
                'is_done' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->order($category, ['Pending', 'Post Prod', 'Under Review', 'Done', 'Cancelled', 'Paused']);
    }

    public function down(): void
    {
        $stage = DB::table('marketing_stages')
            ->where('category', 'production')
            ->where('name', 'Under Review')
            ->whereNull('deleted_at')
            ->first();

        if (! $stage) {
            return;
        }

        // Only remove if no cards are sitting on it.
        $taskCount = DB::table('marketing_tasks')
            ->where('stage_id', $stage->id)
            ->whereNull('deleted_at')
            ->count();

        if ($taskCount > 0) {
            return;
        }

        DB::table('marketing_stages')->where('id', $stage->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->order('production', ['Pending', 'Post Prod', 'Done', 'Cancelled', 'Paused']);
    }

    /** Put the named stages first, in this order; any other stages keep following. */
    private function order(string $category, array $names): void
    {
        $stages = DB::table('marketing_stages')
            ->where('category', $category)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $known = $stages->filter(fn ($s) => in_array($s->name, $names, true))
            ->sortBy(fn ($s) => array_search($s->name, $names, true));
        $others = $stages->reject(fn ($s) => in_array($s->name, $names, true));

        $known->concat($others)->values()->each(
            fn ($s, $i) => DB::table('marketing_stages')->where('id', $s->id)->update(['sort_order' => $i])
        );
    }
};
