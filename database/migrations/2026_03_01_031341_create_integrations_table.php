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
      

        // 2. ننشئ الجدول الجديد
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Integration info
            $table->string('name')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            
            // Step 1: CRM Entities
            $table->string('crm_entity')->nullable();
            $table->boolean('expert_mode')->default(false);
            $table->enum('duplicate_handling', ['allow', 'replace', 'merge'])->default('merge');
            
            // Step 2: Hidden Field Values
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lead_source')->nullable();
            
            // Step 3: Facebook Lead Ads
            $table->string('page_id')->nullable();
            $table->string('facebook_form_id')->nullable();
            $table->string('facebook_form_name')->nullable();
            $table->json('field_mappings')->nullable();
            
            // Step 4: Other Settings
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('dont_make_responsible_if_not_clocked_in')->default(true);
            
            // Meta specific (optional)
            $table->string('platform')->default('meta');
            $table->text('access_token')->nullable(); // Encrypted
            $table->string('meta_account_id')->nullable();
            $table->string('meta_app_id')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['facebook_form_id', 'page_id']);
            // $table->unique(['user_id', 'facebook_form_id'], 'user_form_unique');
        });

        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
        
       
    }
};