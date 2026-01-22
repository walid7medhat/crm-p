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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
              
            // Basic Information
            $table->string('title');
            $table->string('unit_number');
            $table->integer('size_sqft')->nullable();
            $table->integer('size_sqmt')->nullable();
            $table->integer('number_of_bedrooms')->nullable();
            $table->integer('number_of_bathrooms')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('furnished_status')->nullable();
            
            // Ownership Type & Status
            $table->string('ownership_type')->nullable()->default('free_hold');
            
            // Mortgage & Occupancy Details
            $table->string('mortgage_status')->nullable();
            $table->decimal('mortgage_amount', 15, 2)->nullable();
            $table->text('mortgage_comment')->nullable();
            
            $table->string('occupancy_status')->nullable(); 
            $table->text('occupancy_comment')->nullable();
            
            // Documents
            $table->string('spa_document_path')->nullable();
            $table->string('desk_document_path')->nullable();
            $table->string('other_document_path')->nullable();
            $table->text('comment')->nullable();
            
            // Relations
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('owners')->nullOnDelete();
            $table->foreignId('developer_id')->nullable()->constrained('developers')->nullOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
