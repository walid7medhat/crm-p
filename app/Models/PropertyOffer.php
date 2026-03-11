<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyOffer extends Model
{
    use HasFactory;

    protected $table = 'property_offers';

    protected $fillable = [
        'property_id',
        'created_by',
        'offer_number',
        'offer_data',
        'status'
    ];

    protected $casts = [
        'offer_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

   
    public function property(): BelongsTo
    {
        return $this->belongsTo(Listing::class,'property_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * إنشاء رقم عرض فريد
     */
    public static function generateOfferNumber(): string
    {
        $date = now()->format('Ymd');
        $lastOffer = self::whereDate('created_at', today())->count();
        
        return 'OFF-' . $date . '-' . str_pad($lastOffer + 1, 4, '0', STR_PAD_LEFT);
    }
}