<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds latitude/longitude on areas from reference centroids (UAE / Abu Dhabi emirate).
 * IDs match production area records; skipped rows if id missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            1 => [23.4241000, 53.8478000],
            3 => [24.4539000, 54.3773000],
            1557 => [24.5370000, 54.4330000],
            1582 => [24.4900000, 54.6050000],
            1583 => [24.4960000, 54.4060000],
            1584 => [24.4269000, 54.6168000],
            1585 => [24.4170000, 54.6670000],
            1586 => [24.5300000, 54.6700000],
            1587 => [24.4000000, 54.5200000],
            1588 => [24.4700000, 54.3500000],
            1589 => [24.4500000, 54.6100000],
            1590 => [24.9200000, 55.0200000],
            1591 => [24.4600000, 54.6800000],
            1592 => [24.8200000, 55.0200000],
            1593 => [24.3900000, 54.7100000],
            1594 => [24.4539000, 54.3773000],
            1595 => [24.4539000, 54.3773000],
            1596 => [24.4539000, 54.3773000],
            1597 => [24.4539000, 54.3773000],
            1598 => [24.4539000, 54.3773000],
            1599 => [24.4539000, 54.3773000],
            1600 => [24.4539000, 54.3773000],
            1601 => [24.4539000, 54.3773000],
            1602 => [24.4539000, 54.3773000],
            1603 => [24.4539000, 54.3773000],
            1604 => [24.4539000, 54.3773000],
            1605 => [24.4539000, 54.3773000],
        ];

        foreach ($updates as $id => [$lat, $lng]) {
            DB::table('areas')->where('id', $id)->update([
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank: prior coordinates are unknown; restore from backup if needed.
    }
};
