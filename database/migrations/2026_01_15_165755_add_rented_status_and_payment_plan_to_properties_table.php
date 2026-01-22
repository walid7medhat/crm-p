<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('rented_status', ['Available', 'Rented'])->nullable()->after('listing_status');
            
            $table->date('rented_until')->nullable()->after('rented_status');
            
            $table->string('payment_plan')->nullable()->after('rented_until');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['rented_status', 'rented_until', 'payment_plan']);
        });
    }
};