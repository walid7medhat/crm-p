<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the device-reported accuracy and capture time beside the coordinates
     * so a precise fix can be told apart from an approximate or older one.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_login_accuracy')) {
            Schema::table('users', function (Blueprint $table) {
                $column = $table->unsignedInteger('last_login_accuracy')->nullable();
                if (Schema::hasColumn('users', 'last_login_lng')) {
                    $column->after('last_login_lng');
                }
            });
        }

        if (! Schema::hasColumn('users', 'last_login_location_at')) {
            Schema::table('users', function (Blueprint $table) {
                $column = $table->timestamp('last_login_location_at')->nullable();
                if (Schema::hasColumn('users', 'last_login_accuracy')) {
                    $column->after('last_login_accuracy');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['last_login_location_at', 'last_login_accuracy'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
