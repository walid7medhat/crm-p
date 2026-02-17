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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('form_id');           // Meta form ID
            $table->string('form_name');         // Meta form name
            $table->string('meta_account_id');   // Meta ad account / page ID
            $table->text('access_token');       // Stored encrypted in model
            $table->string('meta_app_id')->nullable();
            $table->string('platform')->default('meta'); // meta, etc.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->unique(['user_id', 'form_id', 'meta_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
