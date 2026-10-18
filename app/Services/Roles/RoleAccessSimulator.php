<?php

namespace App\Services\Roles;

/**
 * Predicts navigation and module access for an unsaved role.
 * Reads no records and writes nothing. Role-name checks use the draft name only,
 * because user update replaces roles via syncRoles. Direct permissions are kept.
 * User ids 30, 33, and 186 keep the exceptions hardcoded in the current CRM.
 */
class RoleAccessSimulator
{
    public function __construct(private PermissionCatalog $catalog)
    {
    }

    /**
     * @param  array{
     *     user_id: int,
     *     name: string,
     *     current_roles: array<int, string>,
     *     direct_permissions: array<int, string>,
     *     is_listing_team: bool,
     *     branch_label: ?string,
     *     parent_id: ?int
     * }  $subject
     * @param  array<int, string>  $draftPermissions
     * @return array<string, mixed>
     */
    public function simulate(array $subject, string $draftName, array $draftPermissions): array
    {
        $draftName = trim($draftName);
        $draftPermissions = array_values(array_unique($draftPermissions));
        $direct = array_values(array_unique($subject['direct_permissions'] ?? []));
        $effective = array_values(array_unique(array_merge($draftPermissions, $direct)));

        $builtin = ['super_admin', 'admin', 'manager', 'team_lead', 'sales', 'branch_admin', 'hr'];
        $roleNameIsBuiltin = in_array($draftName, $builtin, true);

        $contextNote = 'Simulation only. Nothing is saved or assigned. '
            .'The draft role would replace the user’s current role. Direct permissions stay. '
            .'A new role name does not inherit manager, team leader, sales, or branch admin behavior.';

        return [
            'disclaimer' => $contextNote,
            'subject' => [
                'id' => $subject['user_id'],
                'name' => $subject['name'],
                'current_roles' => array_values($subject['current_roles'] ?? []),
                'direct_permissions' => $direct,
                'is_listing_team' => (bool) ($subject['is_listing_team'] ?? false),
                'branch_label' => $subject['branch_label'] ?? null,
                'parent_id' => $subject['parent_id'] ?? null,
            ],
            'draft' => [
                'name' => $draftName,
                'permission_count' => count($draftPermissions),
                'replaces_current_role' => true,
                'role_name_is_builtin' => $roleNameIsBuiltin,
            ],
            'effective_permissions' => $effective,
            'warnings' => $this->warnings($draftName, $draftPermissions, $direct, $roleNameIsBuiltin),
            'sidebar' => $this->sidebar($subject, $draftName, $effective),
            'modules' => $this->modules($subject, $draftName, $effective),
        ];
    }

    /**
     * @param  array<int, string>  $draftPermissions
     * @param  array<int, string>  $direct
     * @return array<int, array<string, string>>
     */
    private function warnings(string $draftName, array $draftPermissions, array $direct, bool $builtin): array
    {
        $warnings = $this->catalog->configurationWarnings($draftPermissions);

        if ($draftName !== '' && ! $builtin) {
            $warnings[] = [
                'id' => 'custom-role-name',
                'level' => 'warning',
                'title' => 'This role name has no hardcoded privileges',
                'detail' => 'Lead, deal, listing, and HR exceptions check the role name super_admin, admin, manager, team_lead, sales, branch_admin, or hr. "'.$draftName.'" receives none of those exceptions. Matching another role’s checkboxes does not copy that role’s record scope.',
            ];
        }

        if ($direct !== []) {
            $warnings[] = [
                'id' => 'direct-permissions-kept',
                'level' => 'warning',
                'title' => 'Direct permissions stay on this employee',
                'detail' => 'After the role is assigned, these direct permissions still apply: '.implode(', ', $direct).'.',
            ];
        }

        return $warnings;
    }

