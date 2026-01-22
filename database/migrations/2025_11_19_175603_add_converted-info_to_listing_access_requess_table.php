<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('listing_access_requests', function (Blueprint $table) {
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('conversion_notes')->nullable();
        });
    }

    public function down()
    {
        Schema::table('listing_access_requests', function (Blueprint $table) {
            $table->dropColumn(['converted_at', 'converted_by', 'conversion_notes']);
        });
    }
};