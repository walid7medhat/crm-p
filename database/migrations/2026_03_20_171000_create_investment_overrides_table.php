<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('investment_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 120);
            $table->decimal('overridden_value', 15, 4);
            $table->timestamps();

            $table->unique(['investment_id', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_overrides');
    }
};
