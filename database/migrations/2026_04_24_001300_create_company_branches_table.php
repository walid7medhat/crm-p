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
         if (!Schema::hasTable('company_branches')) {
        Schema::create('company_branches', function (Blueprint $table) {
            $table->id();
             $table->string('name', 255);
            $table->string('code', 100)->nullable()->unique();
            $table->string('address', 500)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
         }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_branches');
    }
};
