<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Solid accent colors matching deal stage gradients (Primary / Secondary / Rental).
     */
    public function up(): void
    {
        $colorsByTypeAndOrder = [
            'primary' => [
                1 => '#39A8EF', // New
                2 => '#AAE9FC', // EOI
                3 => '#00FF00', // Booking
                4 => '#00A64C', // SPA Signed
                5 => '#7BD500', // Deal Won
                6 => '#F11716', // Deal Lost
            ],
            'secondary' => [
                1 => '#39A8EF',
                2 => '#AAE9FC',
                3 => '#00A64C',
                4 => '#47E4C2',
                5 => '#7BD500',
                6 => '#F11716',
            ],
            'rental' => [
                1 => '#39A8EF',
                2 => '#2FC6F6',
                3 => '#00A64C',
                4 => '#47E4C2',
                5 => '#0000FF',
                6 => '#AAE9FC',
                7 => '#7BD500',
                8 => '#F11716',
            ],
        ];

        $now = now();

        foreach ($colorsByTypeAndOrder as $dealType => $orders) {
            foreach ($orders as $order => $color) {
                DB::table('stages')
                    ->where('stage_type', 'deal')
                    ->where('deal_type', $dealType)
                    ->where('order', $order)
                    ->update(['color' => $color, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // Colors are design tokens; no automatic rollback.
    }
};
