<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_expiry_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('passport_days')->default(15);
            $table->unsignedInteger('labor_card_days')->default(15);
            $table->unsignedInteger('emirates_id_days')->default(15);
            $table->unsignedInteger('residency_days')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_expiry_settings');
    }
};
