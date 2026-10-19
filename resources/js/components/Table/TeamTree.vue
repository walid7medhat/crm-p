<template>
    <div class="org-chart-page">
        <header class="org-toolbar">
            <div class="org-toolbar__tools">
                <div class="org-search" :class="{ 'is-open': searchText }">
                    <iconify-icon icon="lucide:search" width="16"></iconify-icon>
                    <input
                        v-model="searchText"
                        type="search"
                        placeholder="Find someone"
                        aria-label="Find an employee"
                        autocomplete="off"
                        @keydown.down.prevent="moveSearch(1)"
                        @keydown.up.prevent="moveSearch(-1)"
                        @keydown.enter.prevent="chooseSearch(searchMatches[searchIndex] || searchMatches[0])"
                        @keydown.esc="clearSearch"
                    >
                    <button v-if="searchText" type="button" class="org-search__clear" aria-label="Clear search" @click="clearSearch">
                        <iconify-icon icon="lucide:x" width="14"></iconify-icon>
                    </button>
                    <div v-if="searchText" class="org-search__panel">
                        <p class="org-search__meta">{{ searchMatches.length }} {{ searchMatches.length === 1 ? 'match' : 'matches' }}</p>
                        <ul v-if="searchMatches.length" class="org-search__list">
                            <li v-for="(person, index) in searchMatches" :key="person.id">
                                <button
                                    type="button"
                                    :class="{ 'is-active': index === searchIndex }"
                                    @mouseenter="searchIndex = index"
                                    @click="chooseSearch(person)"
                                >
                                    <span class="org-search__avatar" :style="{ '--accent': branchColor(person.branch_name) }">{{ initials(person.name) }}</span>
                                    <span class="org-search__copy">
                                        <strong v-html="highlight(person.name)"></strong>
                                        <small>
                                            {{ person.role_name || 'Team member' }}
                                            <template v-if="person.branch_name"> · {{ person.branch_name }}</template>
                                        </small>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p v-else class="org-search__empty">No matching people</p>
                    </div>
                </div>
                <div class="org-legend" aria-hidden="true">
                    <span><i style="background:#733E87"></i>Dubai</span>
                    <span><i style="background:#9A7420"></i>Abu Dhabi</span>
                </div>
                <select v-model="focusBranchId" class="org-select" aria-label="Focus branch">
                    <option value="">All branches</option>
                    <option v-for="branch in branchOptions" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                </select>
                <div class="org-zoom" role="group" aria-label="Chart zoom">
                    <button type="button" title="Zoom out" @click="changeZoom(-0.1)"><iconify-icon icon="lucide:minus" width="15"></iconify-icon></button>
                    <span>{{ Math.round(zoom * 100) }}%</span>
                    <button type="button" title="Zoom in" @click="changeZoom(0.1)"><iconify-icon icon="lucide:plus" width="15"></iconify-icon></button>
                    <button type="button" title="Fit to screen" @click="fitToScreen">Fit</button>
                    <button type="button" title="Center view" @click="centerView">Center</button>
                </div>
                <router-link to="/users" class="org-link">Team table</router-link>
                <button type="button" class="org-drawer-toggle" @click="openUnassigned">
                    Unassigned
                    <span>{{ unassigned.length }}</span>
                </button>
            </div>
        </header>

        <div class="org-body">
            <div
                ref="viewport"
                class="org-viewport"
                :class="{ 'is-panning': panning }"
                @dragover="onViewportDragOver"
                @wheel="onWheel"
                @pointerdown="onPanStart"
                @pointermove="onPanMove"
                @pointerup="onPanEnd"
                @pointercancel="onPanEnd"
            >
                <div v-if="loading" class="org-skeleton" aria-hidden="true">
                    <div class="org-skeleton__card"></div>
                    <div class="org-skeleton__row">
                        <div class="org-skeleton__card"></div>
                        <div class="org-skeleton__card"></div>
                        <div class="org-skeleton__card"></div>
                    </div>
                </div>
                <div v-else-if="!visibleRoots.length" class="org-empty">
                    <iconify-icon icon="lucide:network" width="36"></iconify-icon>
                    <p>No organization chart to show</p>
                </div>
                <div v-else ref="canvas" class="org-canvas" :style="{ zoom }">
                    <div class="org-forest">
                        <TeamTreeNode
                            v-for="root in visibleRoots"
                            :key="root.id"
                            :node="root"
                            accent="#1e3a78"
                            :current-user-id="Number(currentUser.id) || 0"
                            :highlighted-id="highlightedId"
                            :dragging-id="dragEmployee ? dragEmployee.id : 0"
                            :collapsed-map="collapsed"
                            :selected-id="salesScope ? Number(salesScope.id) : 0"
                            :can-drag="canDrag"
                            :can-drop-on="canDropOn"
                            @toggle="toggleNode"
                            @open="openPerson"
                            @drag-start="onDragStart"
                            @drag-end="onDragEnd"
                            @drop="onDrop"
                        />
                    </div>
                </div>
            </div>

            <aside v-if="salesScope" class="org-sales" :class="{ 'is-min': salesMinimized }">
                <header class="org-sales__head">
                    <div class="org-sales__who">
                        <p class="org-sales__kicker">Sales under</p>
                        <strong>{{ salesScope.name }}</strong>
                        <small>{{ salesScope.role_name || 'Team member' }} · {{ scopedSales.length }}</small>
                    </div>
                    <div class="org-sales__tools">
                        <button type="button" class="org-icon-btn" :title="salesMinimized ? 'Expand list' : 'Minimize list'" @click="salesMinimized = !salesMinimized">
                            <iconify-icon :icon="salesMinimized ? 'lucide:chevron-up' : 'lucide:chevron-down'" width="16"></iconify-icon>
                        </button>
                        <button type="button" class="org-icon-btn" title="Close" aria-label="Close sales list" @click="closeSalesScope">
                            <iconify-icon icon="lucide:x" width="16"></iconify-icon>
                        </button>
                    </div>
                </header>
                <div v-show="!salesMinimized" class="org-sales__body">
                    <input v-model="salesQuery" type="search" placeholder="Search these agents" aria-label="Search sales agents">
                    <p v-if="!filteredScopedSales.length" class="org-sales__empty">
                        <template v-if="scopedSales.length">No one matches this search.</template>
                        <template v-else>No sales agents report under this person.</template>
                    </p>
                    <ul v-else class="org-sales__list">
                        <li v-for="agent in filteredScopedSales" :key="agent.id">
                            <button type="button" :style="{ paddingLeft: (8 + Math.min(agent.depth, 4) * 10) + 'px' }" @click="openSalesScope(agent)">
                                <span class="org-sales__avatar">{{ initials(agent.name) }}</span>
                                <span class="org-sales__copy">
                                    <strong>{{ agent.name }}</strong>
                                    <small>
                                        {{ agent.role_name || 'Sales' }}
                                        <template v-if="agent.parent_name"> · {{ agent.parent_name }}</template>
                                    </small>
                                </span>
                            </button>
                        </li>
                    </ul>
                    <button v-if="canViewProfile" type="button" class="org-sales__profile" @click="viewProfile(salesScope)">View profile</button>
                </div>
            </aside>

            <aside v-show="drawerOpen" class="org-drawer" :class="{ 'is-open': drawerOpen }">
                <div class="org-drawer__head">
                    <div>
                        <p class="org-drawer__title">Unassigned <span>{{ filteredUnassigned.length }}</span></p>
                    </div>
                    <button type="button" class="org-icon-btn" :title="drawerOpen ? 'Collapse drawer' : 'Expand drawer'" @click="drawerOpen = !drawerOpen">
                        <iconify-icon :icon="drawerOpen ? 'lucide:panel-right-close' : 'lucide:panel-right-open'" width="18"></iconify-icon>
                    </button>
                </div>
                <div v-show="drawerOpen" class="org-drawer__filters">
                    <input v-model="drawerSearch" type="search" placeholder="Search unassigned" aria-label="Search unassigned employees">
                    <div class="org-drawer__selects">
                        <select v-model="drawerBranch" aria-label="Filter by branch">
                            <option value="">All branches</option>
                            <option v-for="branch in unassignedBranches" :key="branch" :value="branch">{{ branch }}</option>
                        </select>
                        <select v-model="drawerRole" aria-label="Filter by role">
                            <option value="">All roles</option>
                            <option v-for="role in unassignedRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>
                </div>
                <div v-show="drawerOpen" class="org-drawer__list">
                    <p v-if="!filteredUnassigned.length" class="org-drawer__empty">
                        <template v-if="unassigned.length">No one matches these filters.</template>
                        <template v-else>Everyone in your view is already on a team.</template>
                    </p>
                    <article
                        v-for="person in filteredUnassigned"
                        :key="person.id"
                        class="pool-card"
                        :class="{ 'is-dragging': dragEmployee && dragEmployee.id === person.id }"
                        :draggable="canDrag(person) ? 'true' : 'false'"
                        @dragstart="onPoolDragStart($event, person)"
                        @dragend="onDragEnd"
                        @click="onPoolClick(person)"
                    >
                        <div class="pool-card__avatar">{{ initials(person.name) }}</div>
                        <div>
                            <strong>{{ person.name }}</strong>
                            <small>{{ person.role_name || 'Team member' }}</small>
                            <small v-if="person.branch_name">{{ person.branch_name }}</small>
                        </div>
                    </article>
                </div>
            </aside>
        </div>

        <div v-if="person" class="org-modal" @click.self="person = null">
            <div class="org-dialog org-dialog--person" role="dialog" aria-modal="true" :aria-label="person.name">
                <button type="button" class="org-icon-btn org-dialog__close" @click="person = null" aria-label="Close">
                    <iconify-icon icon="lucide:x" width="16"></iconify-icon>
                </button>
                <div class="org-dialog__head">
                    <div class="org-dialog__photo">{{ initials(person.name) }}</div>
                    <div class="org-dialog__identity">
                        <p class="org-dialog__name">{{ person.name }}</p>
                        <p class="org-dialog__role">{{ person.role_name || 'Team member' }}</p>
                    </div>
                </div>
                <dl>
                    <template v-if="person.email"><dt>Email</dt><dd>{{ person.email }}</dd></template>
                    <template v-if="person.phone"><dt>Phone</dt><dd>{{ person.phone }}</dd></template>
                    <template v-if="person.branch_name"><dt>Branch</dt><dd>{{ person.branch_name }}</dd></template>
                    <template v-if="person.parent_name"><dt>Reports to</dt><dd>{{ person.parent_name }}</dd></template>
                    <dt>Status</dt>
                    <dd>
                        <span class="org-status" :class="person.status === 'in_active' ? 'is-off' : 'is-on'">
                            {{ person.status === 'in_active' ? 'Inactive' : (person.status || 'Active') }}
                        </span>
                    </dd>
                </dl>
                <button v-if="canViewProfile" type="button" class="org-primary" @click="viewProfile(person)">View profile</button>
            </div>
        </div>

        <div v-if="picker" class="org-modal" @click.self="picker = null">
            <div class="org-dialog org-dialog--wide" role="dialog" aria-modal="true" aria-label="Choose a team">
                <button type="button" class="org-icon-btn org-dialog__close" @click="picker = null" aria-label="Close">
                    <iconify-icon icon="lucide:x" width="18"></iconify-icon>
                </button>
                <p class="org-dialog__name">Choose {{ picker.manager.name }}'s team</p>
                <p class="org-dialog__role">{{ picker.employee.name }} can join one of these teams.</p>
                <div class="org-picker">
                    <button v-for="team in picker.teams" :key="team.id" type="button" @click="commitAssign(picker.employee, team)">
                        <strong>{{ team.name }}</strong>
                        <small>{{ team.role_name }} · {{ (team.children || []).length }} people</small>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="toast" class="org-toast" :class="'is-' + toast.type" role="status">
            <span>{{ toast.message }}</span>
            <button v-if="toast.undo" type="button" @click="undoAssign">Undo</button>
            <button type="button" aria-label="Dismiss" @click="toast = null">
                <iconify-icon icon="lucide:x" width="14"></iconify-icon>
            </button>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import TeamTreeNode from './TeamTreeNode.vue';

