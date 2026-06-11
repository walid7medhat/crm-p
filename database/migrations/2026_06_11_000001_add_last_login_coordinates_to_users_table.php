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
            if (! Schema::hasColumn('users', 'last_login_lat')) {
                $table->decimal('last_login_lat', 10, 7)->nullable()->after('last_login_location');
            }
            if (! Schema::hasColumn('users', 'last_login_lng')) {
                $table->decimal('last_login_lng', 10, 7)->nullable()->after('last_login_lat');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_login_lat', 'last_login_lng'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
