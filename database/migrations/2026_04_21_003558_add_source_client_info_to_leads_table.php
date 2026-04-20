<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_client_name')->nullable()->after('lead_source');
            $table->string('source_client_phone')->nullable()->after('source_client_name');
            $table->string('source_client_email')->nullable()->after('source_client_phone');
            $table->string('source_relation')->nullable()->after('source_client_email');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'source_client_name',
                'source_client_phone',
                'source_client_email',
                'source_relation'
            ]);
        });
    }
};