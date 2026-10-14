<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * 1) Stage flows
 *    Production: Pending → Post Prod → Done → Cancelled → Paused
 *                ("Working" is renamed "Post Prod"; "On Revision" is removed — its cards move to Post Prod)
 *    Design:     Pending → Working → Done → On Revision → Paused → Cancelled  (revision comes after Done)
 *    Matched by name, so it is safe to run on any install and leaves admin-added stages alone.
 *
 * 2) Photoshoot bookings become internal: requested by a logged-in user (permission
 *    `photo-booking`) with location, unit number and bedrooms. Contact details come from the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ---------- Production ----------
        $prod = fn (string $name) => DB::table('marketing_stages')
            ->where('category', 'production')->where('name', $name)->whereNull('deleted_at')->first();

        $postProd = $prod('Post Prod');
        if (! $postProd && ($working = $prod('Working'))) {
            DB::table('marketing_stages')->where('id', $working->id)
                ->update(['name' => 'Post Prod', 'icon' => 'lucide:scissors', 'updated_at' => $now]);
            $postProd = $prod('Post Prod');
        }

        if ($postProd && ($revision = $prod('On Revision'))) {
            DB::table('marketing_tasks')->where('stage_id', $revision->id)->update(['stage_id' => $postProd->id]);
            DB::table('marketing_stages')->where('id', $revision->id)->update(['deleted_at' => $now]);
        }

        $this->order('production', ['Pending', 'Post Prod', 'Done', 'Cancelled', 'Paused']);

        // ---------- Design ----------
        $this->order('design', ['Pending', 'Working', 'Done', 'On Revision', 'Paused', 'Cancelled']);
        // ---------- Content ----------
        $this->order('content', ['Pending', 'Working', 'Done', 'On Revision', 'Paused', 'Cancelled']);

        // ---------- Bookings ----------
        Schema::table('marketing_bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('reference')->constrained('users')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->after('shoot_type')->constrained('areas')->nullOnDelete();
            $table->string('location_label')->nullable()->after('area_id');
            $table->string('unit_number', 60)->nullable()->after('location_label');
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('unit_number');
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('phone', 40)->nullable()->change();
            $table->index(['user_id', 'status']);
        });

        Permission::firstOrCreate(['name' => 'photo-booking', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('marketing_bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['location_label', 'unit_number', 'bedrooms']);
        });

        Permission::where('name', 'photo-booking')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Stage renames/order are not reverted (admins may have reordered since).
    }

    /** Put the named stages first, in this order; any other (admin-added) stages keep following. */
    private function order(string $category, array $names): void
    {
        $stages = DB::table('marketing_stages')
            ->where('category', $category)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('id')->get();

        $known = $stages->filter(fn ($s) => in_array($s->name, $names, true))
            ->sortBy(fn ($s) => array_search($s->name, $names, true));
        $others = $stages->reject(fn ($s) => in_array($s->name, $names, true));

        $known->concat($others)->values()->each(
            fn ($s, $i) => DB::table('marketing_stages')->where('id', $s->id)->update(['sort_order' => $i])
        );
    }
};
