<?php
// database/migrations/2026_01_01_000001_create_deal_cost_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_cost_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->decimal('value', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // إدخال البيانات الأولية
        DB::table('deal_cost_settings')->insert([
            [
                'key' => 'dari_admin_fee',
                'value' => 0,
                'description' => 'Dari Admin Fee for Deal Costs',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'adgm_admin_fee',
                'value' => 0,
                'description' => 'ADGM Admin Fee for Deal Costs',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_cost_settings');
    }
};