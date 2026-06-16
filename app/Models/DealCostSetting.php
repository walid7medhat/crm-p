<?php
// app/Models/DealCostSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealCostSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'is_active',
        'updated_by'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // العلاقات
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // سكوب للبحث عن الإعدادات النشطة
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // سكوب للبحث بواسطة المفتاح
    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    // دالة مساعدة للحصول على القيمة
    public static function getValue($key, $default = null)
    {
        $setting = static::where('key', $key)->active()->first();
        return $setting ? $setting->value : $default;
    }

    // دالة مساعدة لتحديث القيمة
    public static function setValue($key, $value, $description = null, $userId = null)
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
                'updated_by' => $userId ?? auth()->id(),
                'is_active' => true
            ]
        );
        return $setting;
    }
}