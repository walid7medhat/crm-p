<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leave_balances MODIFY total_days DECIMAL(6,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE leave_balances MODIFY used_days DECIMAL(6,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE leave_balances MODIFY remaining_days DECIMAL(6,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leave_balances MODIFY total_days INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE leave_balances MODIFY used_days INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE leave_balances MODIFY remaining_days INT NOT NULL DEFAULT 0');
    }
};
