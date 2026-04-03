<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Community / sub-community rows: centroid matches reference dataset (Abu Dhabi city center).
 * Updates only latitude and longitude on areas.
 */
return new class extends Migration
{
    private const CENTROID_LAT = 24.4539000;

    private const CENTROID_LNG = 54.3773000;

    public function up(): void
    {
        $ids = [
            1606, 1607, 1608, 1609, 1610, 1611, 1612, 1613, 1614,
            1615, 1616, 1617, 1618, 1619, 1620, 1621, 1622, 1623,
            1628, 1629, 1630, 1631, 1633, 1634, 1635, 1636,
            1638, 1639, 1640, 1641, 1642, 1643,
            1653, 1654,
        ];

        foreach ($ids as $id) {
            DB::table('areas')->where('id', $id)->update([
                'latitude' => self::CENTROID_LAT,
                'longitude' => self::CENTROID_LNG,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Prior values unknown; restore from backup if needed.
    }
};
