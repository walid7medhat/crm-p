<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
        use Spatie\Permission\Models\{Permission, Role};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::table('users', function (Blueprint $table) {
            $table->boolean('receive_absent_email')->default(true)->after('biometric_code');
        });

        Permission::findOrCreate('users-absent-email', 'api');
        Role::findByName('hr')->givePermissionTo('users-absent-email');
        Role::findByName('super_admin')->givePermissionTo('users-absent-email');
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
