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
        Schema::create('employee_profiles', function (Blueprint $table) {
                   $table->id();
              $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Basic Info
            $table->string('employee_code')->unique()->nullable();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->date('contract_end_date')->nullable();
            
            // Emirates ID
            $table->string('emirates_id_number')->nullable()->unique();
            
            // Bank Account Details
            $table->string('bank_account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('branch_location')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('iban_number')->nullable();
            
            // Insurance Details
            $table->string('insurance_policy_type')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->string('insurance_provider')->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            
            // Emissary ID
            $table->string('emissary_id_number')->nullable();
            $table->string('emissary_id_pad')->nullable();
            
            // Notification
            $table->string('notification_provider')->nullable();
            
            // Certificate Name (for the main certificate)
            $table->string('certificate_name')->nullable();
            
            // Employment Status
            $table->enum('employment_status', ['active', 'on_leave', 'terminated', 'suspended'])->default('active');
            
            $table->timestamps();
            
            // Indexes
            $table->index('employee_code');
            $table->index('emirates_id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
