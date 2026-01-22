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
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
                $table->foreignId('added_by')->nullable()->constrained('users')->onDelete('cascade');
            
            // Personal Information
            $table->string('salutation')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone_number')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('second_phone_number')->nullable();
            
            // Location Information - Simplified
            $table->string('nationality')->nullable();
            $table->enum('residency_status', ['resident', 'non_resident'])->default('resident');
            $table->foreignId('location_id')->nullable()->constrained('areas')->onDelete('set null');
            
            // Document Paths
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();
            $table->string('visa_copy_path')->nullable();
            $table->string('passport_copy_path')->nullable();
            
            // Additional Information
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
