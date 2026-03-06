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
        Schema::create('deal_comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('deal_comments')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // image, document, etc.
            $table->integer('file_size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_comment_attachments');
    }
};
