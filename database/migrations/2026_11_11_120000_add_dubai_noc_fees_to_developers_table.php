<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A developer can sell in both Abu Dhabi and Dubai, and the NOC fee differs per
 * emirate. The existing noc_fees_ready / noc_fees_off_plan stay the Abu Dhabi
 * values; these two columns hold the Dubai ones (amounts before 5% VAT).
 */
return new class extends Migration
{
    /**
     * Dubai NOC reference (AED, + 5% VAT). null = not available.
     * Keys are lower-case name fragments matched against developers.name.
     */
    private const DUBAI_NOC_FEES = [
        'dubai holding' => ['ready' => 525, 'off_plan' => 5000],
        'damac' => ['ready' => 1500, 'off_plan' => 5000],
        'nshama' => ['ready' => 2500, 'off_plan' => 5000],
        'town square' => ['ready' => 2500, 'off_plan' => 5000],
        'danube' => ['ready' => 500, 'off_plan' => 5000],
        'emaar' => ['ready' => 500, 'off_plan' => 5000],
        'binghatti' => ['ready' => 1500, 'off_plan' => 5000],
        'aldar' => ['ready' => null, 'off_plan' => 4000],
    ];

    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->decimal('dubai_noc_fees_ready', 15, 2)->nullable()->after('noc_fees_off_plan');
            $table->decimal('dubai_noc_fees_off_plan', 15, 2)->nullable()->after('dubai_noc_fees_ready');
        });

        foreach (self::DUBAI_NOC_FEES as $fragment => $fees) {
            $query = DB::table('developers')->whereRaw('LOWER(name) LIKE ?', ['%'.$fragment.'%']);

            if ($fees['ready'] !== null) {
                (clone $query)->whereNull('dubai_noc_fees_ready')->update(['dubai_noc_fees_ready' => $fees['ready']]);
            }
            (clone $query)->whereNull('dubai_noc_fees_off_plan')->update(['dubai_noc_fees_off_plan' => $fees['off_plan']]);
        }
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn(['dubai_noc_fees_ready', 'dubai_noc_fees_off_plan']);
        });
    }
};
