<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace deal stages with exact names from product screenshots.
     * Primary: New, EOI, Booking, SPA Signed (Deal Done), Deal Won, Deal Lost
     * Secondary: New, Security Deposit, MOU/Contract F Signed, NOC, Deal Won, Deal Lost
     * Rental: New, Lease Offer Letter, Guarantee Letter / Cheque Collected, Internal Contract Signed, Ejari / Tawtheq Issued, Tenant moved in, Deal Won
     */
    public function up(): void
    {
        DB::table('stages')->where('stage_type', 'deal')->delete();

        $now = now();
        $rows = [];

        $primary = [
            ['New', '#3B82F6', 1],
            ['EOI', '#0EA5E9', 2],
            ['Booking', '#22C55E', 3],
            ['SPA Signed (Deal Done)', '#22C55E', 4],
            ['Deal Won', '#22C55E', 5],
            ['Deal Lost', '#EF4444', 6],
        ];
        $secondary = [
            ['New', '#3B82F6', 1],
            ['Security Deposit', '#0EA5E9', 2],
            ['MOU/Contract F Signed', '#22C55E', 3],
            ['NOC', '#22C55E', 4],
            ['Deal Won', '#22C55E', 5],
            ['Deal Lost', '#EF4444', 6],
        ];
        $rental = [
            ['New', '#3B82F6', 1],
            ['Lease Offer Letter', '#0EA5E9', 2],
            ['Guarantee Letter / Cheque Collected', '#22C55E', 3],
            ['Internal Contract Signed', '#22C55E', 4],
            ['Ejari / Tawtheq Issued', '#22C55E', 5],
            ['Tenant moved in', '#22C55E', 6],
            ['Deal Won', '#22C55E', 7],
        ];

        foreach ($primary as [$name, $color, $order]) {
            $rows[] = [
                'name' => $name,
                'order' => $order,
                'stage_type' => 'deal',
                'deal_type' => 'primary',
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($secondary as [$name, $color, $order]) {
            $rows[] = [
                'name' => $name,
                'order' => $order,
                'stage_type' => 'deal',
                'deal_type' => 'secondary',
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($rental as [$name, $color, $order]) {
            $rows[] = [
                'name' => $name,
                'order' => $order,
                'stage_type' => 'deal',
                'deal_type' => 'rental',
                'color' => $color,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('stages')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stages')->where('stage_type', 'deal')->delete();
    }
};