const LEADERS = ['super_admin', 'admin', 'manager', 'team_lead'];

function roleKey(user) {
    if (!user) return '';
    if (user.role_key) return String(user.role_key).toLowerCase();
    const roles = Array.isArray(user.roles) ? user.roles : [];
    if (roles.length && typeof roles[0] === 'string') return String(roles[0]).toLowerCase();
    return String(user.role_name || '').toLowerCase().replace(/\s+/g, '_');
}

function isLeader(user) {
    return LEADERS.includes(roleKey(user));
}

function isManagerRole(user) {
    const key = roleKey(user);
    return key === 'manager' || key.endsWith('_manager');
}

function isTeamLeadRole(user) {
    const key = roleKey(user);
    return key === 'team_lead' || key === 'team lead' || key.includes('team_lead');
}

function isSalesRole(user) {
    const key = roleKey(user);
    return key === 'sales' || key === 'sale';
}
function hasNoRole(user) {
    return !roleKey(user);
}

function isTreeRole(user) {
    const key = roleKey(user);
    return isSalesRole(user) || key === 'admin' || key === 'super_admin' || isManagerRole(user) || isTeamLeadRole(user);
}

function isActiveUser(user) {
    return String(user?.status || 'active').toLowerCase() === 'active';
}

function isAdminRole(user) {
    return roleKey(user) === 'admin';
}

