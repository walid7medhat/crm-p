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
        Schema::create('property_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('listings')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('offer_number')->unique()->nullable();
            $table->json('offer_data')->nullable();
            $table->string('status')->default('generated'); // generated, sent, accepted, rejected

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_offers');
    }
};
