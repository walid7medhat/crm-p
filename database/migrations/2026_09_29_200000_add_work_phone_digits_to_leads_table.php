<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * work_phone with the usual formatting stripped (+, spaces, -, (, ), ., /), so
 * "+77710146647", "777 1014 6647" and "77710146647" all compare equal when finding
 * duplicate leads. Stored + indexed generated column: the DB keeps it in sync on every
 * insert/update (Eloquent, Bitrix bulk inserts, raw queries) with no app code involved.
 */
return new class extends Migration
{
    private const EXPRESSION = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(work_phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), '/', '')";

    public function up(): void
    {
        if (Schema::hasColumn('leads', 'work_phone_digits')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->string('work_phone_digits', 64)->nullable()->storedAs(self::EXPRESSION)->after('work_phone');
            $table->index('work_phone_digits', 'leads_work_phone_digits_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'work_phone_digits')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_work_phone_digits_index');
            $table->dropColumn('work_phone_digits');
        });
    }
};
