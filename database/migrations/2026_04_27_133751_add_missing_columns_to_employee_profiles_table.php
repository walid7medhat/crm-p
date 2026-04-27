<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // ========== معلومات شخصية (من Profile Details) ==========
            $table->string('father_name')->nullable()->after('certificate_name');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->string('religion', 100)->nullable()->after('mother_name');
            $table->string('emergency_contact_name')->nullable()->after('religion');
            $table->string('emergency_email')->nullable()->after('emergency_contact_name');
            $table->string('emergency_phone')->nullable()->after('emergency_email');
            $table->text('address_inside_uae')->nullable()->after('emergency_phone');
            $table->text('address_outside_uae')->nullable()->after('address_inside_uae');
            $table->string('home_country_phone')->nullable()->after('address_outside_uae');
            
            // ========== معلومات الشركة الإضافية ==========
            $table->string('sponsor')->nullable()->after('company_branch_id');
            $table->string('visa_quota')->nullable()->after('sponsor');
            $table->string('vehicle')->nullable()->after('visa_quota');
            $table->date('probation_end_date')->nullable()->after('joining_date');
            $table->date('visa_validity')->nullable()->after('probation_end_date');
            $table->date('contract_joining_date')->nullable()->after('visa_validity');
            $table->date('gratuity_termination')->nullable()->after('contract_joining_date');
            
           
   
        });
        
        // ========== إضافة أعمدة إلى جدول users ==========
        Schema::table('users', function (Blueprint $table) {
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->enum('salary_type', ['daily', 'monthly', 'yearly'])->nullable()->after('nationality');
            $table->decimal('salary_amount', 12, 2)->nullable()->after('salary_type');
            $table->string('personal_phone')->nullable()->after('phone'); // Phone Number *
            $table->string('home_country_phone_number')->nullable()->after('personal_phone'); // Home Country Phone Number
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            
            // حذف الأعمدة
            $table->dropColumn([
                'father_name', 'mother_name', 'religion', 'emergency_contact_name',
                'emergency_email', 'emergency_phone', 'address_inside_uae',
                'address_outside_uae', 'home_country_phone', 'sponsor', 'visa_quota',
                'vehicle', 'probation_end_date', 'visa_validity', 'contract_joining_date',
                'gratuity_termination'
            ]);
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nationality', 'salary_type', 'salary_amount', 
                'personal_phone', 'home_country_phone_number'
            ]);
        });
    }
};