<?php

/**
 * Reference data for `php artisan areas:sync-reference`.
 * Inserts missing `areas` rows and updates existing ones (by id).
 */
return [

    /**
     * [id, name, parent_id, type, latitude, longitude]
     * parent_id null = root (UAE). Abu Dhabi emirate areas use parent 3 where not specified otherwise.
     */
    'base_areas' => [
        [1, 'UAE', null, 'country', 23.4241000, 53.8478000],
        [3, 'Abu Dhabi', 1, 'city', 24.4539000, 54.3773000],
        [1557, 'Saadiyat Island', 3, 'area', 24.5370000, 54.4330000],
        [1582, 'Yas Island', 3, 'area', 24.4900000, 54.6050000],
        [1583, 'Al Reem Island', 3, 'area', 24.4960000, 54.4060000],
        [1584, 'Masdar City', 3, 'area', 24.4269000, 54.6168000],
        [1585, 'Al Hudayriat Island', 3, 'area', 24.4170000, 54.6670000],
        [1586, 'Fahid Island', 3, 'area', 24.5300000, 54.6700000],
        [1587, 'Zayed City', 3, 'area', 24.4000000, 54.5200000],
        [1588, 'Al Salam Street', 3, 'area', 24.4700000, 54.3500000],
        [1589, 'Raha Beach', 3, 'area', 24.4500000, 54.6100000],
        [1590, 'Al Ghadeer', 3, 'area', 24.9200000, 55.0200000],
        [1591, 'Al Reef', 3, 'area', 24.4600000, 54.6800000],
        [1592, 'Samha', 3, 'area', 24.8200000, 55.0200000],
        [1593, 'Al Shamkha', 3, 'area', 24.3900000, 54.7100000],
        [1594, 'Ramhan Island', 3, 'area', 24.4539000, 54.3773000],
        [1595, 'Nurai Island', 3, 'area', 24.4539000, 54.3773000],
        [1596, 'Al Qurm', 3, 'area', 24.4539000, 54.3773000],
        [1597, 'Nareel Island', 3, 'area', 24.4539000, 54.3773000],
        [1598, 'Rawdat Al Reef', 3, 'area', 24.4539000, 54.3773000],
        [1599, 'Ghantoot', 3, 'area', 24.4539000, 54.3773000],
        [1600, 'The Marina', 3, 'area', 24.4539000, 54.3773000],
        [1601, 'Rabdan', 3, 'area', 24.4539000, 54.3773000],
        [1602, 'Baniyas', 3, 'area', 24.4539000, 54.3773000],
        [1603, 'Al Bahya', 3, 'area', 24.4539000, 54.3773000],
        [1604, 'Al Jurf', 3, 'area', 24.4539000, 54.3773000],
        [1605, 'Khalifa City', 3, 'area', 24.4539000, 54.3773000],
    ],

    /** [id, name, parent_id, type, latitude, longitude] */
    'full_communities' => [
        [1606, 'Marina District', 1557, 'community', 24.4539000, 54.3773000],
        [1607, 'Cultural District', 1557, 'community', 24.4539000, 54.3773000],
        [1608, 'Yas Bay', 1582, 'community', 24.4539000, 54.3773000],
        [1609, 'City of Lights', 1583, 'community', 24.4539000, 54.3773000],
        [1610, 'Marina Square', 1583, 'community', 24.4539000, 54.3773000],
        [1611, 'Markers District', 1583, 'community', 24.4539000, 54.3773000],
        [1612, 'Shams Abu Dhabi', 1583, 'community', 24.4539000, 54.3773000],
        [1613, 'Tamouh', 1583, 'community', 24.4539000, 54.3773000],
        [1614, 'Najmat Abu Dhabi', 1583, 'community', 24.4539000, 54.3773000],
        [1615, 'Al Maryah Island', 3, 'area', 24.4539000, 54.3773000],
        [1616, 'Al Maryah Island', 1615, 'community', 24.4539000, 54.3773000],
        [1617, 'Bashayer', 1585, 'community', 24.4539000, 54.3773000],
        [1618, 'Fahid Beach District', 1586, 'community', 24.4539000, 54.3773000],
        [1619, 'Al Muneera', 1589, 'community', 24.4539000, 54.3773000],
        [1620, 'Al Muneera Island', 1589, 'community', 24.4539000, 54.3773000],
        [1621, 'Al Bandar', 1589, 'community', 24.4539000, 54.3773000],
        [1622, 'Al Zeina', 1589, 'community', 24.4539000, 54.3773000],
        [1623, 'Luluat Al Raha', 1589, 'community', 24.4539000, 54.3773000],
        [1628, 'ALREEMAN 1 Plots', 1593, 'community', 24.4539000, 54.3773000],
        [1629, 'Hydra Village', 1598, 'community', 24.4539000, 54.3773000],
        [1630, 'Al Jurf Gardens', 1604, 'community', 24.4539000, 54.3773000],
        [1631, 'Al Jurf Gardens Ph 2', 1604, 'community', 24.4539000, 54.3773000],
        [1633, 'Jacob & Co. Living', 1604, 'community', 24.4539000, 54.3773000],
        [1634, 'Ohana By The Sea', 1604, 'community', 24.4539000, 54.3773000],
        [1635, 'Al Raha Gardens', 1605, 'community', 24.4539000, 54.3773000],
        [1636, 'Golf Gardens', 1605, 'community', 24.4539000, 54.3773000],
        [1638, 'Reportage Village', 1605, 'community', 24.4539000, 54.3773000],
        [1639, 'Hayat Boulevard', 1605, 'community', 24.4539000, 54.3773000],
        [1640, 'Al Merief', 1605, 'community', 24.4539000, 54.3773000],
        [1641, 'SW4', 1605, 'community', 24.4539000, 54.3773000],
        [1642, 'Al Dahre Compound', 1605, 'community', 24.4539000, 54.3773000],
        [1643, 'SE2', 1605, 'community', 24.4539000, 54.3773000],
        [1653, 'Manzel alreef 2', 1592, 'community', 24.4539000, 54.3773000],
        [1654, 'ALREEMAN 2 Plots', 1593, 'community', 24.4539000, 54.3773000],
    ],

    /** [id, name, parent_id, type, latitude, longitude] — Yas Island sub-communities */
    'yas_ad' => [
        [1686, 'Yas Acres', 1582, 'community', 24.4539000, 54.3773000],
        [1687, 'West Yas', 1582, 'community', 24.4539000, 54.3773000],
        [1688, 'West Yas plots', 1582, 'community', 24.4539000, 54.3773000],
        [1689, 'Lea', 1582, 'community', 24.4539000, 54.3773000],
        [1690, 'Yas Riva', 1582, 'community', 24.4539000, 54.3773000],
        [1692, 'Ansam', 1582, 'community', 24.4539000, 54.3773000],
        [1693, 'Mayan', 1582, 'community', 24.4539000, 54.3773000],
        [1694, 'Waldorf Astoria Residence', 1582, 'community', 24.4539000, 54.3773000],
        [1695, 'Gardenia Bay', 1582, 'community', 24.4539000, 54.3773000],
    ],

    /** [id, name, parent_id, type, latitude, longitude] */
    'yas_alt' => [
        [1696, 'Waters Edge', 1582, 'community', 25.2048000, 55.2708000],
        [1697, 'Yas Living', 1582, 'community', 25.2048000, 55.2708000],
        [1698, 'Sama Yas', 1582, 'community', 25.2048000, 55.2708000],
        [1699, 'The Sustainable City', 1582, 'community', 25.2048000, 55.2708000],
        [1700, 'Yas Golf Collection', 1582, 'community', 25.2048000, 55.2708000],
        [1701, 'Noya', 1582, 'community', 25.2048000, 55.2708000],
        [1702, 'Noya Viva', 1582, 'community', 25.2048000, 55.2708000],
        [1703, 'Noya Luma', 1582, 'community', 25.2048000, 55.2708000],
        [1704, 'Yas Park Gate', 1582, 'community', 25.2048000, 55.2708000],
        [1705, 'Yas Park Views', 1582, 'community', 25.2048000, 55.2708000],
    ],
];
