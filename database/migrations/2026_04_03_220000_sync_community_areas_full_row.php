<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Syncs name, lat/lng, parent_id, and type for community/area rows (reference export).
 */
return new class extends Migration
{
    private const LAT = 24.4539000;

    private const LNG = 54.3773000;

    public function up(): void
    {
        $rows = [
            [1606, 'Marina District', 1557, 'community'],
            [1607, 'Cultural District', 1557, 'community'],
            [1608, 'Yas Bay', 1582, 'community'],
            [1609, 'City of Lights', 1583, 'community'],
            [1610, 'Marina Square', 1583, 'community'],
            [1611, 'Markers District', 1583, 'community'],
            [1612, 'Shams Abu Dhabi', 1583, 'community'],
            [1613, 'Tamouh', 1583, 'community'],
            [1614, 'Najmat Abu Dhabi', 1583, 'community'],
            [1615, 'Al Maryah Island', 3, 'area'],
            [1616, 'Al Maryah Island', 1615, 'community'],
            [1617, 'Bashayer', 1585, 'community'],
            [1618, 'Fahid Beach District', 1586, 'community'],
            [1619, 'Al Muneera', 1589, 'community'],
            [1620, 'Al Muneera Island', 1589, 'community'],
            [1621, 'Al Bandar', 1589, 'community'],
            [1622, 'Al Zeina', 1589, 'community'],
            [1623, 'Luluat Al Raha', 1589, 'community'],
            [1628, 'ALREEMAN 1 Plots', 1593, 'community'],
            [1629, 'Hydra Village', 1598, 'community'],
            [1630, 'Al Jurf Gardens', 1604, 'community'],
            [1631, 'Al Jurf Gardens Ph 2', 1604, 'community'],
            [1633, 'Jacob & Co. Living', 1604, 'community'],
            [1634, 'Ohana By The Sea', 1604, 'community'],
            [1635, 'Al Raha Gardens', 1605, 'community'],
            [1636, 'Golf Gardens', 1605, 'community'],
            [1638, 'Reportage Village', 1605, 'community'],
            [1639, 'Hayat Boulevard', 1605, 'community'],
            [1640, 'Al Merief', 1605, 'community'],
            [1641, 'SW4', 1605, 'community'],
            [1642, 'Al Dahre Compound', 1605, 'community'],
            [1643, 'SE2', 1605, 'community'],
            [1653, 'Manzel alreef 2', 1592, 'community'],
            [1654, 'ALREEMAN 2 Plots', 1593, 'community'],
        ];

        foreach ($rows as [$id, $name, $parentId, $type]) {
            DB::table('areas')->where('id', $id)->update([
                'name' => $name,
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'parent_id' => $parentId,
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
