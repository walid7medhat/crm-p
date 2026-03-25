<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded=[];
   protected $guard_name = 'api'; 
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
             'on_vacation' => 'boolean',

        ];
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function getAvatarAttribute($value)
{
    if (!$value) {
        return 'users/user.png';
    }

    return $value;
}


    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    public function getAllSubordinatesIds()
    {
        $subordinatesIds = [$this->id]; 
        
        foreach ($this->children as $child) {
            $subordinatesIds = array_merge($subordinatesIds, $child->getAllSubordinatesIds());
        }
        
        return $subordinatesIds;
    }


    public function canViewLead(Lead $lead): bool
    {
        if ($this->hasRole('admin') || $this->hasRole('super_admin')) {
            return true;
        }

        if ($this->hasRole('sales')) {
            return 
                   $lead->responsible_person_id === $this->id ||
                   $lead->added_by === $this->id;
        }

        $subordinatesIds = $this->getAllSubordinatesIds();
        
        return 
               in_array($lead->responsible_person_id, $subordinatesIds) ||
               in_array($lead->added_by, $subordinatesIds);
    }

    public function isManagerOrTeamLead(): bool
    {
        return $this->hasRole(['manager', 'team_lead']);
    }
    public function listingComments()
{
    return $this->hasMany(ListingComment::class);
}
  // Check if user is manager
    public function isManager()
    {
        return $this->hasRole('manager');
    }

    // Check if user is team lead
    public function isTeamLead()
    {
        return $this->hasRole('team_lead');
    }

    // Check if user is sales
    public function isSales()
    {
        return $this->hasRole('sales');
    }

public function listings()
{
    return $this->hasMany(Listing::class, 'agent_id');
}

public function approvedRequests()
{
    return $this->hasMany(ListingAccessRequest::class, 'requested_by')
                ->where('status', 'approved');
}


public function agents()
{
    return $this->hasMany(User::class, 'parent_id');
}
function getAdminParentAttribute()
{
    $current = $this;

    while ($current->parent_id) {
        // for not branch get parent
        if(!($current && $current->hasRole('admin') && $current->parent && $current->parent->parent_id==null )){
        $current = $current->parent; 
        }

        if ($current && $current->hasRole('admin') && $current->parent && $current->parent->parent_id==null ) {
            return $current; 
        }
    }

    return null; 
}
 public function getManagerAttribute()
    {
        $current = $this;

        while ($current->parent_id) {
            $current = $current->parent; 

            if ($current && $current->hasRole('manager')) {
                return $current; 
            }
        }

        return null; 
    }

    /**
     * Check if user is in listing team
     */
    public function getIsListingTeamAttribute(): bool
    {
        $current = $this;
         
        while ($current->parent_id) {
            if( $current->listing_team == 1){
                return true;
            }else{
            $current = $current->parent; 

            if ($current && $current->hasRole('manager')) {
                return $current->listing_team == 1; 
            }
            }
        }

        return false; 
    }

    /**
     * Check if user can approve/reject/convert access requests
     */
    public function canManageAccessRequests(): bool
    {
        // Super Admin and Admin can always manage
        if ($this->hasRole('super_admin') || $this->hasRole('admin')) {
            return true;
        }
        
        // Manager can only manage if in listing team
        if ($this->hasRole('manager')) {
            return $this->listing_team == 1;
        }else{
            $manager = $this->getManagerAttribute();
            return $manager && $manager->listing_team != 1 ;
        }

        // Team Lead can always manage
        // if ($this->hasRole('team_lead')) {
        //     return true;
        // }

        // Regular Agent can manage only if their manager is in listing team
        // if ($this->hasRole('agent')) {
        //     $manager = $this->getManagerAttribute();
        //     return $manager && $manager->listing_team != 1;
        // }
         

        return false;
    }


    public function canEditListings($agent): bool
    {      $canAssignAgent=false;
          if ($this->hasAnyRole(['super_admin','admin'])) {
                return true;
            } else {
              $allowedAgentIds = $this->getAllSubordinatesIds();

                if ($agent && in_array($agent, $allowedAgentIds)) {
                    $canAssignAgent = true;
                }
            }
        // Super Admin and Admin can always manage
        if ($this->hasRole('super_admin') || $this->hasRole('admin')) {
            return true;
        }
        
        // Manager can only manage if in listing team
        if ($this->hasRole('manager')) {
            return $this->listing_team == 1 && $canAssignAgent;
        }else{
            $manager = $this->getManagerAttribute();
            return $manager && $manager->listing_team == 1 && $canAssignAgent ;
        }

        // Team Lead can always manage
        // if ($this->hasRole('team_lead')) {
        //     return true;
        // }

        // Regular Agent can manage only if their manager is in listing team
        // if ($this->hasRole('agent')) {
        //     $manager = $this->getManagerAttribute();
        //     return $manager && $manager->listing_team != 1;
        // }
         

        return false;
    }

    /**
     * Check if user can respond to a specific access request
     */
    public function canRespondToAccessRequest(ListingAccessRequest $request): bool
    {
        if($request->request_type=='viewing' && $request->status === 'pending'){
             return $request->listing->isOwner($this) ||  $this->id !== $request->handled_by;
        }
        // First check general permission
        if (!$this->canManageAccessRequests()) {
            return false;
        }

        // Check if user owns the listing
        return $request->listing->isOwnedBy($this) ||  $this->id !== $request->handled_by;
    }

    /**
     * Check if user can convert a specific access request
     */
    public function canConvertAccessRequest($request): bool
    {
        if($request->request_type=='viewing' && $request->status === 'pending'){
             return $request->listing->isOwner($this);
        }
        // First check general permission
        if (!$this->canManageAccessRequests()) {
            return false;
        }

        // User can convert if:
        // 1. Request is approved, OR
        // 2. Request is pending AND user owns the listing
        if ($request->status === 'approved') {
            return true;
        }

        if ($request->status === 'pending') {
            return $this->canManageAccessRequests();
        }

        return false;
    }
public function activeAgent()
{
    if ($this->on_vacation && $this->delegate_agent_id) {
        return User::find($this->delegate_agent_id);
    }

    return $this;
}
public function getAvatarUrlAttribute(){
     return $this->avatar ?  asset('storage/'. $this->avatar) : asset('storage/users/user.png');
}

 public function assignedLeads()
    {
        return $this->hasMany(Lead::class, 'responsible_person_id');
    }
      public function createdLeads()
    {
        return $this->hasMany(Lead::class, 'added_by');
    }
}
