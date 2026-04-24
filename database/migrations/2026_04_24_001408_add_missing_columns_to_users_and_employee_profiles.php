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
     

        // ========== 2. إضافة أعمدة إلى جدول employee_profiles ==========
        Schema::table('employee_profiles', function (Blueprint $table) {
            // إضافة رقم جواز السفر بعد emirates_id_number
            $table->string('passport_number', 255)
                  ->nullable()
                  ->after('emirates_id_number');
            
            $table->date('passport_expiry_date')
                  ->nullable()
                  ->after('passport_number');
            
            $table->date('iloe_expiry_date')
                  ->nullable()
                  ->after('passport_expiry_date');
            
            $table->string('labor_card_number', 255)
                  ->nullable()
                  ->after('iloe_expiry_date');
            
            $table->date('labor_card_expiry_date')
                  ->nullable()
                  ->after('labor_card_number');
            
            $table->unsignedBigInteger('company_branch_id')
                  ->nullable()
                  ->after('department_id');
            
            $table->string('landline_phone', 255)
                  ->nullable(); 
        });

        // لو عندك جدول company_branches
        Schema::table('employee_profiles', function (Blueprint $table) {
            if (Schema::hasTable('company_branches')) {
                $table->foreign('company_branch_id')
                      ->references('id')
                      ->on('company_branches')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {


        // // حذف الأعمدة من جدول employee_profiles
        // Schema::table('employee_profiles', function (Blueprint $table) {
        //     // حذف الـ foreign key أولاً
        //     if (Schema::hasTable('company_branches')) {
        //         $table->dropForeign(['company_branch_id']);
        //     }
            
        //     $table->dropColumn([
        //         'passport_number',
        //         'passport_expiry_date',
        //         'iloe_expiry_date',
        //         'labor_card_number',
        //         'labor_card_expiry_date',
        //         'company_branch_id',
        //         'landline_phone'
        //     ]);
        // });
    }
};