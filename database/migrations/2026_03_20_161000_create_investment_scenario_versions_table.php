<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('investment_scenario_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scenario_key', 100);
            $table->string('scenario_name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->json('input_payload');
            $table->json('calculation_payload');
            $table->timestamps();

            $table->index(['investment_id', 'scenario_key']);
            $table->index(['investment_id', 'is_current']);
            $table->unique(['investment_id', 'scenario_key', 'version'], 'inv_scenario_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_scenario_versions');
    }
};
