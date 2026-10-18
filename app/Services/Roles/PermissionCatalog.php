<?php

namespace App\Services\Roles;

/**
 * Read-only descriptions of permission keys that already exist.
 * This catalog does not create permissions and is not consulted by Gates or middleware.
 */
class PermissionCatalog
{
    /**
     * @param  array<int, string>  $databaseNames
     * @return array{modules: array<int, array<string, mixed>>, warnings: array<int, array<string, string>>}
     */
    public function present(array $databaseNames): array
    {
        $known = $this->definitions();
        $databaseNames = array_values(array_unique($databaseNames));
        $modules = [];

        foreach ($databaseNames as $name) {
            $meta = $known[$name] ?? $this->unmapped($name);
            $moduleId = $meta['module_id'];
            if (! isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'label' => $meta['module_label'],
                    'summary' => $meta['module_summary'],
                    'permissions' => [],
                ];
            }
            $facts = $this->facts($name, (bool) ($meta['verified'] ?? false));
            $modules[$moduleId]['permissions'][] = [
                'key' => $name,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'sensitivity' => $meta['sensitivity'],
                'sidebar' => $meta['sidebar'],
                'limitations' => $meta['limitations'],
                'verified' => $meta['verified'],
                'registered' => true,
                'access' => $facts['access'],
                'points' => $facts['points'],
            ];
        }

