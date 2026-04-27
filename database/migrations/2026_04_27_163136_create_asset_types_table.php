<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Laptop, Charger, SIM, Mobile, Printer, Desktop, Other Accessories
            $table->timestamps();
        });

        // Insert default data
        DB::table('asset_types')->insert([
            ['name' => 'Laptop', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Charger', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'SIM', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mobile', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Printer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Desktop', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Monitor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Keyboard', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mouse', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other Accessories', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_types');
    }
};