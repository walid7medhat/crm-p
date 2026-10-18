<template>
    <div class="dashboard-main-body role-builder">
        <Breadcrumb
            :title="isEditMode ? 'Edit Role' : 'Add New Role'"
            :breadcrumbs="[
                { name: 'Roles', path: '/roles' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]"
        />

        <form class="role-stage" @submit.prevent="submitForm">
            <div class="role-bar">
                <div>
                    <p class="role-title">{{ isEditMode ? 'Edit role' : 'Create role' }}</p>
                    <p>Name the role, tick what it can do, then test it before saving.</p>
                </div>
                <div class="role-bar__actions">
                    <span class="count-chip">{{ selectedCount }} selected</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="openSimulator">Test</button>
                    <button type="submit" class="btn btn-sm btn-primary" :disabled="loading || !permissionsLoaded">
                        {{ loading ? 'Saving…' : (isEditMode ? 'Update' : 'Create role') }}
                    </button>
                </div>
            </div>

            <section class="name-row">
                <label class="field-label" for="role-name">Role name</label>
                <input
                    id="role-name"
                    v-model="roleForm.name"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': errors.name }"
                    placeholder="Listing coordinator"
                    autocomplete="off"
                />
                <p v-if="errors.name" class="field-error">{{ errors.name[0] }}</p>
                <p class="field-hint">A new name does not copy Manager, Team Leader, Sales, or Branch Admin rules.</p>
            </section>

            <section v-if="configurationWarnings.length" class="warn-box">
                <p v-for="warning in configurationWarnings" :key="warning.id">
                    <strong>{{ warning.title }}.</strong> {{ warning.detail }}
                </p>
            </section>

            <section class="glass-panel catalog-panel">
                <div class="panel-head panel-head--tools">
                    <p class="section-label">Permissions</p>
                    <div class="tool-row">
                        <input
                            v-model="permissionSearch"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search permissions"
                            aria-label="Search permissions"
                        />
                        <label class="check-line">
                            <input v-model="sensitiveOnly" type="checkbox" />
                            Sensitive only
                        </label>
                    </div>
                </div>

                <div v-if="!permissionsLoaded" class="catalog-loading">Loading permissions…</div>
                <div v-else-if="visibleModules.length === 0" class="catalog-loading">No permissions match this search.</div>

                <div v-else class="builder-workspace">
                    <nav class="module-nav" aria-label="Permission areas">
                        <button
                            v-for="module in visibleModules"
                            :key="module.id"
                            type="button"
                            class="module-nav__item"
                            :class="{ 'is-active': activeModule && activeModule.id === module.id }"
                            @click="selectModule(module)"
                        >
                            <span>{{ module.label }}</span>
                            <span class="module-count">{{ selectedIn(module) }}/{{ module.permissions.length }}</span>
                        </button>
                    </nav>

                    <div v-if="activeModule" class="permission-column">
                        <div class="module-card__head">
                            <p class="section-label">{{ activeModule.label }}</p>
                            <label class="check-line">
                                <input
                                    type="checkbox"
                                    :checked="isModuleFullySelected(activeModule)"
                                    @change="toggleModuleSelection(activeModule)"
                                />
                                Select visible
                            </label>
                        </div>
                        <div class="permission-list">
                            <div
                                v-for="permission in filteredModulePermissions(activeModule)"
                                :key="permission.key"
                                class="permission-row"
                                :class="{
                                    'is-selected': isSelected(permission.key),
                                    'is-focused': focusedKey === permission.key,
                                    'is-sensitive': permission.sensitivity === 'high'
                                }"
                                @click="focusedKey = permission.key"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isSelected(permission.key)"
                                    :aria-label="permission.label"
                                    @click.stop
                                    @change="togglePermission(permission.key)"
                                />
                                <span class="permission-copy">
                                    <span class="permission-title">{{ permission.label }}</span>
                                    <span class="permission-desc">{{ firstPoint(permission) }}</span>
                                </span>
                                <span class="access-word" :class="'access-' + (permission.access || 'unverified')">{{ accessLabel(permission.access) }}</span>
                            </div>
                        </div>
                    </div>

                    <aside class="explain-panel">
                        <template v-if="focusedPermission">
                            <p class="section-label">{{ focusedPermission.label }}</p>
                            <p class="access-word access-word--large" :class="'access-' + (focusedPermission.access || 'unverified')">{{ accessLabel(focusedPermission.access) }}</p>
                            <ul class="point-list">
                                <li v-for="(point, index) in focusedPermission.points || []" :key="index">{{ point }}</li>
                            </ul>
                            <button type="button" class="btn btn-sm" :class="isSelected(focusedPermission.key) ? 'btn-outline-secondary' : 'btn-primary'" @click="togglePermission(focusedPermission.key)">
                                {{ isSelected(focusedPermission.key) ? 'Remove from role' : 'Add to role' }}
                            </button>
                        </template>
                        <p v-else class="field-hint">Select a permission to see what the employee can open and which records stay limited.</p>
                    </aside>
                </div>
            </section>

        </form>

        <div v-if="simulatorOpen" class="sim-backdrop" @click.self="closeSimulator">
            <div
                class="sim-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="sim-title"
            >
                <header class="sim-dialog__head">
                    <div>
                        <p class="role-kicker">Simulation</p>
                        <p id="sim-title" class="role-title">Test this role</p>
                        <p>Nothing is saved. The employee is only the org context: branch, team, and direct permissions.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" @click="closeSimulator">Close</button>
                </header>

                <div class="sim-search">
                    <label for="employee-search">Employee</label>
                    <input
                        id="employee-search"
                        v-model="employeeQuery"
                        type="search"
                        class="form-control"
                        placeholder="Search by name or email"
                        @input="onEmployeeQuery"
                    />
                    <ul v-if="employeeResults.length" class="employee-results">
                        <li v-for="person in employeeResults" :key="person.id">
                            <button type="button" @click="selectEmployee(person)">
                                <strong>{{ person.name }}</strong>
                                <span>{{ person.email }}</span>
                                <span>{{ (person.roles || []).join(', ') || 'No role' }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-if="selectedEmployee" class="selected-employee">
                        Testing as <strong>{{ selectedEmployee.name }}</strong>.
                        Current role: {{ (selectedEmployee.roles || []).join(', ') || 'none' }}.
                        Branch context: {{ selectedEmployee.branch_label || 'not resolved' }}.
                        Listing team: {{ selectedEmployee.is_listing_team ? 'yes' : 'no' }}.
                        This role is not assigned.
                    </p>
                </div>

                <p v-if="previewError" class="field-error">{{ previewError }}</p>
                <p v-if="previewLoading" class="quiet">Calculating access from the current draft…</p>

                <div v-if="preview" class="preview-shell">
                    <aside class="preview-sidebar">
                        <div class="preview-brand">
                            <span class="preview-brand__mark">OIA</span>
                            <span>Menu</span>
                        </div>
                        <MenuBranch
                            :nodes="preview.sidebar"
                            :selected-id="pickedNode ? pickedNode.id : ''"
                            :on-select="pickNode"
                        />
                    </aside>
                    <section class="preview-detail">
                        <p v-if="!pickedNode" class="section-label">Click Leads, Listings, or any menu</p>
                        <template v-else>
                            <p class="section-label">{{ pickedNode.label }}</p>
                            <p class="preview-person">{{ previewHeadline }}</p>
                            <p class="preview-reason">{{ pickedNode.note || pickedNode.reason }}</p>
                            <p v-if="pickedChildren.length" class="field-hint">Inside this menu</p>
                            <ul v-if="pickedChildren.length" class="inside-list">
                                <li v-for="child in pickedChildren" :key="child.id">{{ child.label }}</li>
                            </ul>
                            <section v-for="group in pickedGroups" :key="group.id" class="preview-group">
                                <p class="field-hint">{{ group.label }}</p>
                                <ul class="access-list">
                                    <li v-for="item in group.items" :key="item.id">
                                        <span class="access-word" :class="'access-' + previewAccess(item.state)">{{ stateLabel(item.state) }}</span>
                                        <span>
                                            <strong>{{ item.label }}</strong>
                                            <span class="permission-desc">{{ item.brief || item.detail }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </section>
                        </template>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

const MENU_GLYPH = {
    dashboard: 'M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z',
    crm: 'M4 7h16M4 12h16M4 17h10',
    leads: 'M8 7a4 4 0 1 1 8 0 4 4 0 0 1-8 0zM5 20a7 7 0 0 1 14 0',
    deals: 'M4 8h16v10H4zM8 8V6h8v2',
    listings: 'M5 20V9l7-5 7 5v11H5z',
    calculators: 'M7 4h10v16H7zM9 8h6M9 12h2M13 12h2M9 16h2M13 16h2',
    hr: 'M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zM16 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zM3 19a5 5 0 0 1 10 0M13 19a4 4 0 0 1 7 0',
    marketing: 'M4 12h4l8-5v10l-8-5H4z',
    photo: 'M4 8h3l2-2h6l2 2h3v10H4zM12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    agents: 'M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zM5 20a7 7 0 0 1 14 0',
    settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 3v2M12 19v2M3 12h2M19 12h2',
    announcements: 'M5 7h14v10H5zM5 7l7 6 7-6',
    suggestions: 'M12 18h.01M9 9a3 3 0 1 1 4.5 2.6c-.8.6-1.5 1.2-1.5 2.4v.5',
    default: 'M6 6h12v12H6z',
};

function menuIcon(id) {
    const listing = /listing|developer|owner|propert|feature|project|unit_view|layout|areas/.test(id);
    const key = listing ? 'listings' : (MENU_GLYPH[id] ? id : (id.startsWith('agents') ? 'agents' : 'default'));
    return h('svg', {
        class: 'preview-ico',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        'stroke-width': '1.7',
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
    }, [h('path', { d: MENU_GLYPH[key] || MENU_GLYPH.default })]);
}

const MenuBranch = defineComponent({
    name: 'MenuBranch',
    props: {
        nodes: { type: Array, default: () => [] },
        nested: { type: Boolean, default: false },
        selectedId: { type: String, default: '' },
        onSelect: { type: Function, default: null },
    },
    setup(props) {
        const openIds = ref({});
        const visibleNodes = computed(() => (props.nodes || []).filter((node) => node.state !== 'hidden'));
        const hasKids = (node) => (node.children || []).some((child) => child.state !== 'hidden');
        const pick = (node) => {
            if (hasKids(node)) {
                openIds.value = { ...openIds.value, [node.id]: !openIds.value[node.id] };
            }
            if (typeof props.onSelect === 'function') props.onSelect(node);
        };
        return () => h('ul', { class: ['preview-menu', props.nested ? 'preview-menu--nested' : ''] },
            visibleNodes.value.map((node) => h('li', { key: node.id }, [
                h('button', {
                    type: 'button',
                    class: [
                        'preview-link',
                        openIds.value[node.id] ? 'is-open' : '',
                        props.selectedId === node.id ? 'is-selected' : '',
                        node.state === 'conditional' ? 'is-conditional' : '',
                    ],
                    onClick: () => pick(node),
                }, [
                    menuIcon(node.id),
                    h('span', { class: 'preview-label' }, node.label),
                    hasKids(node) ? h('span', { class: 'preview-chevron' }, openIds.value[node.id] ? '▾' : '▸') : null,
                ]),
                openIds.value[node.id] && hasKids(node)
                    ? h(MenuBranch, {
                        nodes: node.children,
                        nested: true,
                        selectedId: props.selectedId,
                        onSelect: props.onSelect,
                    })
                    : null,
            ]))
        );
    },
});

function stateText(state) {
    if (state === 'visible') return 'Menu shows';
    if (state === 'hidden') return 'Hidden';
    if (state === 'allowed') return 'Allowed';
    if (state === 'denied') return 'Restricted';
    if (state === 'conditional' || state === 'warning') return 'Conditional';
    return 'Not verified';
}

export default {
    name: 'RoleForm',
    components: { Breadcrumb, MenuBranch },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const isEditMode = computed(() => route.name === 'edit-role');
        const roleId = computed(() => route.params.id);

        const errors = ref({});
        const loading = ref(false);
        const permissionsLoaded = ref(false);
        const modules = ref([]);
        const permissionSearch = ref('');
        const sensitiveOnly = ref(false);
        const openModules = ref({});
        const activeModuleId = ref('');
        const focusedKey = ref('');
        const roleForm = ref({ name: '', permissions: [] });
        const pickedNode = ref(null);

        const simulatorOpen = ref(false);
        const employeeQuery = ref('');
        const employeeResults = ref([]);
        const selectedEmployee = ref(null);
        const preview = ref(null);
        const previewLoading = ref(false);
        const previewError = ref('');
        let searchTimer = null;
        let previewTimer = null;

        const selectedCount = computed(() => roleForm.value.permissions.length);

        const configurationWarnings = computed(() => {
            const selected = new Set(roleForm.value.permissions);
            const warnings = [];
            const has = (key) => selected.has(key);
            if (has('show-leads') && !has('leads-list')) {
                warnings.push({
                    id: 'leads-menu-without-api',
                    title: 'Leads menu without the lead API permission',
                    detail: 'show-leads opens Kanban. The lead API still requires leads-list.',
                });
            }
            if (has('leads-list') && !has('show-leads')) {
                warnings.push({
                    id: 'leads-api-without-menu',
                    title: 'Lead API permission without the Leads menu',
                    detail: 'leads-list does not show Leads unless the user is also admin or has show-leads.',
                });
            }
            if (has('show-branch-leads')) {
                warnings.push({
                    id: 'branch-scope',
                    title: 'Branch-wide lead visibility',
                    detail: 'show-branch-leads widens lead records to the employee’s branch. It does not change deal scope or the role name.',
                });
            }
            ['roles-create', 'roles-edit', 'roles-delete', 'roles-assign', 'assign-role'].forEach((key) => {
                if (has(key)) {
                    warnings.push({
                        id: key,
                        title: 'High-privilege access key',
                        detail: `${key} can change who controls the CRM. The server still allows a role editor to attach any existing permission.`,
                    });
                }
            });
            return warnings;
        });

        const warningCount = computed(() => configurationWarnings.value.length);

        const visibleModules = computed(() => modules.value
            .map((module) => ({
                ...module,
                permissions: filteredModulePermissions(module),
            }))
            .filter((module) => module.permissions.length > 0));

        const activeModule = computed(() => visibleModules.value.find((module) => module.id === activeModuleId.value)
            || visibleModules.value[0]
            || null);

        const focusedPermission = computed(() => {
            for (const module of modules.value) {
                const match = module.permissions.find((permission) => permission.key === focusedKey.value);
                if (match) return match;
            }
            return null;
        });

        const pickedChildren = computed(() => (pickedNode.value?.children || []).filter((child) => child.state !== 'hidden'));

        const previewHeadline = computed(() => {
            const name = selectedEmployee.value?.name || 'This employee';
            const node = pickedNode.value;
            if (!node) return '';
            if (node.state === 'hidden') return `${name} does not see this in the menu.`;
            if (node.state === 'conditional') return `${name} may see this, with a limit described below.`;
            return `${name} sees this in the menu. That is not the same as opening every record inside it.`;
        });

        const pickedGroups = computed(() => {
            const labels = { menu: 'Menu', page: 'Page access', records: 'Which records', action: 'Actions' };
            const buckets = {};
            pickedChecks.value.forEach((item) => {
                const group = item.group || 'action';
                if (!buckets[group]) buckets[group] = [];
                buckets[group].push(item);
            });
            return ['menu', 'page', 'records', 'action']
                .filter((group) => buckets[group])
                .map((group) => ({ id: group, label: labels[group], items: buckets[group] }));
        });

        const pickedChecks = computed(() => {
            const node = pickedNode.value;
            const modulesList = preview.value?.modules || [];
            if (!node) return [];
            const id = node.id;
            let moduleId = null;
            if (['leads'].includes(id)) moduleId = 'leads';
            else if (id === 'deals') moduleId = 'deals';
            else if (id === 'hr') moduleId = 'hr';
            else if (/listing|developer|owner|propert|feature|project|unit_view|layout|areas/.test(id)) moduleId = 'listings';
            else if (id.startsWith('agents')) moduleId = 'people';
            else if (['marketing', 'photo', 'announcements-admin', 'announcements-browse', 'settings', 'roles', 'add-role', 'animation', 'daily-message'].includes(id) || id.startsWith('super-') || id.startsWith('lead-') || id.startsWith('admin-')) moduleId = 'other';
            const match = modulesList.find((module) => module.id === moduleId);
            return match?.items || [];
        });

        function filteredModulePermissions(module) {
            const search = permissionSearch.value.trim().toLowerCase();
            return module.permissions.filter((permission) => {
                if (sensitiveOnly.value && permission.sensitivity !== 'high') return false;
                if (!search) return true;
                return [permission.label, permission.description, permission.key, permission.limitations]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(search));
            });
        }

        function isSelected(key) {
            return roleForm.value.permissions.includes(key);
        }

        function selectedIn(module) {
            return module.permissions.filter((permission) => isSelected(permission.key)).length;
        }

        function isModuleFullySelected(module) {
            const visible = filteredModulePermissions(module);
            return visible.length > 0 && visible.every((permission) => isSelected(permission.key));
        }

        function accessLabel(access) {
            if (access === 'allowed') return 'Allowed';
            if (access === 'conditional') return 'Conditional';
            if (access === 'restricted') return 'Restricted';
            return 'Not verified';
        }

        function previewAccess(state) {
            if (state === 'allowed' || state === 'visible') return 'allowed';
            if (state === 'denied' || state === 'hidden') return 'restricted';
            if (state === 'conditional' || state === 'warning') return 'conditional';
            return 'unverified';
        }

        function firstPoint(permission) {
            return (permission.points && permission.points[0]) || permission.description;
        }

        function permissionLabel(key) {
            for (const module of modules.value) {
                const match = module.permissions.find((permission) => permission.key === key);
                if (match) return match.label;
            }
            return key;
        }

        function togglePermission(key) {
            if (roleForm.value.permissions.includes(key)) {
                roleForm.value.permissions = roleForm.value.permissions.filter((item) => item !== key);
                return;
            }
            roleForm.value.permissions = [...roleForm.value.permissions, key];
        }

        function pickNode(node) {
            pickedNode.value = node;
        }

        function selectModule(module) {
            activeModuleId.value = module.id;
            const first = filteredModulePermissions(module)[0];
            if (first) focusedKey.value = first.key;
        }

        function toggleModule(id) {
            openModules.value = {
                ...openModules.value,
                [id]: openModules.value[id] !== true,
            };
        }

        function toggleModuleSelection(module) {
            const visibleKeys = filteredModulePermissions(module).map((permission) => permission.key);
            if (isModuleFullySelected(module)) {
                roleForm.value.permissions = roleForm.value.permissions.filter((key) => !visibleKeys.includes(key));
                return;
            }
            const merged = new Set(roleForm.value.permissions);
            visibleKeys.forEach((key) => merged.add(key));
            roleForm.value.permissions = Array.from(merged);
        }

        function notify(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
                return;
            }
            console[type === 'error' ? 'error' : 'log'](message);
        }

        async function fetchCatalog() {
            const response = await api.get('/roles/permission-catalog');
            const payload = response.data?.data || response.data;
            modules.value = Array.isArray(payload?.modules) ? payload.modules : [];
        }

        async function fetchRole() {
            const response = await api.get(`/roles/${roleId.value}`);
            const roleData = response.data?.data || response.data;
            const known = new Set(modules.value.flatMap((module) => module.permissions.map((permission) => permission.key)));
            const selected = [];
            (roleData.permissions || []).forEach((permission) => {
                const name = permission.name || permission;
                if (known.has(name)) selected.push(name);
            });
            roleForm.value = {
                name: roleData.name || '',
                permissions: selected,
            };
            await nextTick();
        }

        async function submitForm() {
            loading.value = true;
            errors.value = {};
            try {
                if (!roleForm.value.name?.trim()) {
                    notify('Enter a role name', 'error');
                    return;
                }
                const payload = {
                    name: roleForm.value.name.trim(),
                    permissions: roleForm.value.permissions,
                };
                if (isEditMode.value) {
                    await api.put(`/roles/${roleId.value}`, payload);
                } else {
                    await api.post('/roles', payload);
                }
                notify(isEditMode.value ? 'Role updated' : 'Role created', 'success');
                setTimeout(() => router.push('/roles'), 600);
            } catch (error) {
                errors.value = error.response?.data?.errors || {};
                notify(error.response?.data?.message || 'Could not save the role', 'error');
            } finally {
                loading.value = false;
            }
        }

        function openSimulator() {
            simulatorOpen.value = true;
            preview.value = null;
            previewError.value = '';
        }

        function closeSimulator() {
            simulatorOpen.value = false;
        }

        function onEmployeeQuery() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchEmployees, 250);
        }

        async function searchEmployees() {
            const q = employeeQuery.value.trim();
            if (q.length < 2) {
                employeeResults.value = [];
                return;
            }
            try {
                const response = await api.get('/roles/simulation-users', { params: { q } });
                employeeResults.value = response.data?.data || [];
            } catch (error) {
                previewError.value = error.response?.data?.message || 'Employee search failed';
            }
        }

        async function selectEmployee(person) {
            selectedEmployee.value = person;
            employeeResults.value = [];
            employeeQuery.value = person.name;
            await runPreview();
        }

        async function runPreview() {
            if (!selectedEmployee.value) return;
            previewLoading.value = true;
            previewError.value = '';
            try {
                const response = await api.post('/roles/access-preview', {
                    name: roleForm.value.name,
                    permissions: roleForm.value.permissions,
                    user_id: selectedEmployee.value.id,
                });
                preview.value = response.data?.data || null;
                pickedNode.value = null;
            } catch (error) {
                preview.value = null;
                previewError.value = error.response?.data?.message || 'Preview failed';
            } finally {
                previewLoading.value = false;
            }
        }

        watch([permissionSearch, sensitiveOnly], () => {
            if (!permissionSearch.value.trim() && !sensitiveOnly.value) return;
            const next = { ...openModules.value };
            modules.value.forEach((module) => {
                next[module.id] = true;
            });
            openModules.value = next;
        });

        watch(() => roleForm.value.permissions, () => {
            if (!simulatorOpen.value || !selectedEmployee.value) return;
            clearTimeout(previewTimer);
            previewTimer = setTimeout(runPreview, 300);
        });

        function stateLabel(state) {
            return stateText(state);
        }

        function onKeydown(event) {
            if (event.key === 'Escape' && simulatorOpen.value) closeSimulator();
        }

        onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

        onMounted(async () => {
            window.addEventListener('keydown', onKeydown);
            try {
                await fetchCatalog();
                if (isEditMode.value) await fetchRole();
                permissionsLoaded.value = true;
                activeModuleId.value = modules.value[0]?.id || '';
                focusedKey.value = modules.value[0]?.permissions?.[0]?.key || '';
            } catch (error) {
                notify(error.response?.data?.message || 'Could not load permissions', 'error');
            }
        });

        return {
            isEditMode,
            loading,
            permissionsLoaded,
            permissionSearch,
            sensitiveOnly,
            openModules,
            roleForm,
            errors,
            visibleModules,
            selectedCount,
            warningCount,
            configurationWarnings,
            filteredModulePermissions,
            isSelected,
            selectedIn,
            isModuleFullySelected,
            activeModuleId,
            activeModule,
            focusedKey,
            focusedPermission,
            pickedNode,
            pickedChildren,
            pickedChecks,
            pickedGroups,
            previewHeadline,
            permissionLabel,
            accessLabel,
            previewAccess,
            firstPoint,
            togglePermission,
            selectModule,
            pickNode,
            toggleModule,
            toggleModuleSelection,
            submitForm,
            simulatorOpen,
            employeeQuery,
            employeeResults,
            selectedEmployee,
            preview,
            previewLoading,
            previewError,
            openSimulator,
            closeSimulator,
            onEmployeeQuery,
            selectEmployee,
            stateLabel,
        };
    },
};
</script>

