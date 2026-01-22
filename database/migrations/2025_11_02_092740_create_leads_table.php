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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
            // $table->foreignId('sales_id')->nullable()->constrained('users')->onDelete('cascade');

             $table->string('lead_name');
            $table->string('lead_number')->unique();
            $table->foreignId('stage_id')->constrained()->onDelete('cascade');
            
            // Contact Information
            $table->string('salutation')->nullable();
            $table->string('first_name');
            $table->string('second_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            
            // Contact Details
            $table->string('whatsapp_number')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('work_phone_2')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('messenger')->nullable();
            $table->string('facebook')->nullable();
            
            // Company Information
            $table->string('company_name')->nullable();
            $table->string('position')->nullable();
            
            // Property Information
            $table->string('interested_in')->nullable();
            $table->integer('bedrooms')->nullable();
            $table->string('purpose_buying')->nullable();
            $table->string('nationality')->nullable();
            $table->string('citizenship_program')->nullable();
            
            // Lead Source
            $table->string('lead_source')->nullable();
            $table->string('source_information')->nullable();
            $table->string('lead_branch_source')->nullable();
            $table->string('ad_id')->nullable();
            $table->boolean('available_to_everyone')->default(false);
            
            // Status
            $table->string('status_lead')->nullable();
            $table->string('status_unit')->nullable();
            $table->string('status_project')->nullable();
            $table->string('lists')->nullable();
            $table->string('unqualified_reason')->nullable();
            $table->string('why_lost_lead')->nullable();
            
            // Additional
            $table->text('address')->nullable();
            $table->text('comment')->nullable();
            $table->text('additional_services')->nullable();
            
            $table->foreignId('responsible_person_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
