<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    //
     protected $fillable = ['name', 'description'];
    
    public function employeeProfiles()
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