function isDestinationRole(user) {
    return isAdminRole(user) || isManagerRole(user) || isTeamLeadRole(user);
}
const COMPANY = '#0B0736';
const DUBAI = '#733E87';
const ABU_DHABI = '#9A7420';

function branchColor(name) {
    const label = String(name || '').toLowerCase();
    if (label.includes('dubai')) return DUBAI;
    if (label.includes('abu dhabi') || label.includes('abu-dhabi') || label.includes('abudhabi')) return ABU_DHABI;
    return COMPANY;
}

function paintBranch(node, accent) {
    node.accent = accent;
    (node.children || []).forEach((child) => paintBranch(child, accent));
}

export default {
    name: 'TeamTree',
    components: { TeamTreeNode },
    data() {
        return {
            loading: true,
            allUsers: [],
            currentUser: {},
            canAssign: false,
            searchText: '',
            searchIndex: 0,
            highlightedId: 0,
            focusBranchId: '',
            zoom: 1,
            panning: false,
            drawerOpen: false,
            drawerSearch: '',
            drawerBranch: '',
            drawerRole: '',
            collapsed: {},
            salesScope: null,
            salesMinimized: false,
            salesQuery: '',
            dragEmployee: null,
            poolDragged: false,
            assigning: false,
            person: null,
            picker: null,
            toast: null,
            toastTimer: null,
        };
    },
    computed: {
        byId() {
            return new Map(this.allUsers.map((user) => [Number(user.id), user]));
        },
        chartPeople() {
            return this.allUsers.filter((user) => this.inChart(user));
        },
        unassigned() {
            // return this.allUsers.filter((user) => isActiveUser(user) && isSalesRole(user) && !this.inChart(user));
                return this.allUsers.filter((user) => (isSalesRole(user) || hasNoRole(user)) && !this.inChart(user));
        },
        roots() {
            const nodes = new Map(this.chartPeople.map((user) => [Number(user.id), { ...user, children: [] }]));
            const roots = [];
            nodes.forEach((node) => {
                const parentId = Number(node.parent_id);
                if (parentId && nodes.has(parentId)) nodes.get(parentId).children.push(node);
                else roots.push(node);
            });
            const sortNodes = (list) => {
                list.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
                list.forEach((node) => sortNodes(node.children));
            };
            sortNodes(roots);
            roots.forEach((root) => {
                if (roleKey(root) === 'super_admin') {
                    root.accent = COMPANY;
                    (root.children || []).forEach((child) => paintBranch(child, branchColor(child.name)));
                } else {
                    paintBranch(root, branchColor(root.name));
                }
            });
            return roots;
        },
        visibleRoots() {
            if (!this.focusBranchId) return this.roots;
            const match = this.findNode(this.roots, Number(this.focusBranchId));
            return match ? [match] : this.roots;
        },
        branchOptions() {
            const map = new Map();
            this.allUsers.forEach((user) => {
                if (user.branch_id && user.branch_name) map.set(user.branch_id, user.branch_name);
            });
            return [...map.entries()].map(([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
        },
        searchMatches() {
            const query = this.searchText.trim().toLowerCase();
            if (!query) return [];
            const score = (user) => {
                const name = String(user.name || '').toLowerCase();
                const email = String(user.email || '').toLowerCase();
                if (name.startsWith(query)) return 0;
                if (name.split(/\s+/).some((part) => part.startsWith(query))) return 1;
                if (name.includes(query)) return 2;
                if (email.startsWith(query)) return 3;
                return 4;
            };
            return this.allUsers.filter((user) => {
                return [user.name, user.email, user.role_name, user.branch_name, user.parent_name]
                    .some((value) => String(value || '').toLowerCase().includes(query));
            }).sort((a, b) => score(a) - score(b) || String(a.name).localeCompare(String(b.name))).slice(0, 10);
        },
        unassignedBranches() {
            return [...new Set(this.unassigned.map((user) => user.branch_name).filter(Boolean))].sort();
        },
        unassignedRoles() {
            return [...new Set(this.unassigned.map((user) => user.role_name).filter(Boolean))].sort();
        },
        childrenByParent() {
            const map = new Map();
            this.allUsers.forEach((user) => {
                const parentId = Number(user.parent_id);
                if (!parentId) return;
                if (!map.has(parentId)) map.set(parentId, []);
                map.get(parentId).push(user);
            });
            return map;
        },
        scopedSales() {
            if (!this.salesScope) return [];
            const map = this.childrenByParent;
            const rows = [];
            const seen = new Set();
            const walk = (id, depth) => {
                (map.get(Number(id)) || []).forEach((child) => {
                    if (seen.has(child.id)) return;
                    seen.add(child.id);
                    const nextDepth = depth + 1;
                    if (isSalesRole(child) || hasNoRole(child)) rows.push({ ...child, depth });
                    walk(child.id, nextDepth);
                });
            };
            walk(this.salesScope.id, 0);
            return rows.sort((a, b) => a.depth - b.depth || String(a.name).localeCompare(String(b.name)));
        },
        filteredScopedSales() {
            const query = this.salesQuery.trim().toLowerCase();
            if (!query) return this.scopedSales;
            return this.scopedSales.filter((user) => {
                return [user.name, user.email, user.role_name, user.parent_name, user.branch_name]
                    .some((value) => String(value || '').toLowerCase().includes(query));
            });
        },
        filteredUnassigned() {
            const query = this.drawerSearch.trim().toLowerCase();
            return this.unassigned.filter((user) => {
                if (this.drawerBranch && user.branch_name !== this.drawerBranch) return false;
                if (this.drawerRole && user.role_name !== this.drawerRole) return false;
                if (!query) return true;
                return [user.name, user.email, user.role_name, user.branch_name]
                    .some((value) => String(value || '').toLowerCase().includes(query));
            });
        },
        canViewProfile() {
            const roles = Array.isArray(this.currentUser.roles) ? this.currentUser.roles : [];
            if (roles.includes('super_admin') || roles.includes('admin')) return true;
            return !this.$hasPermission || this.$hasPermission('users-list');
        },
        actorIsAdmin() {
            const roles = (Array.isArray(this.currentUser.roles) ? this.currentUser.roles : []).map((role) => {
                return String(typeof role === 'string' ? role : role?.name || '').toLowerCase();
            });
            return roles.includes('super_admin') || roles.includes('admin');
        },
    },
    mounted() {
        const stored = localStorage.getItem('user');
        if (stored) {
            try { this.currentUser = JSON.parse(stored) || {}; } catch (error) { this.currentUser = {}; }
        }
        this.fetchTeamData();
    },
    watch: {
        searchText() {
            this.searchIndex = 0;
        },
    },
    beforeUnmount() {
        clearTimeout(this.toastTimer);
        clearTimeout(this.highlightTimer);
    },
    methods: {
        inChart(user) {
            if (!isTreeRole(user)) return false;
            if (isLeader(user) || isManagerRole(user) || isTeamLeadRole(user)) return true;
            const parent = this.byId.get(Number(user.parent_id));
            if (!parent) return false;
            return isDestinationRole(parent);
        },
        canDrag(user) {
            return this.canAssign && !!user && !isLeader(user) && !this.assigning;
        },
        canDropOn(node) {
            if (!this.dragEmployee || !node || Number(node.id) === Number(this.dragEmployee.id)) return false;
            return !!this.resolveDestination(node);
        },
        resolveDestination(node) {
            if (isDestinationRole(node)) return { type: 'direct', parent: node };
            const parent = this.byId.get(Number(node.parent_id));
            if (parent && isDestinationRole(parent)) {
                const treeParent = this.findNode(this.roots, Number(parent.id)) || parent;
                return this.resolveDestination(treeParent);
            }
            return null;
        },
        sameBranchAllowed(employee, parent) {
            if (this.actorIsAdmin) return true;
            const employeeBranch = employee.branch_id || null;
            const parentBranch = parent.branch_id || null;
            const actor = this.byId.get(Number(this.currentUser.id));
            const actorBranch = actor?.branch_id || null;
            if (employeeBranch && parentBranch && Number(employeeBranch) !== Number(parentBranch)) return false;
            if (actorBranch && parentBranch && Number(actorBranch) !== Number(parentBranch)) return false;
            if (actorBranch && employeeBranch && Number(actorBranch) !== Number(employeeBranch)) return false;
            return true;
        },
        async fetchTeamData() {
            this.loading = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.TEAM_HIERARCHY, {
                    headers: {
                        Authorization: 'Bearer ' + token,
                        Accept: 'application/json',
                    },
                });
                if (!response.ok) throw new Error('Hierarchy request failed');
                const payload = await response.json();
                this.applyPayload(payload);
            } catch (error) {
                console.error(error);
                this.notify('Could not load the organization chart.', 'error');
            } finally {
                this.loading = false;
                this.$nextTick(() => this.fitToScreen());
            }
        },
        applyPayload(payload) {
            const rows = Array.isArray(payload?.data) ? payload.data : [];
            this.allUsers = rows.map((user) => ({
                id: Number(user.id),
                name: user.name || 'Unnamed',
                email: user.email || '',
                phone: user.phone || '',
                avatar: user.avatar || null,
                status: user.status || 'active',
                parent_id: user.parent_id ? Number(user.parent_id) : null,
                parent_name: user.parent_name || null,
                role_name: user.role_name || '',
                role_key: user.role_key || '',
                role_id: user.role_id || null,
                branch_id: user.branch_id ? Number(user.branch_id) : null,
                branch_name: user.branch_name || '',
                team_members_count: user.team_members_count || 0,
                last_login_at: user.last_login_at || null,
            }));
            if (typeof payload?.can_reassign === 'boolean') this.canAssign = payload.can_reassign;
            else this.canAssign = ['super_admin', 'admin', 'manager', 'team_lead', 'branch_admin'].includes(roleKey(this.currentUser));
            this.collapsed = {};
        },
        findNode(list, id) {
            for (const node of list || []) {
                if (Number(node.id) === Number(id)) return node;
                const found = this.findNode(node.children, id);
                if (found) return found;
            }
            return null;
        },
        openUnassigned() {
            if (this.drawerOpen) {
                this.drawerOpen = false;
                return;
            }
            this.drawerSearch = '';
            this.drawerBranch = '';
            this.drawerRole = '';
            this.drawerOpen = true;
        },
        toggleNode(id) {
            this.collapsed = { ...this.collapsed, [id]: !this.collapsed[id] };
        },
        expandAncestors(id) {
            const next = { ...this.collapsed };
            let current = this.byId.get(Number(id));
            const seen = new Set();
            while (current && current.parent_id && !seen.has(current.id)) {
                seen.add(current.id);
                delete next[Number(current.parent_id)];
                current = this.byId.get(Number(current.parent_id));
            }
            this.collapsed = next;
        },
        branchColor(name) {
            return branchColor(name);
        },
        clearSearch() {
            this.searchText = '';
            this.searchIndex = 0;
        },
        moveSearch(step) {
            const total = this.searchMatches.length;
            if (!total) return;
            this.searchIndex = (this.searchIndex + step + total) % total;
        },
        highlight(value) {
            const text = String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));
            const query = this.searchText.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            if (!query) return text;
            return text.replace(new RegExp(`(${query})`, 'ig'), '<mark>$1</mark>');
        },
        chooseSearch(person) {
            if (!person) return;
            this.clearSearch();
            if (!this.inChart(person)) {
                this.drawerOpen = true;
                const fresh = this.byId.get(Number(person.id)) || person;
                if (this.opensSalesSidebar(fresh)) this.openSalesScope(fresh);
                else this.person = fresh;
                return;
            }
            if (this.opensSalesSidebar(person)) this.openSalesScope(person);
            if (!this.findNode(this.visibleRoots, person.id)) this.focusBranchId = '';
            this.expandAncestors(person.id);
            this.highlightedId = Number(person.id);
            clearTimeout(this.highlightTimer);
            this.highlightTimer = setTimeout(() => {
                if (this.highlightedId === Number(person.id)) this.highlightedId = 0;
            }, 2600);
            this.$nextTick(() => {
                const card = this.$el.querySelector(`[data-person="${person.id}"]`);
                card?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'center' });
            });
        },
        clampZoom(value) {
            return Math.min(2, Math.max(0.08, Math.round(value * 100) / 100));
        },
        changeZoom(delta) {
            this.zoomAround(this.zoom + delta);
        },
        zoomAround(nextZoom, clientX, clientY) {
            const viewport = this.$refs.viewport;
            const previous = this.zoom || 1;
            const next = this.clampZoom(nextZoom);
            if (!viewport || next === previous) {
                this.zoom = next;
                return;
            }
            const rect = viewport.getBoundingClientRect();
            const originX = (clientX == null ? rect.left + rect.width / 2 : clientX) - rect.left;
            const originY = (clientY == null ? rect.top + rect.height / 2 : clientY) - rect.top;
            const contentX = viewport.scrollLeft + originX;
            const contentY = viewport.scrollTop + originY;
            const ratio = next / previous;
            this.zoom = next;
            this.$nextTick(() => {
                viewport.scrollLeft = contentX * ratio - originX;
                viewport.scrollTop = contentY * ratio - originY;
            });
        },
        onWheel(event) {
            if (!event.ctrlKey && !event.metaKey) return;
            event.preventDefault();
            this.zoomAround(this.zoom * Math.exp(-event.deltaY * 0.01), event.clientX, event.clientY);
        },
        onPanStart(event) {
            if (event.button !== 0) return;
            if (event.target.closest('.org-card, .org-toggle, button, a, input, select')) return;
            const viewport = this.$refs.viewport;
            if (!viewport) return;
            this.panning = true;
            this.panOriginX = event.clientX;
            this.panOriginY = event.clientY;
            this.panScrollLeft = viewport.scrollLeft;
            this.panScrollTop = viewport.scrollTop;
            viewport.setPointerCapture(event.pointerId);
        },
        onPanMove(event) {
            if (!this.panning) return;
            const viewport = this.$refs.viewport;
            if (!viewport) return;
            viewport.scrollLeft = this.panScrollLeft - (event.clientX - this.panOriginX);
            viewport.scrollTop = this.panScrollTop - (event.clientY - this.panOriginY);
        },
        onPanEnd() {
            this.panning = false;
        },
        fitToScreen() {
            const viewport = this.$refs.viewport;
            const canvas = this.$refs.canvas;
            if (!viewport || !canvas) return;
            const apply = () => {
                const width = canvas.scrollWidth || canvas.offsetWidth || 1;
                const height = canvas.scrollHeight || canvas.offsetHeight || 1;
                const scale = Math.min(
                    (viewport.clientWidth - 16) / width,
                    (viewport.clientHeight - 16) / height,
                    1
                );
                this.zoom = this.clampZoom(scale);
                this.$nextTick(() => {
                    viewport.scrollLeft = Math.max(0, (viewport.scrollWidth - viewport.clientWidth) / 2);
                    viewport.scrollTop = Math.max(0, (viewport.scrollHeight - viewport.clientHeight) / 2);
                });
            };
            if (this.zoom !== 1) {
                this.zoom = 1;
                this.$nextTick(() => requestAnimationFrame(apply));
                return;
            }
            requestAnimationFrame(apply);
        },
        centerView() {
            this.$nextTick(() => {
                const viewport = this.$refs.viewport;
                const card = this.$el.querySelector('.org-forest .org-card');
                if (!viewport || !card) return;
                const viewRect = viewport.getBoundingClientRect();
                const cardRect = card.getBoundingClientRect();
                viewport.scrollLeft += cardRect.left - viewRect.left - (viewRect.width - cardRect.width) / 2;
                viewport.scrollTop = 0;
            });
        },
        onDragStart(user) {
            this.dragEmployee = user;
            this.person = null;
        },
        onPoolDragStart(event, person) {
            if (!this.canDrag(person)) {
                event.preventDefault();
                return;
            }
            this.poolDragged = true;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(person.id));
            this.onDragStart(person);
        },
        onPoolClick(person) {
            if (this.poolDragged) {
                this.poolDragged = false;
                return;
            }
            this.openPerson(person);
        },
        onDragEnd() {
            this.dragEmployee = null;
        },
        onViewportDragOver(event) {
            if (!this.dragEmployee) return;
            const viewport = this.$refs.viewport;
            if (!viewport) return;
            const rect = viewport.getBoundingClientRect();
            const edge = 56;
            if (event.clientX - rect.left < edge) viewport.scrollLeft -= 18;
            else if (rect.right - event.clientX < edge) viewport.scrollLeft += 18;
            if (event.clientY - rect.top < edge) viewport.scrollTop -= 18;
            else if (rect.bottom - event.clientY < edge) viewport.scrollTop += 18;
        },
        onDrop(target) {
            const employee = this.dragEmployee;
            this.dragEmployee = null;
            if (!employee || !target) return;
            const destination = this.resolveDestination(target);
            if (!destination) {
                this.notify('Drop onto a team leader or a manager.', 'warning');
                return;
            }
            if (destination.type === 'choose') {
                this.picker = { employee, manager: destination.manager, teams: destination.teams };
                return;
            }
            if (!this.sameBranchAllowed(employee, destination.parent)) {
                this.notify('You cannot assign employees across branches.', 'error');
                return;
            }
            this.commitAssign(employee, destination.parent);
        },
        async commitAssign(employee, parent) {
            this.picker = null;
            if (!employee || !parent || this.assigning) return;
            if (Number(employee.parent_id) === Number(parent.id)) {
                this.notify(`${employee.name} is already on this team.`, 'info');
                return;
            }
            if (!this.sameBranchAllowed(employee, parent)) {
                this.notify('You cannot assign employees across branches.', 'error');
                return;
            }
            const previous = {
                parent_id: employee.parent_id,
                parent_name: employee.parent_name,
            };
            this.assigning = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.TEAM_REASSIGN, {
                    method: 'POST',
                    headers: {
                        Authorization: 'Bearer ' + token,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: employee.id, parent_id: parent.id }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Assignment was rejected.');
                }
                const row = this.allUsers.find((user) => Number(user.id) === Number(employee.id));
                if (row) {
                    row.parent_id = payload.data?.parent_id ? Number(payload.data.parent_id) : Number(parent.id);
                    row.parent_name = payload.data?.parent_name || parent.name;
                    if (!row.branch_id && parent.branch_id) {
                        row.branch_id = parent.branch_id;
                        row.branch_name = parent.branch_name;
                    }
                }
                this.highlightedId = Number(employee.id);
                this.expandAncestors(employee.id);
                this.notify(`${employee.name} moved to ${parent.name}.`, 'success', previous.parent_id ? { employeeId: employee.id, ...previous } : null);
                this.$nextTick(() => this.chooseSearch(row || employee));
            } catch (error) {
                this.notify(error.message || 'Assignment was rejected.', 'error');
            } finally {
                this.assigning = false;
            }
        },
        async undoAssign() {
            const undo = this.toast?.undo;
            this.toast = null;
            if (!undo) return;
            const employee = this.byId.get(Number(undo.employeeId));
            if (!employee) return;
            this.assigning = true;
            try {
                const token = localStorage.getItem('token');
                const response = await fetch(API_ENDPOINTS.TEAM_REASSIGN, {
                    method: 'POST',
                    headers: {
                        Authorization: 'Bearer ' + token,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: undo.employeeId, parent_id: undo.parent_id }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) throw new Error(payload.message || 'Undo failed.');
                const row = this.allUsers.find((user) => Number(user.id) === Number(undo.employeeId));
                if (row) {
                    row.parent_id = payload.data?.parent_id ? Number(payload.data.parent_id) : null;
                    row.parent_name = payload.data?.parent_name || undo.parent_name || null;
                }
                this.notify('Assignment restored.', 'success');
            } catch (error) {
                this.notify(error.message || 'Undo failed.', 'error');
            } finally {
                this.assigning = false;
            }
        },
        opensSalesSidebar(user) {
            const key = roleKey(user);
            return key === 'admin' || key === 'super_admin' || isManagerRole(user) || isTeamLeadRole(user) || isSalesRole(user);
        },
        openSalesScope(user) {
            const fresh = this.byId.get(Number(user.id)) || user;
            this.salesScope = fresh;
            this.salesMinimized = false;
            this.salesQuery = '';
            this.person = null;
        },
        closeSalesScope() {
            this.salesScope = null;
            this.salesQuery = '';
            this.salesMinimized = false;
        },
        openPerson(user) {
            const fresh = this.byId.get(Number(user.id)) || user;
            if (this.opensSalesSidebar(fresh)) {
                this.openSalesScope(fresh);
                return;
            }
            this.person = fresh;
        },
        viewProfile(user) {
            this.person = null;
            this.$router.push(`/users/${user.id}`);
        },
        initials(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return '?';
            return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        },
        notify(message, type = 'info', undo = null) {
            if (window.$showNotification) window.$showNotification(message, type);
            this.toast = { message, type, undo };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toast = null; }, undo ? 7000 : 3200);
        },
    },
};
</script>

