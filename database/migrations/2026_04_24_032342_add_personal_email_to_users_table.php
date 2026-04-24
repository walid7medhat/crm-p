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
        Schema::table('users', function (Blueprint $table) {
            //
              $table->enum('gender', ['male', 'female', 'other'])
                  ->nullable()
                  ->after('name');
            
            $table->string('company_mobile', 255)
                  ->nullable()
                  ->after('phone');
            
            $table->string('personal_email', 255)
                  ->nullable()
                  ->after('email');
            
            $table->date('birth_date')
                  ->nullable()
                  ->after('personal_email');
            
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])
                  ->nullable()
                  ->after('birth_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
              

        });
    }
};
