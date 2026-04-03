<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yas-related communities: mixed centroids (Abu Dhabi vs export coordinates for second group).
 * Does not change parent_id — set in admin if needed.
 */
return new class extends Migration
{
    private const AD_LAT = 24.4539000;

    private const AD_LNG = 54.3773000;

    private const ALT_LAT = 25.2048000;

    private const ALT_LNG = 55.2708000;

    public function up(): void
    {
        $ad = [
            [1686, 'Yas Acres', 'community'],
            [1687, 'West Yas', 'community'],
            [1688, 'West Yas plots', 'community'],
            [1689, 'Lea', 'community'],
            [1690, 'Yas Riva', 'community'],
            [1692, 'Ansam', 'community'],
            [1693, 'Mayan', 'community'],
            [1694, 'Waldorf Astoria Residence', 'community'],
            [1695, 'Gardenia Bay', 'community'],
        ];

        foreach ($ad as [$id, $name, $type]) {
            DB::table('areas')->where('id', $id)->update([
                'name' => $name,
                'latitude' => self::AD_LAT,
                'longitude' => self::AD_LNG,
                'type' => $type,
                'updated_at' => now(),
            ]);
        }

        $alt = [
            [1696, 'Waters Edge', 'community'],
            [1697, 'Yas Living', 'community'],
            [1698, 'Sama Yas', 'community'],
            [1699, 'The Sustainable City', 'community'],
            [1700, 'Yas Golf Collection', 'community'],
            [1701, 'Noya', 'community'],
            [1702, 'Noya Viva', 'community'],
            [1703, 'Noya Luma', 'community'],
            [1704, 'Yas Park Gate', 'community'],
            [1705, 'Yas Park Views', 'community'],
        ];

        foreach ($alt as [$id, $name, $type]) {
            DB::table('areas')->where('id', $id)->update([
                'name' => $name,
                'latitude' => self::ALT_LAT,
                'longitude' => self::ALT_LNG,
                'type' => $type,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Prior values unknown; restore from backup if needed.
    }
};