<style scoped>
.org-chart-page {
    --ink: #243056;
    --line: #4a4678;
    --muted: #8b93a7;
    --paper: #f5f7fb;
    height: calc(100dvh - var(--app-topbar-height, 2.75rem) - var(--app-header-below-gap, 0.5rem));
    max-height: calc(100dvh - var(--app-topbar-height, 2.75rem) - var(--app-header-below-gap, 0.5rem));
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(circle at 0% 0%, rgba(115, 62, 135, .16), transparent 32%),
        radial-gradient(circle at 100% 0%, rgba(154, 116, 32, .16), transparent 28%),
        var(--paper);
    color: var(--ink);
    position: relative;
}

.org-toolbar {
    display: flex;
    justify-content: flex-start;
    gap: 16px;
    align-items: center;
    padding: 10px 16px 8px;
    flex-wrap: wrap;
}

.org-toolbar__tools {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    position: relative;
}

.org-search {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    border: 1px solid #e1e5f0;
    border-radius: 12px;
    padding: 0 8px 0 10px;
    min-width: min(280px, 72vw);
    height: 38px;
    box-shadow: 0 8px 18px rgba(11, 7, 54, .05);
}

.org-search.is-open {
    border-color: #733E87;
    box-shadow: 0 0 0 3px rgba(115, 62, 135, .12);
}

