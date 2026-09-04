<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM('emirates_id', 'labor_card', 'passport', 'visa', 'attested_certificate', 'degree_certificate', 'experience_letter', 'employee_upload', 'other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY document_type ENUM('emirates_id', 'labor_card', 'passport', 'visa', 'attested_certificate', 'degree_certificate', 'experience_letter', 'other') NOT NULL");
    }
};
