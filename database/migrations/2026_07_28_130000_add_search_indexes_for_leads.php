<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['stage_id', 'updated_at'], 'leads_stage_updated_idx');
            $table->index(['stage_id', 'created_at'], 'leads_stage_created_idx');
            $table->index(['responsible_person_id', 'stage_id', 'updated_at'], 'leads_resp_stage_updated_idx');

            $table->index('lead_source', 'leads_source_idx');
            $table->index('status_lead', 'leads_status_lead_idx');
            $table->index('interaction_result', 'leads_interaction_result_idx');
            $table->index('lead_branch_source', 'leads_branch_source_idx');
            $table->index(['lead_type', 'property_status'], 'leads_type_property_status_idx');
            $table->index(['property_type_id', 'area_id'], 'leads_property_area_idx');
            $table->index(['budget_from', 'budget_to'], 'leads_budget_range_idx');
        });

        Schema::table('lead_histories', function (Blueprint $table) {
            $table->index(['lead_id', 'user_id', 'created_at'], 'lead_histories_lead_user_created_idx');
            $table->index(['user_id', 'created_at'], 'lead_histories_user_created_idx');
        });

        Schema::table('lead_comments', function (Blueprint $table) {
            $table->index(['lead_id', 'created_at'], 'lead_comments_lead_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lead_comments', function (Blueprint $table) {
            $table->dropIndex('lead_comments_lead_created_idx');
        });

        Schema::table('lead_histories', function (Blueprint $table) {
            $table->dropIndex('lead_histories_lead_user_created_idx');
            $table->dropIndex('lead_histories_user_created_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_stage_updated_idx');
            $table->dropIndex('leads_stage_created_idx');
            $table->dropIndex('leads_resp_stage_updated_idx');
            $table->dropIndex('leads_source_idx');
            $table->dropIndex('leads_status_lead_idx');
            $table->dropIndex('leads_interaction_result_idx');
            $table->dropIndex('leads_branch_source_idx');
            $table->dropIndex('leads_type_property_status_idx');
            $table->dropIndex('leads_property_area_idx');
            $table->dropIndex('leads_budget_range_idx');
        });
    }
};

