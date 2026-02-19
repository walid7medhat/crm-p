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
        Schema::create('deal_parties', function (Blueprint $table) {
               $table->id();
                $table->foreignId('deal_id')->constrained()->onDelete('cascade');
                
                // Party Type
                $table->enum('party_type', ['buyer', 'seller', 'tenant', 'landlord', 'client']);
                $table->enum('party_role', ['primary', 'secondary'])->default('primary');
                
                // Personal Details
                $table->string('first_name');
                $table->string('last_name');
                $table->date('date_of_birth')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('nationality')->nullable();
                $table->string('residency_status')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('language')->nullable();
                
                // Financial 
                $table->decimal('amount', 15, 2)->nullable();
                
                $table->timestamps();
                
                $table->index(['deal_id', 'party_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_parties');
    }
};
