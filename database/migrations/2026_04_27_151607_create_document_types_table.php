<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });

        // Insert default data
        DB::table('document_types')->insert([
            ['name' => 'Salary Certificate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Commitment Certificate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Experience Certificate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'No Objection Certificate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employment Certificate', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Completion Of Probationary Period', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};