.org-search__clear {
    border: 0;
    background: #f3f0f6;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #5c5168;
}

.org-search input,
.org-drawer__filters input,
.org-select,
.org-drawer__selects select {
    border: 0;
    outline: none;
    background: transparent;
    width: 100%;
    font-size: 13px;
    color: var(--ink);
}

.org-search__panel {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: min(340px, 86vw);
    background: #fff;
    border: 1px solid #efeaf3;
    border-radius: 14px;
    box-shadow: 0 22px 44px rgba(11, 7, 54, .16);
    z-index: 30;
    padding: 6px;
}

.org-search__meta {
    margin: 2px 8px 4px;
    font-size: 10px;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8b93a7;
}

.org-search__list,
.org-search__empty {
    margin: 0;
    padding: 0;
    list-style: none;
}

.org-search__list button {
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    border-radius: 10px;
    padding: 6px 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.org-search__list button.is-active,
.org-search__list button:hover { background: #f7f2f8; }

.org-search__avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: var(--accent, #733E87);
    background: color-mix(in srgb, var(--accent, #733E87) 14%, #fff);
}

.org-search__copy { min-width: 0; }

.org-search__list strong,
.pool-card strong { display: block; font-size: 13px; }

.org-search__list :deep(mark) {
    background: #f3e7c2;
    color: inherit;
    border-radius: 3px;
    padding: 0 1px;
}

.org-search__list small,
.pool-card small,
.org-search__empty {
    display: block;
    color: var(--muted);
    font-size: 11px;
}

.org-search__empty { padding: 10px 8px; }

.org-legend {
    display: flex;
    gap: 10px;
    align-items: center;
    font-size: 11px;
    color: #5c6478;
}

.org-legend span { display: inline-flex; align-items: center; gap: 5px; }

.org-legend i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.org-select,
.org-zoom,
.org-link,
.org-drawer-toggle {
    height: 36px;
    border-radius: 10px;
    border: 1px solid #e1e5f0;
    background: #fff;
    color: var(--ink);
    font-size: 13px;
}

.org-select { padding: 0 10px; max-width: 180px; }

.org-zoom {
    display: flex;
    align-items: center;
    overflow: hidden;
}

.org-zoom button,
.org-link,
.org-drawer-toggle {
    border: 0;
    background: transparent;
    height: 100%;
    padding: 0 10px;
    color: var(--ink);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.org-zoom span {
    min-width: 42px;
    text-align: center;
    font-size: 12px;
    color: var(--muted);
    border-left: 1px solid #eef0f6;
    border-right: 1px solid #eef0f6;
    line-height: 36px;
}

.org-drawer-toggle span {
    background: #eef2fb;
    border-radius: 99px;
    padding: 1px 7px;
    font-size: 11px;
}

.org-body {
    flex: 1;
    min-height: 0;
    display: flex;
    gap: 0;
}

.org-sales {
    width: 260px;
    flex: 0 0 260px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    border-left: 1px solid #e6e9f2;
    background: rgba(255, 255, 255, .94);
    animation: org-sales-in .22s ease;
}

.org-sales.is-min {
    width: 220px;
    flex-basis: 220px;
}

.org-sales__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 10px 8px;
}

.org-sales__who { min-width: 0; }

.org-sales__kicker {
    margin: 0;
    color: #8b93a7;
    font-size: 10px;
    font-weight: 650;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.org-sales__who strong {
    display: block;
    margin-top: 2px;
    color: #243056;
    font-size: 13px;
    line-height: 1.25;
}

.org-sales__who small,
.org-sales__empty,
.org-sales__copy small {
    display: block;
    color: #8b93a7;
    font-size: 11px;
    line-height: 1.3;
}

.org-sales__tools { display: flex; gap: 4px; flex: 0 0 auto; }

.org-sales__body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
    padding: 0 10px 10px;
    gap: 8px;
}

.org-sales__body input {
    height: 30px;
    border: 1px solid #e6e9f2;
    border-radius: 8px;
    padding: 0 8px;
    font-size: 12px;
    background: #fff;
}

.org-sales__list {
    list-style: none;
    margin: 0;
    padding: 0;
    overflow: auto;
    min-height: 0;
    flex: 1;
}

.org-sales__list button {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    text-align: left;
    padding: 6px 8px;
}

.org-sales__list button:hover { background: #f6f3f8; }

.org-sales__avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f3eaf6;
    color: #733e87;
    font-size: 10px;
    font-weight: 700;
}

.org-sales__copy { min-width: 0; }
.org-sales__copy strong {
    display: block;
    color: #243056;
    font-size: 12px;
    font-weight: 650;
    line-height: 1.25;
}

.org-sales__empty { margin: 4px 2px; }

.org-sales__profile {
    border: 1px solid #e1e5f0;
    background: #fff;
    border-radius: 8px;
    padding: 6px 8px;
    font-size: 11px;
    color: #243056;
}

@keyframes org-sales-in {
    from { opacity: 0; transform: translateX(12px); }
    to { opacity: 1; transform: none; }
}

.org-viewport {
    flex: 1;
    min-width: 0;
    min-height: 0;
    overflow: auto;
    padding: 8px 16px 24px;
    cursor: grab;
    touch-action: pan-x pan-y;
}

.org-viewport.is-panning {
    cursor: grabbing;
    user-select: none;
}

.org-canvas {
    width: max-content;
    min-width: 100%;
    padding: 12px 24px 40px;
}

.org-forest {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 28px;
}

.org-empty,
.org-skeleton {
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    gap: 8px;
}

.org-skeleton__row { display: flex; gap: 18px; margin-top: 36px; }

.org-skeleton__card {
    width: 168px;
    height: 92px;
    border-radius: 12px;
    background: linear-gradient(90deg, #eceff6 25%, #f7f8fb 50%, #eceff6 75%);
    background-size: 200% 100%;
    animation: org-shimmer 1.2s infinite;
}

.org-drawer {
    width: 320px;
    flex: 0 0 320px;
    border-left: 1px solid #e6e9f2;
    background: rgba(255, 255, 255, .9);
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.org-drawer__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px 6px;
    gap: 8px;
}

.org-drawer__title {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.2;
    color: var(--muted);
    letter-spacing: .02em;
}

.org-drawer__title span {
    margin-left: 4px;
    color: var(--ink);
}

.org-drawer__head p,
.org-drawer__empty {
    margin: 2px 0 0;
    color: var(--muted);
    font-size: 12px;
}

.org-drawer__filters {
    padding: 0 12px 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.org-drawer__filters input,
.org-drawer__selects select {
    height: 34px;
    border: 1px solid #e6e9f2;
    border-radius: 9px;
    padding: 0 10px;
    background: #fff;
}

.org-drawer__selects { display: flex; gap: 6px; }

.org-drawer__list {
    overflow: auto;
    padding: 4px 12px 16px;
    min-height: 0;
    flex: 1;
}

.pool-card {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 8px;
    border: 1px solid #e8ebf3;
    border-radius: 12px;
    margin-bottom: 8px;
    background: #fff;
    cursor: grab;
}

.pool-card.is-dragging { opacity: .45; }

.pool-card__avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e7eefc;
    color: #1d4e9e;
    font-size: 12px;
    font-weight: 700;
    flex: 0 0 auto;
}

.org-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e6e9f2;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--ink);
}

.org-modal {
    position: fixed;
    inset: 0;
    background: rgba(20, 28, 58, .35);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 80;
    padding: 16px;
}

.org-dialog {
    width: min(360px, 100%);
    background: #fff;
    border-radius: 14px;
    padding: 16px 16px 14px;
    position: relative;
    box-shadow: 0 18px 48px rgba(20, 28, 58, .18);
}

.org-dialog--person { width: min(340px, 100%); }

.org-dialog--wide { width: min(440px, 100%); }

.org-dialog__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-right: 28px;
    margin-bottom: 12px;
}

.org-dialog__identity { min-width: 0; }

.org-dialog__name {
    margin: 0;
    font-size: 15px !important;
    line-height: 1.25;
    font-weight: 650;
    color: #1a2340;
    overflow-wrap: anywhere;
}

.org-dialog__role {
    color: #7b8499;
    margin: 2px 0 0;
    font-size: 12px;
}

.org-dialog__photo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e7eefc;
    color: #1d4e9e;
    font-size: 12px;
    font-weight: 700;
    flex: 0 0 auto;
}

.org-dialog__close { position: absolute; top: 10px; right: 10px; width: 28px; height: 28px; }

.org-dialog dl {
    display: grid;
    grid-template-columns: 78px 1fr;
    gap: 7px 10px;
    align-items: center;
    font-size: 12px;
    margin: 0 0 12px;
    padding-top: 10px;
    border-top: 1px solid #eef1f6;
}

.org-dialog dt { color: #8b93a7; font-weight: 500; }

.org-dialog dd {
    margin: 0;
    color: #243056;
    overflow-wrap: anywhere;
}

.org-status {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
    background: #e8f6ee;
    color: #1c7a45;
}

.org-status.is-off { background: #fdecee; color: #9a3040; }

.org-primary,
.org-picker button {
    border: 1px solid #d9e0ef;
    background: #fff;
    border-radius: 8px;
    padding: 7px 12px;
    font-size: 12px;
}

.org-primary {
    background: #1d4e9e;
    color: #fff;
    border-color: #1d4e9e;
}

.org-picker { display: flex; flex-direction: column; gap: 8px; }

.org-picker button { text-align: left; }

.org-picker strong, .org-picker small { display: block; }
.org-picker small { color: var(--muted); }

.org-toast {
    position: fixed;
    left: 50%;
    bottom: 22px;
    transform: translateX(-50%);
    background: #243056;
    color: #fff;
    border-radius: 999px;
    padding: 8px 10px 8px 16px;
    display: flex;
    gap: 8px;
    align-items: center;
    z-index: 90;
    box-shadow: 0 10px 30px rgba(20, 28, 58, .25);
    font-size: 13px;
}

.org-toast button {
    border: 0;
    background: transparent;
    color: #fff;
    border-radius: 99px;
    padding: 4px 8px;
}

.org-toast.is-error { background: #8d2f39; }
.org-toast.is-success { background: #1d4e9e; }

@keyframes org-shimmer {
    0% { background-position: 100% 0; }
    100% { background-position: -100% 0; }
}

@media (max-width: 860px) {
    .org-chart-page {
        height: calc(100dvh - var(--app-topbar-height, 3.25rem) - 96px);
        max-height: calc(100dvh - var(--app-topbar-height, 3.25rem) - 96px);
    }
    .org-body { display: block; }
    .org-viewport { height: calc(100dvh - 230px); }
    .org-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        z-index: 70;
        width: min(360px, 92vw);
        flex-basis: auto;
        transform: translateX(105%);
        transition: transform .2s ease;
        box-shadow: -12px 0 40px rgba(20, 28, 58, .15);
    }
    .org-drawer.is-open { transform: translateX(0); }
    .org-sales {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        z-index: 69;
        width: min(300px, 92vw);
        flex-basis: auto;
        box-shadow: -12px 0 40px rgba(20, 28, 58, .15);
    }
    .org-sales.is-min { width: min(300px, 92vw); }
    .org-body:has(.org-drawer.is-open) .org-sales { right: min(360px, 92vw); }
}

@media (prefers-reduced-motion: reduce) {
    .org-skeleton__card,
    .org-drawer,
    .org-sales { animation: none; transition: none; }
}
</style>
