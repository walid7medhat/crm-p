<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales Action Center looks up pending reminders by lead in one grouped
     * condition (overdue / due today). The foreign key on lead_id alone still
     * scans every activity row for that lead.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lead_activities')) {
            return;
        }

        $existing = collect(Schema::getIndexes('lead_activities'))->pluck('name');
        if ($existing->contains('lead_activities_follow_up_index')) {
            return;
        }

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->index(
                ['lead_id', 'is_completed', 'reminder_date'],
                'lead_activities_follow_up_index'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_activities')) {
            return;
        }

        Schema::table('lead_activities', function (Blueprint $table) {
            $table->dropIndex('lead_activities_follow_up_index');
        });
    }
};
