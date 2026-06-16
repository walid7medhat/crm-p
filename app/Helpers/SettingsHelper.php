<?php
// app/Helpers/SettingsHelper.php

namespace App\Helpers;

use App\Models\DealCostSetting;

class SettingsHelper
{
    /**
     * Get admin fee for a specific deal type
     */
    public static function getAdminFee(string $type): float
    {
        $key = strtolower($type) . '_admin_fee';
        return (float) DealCostSetting::getValue($key, 0);
    }

    /**
     * Get Dari admin fee
     */
    public static function getDariAdminFee(): float
    {
        return self::getAdminFee('dari');
    }

    /**
     * Get ADGM admin fee
     */
    public static function getAdgmAdminFee(): float
    {
        return self::getAdminFee('adgm');
    }

    /**
     * Calculate total admin fees for a deal
     */
    public static function calculateAdminFee(string $type, float $dealAmount): float
    {
        $feePercentage = self::getAdminFee($type);
        return ($dealAmount * $feePercentage) / 100;
    }
}