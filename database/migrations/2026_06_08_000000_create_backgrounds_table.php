<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backgrounds the superadmin uploads. Users pick one of the active ones; the
     * one flagged is_default is used when a user hasn't chosen their own.
     */
    public function up(): void
    {
        Schema::create('backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('path');                       // storage path on the public disk
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);  // superadmin can hide without deleting
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backgrounds');
    }
};
