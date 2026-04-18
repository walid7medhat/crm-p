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
        Schema::create('employee_documents', function (Blueprint $table) {
      
                $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->onDelete('cascade');
            
            // Document Type
            $table->enum('document_type', [
                'emirates_id',
                'labor_card', 
                'passport',
                'visa',
                'attested_certificate',
                'degree_certificate',
                'experience_letter',
                'other'
            ]);
            
            // Document Details
            $table->string('document_name')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->integer('file_size')->nullable(); // size in KB
            $table->string('mime_type')->nullable();
            
            // Ordering
            $table->integer('sort_order')->default(0);
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('document_type');
            $table->index('employee_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