<style scoped>
.role-builder {
    --ink: #1c2430;
    --muted: #3e4c5f;
    --line: #e3e7ee;
    color: var(--ink);
}

.role-stage {
    display: grid;
    gap: 0.75rem;
}

.role-bar,
.name-row,
.glass-panel,
.warn-box,
.sim-dialog {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    color: var(--ink);
}

.role-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.7rem 0.9rem;
}

.role-title,
.section-label {
    margin: 0;
    font-size: 0.84rem;
    font-weight: 650;
    line-height: 1.3;
    color: var(--ink);
}

.role-bar p,
.field-hint,
.quiet,
.permission-desc,
.nav-reason,
.disclaimer,
.sim-dialog__head p,
.sim-module p {
    margin: 0.15rem 0 0;
    color: var(--muted);
    font-size: 0.84rem;
    line-height: 1.45;
}

.role-bar__actions,
.tool-row,
.panel-head,
.sim-dialog__head,
.save-bar {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.count-chip {
    font-size: 0.78rem;
    font-weight: 650;
    color: #243044;
    background: #eef2f7;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
}

.name-row,
.glass-panel,
.sim-dialog {
    padding: 0.85rem 0.95rem;
}

.warn-box {
    padding: 0.7rem 0.9rem;
    background: #fff8ee;
    border-color: #f0d7a8;
}

.warn-box p {
    margin: 0.25rem 0;
    color: #5c4314;
    font-size: 0.84rem;
    line-height: 1.4;
}

.field-label,
.sim-search label {
    display: block;
    font-weight: 650;
    margin-bottom: 0.35rem;
    color: var(--ink);
    font-size: 0.86rem;
}

.field-error {
    color: #9f1239;
}

.role-stats {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.panel-head {
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.panel-head .section-label,
.sim-sidebar .section-label,
.sim-modules .section-label {
    margin: 0 0 0.35rem;
}

.text-button {
    border: 0;
    background: transparent;
    color: #8a6420;
    font-weight: 650;
}

.warning-list,
.sim-module ul,
.nav-tree,
.employee-results {
    list-style: none;
    margin: 0;
    padding: 0;
}

.warning-list li,
.sim-module li {
    display: grid;
    gap: 0.15rem;
    padding: 0.75rem 0;
    border-top: 1px solid var(--line);
}

.panel-head--tools {
    align-items: flex-end;
}

.tool-row {
    min-width: min(100%, 28rem);
}

.tool-row .form-control {
    flex: 1;
}

.check-line {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    white-space: nowrap;
    font-size: 0.92rem;
}

.builder-workspace {
    display: grid;
    grid-template-columns: 168px minmax(0, 1fr) 280px;
    gap: 0.85rem;
    align-items: start;
}

.module-nav {
    display: grid;
    align-content: start;
    gap: 0.15rem;
}

.module-nav__item {
    display: flex;
    justify-content: space-between;
    gap: 0.35rem;
    align-items: center;
    border: 0;
    background: transparent;
    color: var(--ink);
    text-align: left;
    border-radius: 8px;
    padding: 0.38rem 0.45rem;
    font-size: 0.78rem;
    font-weight: 650;
}

.module-nav__item.is-active {
    background: #f4f1ea;
}

.permission-column {
    min-width: 0;
}

.explain-panel {
    border-left: 1px solid var(--line);
    padding-left: 0.85rem;
    min-height: 220px;
}

.point-list {
    margin: 0.45rem 0 0.75rem;
    padding-left: 1.05rem;
    color: var(--ink);
    font-size: 0.82rem;
    line-height: 1.4;
}

.point-list li {
    margin: 0.28rem 0;
}

.access-word {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #66758c;
    white-space: nowrap;
}

.access-word--large {
    margin: 0.35rem 0 0;
}

.access-allowed { color: #166534; }
.access-conditional { color: #8a5a12; }
.access-restricted { color: #9f1239; }
.access-unverified { color: #66758c; }

.permission-row.is-focused {
    border-color: #e4ddd2;
    background: #faf8f5;
}

.preview-group {
    margin-top: 0.65rem;
}

.builder-stage {
    display: grid;
    gap: 0.8rem;
}

.icon-rail {
    display: flex;
    gap: 0.7rem;
    overflow-x: auto;
    perspective: 900px;
    padding: 0.35rem 0.15rem 0.85rem;
}

.icon-card {
    min-width: 96px;
    border: 1px solid var(--line);
    background: linear-gradient(165deg, #ffffff, #f3f6fb);
    border-radius: 16px;
    padding: 0.65rem 0.4rem 0.5rem;
    display: grid;
    justify-items: center;
    gap: 0.2rem;
    color: var(--ink);
    cursor: grab;
    transform-style: preserve-3d;
    transition: transform 0.28s ease, box-shadow 0.28s ease;
    box-shadow: 0 7px 0 #e4e9f1, 0 12px 22px rgba(36, 48, 68, 0.08);
}

.icon-card:hover {
    transform: translateY(-7px) rotateX(10deg) rotateY(-8deg);
}

.icon-card.is-active {
    border-color: #c4b3ea;
    box-shadow: 0 7px 0 #ddd2f3, 0 14px 24px rgba(76, 29, 149, 0.14);
}

.icon-card__glyph {
    width: 28px;
    height: 28px;
    color: #5b3f9a;
}

.icon-card__glyph :deep(svg) {
    width: 28px;
    height: 28px;
}

.icon-card__name {
    font-size: 0.72rem;
    font-weight: 650;
    text-align: center;
    line-height: 1.2;
}

.builder-columns {
    display: grid;
    grid-template-columns: 1.25fr 0.85fr;
    gap: 0.75rem;
}

.source-pane,
.role-tray {
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 0.7rem;
    background: #fbfcfe;
    min-height: 220px;
}

.role-tray {
    border-style: dashed;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(244, 247, 252, 0.9));
}

.tray-empty {
    margin-top: 0.8rem;
    color: var(--muted);
    font-size: 0.84rem;
}

.tray-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.7rem;
}

.tray-chip {
    border: 0;
    border-radius: 999px;
    background: #efe7ff;
    color: #3d2a6d;
    font-size: 0.75rem;
    font-weight: 650;
    padding: 0.28rem 0.55rem;
    cursor: grab;
}

.module-card {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 0.55rem 0.7rem;
    background: #fbfcfe;
}

.module-card__head,
.permission-title,
.nav-node__button {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
}

.module-toggle {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 0;
    background: transparent;
    padding: 0.25rem 0;
    font-weight: 700;
    color: var(--ink);
    text-align: left;
}

.module-name {
    font-size: 0.92rem;
}

.module-count {
    margin-left: auto;
    font-size: 0.75rem;
    font-weight: 650;
    color: #243044;
    background: #e7edf5;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
}

.module-summary {
    color: var(--muted);
    margin: 0.35rem 0 0.75rem;
}

.permission-list {
    display: grid;
    gap: 0.35rem;
    margin-top: 0.35rem;
}

.permission-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.55rem;
    align-items: start;
    cursor: pointer;
    padding: 0.45rem 0.5rem;
    border-radius: 8px;
    border: 1px solid transparent;
    background: #fff;
    color: var(--ink);
}

.permission-row.is-selected {
    border-color: #b9d4f5;
    background: #f3f8ff;
}

.permission-row.is-sensitive {
    background: #fff8ef;
}

.permission-row.is-unmapped {
    background: #f4f6f8;
}

.permission-title {
    color: var(--ink);
    font-size: 0.88rem;
    font-weight: 650;
}

.permission-title em,
.state-pill {
    font-style: normal;
    font-size: 0.68rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #8a4b12;
}

.tag-muted {
    color: var(--muted);
}

.permission-copy {
    display: grid;
    gap: 0.2rem;
}

.permission-copy code {
    width: fit-content;
    color: #66758c;
    font-size: 0.75rem;
}

.save-bar {
    justify-content: flex-end;
}

.sim-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(12, 16, 28, 0.46);
}

.sim-dialog {
    width: min(980px, 100%);
    max-height: min(92vh, 860px);
    overflow: auto;
    padding: 0.85rem;
}

.preview-shell {
    margin-top: 0.75rem;
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0.85rem;
    align-items: start;
}

.preview-sidebar {
    width: 100%;
    background: #fff;
    border: 1px solid #e8eaef;
    border-radius: 28px;
    box-shadow: 0 12px 32px rgba(76, 29, 149, 0.08);
    padding: 0.85rem 0.55rem 0.7rem;
}

.preview-brand {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.15rem 0.7rem 0.65rem;
    color: #243044;
    font-size: 0.78rem;
    font-weight: 650;
}

.preview-brand__mark {
    width: 28px;
    height: 28px;
    border-radius: 9px;
    display: grid;
    place-items: center;
    background: #24143d;
    color: #fff;
    font-size: 0.62rem;
    letter-spacing: 0.04em;
}

.preview-detail {
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 0.85rem;
    background: #fff;
    min-height: 280px;
}

.preview-person {
    margin: 0.35rem 0 0;
    font-size: 0.84rem;
    font-weight: 650;
    color: var(--ink);
}

.preview-reason,
.inside-list,
.access-list {
    margin: 0.45rem 0 0;
    color: var(--muted);
    font-size: 0.82rem;
    line-height: 1.45;
}

.inside-list,
.access-list {
    list-style: none;
    padding: 0;
}

.inside-list li {
    padding: 0.28rem 0;
    border-top: 1px solid var(--line);
}

.access-list li {
    display: grid;
    grid-template-columns: 92px 1fr;
    gap: 0.55rem;
    padding: 0.55rem 0;
    border-top: 1px solid var(--line);
}

.access-list strong {
    display: block;
    color: var(--ink);
    font-size: 0.82rem;
}

.preview-note {
    margin: 0.55rem 0 0;
    color: var(--muted);
    font-size: 0.78rem;
    line-height: 1.4;
}

:deep(.preview-menu) {
    list-style: none;
    margin: 0;
    padding: 0;
}

:deep(.preview-menu--nested) {
    margin: 0.15rem 0 0.25rem 0.85rem;
    padding-left: 0.35rem;
    border-left: 1px solid #eceff4;
}

:deep(.preview-link) {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    border: 0;
    background: transparent;
    color: #243044;
    font-size: 0.84rem;
    font-weight: 600;
    text-align: left;
    border-radius: 12px;
    padding: 0.48rem 0.7rem;
}

:deep(.preview-label) {
    flex: 1;
}

:deep(.preview-ico) {
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
    color: #6d28d9;
}

:deep(.preview-link:hover),
:deep(.preview-link.is-open),
:deep(.preview-link.is-selected) {
    background: #f3eefe;
}

:deep(.preview-link.is-conditional) {
    color: #8a5a12;
}

:deep(.preview-chevron) {
    color: #8b95a7;
    font-size: 0.72rem;
}

.sim-dialog__head {
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.sim-dialog__head p,
.sim-dialog .role-kicker {
    color: var(--muted);
}

.employee-results {
    margin-top: 0.4rem;
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
}

.employee-results button {
    width: 100%;
    display: flex;
    gap: 0.8rem;
    justify-content: space-between;
    padding: 0.7rem 0.8rem;
    border: 0;
    background: white;
    text-align: left;
}

.employee-results button:hover,
.employee-results button:focus {
    background: #f8f4ea;
}

.selected-employee,
.disclaimer {
    margin-top: 0.8rem;
}

.sim-grid {
    display: grid;
    grid-template-columns: minmax(240px, 0.85fr) 1.15fr;
    gap: 1rem;
    margin-top: 1rem;
}

.nav-node {
    margin: 0.35rem 0 0.35rem 0.4rem;
}

.nav-node__button {
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 0.45rem 0.55rem;
    background: #f4f6fa;
    font-weight: 650;
}

.state-visible > .nav-node__button,
.state-allowed {
    box-shadow: inset 3px 0 0 #1f8a4c;
}

.state-hidden > .nav-node__button,
.state-denied {
    box-shadow: inset 3px 0 0 #b42318;
}

.state-conditional > .nav-node__button,
.state-warning {
    box-shadow: inset 3px 0 0 #b7862f;
}

.state-unverified > .nav-node__button {
    box-shadow: inset 3px 0 0 #66758c;
}

.sim-module li {
    grid-template-columns: auto 1fr;
    align-items: start;
}

.state-pill {
    margin-top: 0.15rem;
    font-weight: 700;
}

.state-allowed,
.state-visible {
    color: #166534;
}

.state-denied,
.state-hidden {
    color: #9f1239;
}

.state-conditional,
.state-warning {
    color: #8a5a12;
}

@media (max-width: 900px) {
    .role-bar,
    .sim-grid,
    .panel-head--tools,
    .role-bar__actions {
        display: grid;
    }

    .builder-columns,
    .builder-workspace,
    .preview-shell,
    .access-list li {
        grid-template-columns: 1fr;
    }

    .explain-panel {
        border-left: 0;
        border-top: 1px solid var(--line);
        padding-left: 0;
        padding-top: 0.75rem;
    }

    .tool-row,
    .role-bar__actions {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .icon-card,
    .permission-row,
    .sim-dialog {
        transition: none;
        transform: none;
    }
}
</style>
