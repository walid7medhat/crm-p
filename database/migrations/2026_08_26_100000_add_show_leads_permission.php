<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'show-leads', 'guard_name' => 'api']);
    }

    public function down(): void
    {
        Permission::where('name', 'show-leads')->delete();
    }
};
