<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetRequest extends Model
{
    protected $fillable = [
        'user_id',
        'asset_item',
        'company_name',
        'branch_id',
        'department_id',
        'qty',
        'description',
        'status',
        'applied_at',
        'approved_by',
        'rejection_reason',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'qty' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
