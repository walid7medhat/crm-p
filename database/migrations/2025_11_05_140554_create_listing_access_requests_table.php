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
        Schema::create('listing_access_requests', function (Blueprint $table) {
            $table->id();
            // Foreign keys
            $table->foreignId('listing_id')->constrained('listings')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('owners')->onDelete('cascade');
            
            // Request details
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected','cancelled','converted'])->default('pending');
              $table->enum('request_type', ['owner_data', 'unit_number'])->default('owner_data');
            $table->string('requested_field')->nullable();
            $table->text('owner_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            
            // Timestamps
            $table->timestamps();

         
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_access_requests');
    }
};