    /**
     * Mirrors resources/js/components/layout/header/index.vue visibility.
     * Counts and icons are omitted. This is a navigation preview, not an API guarantee.
     *
     * @param  array<string, mixed>  $subject
     * @param  array<int, string>  $perms
     * @return array<int, array<string, mixed>>
     */
    private function sidebar(array $subject, string $draftName, array $perms): array
    {
        $id = (int) $subject['user_id'];
        $has = fn (string $key) => in_array($key, $perms, true);
        $isSuper = $draftName === 'super_admin';
        $isAdmin = $isSuper || $draftName === 'admin' || $has('admin');
        $isHr = $draftName === 'hr';
        $onlyListings = $draftName === 'only show listings';
        $customAdmin = $isSuper || ($draftName === 'admin' && in_array($id, [30, 33], true));

        $items = [];

        $items[] = $this->node(
            'dashboard',
            'Dashboard',
            'visible',
            $onlyListings
                ? 'This role name is sent to All Listings instead of the dashboard.'
                : 'Shown to every signed-in user. The role name "only show listings" redirects this item.',
            $onlyListings ? [] : []
        );
        if ($onlyListings) {
            $items[0]['state'] = 'conditional';
        }

        $leadState = (! $isHr && ($isAdmin || $has('show-leads'))) ? 'visible' : 'hidden';
        $leadReason = $isHr
            ? 'The HR role hides the CRM block.'
            : (($isAdmin || $has('show-leads'))
                ? 'Visible because the draft role is admin or super_admin, or show-leads is effective. This does not prove the lead API will allow the request.'
                : 'Hidden. The menu requires show-leads, or the admin or super_admin role. leads-list alone does not show it.');

        $listingChildren = $this->listingChildren($has, $isAdmin, (bool) $subject['is_listing_team'], $draftName);
        $listingNodes = [];
        if ($isAdmin && ! $onlyListings) {
            $listingNodes[] = $this->node(
                'listings-menu',
                'Listings',
                'visible',
                'Admin and super_admin always get this submenu. All Requests is included with no permission check. Other children still depend on their own permission.',
                $listingChildren
            );
        } elseif (! $isAdmin && ! $onlyListings && ! $isHr) {
            $listingNodes[] = $this->node(
                'listings-flat',
                'Listings',
                'conditional',
                'Non-admin users see a Listings link without a listings-list check. The published grid is open to any signed-in user. Listing detail still requires listings-list.',
                $listingChildren
            );
        }

        $crmChildren = [
            $this->node('leads', 'Leads', $leadState, $leadReason),
            $this->node('deals', 'Deals', $leadState, $leadState === 'visible'
                ? 'Uses the same show-leads / admin gate as Leads. Deal record scope is a separate rule.'
                : $leadReason),
            ...$listingNodes,
        ];

        $items[] = $this->node(
            'crm',
            'CRM',
            $isHr ? 'hidden' : 'visible',
            $isHr ? 'Hidden for the hr role.' : 'CRM is shown for every role other than hr. Children can still be hidden.',
            $crmChildren
        );

        $items[] = $this->node(
            'calculators',
            'Calculators',
            $isHr ? 'hidden' : 'visible',
            $isHr ? 'Hidden for the hr role.' : 'ROI and ROE are shown to every non-HR user. Their routes only require a signed-in session.',
            [
                $this->node('roi', 'ROI', $isHr ? 'hidden' : 'visible', 'Route requires authentication only.'),
                $this->node('roe', 'ROE', $isHr ? 'hidden' : 'visible', 'Route requires authentication only.'),
            ]
        );

        $hrMenu = $isSuper || $id === 186 || $isHr;
        $hrRoute = $isSuper || $draftName === 'admin' || $isHr;
        if ($hrMenu && ! $hrRoute) {
            $hrState = 'conditional';
            $hrReason = 'The sidebar shows HR for user 186. The /hr route still requires the admin, super_admin, or hr role, so this employee is sent back to the dashboard if they open it.';
        } elseif ($hrMenu) {
            $hrState = 'visible';
            $hrReason = 'Sidebar shows HR for super_admin, the hr role, or user 186. This role also passes the /hr route guard.';
        } elseif ($hrRoute) {
            $hrState = 'conditional';
            $hrReason = 'The sidebar hides HR for the admin role. The /hr route still allows admin, so the page opens by URL.';
        } else {
            $hrState = 'hidden';
            $hrReason = 'Hidden in the sidebar and blocked by the /hr route guard.';
        }
        $items[] = $this->node('hr', 'HR', $hrState, $hrReason);

        $items[] = $this->node(
            'marketing',
            'Marketing',
            ($isSuper || $has('marketing')) ? 'visible' : 'hidden',
            'Requires the marketing permission or super_admin. The admin role does not bypass this.',
        );

        $items[] = $this->node(
            'photo',
            'Photo Shoots',
            ($isSuper || $has('photo-booking')) ? 'visible' : 'hidden',
            'Requires photo-booking or super_admin. The admin role does not bypass this.',
        );

        $agentChildren = [];
        if ($has('users-list')) {
            $agentChildren[] = $this->node('agents-list', 'Agents List', 'visible', 'Requires users-list.');
        }
        if ($has('users-create')) {
            $agentChildren[] = $this->node('agents-add', 'Add Agent', 'visible', 'Requires users-create.');
        }
        $items[] = $this->node(
            'agents',
            'Agents',
            $agentChildren === [] ? 'hidden' : 'visible',
            $agentChildren === [] ? 'Hidden until users-list or users-create is effective.' : 'Shown from the users-list and users-create permissions.',
            $agentChildren
        );

        $items[] = $this->node(
            'animation',
            'Animation',
            ($isSuper || $has('user-animation-control')) ? 'visible' : 'hidden',
            'Requires user-animation-control or super_admin.',
        );

        $items[] = $this->node(
            'daily-message',
            'Daily Message',
            $isSuper ? 'visible' : 'hidden',
            'Super admin only in the sidebar and on the route.',
        );

        $manageAnnouncements = $isSuper || $id === 33;
        if ($manageAnnouncements) {
            $items[] = $this->node('announcements-admin', 'Announcements (manage)', 'visible', 'Sidebar management item is super_admin or user 33, not the announcements-* permissions.');
        } elseif (! $onlyListings) {
            $items[] = $this->node('announcements-browse', 'Announcements', 'visible', 'Browse item is shown when the user is not the announcement manager and not the only-show-listings role.');
        } else {
            $items[] = $this->node('announcements-browse', 'Announcements', 'hidden', 'Hidden for the only-show-listings role.');
        }

        $items[] = $this->node(
            'suggestions',
            'Suggestions',
            $onlyListings ? 'hidden' : 'visible',
            'Shown to every signed-in user except the only-show-listings role. The route only requires authentication.',
        );

        $settingsChildren = [];
        if ($isAdmin) {
            $settingsChildren[] = $this->node('system-map', 'System Map', 'visible', 'Admin or super_admin.');
        }
        if ($isSuper) {
            foreach (['Logs', 'Agent Performance', 'Import Leads', 'Sync Leads', 'Sync Responsible', 'Deal Costs'] as $label) {
                $settingsChildren[] = $this->node('super-'.md5($label), $label, 'visible', 'Super admin only.');
            }
        }
        if ($has('roles-list')) {
            $settingsChildren[] = $this->node('roles', 'Role & Access', 'visible', 'Requires roles-list. The page route itself only checks that the user is signed in.');
        }
        if ($has('roles-create')) {
            $settingsChildren[] = $this->node('add-role', 'Add Role', 'visible', 'Requires roles-create. The page route itself only checks that the user is signed in.');
        }
        if ($isSuper) {
            $settingsChildren[] = $this->node('lead-reports', 'Lead Reports', 'visible', 'Super admin route.');
            $settingsChildren[] = $this->node('lead-export', 'Export Leads', 'visible', 'Super admin route.');
        }
        if ($isSuper || $id === 30) {
            $settingsChildren[] = $this->node('lead-pool', 'Lead Pool Assignments', 'conditional', 'Super admin, or user 30 regardless of the draft role name.');
        }
        if ($isAdmin && ! $isSuper) {
            $settingsChildren[] = $this->node(
                'admin-url-settings',
                'Settings opened by URL',
                'conditional',
                'Logs, imports, deal costs, and similar items are hidden unless the role is super_admin. Several of those routes still allow the admin role when the URL is opened directly. This preview does not treat those pages as menu access.'
            );
        }
        if ($isAdmin) {
            $settingsChildren[] = $this->node('lead-source', 'Leads by Source', 'visible', 'Admin or super_admin.');
        }
        if ($isSuper || $draftName === 'branch_admin' || $id === 33) {
            $settingsChildren[] = $this->node('daily-leads', 'Daily Leads Report', 'conditional', 'Super admin, branch_admin role name, or user 33.');
        }
        if ($customAdmin) {
            $settingsChildren[] = $this->node('chat', 'All Chats', 'conditional', 'Super admin, or an admin whose user id is 30 or 33.');
        }

        $items[] = $this->node(
            'settings',
            'Settings',
            $settingsChildren === [] ? 'hidden' : 'visible',
            'Shown when at least one settings child is visible. Suggestions are a separate item.',
            $settingsChildren
        );

        return $items;
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<int, array<string, mixed>>
     */
    private function listingChildren(callable $has, bool $isAdmin, bool $listingTeam, string $draftName): array
    {
        $map = [
            'listings-list' => ['All Listing', 'listings-list'],
            'listings-create' => ['Create Listing', 'listings-create'],
            'developers-list' => ['Developers List', 'developers-list'],
            'developers-create' => ['Add Developer', 'developers-create'],
            'owners-list' => ['Owners List', 'owners-list'],
            'owners-create' => ['Add Owner', 'owners-create'],
            'property_types-list' => ['Property Types List', 'property_types-list'],
            'property_types-create' => ['Add Property Type', 'property_types-create'],
            'features-list' => ['Features List', 'features-list'],
            'features-create' => ['Add Feature', 'features-create'],
            'projects-list' => ['Projects List', 'projects-list'],
            'unit_views-list' => ['Unit Views List', 'unit_views-list'],
            'unit_views-create' => ['Add Unit View', 'unit_views-create'],
            'layout_types-list' => ['Layout Types List', 'layout_types-list'],
            'layout_types-create' => ['Add Layout Type', 'layout_types-create'],
            'areas-list' => ['Areas List', 'areas-list'],
            'areas-create' => ['Add Area', 'areas-create'],
        ];

        $children = [];
        if ($isAdmin) {
            $children[] = $this->node(
                'all-requests',
                'All Requests',
                'visible',
                'Shown for admin and super_admin with no permission check. This is menu visibility, not a verified API grant.'
            );
        }
        foreach ($map as $key => [$label]) {
            if ($has($key)) {
                $children[] = $this->node($key, $label, 'visible', 'Shown because '.$key.' is effective. The matching API may still be limited to the detail action.');
            }
        }

        if (! $isAdmin && $has('listings-list')) {
            $children[] = $this->node('my-listing', 'My Listing', 'visible', 'Inserted for non-admin users who have listings-list.');
            $children[] = $this->node('listing-requests', 'Inbound, outbound, and viewings', 'visible', 'These request links require listings-list and are only in the non-admin menu.');
        }

        if (! $isAdmin && $listingTeam && in_array($draftName, ['team_lead', 'manager'], true)) {
            $children[] = $this->node(
                'hot-deals',
                'Hot Deal Requests',
                'conditional',
                'Shown only when this employee already has the listing-team flag and the role name is team_lead or manager. Admin and super_admin use All Requests instead. The draft role does not change the listing-team flag.'
            );
            $children[] = $this->node(
                'need-approval',
                'Need Approval Listings',
                'conditional',
                'Same listing-team and role-name condition as the non-admin sidebar. Not granted by a permission checkbox.'
            );
        }

        return $children;
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  array<int, string>  $perms
     * @return array<int, array<string, mixed>>
     */
    private function modules(array $subject, string $draftName, array $perms): array
    {
        $id = (int) $subject['user_id'];
        $has = fn (string $key) => in_array($key, $perms, true);
        $seesAll = $draftName === 'super_admin' || in_array($id, [30, 33], true);
        $branchWide = $draftName === 'branch_admin' || $has('show-branch-leads');
        $hierarchyRole = in_array($draftName, ['manager', 'team_lead', 'admin', 'branch_admin'], true);

        if ($seesAll) {
            $leadScope = 'Organization-wide. super_admin, or user 30 or 33, bypasses lead ownership filters in User::canViewLead and the Kanban query.';
            $leadState = 'allowed';
        } elseif ($branchWide) {
            $leadScope = 'Whole branch. branch_admin uses the branch tree. show-branch-leads does the same for leads only, through User::seesBranchLeads().';
            $leadState = 'conditional';
        } elseif ($hierarchyRole) {
            $leadScope = 'Own leads plus people below this employee in the parent tree. The Kanban query uses that set for manager, team_lead, admin, and branch_admin.';
            $leadState = 'conditional';
        } elseif ($draftName === 'sales') {
            $leadScope = 'Own leads only. The sales role sees leads where this employee is the current responsible person, unless show-branch-leads is also effective.';
            $leadState = 'conditional';
        } else {
            $leadScope = 'Split behavior for this role name. The Kanban query (StageController::scopedLeadsQuery) returns only leads assigned to this employee. Opening a lead by id (User::canViewLead) also allows leads assigned to people below them, because that path uses the subordinate tree for any role that is not sales and not branch-wide.';
            $leadState = 'conditional';
        }

        $dealScope = $seesAll
            ? 'Organization-wide, same user-id and super_admin bypass as leads.'
            : ($draftName === 'branch_admin'
                ? 'Branch-wide deal visibility, because the role name is branch_admin.'
                : (in_array($draftName, ['manager', 'team_lead', 'admin'], true)
                    ? 'Deals assigned to this employee or to people below them.'
                    : 'Deals assigned to this employee, plus a deal whose source lead they can already open. show-branch-leads does not widen deals. The role name does.'));

        $modules = [];

        $modules[] = [
            'id' => 'leads',
            'label' => 'Leads',
            'items' => [
                $this->check('leads.menu', 'Leads and Deals menu', ($draftName === 'admin' || $draftName === 'super_admin' || $has('show-leads')) && $draftName !== 'hr' ? 'visible' : 'hidden', 'Header uses show-leads, or the admin or super_admin role. The hr role hides CRM. Visible does not mean the lead API will succeed.'),
                $this->check('leads.api', 'Lead list, detail, and export API', $has('leads-list') ? 'allowed' : 'denied', 'LeadController middleware requires leads-list. This is an API check, not menu visibility.'),
                $this->check('leads.create', 'Create lead', $has('leads-create') ? 'allowed' : 'denied', 'Enforced on LeadController::store.'),
                $this->check('leads.edit', 'Edit lead and assign responsible person', $has('leads-edit') ? 'conditional' : 'denied', 'leads-edit is required. Assignment is still limited to the caller’s scope unless the role is admin or super_admin.'),
                $this->check('leads.delete', 'Delete lead', ($has('leads-delete') || in_array($draftName, ['admin', 'super_admin'], true)) ? 'allowed' : 'denied', 'Destroy allows super_admin, admin, or leads-delete.'),
                $this->check('leads.scope', 'Which leads', $leadState, $leadScope),
                $this->check('leads.activities', 'Lead comments and activities', $has('leads-list') ? 'conditional' : 'unverified', 'Activity endpoints were not re-verified one by one. Where they load a lead, they follow the same lead visibility helpers. Treat a specific activity route as unverified until exercised.'),
            ],
        ];

        $modules[] = [
            'id' => 'deals',
            'label' => 'Deals',
            'items' => [
                $this->check('deals.menu', 'Deals menu', ($draftName === 'admin' || $draftName === 'super_admin' || $has('show-leads')) && $draftName !== 'hr' ? 'visible' : 'hidden', 'Same menu flag as Leads. There is no deals-list permission. Deal records use a separate scope.'),
                $this->check('deals.scope', 'Which deals', $seesAll ? 'allowed' : 'conditional', $dealScope),
                $this->check('deals.actions', 'Deal stage changes and edits', 'unverified', 'Deal routes are not gated by a deals-* permission. They call User::canViewDeal and extra role checks inside the controller. This preview does not claim a specific button is enabled.'),
            ],
        ];

        $modules[] = [
            'id' => 'listings',
            'label' => 'Listings',
            'items' => [
                $this->check('listings.published', 'Published listing grid', 'conditional', 'Any signed-in user can load the published, approved grid in ListingController::getListingsData. listings-list is not required for that index. Drafts and unapproved listings are a different rule.'),
                $this->check('listings.detail', 'Listing detail', $has('listings-list') ? 'conditional' : 'denied', 'Detail requires listings-list. Unpublished listings are limited to the owner, super_admin, admin, branch_admin, a listing-team manager, or a listing-team team lead over their own team.'),
                $this->check('listings.create', 'Create listing', $has('listings-create') ? 'allowed' : 'denied', 'Enforced on store.'),
                $this->check('listings.edit', 'Edit listing', $has('listings-edit') ? 'conditional' : 'denied', 'Permission is required, and ListingController still checks ownership, super_admin, or canEditListings. Manager edit of all listings also needs the existing listing-team flag.'),
                $this->check('listings.delete', 'Delete listing', $has('listings-delete') ? 'conditional' : 'denied', 'Permission is required, then the same ownership and super_admin checks as edit.'),
                $this->check('listings.assign', 'Assign listing agent', $has('listings-assign') ? 'conditional' : 'denied', 'The permission is necessary for the assign control. Team and role checks still apply.'),
                $this->check('listings.team', 'Listing-team flag', ! empty($subject['is_listing_team']) ? 'conditional' : 'denied', 'Taken from this employee’s current listing-team flag. The draft role does not change it. Hot-deal and approval menus also look at this flag plus the role name.'),
            ],
        ];

        $modules[] = [
            'id' => 'people',
            'label' => 'Users and employees',
            'items' => [
                $this->check('users.list', 'Agent list API', 'conditional', $this->userListScope($draftName, $id, $has)),
                $this->check('users.create', 'Create agent', $has('users-create') ? 'allowed' : 'denied', 'Enforced on UserController::store. New users also receive the direct show-leads permission unless they sit under a listing team.'),
                $this->check('users.edit', 'Edit agent', 'conditional', 'users-edit is not enforced. Update is allowed when canAccessUser passes: super_admin, admin, branch_admin, the user themself, a manager over two levels, or a team lead over direct reports.'),
                $this->check('users.delete', 'Delete agent', $has('users-delete') ? 'conditional' : 'denied', 'users-delete is enforced. Hierarchy checks inside the method still apply.'),
                $this->check('users.role', 'Change the employee’s role', $has('roles-assign') ? 'allowed' : 'denied', 'The user form checks roles-assign and then syncRoles, which replaces existing roles.'),
                $this->check('users.hierarchy', 'People below this employee', 'conditional', 'The parent tree is unchanged by the draft role. Subordinate visibility is used only when the role name or a scope permission tells a query to walk that tree.'),
            ],
        ];

        $modules[] = [
            'id' => 'hr',
            'label' => 'HR',
            'items' => [
                $this->check('hr.menu', 'HR menu', ($draftName === 'super_admin' || $draftName === 'hr' || $id === 186) ? 'allowed' : 'denied', 'Sidebar condition. The admin role is not included here.'),
                $this->check('hr.route', 'HR page route', (in_array($draftName, ['super_admin', 'admin', 'hr'], true)) ? 'allowed' : 'denied', 'Vue router allows admin, super_admin, or hr. That is wider than the sidebar.'),
                $this->check('hr.api', 'Employee read API', ($has('employees-list') || in_array($draftName, ['super_admin', 'admin', 'hr'], true)) ? 'allowed' : 'denied', 'Middleware allows those role names or employees-list. hr-view is not a stored permission.'),
            ],
        ];

        $modules[] = [
            'id' => 'other',
            'label' => 'Marketing, announcements, and settings',
            'items' => [
                $this->check('marketing', 'Marketing workspace', ($draftName === 'super_admin' || $has('marketing')) ? 'allowed' : 'denied', 'Permission or super_admin. Stage deletion inside Marketing still needs admin or super_admin.'),
                $this->check('photo', 'Photo shoot requests', ($draftName === 'super_admin' || $has('photo-booking')) ? 'allowed' : 'denied', 'Own requests only.'),
                $this->check('announcements.api', 'Announcement API', $has('announcements-list') ? 'conditional' : 'denied', 'API keys exist. The management screen is additionally limited to super_admin and user 33.'),
                $this->check('settings.role', 'Role-gated settings', in_array($draftName, ['super_admin', 'admin'], true) ? 'conditional' : 'denied', 'Logs, imports, lead reports, and similar pages check the role name. They cannot be granted with a permission checkbox.'),
            ],
        ];

        return $modules;
    }

    /**
     * @param  callable(string): bool  $has
     */
    private function userListScope(string $draftName, int $id, callable $has): string
    {
        if ($draftName === 'super_admin' || $id === 30 || $has('users-code')) {
            return 'The user index returns the full agent list for super_admin, user 30, or users-code. users-list is not required on that index.';
        }
        if (in_array($draftName, ['admin', 'branch_admin'], true) || $id === 33) {
            return 'The user index is limited to this employee’s branch. users-list is not required on that index.';
        }

        return 'The user index is limited to this employee and people below them. users-list hides or shows the Agents menu, but the index itself does not check it.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function node(string $id, string $label, string $state, string $reason, array $children = []): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'state' => $state,
            'reason' => $reason,
            'note' => $this->menuNote($id, $reason),
            'children' => $children,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function check(string $id, string $label, string $state, string $detail): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'state' => $state,
            'detail' => $detail,
            'brief' => $this->checkBrief($id, $detail),
            'group' => $this->checkGroup($id),
        ];
    }

    private function checkGroup(string $id): string
    {
        return match (true) {
            str_ends_with($id, '.menu') => 'menu',
            str_ends_with($id, '.scope'), $id === 'listings.published', $id === 'listings.team', $id === 'users.hierarchy', $id === 'users.list' => 'records',
            str_ends_with($id, '.api'), $id === 'listings.detail', $id === 'hr.route', $id === 'marketing', $id === 'photo', $id === 'settings.role' => 'page',
            default => 'action',
        };
    }

    private function checkBrief(string $id, string $detail): string
    {
        $fixed = [
            'leads.menu' => 'The Leads and Deals menu. Seeing it does not load the lead records.',
            'leads.api' => 'Opening the lead list, one lead, and export.',
            'leads.create' => 'Creating a lead.',
            'leads.edit' => 'Editing a lead they can already open, including the responsible person.',
            'leads.delete' => 'Deleting a lead. Admin and super admin can also delete without this checkbox.',
            'leads.activities' => 'Comments and activity on a lead they can already open. Not every activity screen was rechecked.',
            'deals.menu' => 'The Deals menu uses the same switch as Leads. Which deals appear is separate.',
            'deals.actions' => 'Not verified. Moving or editing a deal has no deals permission, and a specific button was not rechecked.',
            'listings.published' => 'Published, approved listings can appear for any signed-in employee. That is not the same as opening every listing.',
            'listings.detail' => 'Opening one listing. Unpublished listings stay with the owner, admins, or a listing-team manager or team leader.',
            'listings.create' => 'Creating a listing when the form is valid.',
            'listings.edit' => 'Editing a listing they own, or another listing when listing-team and role rules already allow it.',
            'listings.delete' => 'Deleting a listing they are already allowed to edit.',
            'listings.assign' => 'Assigning an agent when this is granted and the listing rules already allow the change.',
            'listings.team' => 'Hot deals and approvals use this employee’s current listing-team flag. The draft role does not change that flag.',
            'users.list' => 'The agent list shows everyone, the branch, or people below them, depending on the role.',
            'users.create' => 'Creating an agent account.',
            'users.edit' => 'The edit screen checks this permission, but saving an agent follows the role and reporting line.',
            'users.delete' => 'Deleting an agent, still limited to people they are allowed to manage.',
            'users.role' => 'Replacing the employee’s role from the user form.',
            'users.hierarchy' => 'People below this employee do not change. The role uses that tree only when its rules say so.',
            'hr.menu' => 'Whether HR appears in the menu.',
            'hr.route' => 'Whether the HR page opens. Admin can open it by address even when the menu hides it.',
            'hr.api' => 'Reading employee records. Admin, super admin, and the HR role can also read them.',
            'marketing' => 'Opening Marketing. Admin does not get this automatically.',
            'photo' => 'Their own photo-shoot requests. Admin does not get this automatically.',
            'announcements.api' => 'Announcement records. Managing them on screen is still limited to super admin and one specific account.',
            'settings.role' => 'Logs, imports, and similar settings follow the role name, not a checkbox.',
        ];

        if (isset($fixed[$id])) {
            return $fixed[$id];
        }

        if ($id === 'deals.scope') {
            if (str_contains($detail, 'Organization-wide')) {
                return 'Deals across the company.';
            }
            if (str_contains($detail, 'Branch-wide')) {
                return 'Deals across their branch, because the role name is branch admin.';
            }
            if (str_contains($detail, 'people below')) {
                return 'Deals assigned to them or to people below them.';
            }

            return 'Their own deals, plus a deal whose lead they can already open. A branch-wide deal list needs the branch admin role name.';
        }

        if ($id === 'leads.scope') {
            if (str_contains($detail, 'Organization-wide')) {
                return 'Leads across the company.';
            }
            if (str_contains($detail, 'Whole branch')) {
                return 'Leads across their branch.';
            }
            if (str_contains($detail, 'Own leads plus')) {
                return 'Their own leads and leads for people below them.';
            }
            if (str_contains($detail, 'Own leads only')) {
                return 'Only leads where they are the responsible person.';
            }

            return 'The board shows leads assigned to them. Opening a lead can also include people below them.';
        }

        return $detail;
    }

    private function menuNote(string $id, string $reason): string
    {
        $fixed = [
            'dashboard' => 'The home page for every signed-in employee.',
            'leads' => 'Leads in the menu. This does not decide which leads they can open.',
            'deals' => 'Deals in the menu. Which deals they see is a separate rule.',
            'listings-menu' => 'Listings for an admin. All Requests is included. Other items still need their own permission.',
            'listings-flat' => 'A Listings link. The published grid is broad. Opening one listing still needs View Listings.',
            'all-requests' => 'All Requests is included for admin with no extra permission. It is only the menu.',
            'my-listing' => 'Their own listings, when View Listings is granted.',
            'hot-deals' => 'Shown for a listing-team team leader or manager. The role draft does not change that team flag.',
            'need-approval' => 'Same listing-team rule as hot deals. A checkbox does not grant it.',
            'hr' => 'The HR menu. Opening the page can follow a different rule from the menu.',
            'agents' => 'The Agents menu. Who appears in the list still follows branch or reporting line.',
            'crm' => 'The CRM section. Items inside it can still be hidden.',
        ];

        if (isset($fixed[$id])) {
            return $fixed[$id];
        }

        if (str_contains($reason, '::') || str_contains($reason, 'Controller')) {
            return 'Follows the existing menu rules. Open it to see what is actually included.';
        }

        return $reason;
    }
}
