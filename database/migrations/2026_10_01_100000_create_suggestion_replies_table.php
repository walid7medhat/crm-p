<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Official responses on a suggestion. Multiple rows are allowed so a
     * suggestion can carry a chronological thread. user_id is the replying
     * admin, kept for audit; the employee UI shows "OIA Properties".
     */
    public function up(): void
    {
        Schema::create('suggestion_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained('suggestions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['suggestion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_replies');
    }
};
