<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealDocument extends Model
{
    protected $table = 'deal_documents';

    protected $fillable = [
        'deal_id',
        'deal_party_id',
        'document_category',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by'
    ];

    // Relationships
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function party()
    {
        return $this->belongsTo(DealParty::class, 'deal_party_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDocumentTypeNameAttribute()
    {
        $types = [
            'national_id' => 'National ID',
            'passport' => 'Passport',
            'visa' => 'Visa',
            'kyc' => 'KYC',
            'spa' => 'SPA',
            'title_deed' => 'Title Deed',
            'tenancy_contract' => 'Tenancy Contract',
            'payment_proof' => 'Payment Proof',
            'noc' => 'NOC Letter',
            'tawtheeq' => 'Tawtheeq/Ejari',
            'move_in' => 'Move In Form'
        ];

        return $types[$this->document_type] ?? $this->document_type;
    }

    // Scopes
    public function scopeForParty($query, $partyId)
    {
        return $query->where('deal_party_id', $partyId);
    }

    public function scopeOfCategory($query, $category)
    {
        return $query->where('document_category', $category);
    }
}