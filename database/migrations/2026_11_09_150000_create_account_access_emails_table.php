<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** History of "your account + set/reset password" emails (Settings → Account Emails). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_access_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();      // account the email is about
            $table->unsignedBigInteger('sent_by')->nullable();   // admin who sent it (null = console)
            $table->string('sent_to');                           // address it was delivered to
            $table->boolean('is_test')->default(false);
            $table->string('status', 16);                        // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_access_emails');
    }
};
