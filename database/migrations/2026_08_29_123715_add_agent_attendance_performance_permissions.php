<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view-agent-attendance', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'view-agent-performance', 'guard_name' => 'api']);
    }

    public function down(): void
    {
        Permission::whereIn('name', ['view-agent-attendance', 'view-agent-performance'])->delete();
    }
};
