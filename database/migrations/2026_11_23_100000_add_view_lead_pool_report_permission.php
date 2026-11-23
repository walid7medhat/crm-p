<?php

use App\Http\Controllers\Api\ReportController;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `view-lead-pool-report`: open the Lead Pool Assignments report for your own branch.
 * Not granted to anyone here — give it from the user's permissions screen.
 * super_admin and user #30 keep seeing every branch without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate(ReportController::LEAD_POOL_REPORT_PERMISSION, config('auth.defaults.guard', 'api'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', ReportController::LEAD_POOL_REPORT_PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
