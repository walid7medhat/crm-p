<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * One desired animation per user, disabled unless an authorized operator starts it.
 * user-animation-control is not attached to any role — assign it from the permissions screen.
 * super_admin reaches the endpoints through the existing role check, not a new role grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_animation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('animation_id', 64)->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Permission::firstOrCreate([
            'name' => 'user-animation-control',
            'guard_name' => config('auth.defaults.guard', 'api'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_animation_states');
        Permission::where('name', 'user-animation-control')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
