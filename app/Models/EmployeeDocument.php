<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    //
       protected $fillable = [
        'employee_profile_id',
        'document_type',
        'document_name',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'sort_order',
        'notes'
    ];
    
    protected $appends = ['file_url'];
    
    public function employeeProfile()
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
    
    public function getFileUrlAttribute()
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : '';
    }
}
