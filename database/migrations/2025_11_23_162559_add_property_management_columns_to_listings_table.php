<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false);
            
            // أعمدة التحويل (Sold Out)
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->foreign('converted_by')->references('id')->on('users');
            
            // أعمدة تعيين الوكيل
            $table->text('assignment_notes')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->foreign('assigned_by')->references('id')->on('users');

            $table->enum('sold_by', ['me', 'oia','other_company'])
              ->nullable();
        });
    }

    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['converted_by']);
            $table->dropForeign(['assigned_by']);
            
            $table->dropColumn([
                'is_archived',
                'is_active',
                'converted_at',
                'converted_by',
                'assignment_notes',
                'assigned_by',
                'assigned_at'
            ]);
        });
    }
};