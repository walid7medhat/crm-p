<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Brand\Concerns\BrandOwned;

class KanbanSetting extends Model
{
    use BrandOwned;

    protected $table = 'kanban_settings';
    
    protected $fillable = [
        'key',
        'value'
    ];
    
    protected $casts = [
        'value' => 'array'
    ];
    
    /**
     * The current brand's row; if this brand has never saved the setting, fall back
     * to the OIA / legacy row. Without this, Luxvant got empty card fields and a
     * 1-hour revert timer (Assigned leads reverted and vanished from sales boards).
     */
    public static function findForBrand(string $key): ?self
    {
        $own = self::where('key', $key)->first();
        if ($own) {
            return $own;
        }

        return self::withoutBrandScope()
            ->where('key', $key)
            ->where(function ($q) {
                $q->where('brand_key', 'oia')->orWhereNull('brand_key');
            })
            ->orderByRaw('brand_key IS NULL')
            ->first();
    }

    public static function getCardFields()
    {
        $setting = self::findForBrand('card_fields');
        return $setting ? $setting->value : [];
    }

    public static function getRevertHours()
    {
        $setting = self::findForBrand('revert_hours');
        $value = $setting ? $setting->value : 1;
        
        if (is_array($value)) {
            return $value['hours'] ?? 1;
        }
        
        return (int) $value;
    }
    
    public static function getEnabledCardFields()
    {
        $fields = self::getCardFields();
        return array_filter($fields, function($field) {
            return $field['enabled'] === true;
        });
    }
}