<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stage extends Model
{
    //
    use HasFactory;
    protected $guarded=[];
     public function leads()
    {
        $leads= $this->hasMany(Lead::class);
        
             $user=auth()->user();
             if ($user->hasAnyRole(['admin', 'super_admin'])) {
             }
            elseif ($user->hasRole(['manager', 'team_lead'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                
                $leads = $leads->where(function($query) use ($subordinatesIds, $user) {
                    $query->whereIn('responsible_person_id',array_merge( $subordinatesIds,$user->id))
                          ->orWhereIn('added_by', $subordinatesIds);
                                });
            }
            else {
                $leads = $leads->where(function($query) use ($user) {
                            $query->where('responsible_person_id', $user->id)
                                ->orWhere('added_by', $user->id);
                        });
            }
            return $leads;
    }
}
