<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('city_investment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('city', 80)->unique();
            $table->decimal('purchase_price_min', 15, 2)->nullable();
            $table->decimal('purchase_price_max', 15, 2)->nullable();
            $table->decimal('down_payment_percent', 6, 2);
            $table->decimal('loan_interest_percent', 6, 2);
            $table->unsignedSmallInteger('hold_years');
            $table->decimal('vacancy_rate_percent', 6, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('city_investment_settings')->insert([
            [
                'city' => 'Dubai',
                'purchase_price_min' => 600000,
                'purchase_price_max' => 5000000,
                'down_payment_percent' => 25,
                'loan_interest_percent' => 5.5,
                'hold_years' => 5,
                'vacancy_rate_percent' => 5,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'city' => 'Abu Dhabi',
                'purchase_price_min' => 400000,
                'purchase_price_max' => 3500000,
                'down_payment_percent' => 25,
                'loan_interest_percent' => 5.25,
                'hold_years' => 5,
                'vacancy_rate_percent' => 4.5,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('city_investment_settings');
    }
};
