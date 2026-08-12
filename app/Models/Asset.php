<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $table = 'assets';
    
    protected $fillable = [
        'asset_code', 'name', 'asset_type_id', 'serial_number', 'model_number',
        'rdp_number', 'description', 'remarks', 'purchase_date', 'warranty_date',
        'unit_price', 'supplier_name', 'quantity', 'condition', 'status',
        'branch_id', 'department_id'  
    ];
    
    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'warranty_date' => 'date:Y-m-d',
        'unit_price' => 'decimal:2',
    ];
    
    // Relationships
    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }
    
    public function branch()
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }
    
    public function department() 
    {
        return $this->belongsTo(Department::class);
    }
    
    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class)->where('status', 'active')->latest();
    }
    
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }
    
    public function histories()
    {
        return $this->hasMany(AssetHistory::class);
    }
    
    public function currentUser()
    {
        return $this->hasOneThrough(User::class, AssetAssignment::class, 'asset_id', 'id', 'id', 'user_id')
            ->where('asset_assignments.status', 'active');
    }
    
    // Helper
    public static function generateAssetCode()
    {
        $latest = self::latest('id')->first();
        $number = $latest ? intval(substr($latest->asset_code, 5)) + 1 : 1;
        return 'AST-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }
    
    public function isAvailable()
    {
        return $this->status === 'available';
    }
    
    public function assignTo($userId, $handoverDate, $notes = null)
    {
        $this->update(['status' => 'assigned']);
        
        return $this->assignments()->create([
            'user_id' => $userId,
            'assigned_by' => auth()->id(),
            'handover_date' => $handoverDate,
            'notes' => $notes,
            'status' => 'active'
        ]);
    }
    
    public function returnAsset($returnDate, $notes = null)
    {
        $currentAssignment = $this->currentAssignment;
        if ($currentAssignment) {
            $currentAssignment->update([
                'return_date' => $returnDate,
                'status' => 'returned',
                'notes' => $notes
            ]);
        }
        
        $this->update(['status' => 'available']);
        
        return $this;
    }
}