        $order = array_keys($this->moduleOrder());
        uksort($modules, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = $ia === false ? 999 : $ia;
            $ib = $ib === false ? 999 : $ib;

            return $ia <=> $ib;
        });

        return [
            'modules' => array_values($modules),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<int, string>  $selected
     * @return array<int, array<string, string>>
     */
    public function configurationWarnings(array $selected): array
    {
        $selected = array_values(array_unique($selected));
        $has = fn (string $key) => in_array($key, $selected, true);
        $warnings = [];

        if ($has('show-leads') && ! $has('leads-list')) {
            $warnings[] = $this->warning(
                'leads-menu-without-api',
                'warning',
                'Leads menu without the leads API permission',
                'show-leads opens the Leads and Deals menu and the Kanban routes. LeadController still requires leads-list for the lead list, lead detail, and export. The menu can appear and the API can return 403.'
            );
        }

        if ($has('leads-list') && ! $has('show-leads')) {
            $warnings[] = $this->warning(
                'leads-api-without-menu',
                'warning',
                'Lead API permission without the Leads menu',
                'leads-list allows the lead API. The sidebar and /kanban stay hidden unless the user is admin or super_admin, or also has show-leads. Direct API calls can still succeed.'
            );
        }

        if ($has('show-branch-leads')) {
            $warnings[] = $this->warning(
                'branch-lead-scope',
                'warning',
                'Branch-wide lead visibility',
                'show-branch-leads is a data-scope flag (User::seesBranchLeads), not a menu switch. It widens lead visibility to the whole branch. It does not by itself make the user a branch_admin, and deal visibility still depends on the role name.'
            );
        }

        foreach (['roles-create', 'roles-edit', 'roles-delete', 'roles-assign', 'assign-role'] as $key) {
            if ($has($key)) {
                $warnings[] = $this->warning(
                    'privilege-'.$key,
                    'warning',
                    'Role administration permission',
                    $key.' lets the holder change roles or who holds them. The current save path does not limit which permissions a role editor can attach. Review this before granting it.'
                );
            }
        }

        if ($has('roles-assign') xor $has('assign-role')) {
            $warnings[] = $this->warning(
                'split-assign-permissions',
                'warning',
                'Role assignment is split across two keys',
                'Changing a user from the user form checks roles-assign. The assign-role and sync-roles APIs check assign-role. They are not interchangeable.'
            );
        }

        if ($has('users-edit')) {
            $warnings[] = $this->warning(
                'users-edit-not-enforced',
                'warning',
                'Edit-users is not enforced on the user update API',
                'users-edit is checked in the interface. UserController::update has that middleware commented out. Update access follows canAccessUser (hierarchy and a few role names), not this checkbox.'
            );
        }

        return $warnings;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        $items = [];

        $crud = [
            'leads' => ['Leads', 'Lead records', 'The lead API. Menu visibility is show-leads, not these keys. Record scope follows the role name and reporting tree.'],
            'listings' => ['Listings', 'Property listings', 'listings-list is enforced on listing detail. The published listing grid is available to any logged-in user. Drafts and approval follow role name and listing-team flags.'],
            'developers' => ['Listing setup', 'Developers', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'owners' => ['Listing setup', 'Owners', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'property_types' => ['Listing setup', 'Property types', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'features' => ['Listing setup', 'Listing features', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'projects' => ['Listing setup', 'Projects', 'Projects routes also declare requiresPermission in the Vue router. Admin bypasses that router check unless it is marked strict. The API still checks the permission.'],
            'unit_views' => ['Listing setup', 'Unit views', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'layout_types' => ['Listing setup', 'Layout types', 'List permission is enforced on the detail endpoint. The index endpoint is not permission-gated.'],
            'areas' => ['Listing setup', 'Areas', 'List permission is enforced on show, byType, and children. The index endpoint is not permission-gated.'],
            'sources' => ['Leads', 'Lead sources', 'Source detail is permission-gated. Scope is not changed by these keys.'],
            'stages' => ['Leads', 'Pipeline stages', 'stages-list is registered with an empty method list, so it does not protect the stage index. Create, edit, and delete are enforced.'],
            'users' => ['People', 'Agent accounts', 'users-list and users-create match the Agents menu. users-edit is not enforced on update. Visibility of other people follows the reporting tree and role name, not this checkbox.'],
            'roles' => ['Access control', 'Roles', 'Creating or editing a role can attach any existing permission. Protected role ids 1–5 can be changed only by super_admin.'],
            'employees' => ['HR', 'Employee profiles', 'Read access also allows the hr role name, admin, and super_admin. hr-view is referenced in code and is not a stored permission.'],
            'departments' => ['HR', 'Departments', 'Read access also allows the hr role name, admin, and super_admin.'],
            'designations' => ['HR', 'Designations', 'Read access also allows the hr role name, admin, and super_admin.'],
            'branches' => ['HR', 'Company branches', 'These are HR company-branch records, not the lead reporting branch.'],
            'jobs' => ['HR', 'Job posts', 'Recruitment job records.'],
            'announcements' => ['Announcements', 'Announcement records', 'The management sidebar item is super_admin or user 33. These API keys are separate from that menu check.'],
        ];

        foreach ($crud as $prefix => [$moduleLabel, $noun, $limitation]) {
            $moduleId = $this->moduleIdForPrefix($prefix);
            foreach (['list' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'] as $action => $verb) {
                $key = $prefix.'-'.$action;
                $items[$key] = $this->entry(
                    $moduleId,
                    $moduleLabel,
                    $this->moduleSummary($moduleId),
                    $verb.' '.$noun,
                    'Allows the '.$action.' operation associated with '.$key.' where that middleware is applied.',
                    $action === 'list' ? 'navigation' : 'action',
                    in_array($action, ['delete'], true) || str_starts_with($key, 'roles-') ? 'high' : 'normal',
                    $this->sidebarFor($key),
                    $limitation,
                    true
                );
            }
        }

        $items['show-leads'] = $this->entry(
            'leads',
            'Leads',
            $this->moduleSummary('leads'),
            'Show Leads and Deals in the menu',
            'Sidebar and router gate for /kanban and /kanban_deal. Admin and super_admin see those entries without this key. It does not set which lead records are returned.',
            'navigation',
            'normal',
            'CRM → Leads and CRM → Deals',
            'Pair it with leads-list or the lead API returns 403. Record scope is still the reporting tree, branch flag, or role name.',
            true
        );

        $items['show-branch-leads'] = $this->entry(
            'leads',
            'Leads',
            $this->moduleSummary('leads'),
            'See every lead in the user branch',
            'User::seesBranchLeads() treats this like a branch-wide lead scope. It is not a sidebar item.',
            'scope',
            'high',
            null,
            'Does not rename the user to branch_admin. Deal boards still use the role name for branch-wide deal scope.',
            true
        );

        $items['listings-assign'] = $this->entry(
            'listings',
            'Listings',
            $this->moduleSummary('listings'),
            'Assign a listing agent',
            'ListingResource exposes can_assign_agent only when this permission is held and the existing assignment rules already allow it.',
            'action',
            'normal',
            null,
            'Role name and listing-team flags still decide who may edit another agent’s listing.',
            true
        );

        $items['marketing'] = $this->entry(
            'marketing',
            'Marketing',
            $this->moduleSummary('marketing'),
            'Open the Marketing workspace',
            'Sidebar, route, and API all require this permission or the super_admin role. The admin role does not bypass it.',
            'navigation',
            'normal',
            'Marketing',
            'Deleting cards, managing stages, and the daily report still require the admin or super_admin role inside that workspace.',
            true
        );

        $items['photo-booking'] = $this->entry(
            'marketing',
            'Marketing',
            $this->moduleSummary('marketing'),
            'Request a photo shoot',
            'Sidebar and /photo-bookings require this permission or super_admin. The admin role does not bypass it.',
            'navigation',
            'normal',
            'Photo Shoots',
            'The user sees their own requests. Handling bookings inside Marketing is a separate permission.',
            true
        );

        $items['user-animation-control'] = $this->entry(
            'system',
            'System',
            $this->moduleSummary('system'),
            'Control user animations',
            'Sidebar and the animation-control route require this permission or super_admin.',
            'navigation',
            'normal',
            'Animation',
            'Strict route: the admin role does not bypass it.',
            true
        );

        $items['assign-role'] = $this->entry(
            'access',
            'Access control',
            $this->moduleSummary('access'),
            'Assign roles through the role API',
            'Protects assign-role, remove-role, and sync-roles on UserRoleController.',
            'action',
            'high',
            null,
            'The user edit screen checks roles-assign instead. super_admin in the current data set has roles-assign and not this key.',
            true
        );

        $items['roles-assign'] = $this->entry(
            'access',
            'Access control',
            $this->moduleSummary('access'),
            'Change a user’s role from the user form',
            'UserController::update calls syncRoles when role_id is sent and the editor can(roles-assign).',
            'action',
            'high',
            null,
            'This replaces the user’s roles with the one submitted. It is not the same key as assign-role.',
            true
        );

        $items['users-code'] = $this->entry(
            'people',
            'People',
            $this->moduleSummary('people'),
            'Manage biometric codes',
            'Shows the biometric column and bypasses the subordinate filter on the user list.',
            'action',
            'high',
            'Agents list, biometric column',
            'User 30 also bypasses that list filter by id, without this permission.',
            true
        );

        $items['settings-view'] = $this->entry(
            'system',
            'System',
            $this->moduleSummary('system'),
            'View settings',
            'Stored permission. No single settings screen was found that gates itself only on this key.',
            'action',
            'normal',
            null,
            'Treat the effect as not fully mapped. Most settings screens check the admin or super_admin role.',
            false
        );

        $items['settings-update'] = $this->entry(
            'system',
            'System',
            $this->moduleSummary('system'),
            'Update deal cost settings',
            'DealCostSettingController enforces this on update. The Vue page also checks it.',
            'action',
            'high',
            null,
            'The deal-cost route itself requires the admin role before this permission is consulted.',
            true
        );

        $items['view-agent-attendance'] = $this->entry(
            'people',
            'People',
            $this->moduleSummary('people'),
            'View agent attendance',
            'User detail shows attendance when this permission is held or the viewer is an admin or the direct manager.',
            'action',
            'normal',
            null,
            'Direct managers can see it without the permission. That exception is not a checkbox.',
            true
        );

        $items['view-agent-performance'] = $this->entry(
            'people',
            'People',
            $this->moduleSummary('people'),
            'View agent performance',
            'User detail shows performance when this permission is held or the viewer is an admin or the direct manager.',
            'action',
            'normal',
            null,
            'Direct managers can see it without the permission.',
            true
        );

        $items['view-employee-balance'] = $this->entry(
            'hr',
            'HR',
            $this->moduleSummary('hr'),
            'View employee leave balance',
            'The leave balance route is protected with this permission.',
            'action',
            'normal',
            null,
            'Does not open the HR sidebar. That item follows super_admin, the hr role, or user 186.',
            true
        );

        $items['applicants-list'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'View applicants', 'Recruitment applicant list and detail.', 'action', 'normal', null, 'HR route access is still role-gated in the Vue router.', true);
        $items['applicants-status-edit'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'Change applicant status', 'Updates applicant status in recruitment.', 'action', 'normal', null, 'HR route access is still role-gated in the Vue router.', true);
        $items['interviews-list'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'View interviews', 'Recruitment interview list.', 'action', 'normal', null, 'HR route access is still role-gated in the Vue router.', true);
        $items['interviews-create'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'Schedule interviews', 'Creates a recruitment interview.', 'action', 'normal', null, 'HR route access is still role-gated in the Vue router.', true);
        $items['interviews-edit'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'Edit interviews', 'Updates or deletes a recruitment interview.', 'action', 'normal', null, 'The delete action is bundled into interviews-edit.', true);
        $items['recruitment-list'] = $this->entry('hr', 'HR', $this->moduleSummary('hr'), 'View recruitment statistics', 'Recruitment statistics endpoint.', 'action', 'normal', null, 'Does not open the HR menu by itself.', true);

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function moduleOrder(): array
    {
        return [
            'leads' => 'Leads',
            'listings' => 'Listings',
            'listing_setup' => 'Listing setup',
            'people' => 'People',
            'access' => 'Access control',
            'hr' => 'HR',
            'marketing' => 'Marketing',
            'announcements' => 'Announcements',
            'system' => 'System',
            'unmapped' => 'Unmapped permissions',
        ];
    }

    private function moduleIdForPrefix(string $prefix): string
    {
        return match ($prefix) {
            'leads', 'sources', 'stages' => 'leads',
            'listings' => 'listings',
            'developers', 'owners', 'property_types', 'features', 'projects', 'unit_views', 'layout_types', 'areas' => 'listing_setup',
            'users' => 'people',
            'roles' => 'access',
            'employees', 'departments', 'designations', 'branches', 'jobs', 'announcements' => $prefix === 'announcements' ? 'announcements' : 'hr',
            default => 'unmapped',
        };
    }

    private function moduleSummary(string $id): string
    {
        return match ($id) {
            'leads' => 'Menu access, lead API actions, and the separate branch-scope flag. Which records appear still depends on role name and the reporting tree.',
            'listings' => 'Listing actions. Published inventory is broader than the listings-list checkbox.',
            'listing_setup' => 'Developers, owners, projects, and other listing reference data.',
            'people' => 'Agent accounts. Who can see whom follows the org tree, not only these checkboxes.',
            'access' => 'Who can create roles and who can attach a role to a user.',
            'hr' => 'Employee and recruitment tools. The HR menu itself is role and user-id based.',
            'marketing' => 'Marketing workspace and photo-shoot requests. Admin does not bypass these.',
            'announcements' => 'Announcement API keys. The management screen is additionally limited to super_admin and user 33.',
            'system' => 'Settings and animation control. Several settings pages ignore these keys and check the role name.',
            default => 'These permission keys exist in the database and are not mapped to a verified module yet.',
        };
    }

    private function sidebarFor(string $key): ?string
    {
        return match ($key) {
            'leads-list', 'leads-create' => null,
            'listings-list' => 'CRM → Listings entries that declare listings-list',
            'listings-create' => 'CRM → Create Listing',
            'developers-list' => 'CRM → Developers List',
            'developers-create' => 'CRM → Add Developer',
            'owners-list' => 'CRM → Owners List',
            'owners-create' => 'CRM → Add Owner',
            'property_types-list' => 'CRM → Property Types List',
            'property_types-create' => 'CRM → Add Property Type',
            'features-list' => 'CRM → Features List',
            'features-create' => 'CRM → Add Feature',
            'projects-list' => 'CRM → Projects List',
            'unit_views-list' => 'CRM → Unit Views List',
            'unit_views-create' => 'CRM → Add Unit View',
            'layout_types-list' => 'CRM → Layout Types List',
            'layout_types-create' => 'CRM → Add Layout Type',
            'areas-list' => 'CRM → Areas List',
            'areas-create' => 'CRM → Add Area',
            'users-list' => 'Agents → Agents List',
            'users-create' => 'Agents → Add Agent',
            'roles-list' => 'Settings → Role & Access',
            'roles-create' => 'Settings → Add Role',
            default => null,
        };
    }

    /**
     * Short, non-technical facts for the role builder. Access is guaranteed,
     * conditional, still restricted, or not verified. This is not consulted by Gates.
     *
     * @return array{access: string, points: array<int, string>}
     */
    private function facts(string $key, bool $verified): array
    {
        $special = $this->specialFacts()[$key] ?? null;
        if ($special !== null) {
            return $special;
        }

        if (preg_match('/^([a-z_]+)-(list|create|edit|delete)$/', $key, $match) === 1) {
            return $this->crudFacts($match[1], $match[2]);
        }

        return [
            'access' => 'unverified',
            'points' => $verified
                ? ['Not verified. This permission is stored, but its exact screen and data effect was not confirmed.', 'Do not read it as access to every lead, listing, or employee.']
                : ['Not verified. This permission is stored, but no confirmed screen or data rule was found.', 'Selecting it does not prove the employee can open a page or a record.'],
        ];
    }

    /**
     * @return array<string, array{access: string, points: array<int, string>}>
     */
    private function specialFacts(): array
    {
        $leadScope = 'Own leads, a team leader’s team, a branch, or the whole company still follow the role name and reporting line.';

        return [
            'show-leads' => ['access' => 'conditional', 'points' => [
                'Shows Leads and Deals in the menu and opens those boards.',
                'Does not decide which lead or deal records appear.',
                'Opening a lead still needs View Leads.',
                'Admin and super admin see this menu without this permission.',
            ]],
            'show-branch-leads' => ['access' => 'conditional', 'points' => [
                'Shows leads across the employee’s branch.',
                'Does not turn the role into a branch admin.',
                'Deals stay on the role-name rule and do not widen with this permission.',
                'It is not a menu item.',
            ]],
            'leads-list' => ['access' => 'conditional', 'points' => [
                'Open the lead list, one lead, and lead export.',
                'The Leads menu is a separate permission.',
                $leadScope,
                'This does not show every lead in the company.',
            ]],
            'leads-create' => ['access' => 'allowed', 'points' => [
                'Create a lead when the form is valid.',
                'Does not widen which existing leads they can see.',
            ]],
            'leads-edit' => ['access' => 'conditional', 'points' => [
                'Edit a lead they can already open, including the responsible person.',
                'Assignment stays inside their current visibility unless the role is admin or super admin.',
                $leadScope,
            ]],
            'leads-delete' => ['access' => 'conditional', 'points' => [
                'Delete a lead they are allowed to open.',
                'Admin and super admin can also delete without this permission.',
            ]],
            'listings-list' => ['access' => 'conditional', 'points' => [
                'Open a listing’s details.',
                'Published, approved listings can appear on the main grid for any signed-in employee.',
                'Unpublished listings stay with the owner, admins, or a listing-team manager or team leader over their team.',
                'Seeing Listings in the menu does not open every listing.',
            ]],
            'listings-create' => ['access' => 'allowed', 'points' => [
                'Create a listing when the required fields pass.',
                'Existing approval and ownership rules still apply after it is created.',
            ]],
            'listings-edit' => ['access' => 'conditional', 'points' => [
                'Edit a listing they own.',
                'Editing someone else’s listing still depends on listing-team membership and the role name.',
                'This does not publish or approve every listing.',
            ]],
            'listings-delete' => ['access' => 'conditional', 'points' => [
                'Delete a listing they are already allowed to edit.',
                'Ownership, admin, and listing-team checks still apply.',
            ]],
            'listings-assign' => ['access' => 'conditional', 'points' => [
                'Assign a listing agent when the existing assignment rules already allow it.',
                'The permission alone does not override team or role limits.',
            ]],
            'stages-list' => ['access' => 'restricted', 'points' => [
                'Viewing the pipeline stage list is not locked by this permission.',
                'Create, edit, and delete stage permissions are the ones that are enforced.',
            ]],
            'users-list' => ['access' => 'conditional', 'points' => [
                'Shows the agent list in the menu.',
                'Super admin can see everyone. A branch admin sees the branch. Others see themselves and people below them.',
                'Opening one profile still follows that same visibility.',
            ]],
            'users-create' => ['access' => 'allowed', 'points' => [
                'Create an agent account when the form is valid.',
                'New agents receive the leads menu unless they sit under a listing team.',
            ]],
            'users-edit' => ['access' => 'restricted', 'points' => [
                'The edit screen checks this permission.',
                'Saving an agent does not. Save follows the role and reporting line.',
                'Admin, branch admin, the person themself, a manager over two levels, or a team leader over direct reports can save.',
            ]],
            'users-delete' => ['access' => 'conditional', 'points' => [
                'Delete an agent account.',
                'They can only delete people their role and reporting line already allow.',
            ]],
            'users-code' => ['access' => 'conditional', 'points' => [
                'Shows biometric codes on the agent list.',
                'Also removes the subordinate-only filter, so the list is company-wide.',
                'One existing account can see that full list without this permission.',
            ]],
            'roles-list' => ['access' => 'conditional', 'points' => [
                'Shows Role & Access in Settings.',
                'The page itself opens for any signed-in employee who knows the address.',
                'Protected built-in roles can be changed only by super admin.',
            ]],
            'roles-create' => ['access' => 'conditional', 'points' => [
                'Create a role and attach any existing permission.',
                'There is no ceiling on what a role editor can grant.',
            ]],
            'roles-edit' => ['access' => 'conditional', 'points' => [
                'Edit a role and change its permissions.',
                'Protected built-in roles can be changed only by super admin.',
            ]],
            'roles-delete' => ['access' => 'conditional', 'points' => [
                'Delete a role.',
                'Protected built-in roles can be removed only by super admin.',
            ]],
            'roles-assign' => ['access' => 'allowed', 'points' => [
                'Replace an employee’s role from the user form.',
                'This is separate from the role-assignment action used on the role screen.',
            ]],
            'assign-role' => ['access' => 'allowed', 'points' => [
                'Assign, remove, or replace roles through the role tools.',
                'The user form checks a different permission.',
            ]],
            'marketing' => ['access' => 'conditional', 'points' => [
                'Open the Marketing workspace.',
                'Admin does not get this automatically. Super admin does.',
                'Deleting marketing stages still needs an admin or super admin role.',
            ]],
            'photo-booking' => ['access' => 'conditional', 'points' => [
                'Open photo-shoot requests.',
                'The employee sees their own requests.',
                'Admin does not get this automatically.',
            ]],
            'user-animation-control' => ['access' => 'allowed', 'points' => [
                'Open animation control.',
                'Admin does not get this automatically. Super admin does.',
            ]],
            'settings-view' => ['access' => 'unverified', 'points' => [
                'Not verified. No settings screen was found that opens only because of this permission.',
                'Most settings pages check the admin or super admin role instead.',
            ]],
            'settings-update' => ['access' => 'conditional', 'points' => [
                'Update deal-cost settings when the page is already open.',
                'The page itself still requires an admin role.',
            ]],
            'view-agent-attendance' => ['access' => 'conditional', 'points' => [
                'See attendance on an agent profile they can already open.',
                'A direct manager and an admin can see it without this permission.',
            ]],
            'view-agent-performance' => ['access' => 'conditional', 'points' => [
                'See performance on an agent profile they can already open.',
                'A direct manager and an admin can see it without this permission.',
            ]],
            'view-employee-balance' => ['access' => 'conditional', 'points' => [
                'Open an employee’s leave balance.',
                'This does not show the HR menu. That menu follows the HR role, super admin, or one specific account.',
            ]],
            'applicants-list' => ['access' => 'conditional', 'points' => [
                'View recruitment applicants.',
                'The HR area itself still requires the HR role, admin, or super admin.',
            ]],
            'applicants-status-edit' => ['access' => 'conditional', 'points' => [
                'Change an applicant’s status.',
                'The HR area itself still requires the HR role, admin, or super admin.',
            ]],
            'interviews-list' => ['access' => 'conditional', 'points' => [
                'View scheduled interviews.',
                'The HR area itself still requires the HR role, admin, or super admin.',
            ]],
            'interviews-create' => ['access' => 'conditional', 'points' => [
                'Schedule an interview.',
                'The HR area itself still requires the HR role, admin, or super admin.',
            ]],
            'interviews-edit' => ['access' => 'conditional', 'points' => [
                'Edit an interview.',
                'Removing an interview uses this same permission.',
            ]],
            'recruitment-list' => ['access' => 'conditional', 'points' => [
                'View recruitment totals.',
                'This does not open the HR menu by itself.',
            ]],
        ];
    }

    /**
     * @return array{access: string, points: array<int, string>}
     */
    private function crudFacts(string $prefix, string $action): array
    {
        $nouns = [
            'sources' => 'lead source',
            'developers' => 'developer',
            'owners' => 'owner',
            'property_types' => 'property type',
            'features' => 'listing feature',
            'projects' => 'project',
            'unit_views' => 'unit view',
            'layout_types' => 'layout type',
            'areas' => 'area',
            'employees' => 'employee profile',
            'departments' => 'department',
            'designations' => 'designation',
            'branches' => 'company branch',
            'jobs' => 'job post',
            'announcements' => 'announcement',
            'stages' => 'pipeline stage',
        ];
        $noun = $nouns[$prefix] ?? str_replace('_', ' ', $prefix);

        if ($prefix === 'announcements') {
            $menu = 'The management screen stays limited to super admin and one specific account.';
            return match ($action) {
                'list' => ['access' => 'conditional', 'points' => ["View announcement records.", $menu]],
                'create' => ['access' => 'conditional', 'points' => ['Create an announcement when the form is valid.', $menu]],
                'edit' => ['access' => 'conditional', 'points' => ['Edit an announcement.', $menu]],
                default => ['access' => 'conditional', 'points' => ['Delete an announcement.', $menu]],
            };
        }

        if (in_array($prefix, ['employees', 'departments', 'designations', 'branches'], true)) {
            $hr = 'Admin, super admin, and the HR role can also read these records.';
            return match ($action) {
                'list' => ['access' => 'conditional', 'points' => ["Open one {$noun}.", $hr, 'The HR menu itself is not granted by this permission.']],
                'create' => ['access' => 'allowed', 'points' => ["Add a {$noun} when the form is valid.", 'This does not open the HR menu.']],
                'edit' => ['access' => 'allowed', 'points' => ["Edit a {$noun}.", 'This does not open the HR menu.']],
                default => ['access' => 'allowed', 'points' => ["Delete a {$noun}.", 'This does not open the HR menu.']],
            };
        }

        if ($action === 'list') {
            $points = [
                "Open one {$noun}.",
                'The full list can still load without this permission.',
                'It does not change which leads, deals, or property listings they can see.',
            ];
            if ($prefix === 'areas') {
                $points = [
                    'Open one area, including its type and child areas.',
                    'The full area list can still load without this permission.',
                ];
            }
            if ($prefix === 'projects') {
                $points[] = 'Admin can pass the page check, but opening one project still needs this permission.';
            }

            return ['access' => 'conditional', 'points' => array_slice($points, 0, 4)];
        }

        if ($action === 'create') {
            return ['access' => 'allowed', 'points' => [
                "Add a {$noun} when the form is valid.",
                'This does not grant access to leads or property listings.',
            ]];
        }

        if ($action === 'edit') {
            return ['access' => 'allowed', 'points' => [
                "Edit a {$noun}.",
                'This does not grant access to leads or property listings.',
            ]];
        }

        return ['access' => 'allowed', 'points' => [
            "Delete a {$noun}.",
            'This does not grant access to leads or property listings.',
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(
        string $moduleId,
        string $moduleLabel,
        string $moduleSummary,
        string $label,
        string $description,
        string $type,
        string $sensitivity,
        ?string $sidebar,
        string $limitations,
        bool $verified
    ): array {
        return compact(
            'moduleId',
            'moduleLabel',
            'moduleSummary',
            'label',
            'description',
            'type',
            'sensitivity',
            'sidebar',
            'limitations',
            'verified'
        ) + [
            'module_id' => $moduleId,
            'module_label' => $moduleLabel,
            'module_summary' => $moduleSummary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unmapped(string $name): array
    {
        return $this->entry(
            'unmapped',
            'Unmapped permissions',
            $this->moduleSummary('unmapped'),
            $name,
            'This key exists in the database. It is not mapped to a verified screen or API check in the catalog, so selecting it must not be read as a confirmed capability.',
            'unmapped',
            'normal',
            null,
            'Left visible so it cannot disappear. Confirm its effect in code before relying on it.',
            false
        );
    }

    /**
     * @return array<string, string>
     */
    private function warning(string $id, string $level, string $title, string $detail): array
    {
        return compact('id', 'level', 'title', 'detail');
    }
}
