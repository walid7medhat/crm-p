<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('abu_dhabi_market_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->enum('benchmark_type', ['city', 'area', 'property_type']);
            $table->string('benchmark_key', 100);
            $table->decimal('avg_roi_percent', 6, 2);
            $table->decimal('avg_vacancy_percent', 6, 2);
            $table->decimal('avg_risk_score', 6, 2)->default(50);
            $table->timestamps();
            $table->unique(['benchmark_type', 'benchmark_key'], 'abudhabi_benchmark_unique');
        });

        DB::table('abu_dhabi_market_benchmarks')->insert([
            ['benchmark_type' => 'city', 'benchmark_key' => 'Abu Dhabi', 'avg_roi_percent' => 12.5, 'avg_vacancy_percent' => 5.0, 'avg_risk_score' => 45, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'area', 'benchmark_key' => 'Saadiyat Island', 'avg_roi_percent' => 11.2, 'avg_vacancy_percent' => 4.5, 'avg_risk_score' => 42, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'area', 'benchmark_key' => 'Al Reem Island', 'avg_roi_percent' => 13.4, 'avg_vacancy_percent' => 5.0, 'avg_risk_score' => 44, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'area', 'benchmark_key' => 'Yas Island', 'avg_roi_percent' => 12.8, 'avg_vacancy_percent' => 5.1, 'avg_risk_score' => 46, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'area', 'benchmark_key' => 'Khalifa City', 'avg_roi_percent' => 13.9, 'avg_vacancy_percent' => 5.4, 'avg_risk_score' => 48, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'area', 'benchmark_key' => 'Al Raha', 'avg_roi_percent' => 12.1, 'avg_vacancy_percent' => 4.8, 'avg_risk_score' => 43, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'property_type', 'benchmark_key' => 'Apartment', 'avg_roi_percent' => 13.1, 'avg_vacancy_percent' => 5.1, 'avg_risk_score' => 46, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'property_type', 'benchmark_key' => 'Villa', 'avg_roi_percent' => 10.9, 'avg_vacancy_percent' => 4.7, 'avg_risk_score' => 41, 'created_at' => now(), 'updated_at' => now()],
            ['benchmark_type' => 'property_type', 'benchmark_key' => 'Townhouse', 'avg_roi_percent' => 11.8, 'avg_vacancy_percent' => 4.9, 'avg_risk_score' => 43, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('abu_dhabi_market_benchmarks');
    }
};
