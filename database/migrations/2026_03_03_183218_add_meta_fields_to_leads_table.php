<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
             $table->unsignedBigInteger('project_id')->nullable()->after('converted_at');
             $table->json('field_mappings_data')->nullable()->after('project_id');

            $table->json('raw_meta_data')->nullable()->after('field_mappings_data');
    
            $table->unsignedBigInteger('integration_id')->nullable()->after('raw_meta_data');
    
            $table->string('meta_lead_id')->nullable()->unique()->after('integration_id');
    
            $table->foreign('integration_id')
                  ->references('id')
                  ->on('integrations')
                  ->nullOnDelete();
              $table->foreign('project_id')
              ->references('id')
              ->on('projects')
              ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            //
        });
    }
};
