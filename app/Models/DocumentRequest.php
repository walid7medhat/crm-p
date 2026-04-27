<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'hr_user_id',
        'document_type_id',
        'description',
        'file_path',
        'original_name',
        'file_size',
        'status',
        'rejection_reason',
        'requested_date',
        'approved_date',
        'rejected_date',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function hrUser()
    {
        return $this->belongsTo(User::class, 'hr_user_id');
    }
    
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}