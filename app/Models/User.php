<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class User extends Authenticatable implements JWTSubject, CanResetPasswordContract
{

 use HasFactory;
   use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // use $listing->activities
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('user');
    }
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CanResetPassword;
    use HasFactory, Notifiable, HasRoles;

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
            'birth_date' => 'date',
            'on_vacation' => 'boolean',
            'last_lead_assigned_at' => 'datetime',
            'lead_assign_count_date' => 'date',
            'lead_assign_daily_count' => 'integer',
            'commission_percentage' => 'decimal:2',
        ];
    }
    /**
     * Trim a user's name down to its first two words (e.g. first + last name).
     * Returns null when the name is empty.
     */
    public static function shortName(?string $name): ?string
{
    if ($name === null || trim($name) === '') {
        return null;
    }

    // normalize spaces
    $clean = trim(preg_replace('/\s+/', ' ', $name));

    $parts = explode(' ', $clean);

    if (count($parts) === 1) {
        return $parts[0]; // اسم واحد بس
    }

    // first + last
    return $parts[0] . ' ' . end($parts);
}

    /**
     * Resolve the name to display for a user: the custom display_name when set,
     * otherwise the two-word short version of the real name.
     * Note: display_name must be loaded on the model (include it in partial selects).
     */
    public static function resolveDisplayName(?User $user): ?string
    {
        if (! $user instanceof self) {
            return null;
        }

        return filled($user->display_name)
            ? $user->display_name
            : static::shortName($user->name);
    }

    /**
     * Instance helper: this user's display name (display_name ?: short name).
     */
    public function displayName(): ?string
    {
        return static::resolveDisplayName($this);
    }

    /**
     * Active staff whose birth_date month/day match the given date (defaults to today).
     * Shared by birthday celebration emails/notifications and the in-app celebration layer.
     */
    public function scopeActiveBirthdayOn($query, $date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $query->where('status', 'active')
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $date->month)
            ->whereDay('birth_date', $date->day);
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


    public function background()
    {
        return $this->belongsTo(Background::class, 'background_id');
    }

    /**
     * Resolve the background to actually show this user: their chosen one if it is
     * still set and active, otherwise the system default, otherwise null (no override).
     */
    public function getBackgroundUrlAttribute(): ?string
    {
        $chosen = $this->background;

        if ($chosen && $chosen->is_active) {
            return $chosen->url;
        }

        return Background::default()?->url;
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

 public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    public function getAllSubordinatesIds()
    {
        // Level-by-level batch query instead of one query per user in the tree —
        // the previous recursive version walked `children` node-by-node, which
        // triggered a lazy-loaded query for every single subordinate (huge N+1
        // on any manager/team_lead with a deep or wide team).
        $ids = [$this->id];
        $visited = [$this->id => true];
        $queue = [$this->id];

        while (!empty($queue)) {
            $children = static::whereIn('parent_id', $queue)->pluck('id')->all();

            // Only follow ids we haven't seen yet — a cycle in parent_id (e.g. two
            // users pointing at each other, or a parent_id set to a descendant)
            // would otherwise re-queue the same ids forever, growing $ids without
            // bound until memory is exhausted.
            $newIds = [];
            foreach ($children as $childId) {
                if (!isset($visited[$childId])) {
                    $visited[$childId] = true;
                    $newIds[] = $childId;
                }
            }

            if (empty($newIds)) {
                break;
            }

            $ids = array_merge($ids, $newIds);
            $queue = $newIds;
        }

        return $ids;
    }


    /**
     * branch_admin is placed as a regular team member INSIDE an office (not above it,
     * unlike admin/manager/team_lead) — they typically have no subordinates of their
     * own. Their scope is everyone under the real office/branch admin their own
     * ->office resolves to (siblings + themselves), not their own descendants.
     */
    public function getBranchAdminSubordinateIds(): array
    {
        $officeAdmin = $this->office;

        return $officeAdmin ? $officeAdmin->getAllSubordinatesIds() : [$this->id];
    }

    /**
     * `show-branch-leads`: see every lead in the user's whole BRANCH (Abu Dhabi /
     * Dubai — the admin right under super_admin, i.e. admin_parent), across all of
     * its offices. Wider than branch_admin, which only sees their own office.
     */
    public function hasBranchLeadsPermission(): bool
    {
        // checkPermissionTo (not hasPermissionTo): returns false instead of throwing
        // when the permission row doesn't exist yet.
        return $this->checkPermissionTo('show-branch-leads');
    }

    /**
     * The user's branch, by position in the tree only (no role check):
     *   super_admin → parent_id = null
     *   branch      → parent->parent_id = null   (e.g. Abu Dhabi #25, Dubai #59)
     * Returns null for super_admin itself (no branch above them).
     */
    public function getBranchNode(): ?self
    {
        $current = $this;
        $visited = [];

        while ($current && $current->parent_id !== null) {
            if (isset($visited[$current->id])) {
                return null; // parent_id cycle
            }
            $visited[$current->id] = true;

            $parent = $current->parent;
            if (! $parent) {
                return null;
            }
            if ($parent->parent_id === null) {
                return $current; // parent is super_admin → current is the branch
            }
            $current = $parent;
        }

        return null;
    }

    /** Every user under this user's branch, all offices included. */
    public function getBranchUserIds(): array
    {
        $branch = $this->getBranchNode();

        return $branch ? $branch->getAllSubordinatesIds() : [$this->id];
    }

    /** Sees every lead in their whole branch: branch_admin, or the show-branch-leads permission. */
    public function seesBranchLeads(): bool
    {
        return $this->hasRole('branch_admin') || $this->hasBranchLeadsPermission();
    }

    /**
     * User ids whose leads this user sees:
     *  - branch_admin / show-branch-leads → whole branch (all its offices)
     *  - otherwise                        → their own hierarchy
     * (Deals still scope branch_admin to their office — see getBranchAdminSubordinateIds().)
     */
    public function leadScopeUserIds(): array
    {
        return $this->seesBranchLeads()
            ? $this->getBranchUserIds()
            : $this->getAllSubordinatesIds();
    }

    public function canViewLead(Lead $lead): bool
    {
        if ($this->hasRole('super_admin') || $this->id == 30 || $this->id == 33) {
            return true;
        }

        if ((int) $lead->stage_id === 10) {
            // Lead Pool: same rules as LeadController::index() — a lead already assigned to
            // this user is hidden from their pool entirely (it belongs in their own
            // kanban/leads view), and everything else must belong to their branch. Without
            // the branch check, any authenticated user could open any pool lead by id.
            if ($lead->responsible_person_id === $this->id) {
                return false;
            }

            $branchAdmin = $this->admin_parent;
            $userBranch = $branchAdmin?->name;
            if (! $userBranch) {
                return false;
            }

            if ($lead->lead_branch_source === $userBranch) {
                return true;
            }

            if ($lead->lead_branch_source === null) {
                $branchUserIds = $branchAdmin->getAllSubordinatesIds();

                if ($lead->responsible_person_id !== null) {
                    return in_array($lead->responsible_person_id, $branchUserIds);
                }

                return in_array($lead->added_by, $branchUserIds);
            }

            return false;
        }

        if ($this->hasRole('sales') && ! $this->seesBranchLeads()) {
            // Current responsible person only — matches LeadController::index()'s
            // "reassigned leads no longer belong to whoever merely added them" rule.
            return $lead->responsible_person_id === $this->id;
        }

        // branch_admin / show-branch-leads: whole branch (see getBranchAdminSubordinateIds()).
        return in_array($lead->responsible_person_id, $this->leadScopeUserIds());
    }

    /** Deal equivalent of canViewLead() — matches DealController::authorizeAccess(). */
    public function canViewDeal(Deal $deal): bool
    {
        if ($this->hasRole('super_admin') || $this->id == 30 || $this->id == 33) {
            return true;
        }

        if ($this->hasAnyRole(['manager', 'team_lead', 'admin', 'branch_admin'])) {
            $subordinatesIds = $this->hasRole('branch_admin')
                ? $this->getBranchAdminSubordinateIds()
                : $this->getAllSubordinatesIds();
            return in_array($deal->responsible_person_id, array_merge($subordinatesIds, [$this->id]));
        }

        return $deal->responsible_person_id == $this->id;
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

    public function salesPerformance()
    {
        return $this->hasOne(SalesPerformance::class, 'sales_id');
    }

    public function assignmentSkills()
    {
        return $this->hasMany(UserSkill::class, 'user_id');
    }
    // Per-request memoization for the ancestor-chain walks below. Without this, mapping
    // a large list of users through ->admin_parent/->office re-walks and re-lazy-loads
    // the same shared ancestors (siblings under the same team/branch — most rows) over
    // and over, which is what made large-team responses slow. Any node visited by any
    // walk caches its final result, so a sibling's walk that reaches the same node
    // short-circuits immediately instead of repeating the climb.
    protected static array $adminParentCache = [];
    protected static array $officeCache = [];

function getAdminParentAttribute()
{
    if (array_key_exists($this->id, static::$adminParentCache)) {
        return static::$adminParentCache[$this->id];
    }

    $visited = [$this->id];
    $current = $this;
    $result = null;

    while ($current->parent_id) {
        if (array_key_exists($current->id, static::$adminParentCache)) {
            $result = static::$adminParentCache[$current->id];
            break;
        }

        // for not branch get parent
        if(!($current && $current->hasRole('admin') && $current->parent && $current->parent->parent_id==null )){
        $current = $current->parent;
        $visited[] = $current->id;
        }

        if ($current && $current->hasRole('admin') && $current->parent && $current->parent->parent_id==null ) {
            $result = $current;
            break;
        }
    }

    foreach ($visited as $id) {
        static::$adminParentCache[$id] = $result;
    }

    return $result;
}
function getOfficeAttribute()
{
    if (array_key_exists($this->id, static::$officeCache)) {
        return static::$officeCache[$this->id];
    }

    $visited = [$this->id];
    $current = $this;
    $result = null;

    while ($current->parent_id) {
        if (array_key_exists($current->id, static::$officeCache)) {
            $result = static::$officeCache[$current->id];
            break;
        }

        // for not branch get parent
        if(!($current && $current->hasRole('admin') && $current->parent && $current->parent->parent && $current->parent->parent->parent_id==null )){
        $current = $current->parent;
        $visited[] = $current->id;
        }

        if ($current && $current->hasRole('admin') && $current->parent && $current->parent->parent && $current->parent->parent->parent_id==null ) {
            $result = $current;
            break;
        }
    }

    foreach ($visited as $id) {
        static::$officeCache[$id] = $result;
    }

    return $result;
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
        // Check the user's own flag first — a manager with no parent_id (a top-level
        // manager, nobody above them) never entered the while loop below, so their own
        // listing_team=1 was silently ignored and this always returned false for them.
        if ($this->listing_team == 1) {
            return true;
        }

        $current = $this;

        while ($current->parent_id) {
            $current = $current->parent;

            if ($current && $current->hasRole('manager')) {
                return $current->listing_team == 1;
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
        }elseif ($this->hasRole('team_lead')) {
            $manager = $this->getManagerAttribute();
            return $manager && $manager->listing_team == 1;
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
        
        // Manager can only manage if in listing team shourouq can edit all listings
        if ($this->hasRole('manager')) {
            return $this->listing_team == 1 ;
        }elseif ($this->hasRole('team_lead')) {
            $manager = $this->getManagerAttribute();
            return $manager && $manager->listing_team == 1 && $canAssignAgent;
        }else{
        //   check if owner
            // $manager = $this->getManagerAttribute();
            // return $manager && $manager->listing_team == 1 && $canAssignAgent ;
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
            // A user can never approve/reject their own request.
            if ($this->id === $request->requested_by) {
                return false;
            }

            $subordinatesIds = $this->getAllSubordinatesIds();

    $canAccessHierarchy =
        in_array($request->listing->agent_id, $subordinatesIds) ||
        in_array($request->handled_by, $subordinatesIds);
        
        if($request->request_type=='viewing' && ($request->status == 'pending' || $request->status == 'in_progress')){
             return $this->canManageAccessRequests() || $request->listing->isOwner($this) ||  $this->id == $request->handled_by;
        }
        // First check general permission
        if (!$this->canManageAccessRequests()) {
            return false;
        }

        // Check if user owns the listing
        return $request->listing->isOwnedBy($this) ||  $this->id == $request->handled_by  || $canAccessHierarchy;
    }

    /**
     * Check if user can convert a specific access request
     */
    public function canConvertAccessRequest($request): bool
    {
        if($request->request_type=='viewing' && ($request->status === 'pending'  || $request->status == 'in_progress')){
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
    public function employeeProfile()
{
    return $this->hasOne(EmployeeProfile::class);
}

public function isEmployee()
{
    return $this->employeeProfile()->exists();
}

public function getEmployeeDocumentsAttribute()
{
    return $this->employeeProfile?->documents;
}

    public function agentMetric()
    {
        return $this->hasOne(AgentMetric::class);
    }

    public function agentScores()
    {
        return $this->hasMany(AgentScore::class);
    }
    
        public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'user_id');
    }
    
    public function handledDocumentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'hr_user_id');
    }

    /**
     * Echo / Pusher private channel (matches `Echo.private('user.{id}')` in the frontend).
     */
    public function receivesBroadcastNotificationsOn($notification = null): string
    {
        return 'user.'.$this->getKey();
    }
}
