<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanbanSetting extends Model
{
    protected $table = 'kanban_settings';
    
    protected $fillable = [
        'key',
        'value'
    ];
    
    protected $casts = [
        'value' => 'array'
    ];
    
    public static function getCardFields()
    {
        $setting = self::where('key', 'card_fields')->first();
        return $setting ? $setting->value : [];
    }
    
    public static function getRevertHours()
    {
        $setting = self::where('key', 'revert_hours')->first();
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