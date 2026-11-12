<template>
    <div class="card basic-data-table users-list">
        <div v-if="!isMobileViewport" class="card-header">
            <div class="row">
                <div class="col-md-6 text-start">
                    <h5 class="card-title mb-0">Users List</h5>
                </div>
                <div class="col-md-6 text-end">
                    <router-link to="/team-tree" class="btn btn-primary">
                        <iconify-icon icon="lucide:network" class="me-2"></iconify-icon>
                        Open Team Tree View
                    </router-link>
                </div>
            </div>
        </div>
               
        <div class="card">
            <div
                v-if="!isMobileViewport"
                class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-lr w-auto rounded-3 me-10" v-model="selectedShow"
                            style="border-radius: 10px; height: 2.4rem;">
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                        </select>
                        <span>entries per page</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <!-- Add User Button -->
                    <button v-if="this.$hasPermission('users-create')" 
                        class="btn btn-primary"
                        @click="addUser">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add User
                    </button>
                    
                    <label class="users-search">
                        <iconify-icon icon="lucide:search" class="users-search__icon"></iconify-icon>
                        <input
                            type="text"
                            v-model="searchText"
                            placeholder="Search name, email..."
                            aria-label="Search users"
                        />
                    </label>
                </div>
            </div>

            <div v-else class="agents-mobile__bar">
                <label class="users-search agents-mobile__search">
                    <input
                        type="text"
                        v-model="searchText"
                        placeholder="Search name, email..."
                        aria-label="Search users"
                    />
                    <iconify-icon icon="lucide:search" class="users-search__icon"></iconify-icon>
                </label>
                <div class="agents-mobile__chips">
                    <button
                        type="button"
                        class="agents-mobile__chip"
                        :class="{ 'is-on': statusFilter === 'active' }"
                        @click="toggleStatusShortcut('active')"
                    >Active</button>
                    <button
                        type="button"
                        class="agents-mobile__chip"
                        :class="{ 'is-on': statusFilter === 'in_active' }"
                        @click="toggleStatusShortcut('in_active')"
                    >Inactive</button>
                    <button
                        v-if="hasSuperAdminRole()"
                        type="button"
                        class="agents-mobile__chip"
                        :class="{ 'is-on': onlineOnly }"
                        @click="toggleOnlineShortcut"
                    >Online</button>
                    <button
                        type="button"
                        class="agents-mobile__chip"
                        :class="{ 'is-on': sortKey === 'last_login_at' && !sortAsc }"
                        @click="sortLatestLogin"
                    >Last login</button>
                </div>
                <div class="agents-mobile__tools">
                    <router-link to="/team-tree" class="agents-mobile__tool">
                        <iconify-icon icon="lucide:network"></iconify-icon>
                        <span>Team Tree</span>
                    </router-link>
                    <button
                        v-if="this.$hasPermission('users-create')"
                        type="button"
                        class="agents-mobile__tool agents-mobile__tool--add"
                        @click="addUser"
                    >
                        <iconify-icon icon="lucide:plus"></iconify-icon>
                        <span>Add</span>
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="card-body">
                <div v-if="isMobileViewport" class="agents-mobile__list">
                    <article
                        v-for="user in paginatedUsers"
                        :key="'m-' + user.id"
                        class="agents-mobile__card"
                        @click="viewUser(user.id)"
                    >
                        <span class="agents-mobile__avatar">
                            <img
                                :src="avatarUrl(user)"
                                :alt="user.name || ''"
                                width="44"
                                height="44"
                                @error="handleImageError"
                            />
                            <span v-if="canSeeOnlineStatus(user)" class="agents-mobile__online"></span>
                        </span>
                        <div class="agents-mobile__body">
                            <div class="agents-mobile__name">{{ user.name }}</div>
                            <div class="agents-mobile__email">{{ user.email }}</div>
                            <div class="agents-mobile__meta">
                                <span v-if="user.role_name" class="agents-mobile__role">{{ user.role_name.replace(/_/g, ' ') }}</span>
                                <span class="agents-mobile__branch">{{ user.branch || user.department || user.office_name || '—' }}</span>
                            </div>
                            <div class="agents-mobile__login" :class="{ 'is-online': canSeeOnlineStatus(user) }">
                                <span v-if="canSeeOnlineStatus(user)" class="agents-mobile__login-dot" aria-hidden="true"></span>
                                {{ canSeeOnlineStatus(user) ? 'Online' : 'Last login ' + formatLastLoginClock(user.last_login_at) }}
                            </div>
                        </div>
                        <div class="agents-mobile__side" @click.stop>
                            <div
                                v-if="(hasAdminRole() || hasSuperAdminRole() || hasBranchAdminRole()) && user.id != 1"
                                class="status-toggle"
                            >
                                <label class="toggle-switch">
                                    <input
                                        type="checkbox"
                                        :checked="user.status === 'active'"
                                        class="toggle-input"
                                        :disabled="statusLoading === user.id"
                                        @change="confirmStatusChange(user, user.status === 'active' ? 'in_active' : 'active')"
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <span v-else class="agents-mobile__status" :class="user.status === 'active' ? 'is-active' : 'is-inactive'">
                                {{ user.status === 'active' ? 'Active' : 'Inactive' }}
                            </span>
                            <div class="dropdown">
                                <button
                                    class="agents-mobile__more"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-label="Agent actions"
                                    :disabled="!hasAnyUserPermission() && !hasSuperAdminRole()"
                                    @click.stop
                                >
                                    <iconify-icon icon="lucide:ellipsis"></iconify-icon>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li v-if="this.$hasPermission('users-list')">
                                        <a class="dropdown-item" href="javascript:void(0)" @click="viewUser(user.id)">View</a>
                                    </li>
                                    <li v-if="this.$hasPermission('users-edit') && user.id != 1">
                                        <a class="dropdown-item" href="javascript:void(0)" @click="editUser(user.id)">Edit</a>
                                    </li>
                                    <li v-if="canSwitchTo(user)">
                                        <a class="dropdown-item" href="javascript:void(0)" @click="switchToUser(user)">Switch account</a>
                                    </li>
                                    <li v-if="this.$hasPermission('users-delete') && user.id != 1">
                                        <a class="dropdown-item text-danger" href="javascript:void(0)" @click="deleteUser(user)">Delete</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </article>
                </div>
                <!-- Bulk actions for the selected users (super admin only) -->
                <transition name="ubb">
                <div v-if="!isMobileViewport && canUseBulkActions && selectedUserIds.length" class="ubb" :class="{ 'is-busy': bulkBusy }">
                    <div class="ubb__count">
                        <span class="ubb__num">{{ selectedUserIds.length }}</span>
                        <span class="ubb__label">user{{ selectedUserIds.length === 1 ? '' : 's' }} selected</span>
                        <button type="button" class="ubb__clear" title="Clear selection" aria-label="Clear selection" @click="clearSelection">
                            <iconify-icon icon="lucide:x"></iconify-icon>
                        </button>
                    </div>

                    <div class="ubb__section">
                        <span class="ubb__section-label"><iconify-icon icon="lucide:shield"></iconify-icon> Role</span>
                        <div class="ubb__field">
                            <select class="ubb__select" v-model="bulkRoleId" aria-label="Role to assign">
                                <option :value="null" disabled>Choose role…</option>
                                <option v-for="role in bulkRoles" :key="role.id" :value="role.id">{{ role.name.replace(/_/g, ' ') }}</option>
                            </select>
                            <button type="button" class="ubb__btn ubb__btn--primary" :disabled="!bulkRoleId || bulkBusy" @click="runBulk('assign_role')">
                                Assign
                            </button>
                        </div>
                    </div>

                    <div class="ubb__section ubb__section--grow">
                        <span class="ubb__section-label"><iconify-icon icon="lucide:key-round"></iconify-icon> Permissions</span>
                        <div class="ubb__field">
                            <v-select
                                v-model="bulkPermissions"
                                :options="bulkAllPermissions"
                                multiple
                                placeholder="Choose permissions…"
                                class="ubb__perms"
                                :close-on-select="false"
                                :append-to-body="true"
                            />
                            <button type="button" class="ubb__btn ubb__btn--primary" :disabled="!bulkPermissions.length || bulkBusy" @click="runBulk('assign_permissions')">
                                Add<span v-if="bulkPermissions.length">&nbsp;({{ bulkPermissions.length }})</span>
                            </button>
                        </div>
                    </div>

                    <div class="ubb__section">
                        <span class="ubb__section-label"><iconify-icon icon="lucide:zap"></iconify-icon> Status</span>
                        <div class="ubb__field">
                            <button type="button" class="ubb__btn ubb__btn--success" :disabled="bulkBusy" @click="runBulk('activate')">
                                <iconify-icon icon="lucide:user-check"></iconify-icon> Activate
                            </button>
                            <button type="button" class="ubb__btn ubb__btn--muted" :disabled="bulkBusy" @click="runBulk('deactivate')">
                                <iconify-icon icon="lucide:user-x"></iconify-icon> Deactivate
                            </button>
                        </div>
                    </div>

                    <span v-if="bulkBusy" class="ubb__spinner" aria-label="Working"></span>
                </div>
                </transition>

                <div v-if="!isMobileViewport" class="table-responsive">
                    <table class="table bordered-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col" @click="sortBy('id')" class="sortable">
                                    <div class="form-check style-check d-flex align-items-center">
                                        <input
                                            v-if="canUseBulkActions"
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            title="Select all on this page"
                                            :checked="allOnPageSelected"
                                            :indeterminate.prop="someOnPageSelected && !allOnPageSelected"
                                            @click.stop
                                            @change="toggleSelectPage($event.target.checked)"
                                        >
                                        <label class="form-check-label d-flex align-items-center">
                                            ID
                                            <span v-if="sortKey === 'id'">
                                                <iconify-icon
                                                    :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                            </span>
                                        </label>
                                    </div>
                                </th>
                                <th scope="col" class="users-col-name sortable" @click="sortBy('name')">
                                    User
                                    <span v-if="sortKey === 'name'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <th scope="col">Email</th>
                                <th scope="col"  v-if="$hasPermission('users-code')">Biometric Code</th>
                                <th scope="col" v-if="$hasPermission('users-absent-email')">Absence Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Manager</th>
                                <th scope="col" class="users-col-meta">Branch</th>
                                <th scope="col" class="users-col-meta">Department</th>
                                
                                <th scope="col">Active/Inactive</th>
                                <th scope="col" @click="sortBy('last_login_at')" class="sortable">
                                    Last Login
                                    <span v-if="sortKey === 'last_login_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                                <!-- <th scope="col" v-if="hasAdminRole() || hasSuperAdminRole()">Location</th> -->

                                <th scope="col">Actions</th>
                                <th scope="col" @click="sortBy('created_at')" class="sortable">
                                    Created Date
                                    <span v-if="sortKey === 'created_at'">
                                        <iconify-icon :icon="sortAsc ? 'mdi:arrow-up' : 'mdi:arrow-down'"></iconify-icon>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in paginatedUsers" :key="user.id" :class="{ 'users-row--selected': selectedUserIds.includes(user.id) }">
                                <td>
                                    <div class="form-check style-check d-flex align-items-center">
                                        <input
                                            v-if="canUseBulkActions"
                                            class="form-check-input me-2"
                                            type="checkbox"
                                            :value="user.id"
                                            :disabled="user.id == 1"
                                            v-model="selectedUserIds"
                                        >
                                        <label class="form-check-label">{{ user.id }}</label>
                                    </div>
                                </td>
                                <td class="users-col-name">
                                    <div class="user-name-link" @click="viewUser(user.id)">
                                        <span class="user-name-link__avatar">
                                            <img
                                                :src="avatarUrl(user)"
                                                :alt="user.name || ''"
                                                width="32"
                                                height="32"
                                                @error="handleImageError"
                                            />
                                            <span v-if="canSeeOnlineStatus(user)" class="user-name-link__online"></span>
                                        </span>
                                        <span class="user-name-link__name">{{ user.name }}</span>
                                    </div>
                                </td>
                                <td>{{user.email}}</td>
                                <td  v-if="$hasPermission('users-code')">
                                <!-- لو عنده permission -->
                                <input 
                                    v-if="$hasPermission('users-code')"
                                    type="text"
                                    class="form-control form-control-sm"
                                    v-model="user.biometric_code"
                                    @blur="updateBiometricCode(user)"
                                    placeholder="Enter code"
                                    style="max-width: 120px;"
                                />
                            
                                <!-- لو معندوش permission -->
                                <span v-else>
                                    {{ user.biometric_code || '-' }}
                                </span>
                            </td>
                            <td v-if="$hasPermission('users-absent-email')">
                                <label class="toggle-switch">
                                    <input type="checkbox" class="toggle-input"
                                        :checked="user.receive_absent_email"
                                        :disabled="absentEmailLoading === user.id"
                                        @change="toggleAbsentEmail(user, $event.target.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                                <td>
                                    <span class="">{{ user.role_name?.replace(/_/g, ' ') }}</span>
                                </td>
                                <td>
                                    <p class="text-md mb-0 fw-medium" v-if="user.parent_name">{{ user.parent_name }}</p>
                                    <p v-else class="text-muted">-</p>
                                    <span class="text-muted" v-if="user.admin_parent_name && user.parent_id != user.admin_parent_id">{{ user.admin_parent_name }}</span>
                                </td>
                             <td class="users-col-meta">
                                <span>{{ user.branch || user.admin_parent_name || '—' }}</span>
                            </td>
                            <td class="users-col-meta">
                                <span>{{ user.department || user.office_name || '—' }}</span>
                            </td>
                             <td>
                                    <div class="status-toggle" v-if="(hasAdminRole() || hasSuperAdminRole() || hasBranchAdminRole()) && user.id != 1">
                                        <label class="toggle-switch">
                                            <input 
                                                type="checkbox" 
                                                :checked="user.status === 'active'"
                                                @change="confirmStatusChange(user, user.status === 'active' ? 'in_active' : 'active')"
                                                :disabled="statusLoading === user.id"
                                                class="toggle-input">
                                            <span class="toggle-slider"></span>
                                            <span class="toggle-labels">
                                                <!-- <span class="active-label">Active</span>
                                                <span class="inactive-label">Inactive</span> -->
                                            </span>
                                        </label>
                                        <div v-if="statusLoading === user.id" class="loading-spinner">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        </div>
                                    </div>
                                    <span v-else class="text-sm fw-medium" :class="user.status === 'active' ? 'text-success' : 'text-muted'">
                                        {{ user.status === 'active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="canSeeOnlineStatus(user)" class="text-sm text-success fw-semibold d-inline-flex align-items-center gap-1">
                                        <span class="user-online-pill-dot" aria-hidden="true"></span>
                                        Online
                                    </span>
                                    <span v-else-if="user.last_login_at" class="text-sm">
                                        {{ formatLastLogin(user.last_login_at) }}
                                    </span>
                                    <span v-else class="text-muted text-sm">Never</span>
                                </td>
                                <!-- <td v-if="hasAdminRole() || hasSuperAdminRole()">
                                    <a v-if="user.last_login_location && user.last_login_lat && user.last_login_lng"
                                       :href="mapsLink(user)"
                                       target="_blank"
                                       rel="noopener"
                                       class="text-sm"
                                       :title="user.last_login_location">
                                        <iconify-icon icon="mdi:map-marker-outline" class="me-1"></iconify-icon>{{ user.last_login_location }}
                                    </a>
                                    <span v-else-if="user.last_login_location" class="text-sm" :title="user.last_login_location">
                                        <iconify-icon icon="mdi:map-marker-outline" class="me-1"></iconify-icon>{{ user.last_login_location }}
                                    </span>
                                    <span v-else class="text-muted text-sm">-</span>
                                </td> -->
                                    <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                type="button"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                                :disabled="!hasAnyUserPermission() && !hasSuperAdminRole()">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <!-- View Action -->
                                            <li v-if="this.$hasPermission('users-list')">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="viewUser(user.id)">
                                                    <iconify-icon icon="iconamoon:eye-light" class="me-2"></iconify-icon>
                                                    View User
                                                </a>
                                            </li>

                                            <!-- Edit Action -->
                                            <li v-if="this.$hasPermission('users-edit') && user.id != 1">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="editUser(user.id)">
                                                    <iconify-icon icon="lucide:edit" class="me-2"></iconify-icon>
                                                    Edit User
                                                </a>
                                            </li>

                                            <!-- Switch Account (super_admin; user #30 → sales only) -->
                                            <li v-if="canSwitchTo(user)">
                                                <a class="dropdown-item" href="javascript:void(0)" @click="switchToUser(user)">
                                                    <iconify-icon icon="lucide:repeat" class="me-2"></iconify-icon>
                                                    Switch to This Account
                                                </a>
                                            </li>

                                            <!-- Delete Action -->
                                            <li v-if="this.$hasPermission('users-delete') && user.id != 1">
                                                <a class="dropdown-item text-danger" href="javascript:void(0)" @click="deleteUser(user)">
                                                    <iconify-icon icon="mingcute:delete-2-line" class="me-2"></iconify-icon>
                                                    Delete User
                                                </a>
                                            </li>

                                            <!-- No Actions Available -->
                                            <li v-if="!hasAnyUserPermission() && !hasSuperAdminRole()">
                                                <span class="dropdown-item text-muted">No actions available</span>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td>{{ formatDate(user.created_at) }}</td>
                            
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="loading text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading users...</p>
                </div>

                <!-- No Data State -->
                <div v-if="!loading && users.length === 0" class="no-data text-center py-4">
                    <iconify-icon icon="lucide:users" class="text-muted mb-2" width="48"></iconify-icon>
                    <p>No users found</p>
                    <button v-if="this.$hasPermission('users-create')" class="btn btn-primary mt-2" @click="addUser">
                        <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                        Add First User
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && users.length > 0" class="users-list__pager d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span>
                        Showing {{ startIndex + 1 }} to {{ endIndex }} of {{ totalEntries }} entries
                    </span>
                    <ul class="pagination d-flex flex-wrap align-items-center gap-2 justify-content-center">
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                :class="{ disabled: currentPage === 1 }">
                                <iconify-icon icon="ep:d-arrow-left" class="text-xl"></iconify-icon>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage - 1)"
                                :class="{ disabled: currentPage === 1 }">
                                <iconify-icon icon="ep:arrow-left"></iconify-icon>
                            </a>
                        </li>

                        <li v-for="page in displayedPages" :key="page" class="page-item">
                            <a v-if="page !== '...'" href="javascript:void(0)"
                                class="page-link fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px"
                                :class="{
                                    'bg-primary-600 text-white': currentPage === page,
                                    'bg-primary-50 text-secondary-light': currentPage !== page
                                }" @click="goToPage(page)">
                                {{ page }}
                            </a>
                            <span v-else class="page-link bg-transparent border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px">
                                ...
                            </span>
                        </li>

                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage + 1)"
                                :class="{ disabled: currentPage === totalPages }">
                                <iconify-icon icon="ep:arrow-right" class="text-xl"></iconify-icon>
                            </a>
                        </li>

                        <li class="page-item">
                            <a class="page-link text-secondary-light fw-medium radius-4 border-0 px-10 py-10 d-flex align-items-center justify-content-center h-32-px w-32-px bg-base"
                                href="javascript:void(0)" @click="goToPage(currentPage + 1)"
                                :class="{ disabled: currentPage === totalPages }">
                                <iconify-icon icon="ep:d-arrow-right" class="text-xl"></iconify-icon>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
import { useImpersonation } from '@/composables/useImpersonation.js';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import vSelect from 'vue-select';
export default {
    name: 'UsersTable',
    // vue-select is not registered globally (main.js only patches its defaults).
    components: { vSelect },
    setup() {
        const { switchToUser: rawSwitchToUser } = useImpersonation();
        const { isMobileViewport } = useMobileNavigation();
        return { rawSwitchToUser, isMobileViewport };
    },
    data() {
        return {
            loading: true,
            selectedShow: 10,
            searchText: '',
            currentPage: 1,
            sortKey: '',
            sortAsc: true,
            statusFilter: '',
            onlineOnly: false,
            users: [],
            meta: { total: 0, last_page: 1, current_page: 1, per_page: 10 },
            statusLoading: null,
            searchDebounceTimer: null,
            fetchAbortController: null,
            defaultAvatar: '/assets/images/user.png',
            absentEmailLoading: null,

            // Bulk actions — super admin only (kept across pages until cleared)
            selectedUserIds: [],
            bulkRoles: [],
            bulkRoleId: null,
            bulkAllPermissions: [],
            bulkPermissions: [],
            bulkBusy: false,
        };
    },
    computed: {
        /** Bulk actions are super admin only (UserController::bulkAction checks it too). */
        canUseBulkActions() {
            return !!this.hasSuperAdminRole();
        },
        selectablePageIds() {
            return this.users.filter(u => u.id != 1).map(u => u.id);
        },
        allOnPageSelected() {
            return this.selectablePageIds.length > 0
                && this.selectablePageIds.every(id => this.selectedUserIds.includes(id));
        },
        someOnPageSelected() {
            return this.selectablePageIds.some(id => this.selectedUserIds.includes(id));
        },
        entriesPerPage() {
            return Number(this.selectedShow);
        },
        // Pagination, search, and sorting now happen on the backend (see fetchUsers) —
        // `users` is already just the current page, so this is a plain alias kept so the
        // template doesn't need to change.
        paginatedUsers() {
            return this.users;
        },
        totalEntries() {
            return this.meta.total;
        },
        totalPages() {
            return this.meta.last_page;
        },
        startIndex() {
            return (this.meta.current_page - 1) * this.meta.per_page;
        },
        endIndex() {
            return this.startIndex + this.users.length;
        },
        displayedPages() {
            const pages = [];
            const total = this.totalPages;
            const current = this.currentPage;
            
            if (total <= 7) {
                for (let i = 1; i <= total; i++) {
                    pages.push(i);
                }
            } else {
                if (current <= 4) {
                    for (let i = 1; i <= 5; i++) {
                        pages.push(i);
                    }
                    pages.push('...');
                    pages.push(total);
                } else if (current >= total - 3) {
                    pages.push(1);
                    pages.push('...');
                    for (let i = total - 4; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    pages.push(1);
                    pages.push('...');
                    for (let i = current - 1; i <= current + 1; i++) {
                        pages.push(i);
                    }
                    pages.push('...');
                    pages.push(total);
                }
            }
            
            return pages;
        }
    },
    watch: {
        selectedShow() {
            this.currentPage = 1;
            this.fetchUsers();
        },
        searchText() {
            this.currentPage = 1;
            // Debounce so we don't hit the API on every keystroke.
            clearTimeout(this.searchDebounceTimer);
            this.searchDebounceTimer = setTimeout(() => {
                this.fetchUsers();
            }, 400);
        }
    },
    mounted() {
        this.fetchUsers();
        if (this.canUseBulkActions) this.loadBulkOptions();
    },
    methods: {
        // ---------- Bulk actions (super admin only) ----------
        async loadBulkOptions() {
            try {
                const [rolesRes, permsRes] = await Promise.all([api.get('/roles'), api.get('/permissions')]);
                const roles = rolesRes.data?.data || rolesRes.data || [];
                this.bulkRoles = (Array.isArray(roles) ? roles : []).map(r => ({ id: r.id, name: r.name }));
                const perms = permsRes.data?.data || permsRes.data || [];
                this.bulkAllPermissions = (Array.isArray(perms) ? perms : []).map(p => p.name).sort();
            } catch (error) {
                console.error('Error loading bulk action options:', error);
            }
        },

        toggleSelectPage(checked) {
            const pageIds = this.selectablePageIds;
            if (checked) {
                this.selectedUserIds = [...new Set([...this.selectedUserIds, ...pageIds])];
            } else {
                this.selectedUserIds = this.selectedUserIds.filter(id => !pageIds.includes(id));
            }
        },

        clearSelection() {
            this.selectedUserIds = [];
            this.bulkRoleId = null;
            this.bulkPermissions = [];
        },

        async runBulk(action) {
            const count = this.selectedUserIds.length;
            if (!count) return;

            const roleName = this.bulkRoles.find(r => r.id === this.bulkRoleId)?.name?.replace(/_/g, ' ');
            const dialogs = {
                assign_role: {
                    title: 'Assign role',
                    message: `Set the role of ${count} user(s) to "${roleName}"? It replaces their current role.`,
                    confirmText: 'Assign role',
                    type: 'info',
                },
                assign_permissions: {
                    title: 'Add permissions',
                    message: `Add ${this.bulkPermissions.length} permission(s) to ${count} user(s)?`,
                    confirmText: 'Add permissions',
                    type: 'info',
                },
                activate: {
                    title: 'Activate users',
                    message: `Activate ${count} user(s)? Each one gets an "account activated" email.`,
                    confirmText: 'Activate',
                    type: 'success',
                },
                deactivate: {
                    title: 'Deactivate users',
                    message: `Deactivate ${count} user(s)? They will no longer be able to work in the CRM.`,
                    confirmText: 'Deactivate',
                    type: 'warning',
                },
            };
            const confirm = this.$showConfirmation || window.$showConfirmation;
            const confirmed = confirm
                ? await confirm({ ...dialogs[action], cancelText: 'Cancel' })
                : window.confirm(dialogs[action].message);
            if (!confirmed) return;

            this.bulkBusy = true;
            try {
                const payload = { user_ids: this.selectedUserIds, action };
                if (action === 'assign_role') payload.role_id = this.bulkRoleId;
                if (action === 'assign_permissions') payload.permissions = this.bulkPermissions;

                const res = await api.post('/users/bulk-action', payload);
                const data = res.data?.data || {};
                const skipped = data.skipped || [];

                this.showNotification(res.data?.message || 'Done', skipped.length ? 'warning' : 'success');
                if (skipped.length) {
                    const lines = skipped.slice(0, 10).map(s => `${s.name || '#' + s.id}: ${s.reason}`);
                    if (skipped.length > 10) lines.push(`…and ${skipped.length - 10} more`);
                    this.showNotification('Skipped — ' + lines.join(' · '), 'warning');
                }

                // Keep only the skipped users selected so they can be fixed and retried.
                this.selectedUserIds = skipped.map(s => s.id).filter(id => this.selectedUserIds.includes(id));
                if (action === 'assign_permissions') this.bulkPermissions = [];

                await this.fetchUsers();
            } catch (error) {
                console.error('Bulk action failed:', error);
                this.showNotification(error.response?.data?.message || 'Bulk action failed', 'error');
            } finally {
                this.bulkBusy = false;
            }
        },

        async updateBiometricCode(user) {
            try {
                const token = localStorage.getItem('token');
        
                const response = await fetch(API_ENDPOINTS.USER_BIOMETRIC(user.id), {
                    method: 'PUT',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        biometric_code: user.biometric_code
                    })
                });
        
                const result = await response.json();
        
                if (!response.ok) {
                    if (result.errors && result.errors.biometric_code) {
                        throw new Error(result.errors.biometric_code[0]);
                    }
                    throw new Error(result.message || 'Failed to update biometric code');
                }
        
                this.showNotification(`Biometric code updated for ${user.name}`, 'success');
        
            } catch (error) {
                console.error('Error updating biometric code:', error);
        
                this.showNotification(error.message, 'error');
            }
        },
        async toggleAbsentEmail(user, value) {
                this.absentEmailLoading = user.id;
                const previous = user.receive_absent_email;
                user.receive_absent_email = value; // optimistic
                try {
                    const response = await fetch(API_ENDPOINTS.USER_ABSENT_EMAIL(user.id), {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + localStorage.getItem('token'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ receive_absent_email: value }),
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'Failed to update');
                    this.showNotification(
                        `Absence email ${value ? 'enabled' : 'disabled'} for ${user.name}`, 'success'
                    );
                } catch (error) {
                    user.receive_absent_email = previous; // revert
                    this.showNotification(error.message, 'error');
                } finally {
                    this.absentEmailLoading = null;
                }
            },
        hasAnyUserPermission() {
            return this.$hasPermission('users-list') || 
                   this.$hasPermission('users-edit') || 
                   this.$hasPermission('users-delete');
        },
        
        hasAdminRole() {
            const userData = JSON.parse(localStorage.getItem('user') || '{}');
            return userData.roles && userData.roles.includes('admin');
        },
         hasBranchAdminRole() {
            const userData = JSON.parse(localStorage.getItem('user') || '{}');
            return userData.roles && userData.roles.includes('branch_admin');
        },

        hasSuperAdminRole() {
            const userData = JSON.parse(localStorage.getItem('user') || '{}');
            return userData.roles && userData.roles.includes('super_admin');
        },

        currentUserId() {
            try {
                const userData = JSON.parse(localStorage.getItem('user') || '{}');
                return userData.id;
            } catch {
                return null;
            }
        },

        // "Switch account" (mirrors User::canSwitchInto): super admin → any active user;
        // user #30 → sales users only. role_name is the formatted first role ("Sales").
        canSwitchTo(user) {
            if (!user || user.id === this.currentUserId() || user.status !== 'active') return false;
            if (this.hasSuperAdminRole()) return true;
            return Number(this.currentUserId()) === 30
                && String(user.role_name || '').toLowerCase() === 'sales';
        },

        // Log in as this user (see "Switch to This Account" action). Stashes the
        // current session first so "Return to my account" (shown app-wide while
        // switched) can restore it without a second login.
        async switchToUser(user) {
            if (!this.canSwitchTo(user)) {
                this.showNotification('You are not allowed to switch to this account', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Switch account?',
                `You will be logged in as "${user.name}". You can return to your own account anytime from the button at the top of the page.`,
                'info'
            );
            if (!confirmed) return;

            try {
                await this.rawSwitchToUser(user);
            } catch (error) {
                console.error('Error switching account:', error);
                const message = error.response?.data?.message || error.message || 'Failed to switch account';
                this.showNotification(message, 'error');
            }
        },

        // New method to confirm status change
        async confirmStatusChange(user, newStatus) {
            // Check permissions
            if (!this.hasAdminRole() && !this.hasSuperAdminRole() && !this.hasBranchAdminRole()) {
                this.showNotification('Only administrators can update user status', 'warning');
                // Reset toggle to original state
                this.$nextTick(() => {
                    user.status = user.status;
                });
                return;
            }

            // If already in the desired status, do nothing
            if (user.status === newStatus) {
                return;
            }

            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            const actionText = newStatus === 'active' ? 'activate' : 'deactivate';
            const actionTitle = newStatus === 'active' ? 'Activate User' : 'Deactivate User';
            const actionMessage = newStatus === 'active' 
                ? `Are you sure you want to activate user "${user.name}"? This will allow them to access the system.`
                : `Are you sure you want to deactivate user "${user.name}"? This will prevent them from accessing the system.`;

            const confirmed = await this.showConfirm(
                actionTitle,
                actionMessage,'info'
                // newStatus === 'active' ? 'info' : 'warning'
            );

            if (confirmed) {
                await this.updateUserStatus(user, newStatus);
            } else {
                // Reset toggle to original state if user cancels
                this.$nextTick(() => {
                    user.status = user.status;
                });
            }
        },

        // Main function to handle status update
        async updateUserStatus(user, newStatus) {
            const actionText = newStatus === 'active' ? 'activate' : 'deactivate';

            try {
                this.statusLoading = user.id;

                // Call API to update status
                const updatedUser = await this.updateUserStatusAPI(user.id, newStatus);
                
                // Update local data
                const userIndex = this.users.findIndex(u => u.id === user.id);
                if (userIndex !== -1) {
                    this.users[userIndex].status = newStatus;
                }

                this.showNotification(`User "${user.name}" has been ${actionText}d successfully.`, 'success');
                
            } catch (error) {
                console.error(`Error ${actionText}ing user:`, error);
                
                // Reset to original status on error
                this.$nextTick(() => {
                    user.status = user.status;
                });
                
                this.showNotification(`Failed to ${actionText} user: ${error.message}`, 'error');
            } finally {
                this.statusLoading = null;
            }
        },

        // API methods — pagination, search, and sorting are all done server-side now
        // (passing page/per_page opts the backend into `paginate()` instead of it
        // returning every user), so this only ever fetches the one page being viewed.
        async fetchUsers() {
            // Cancel any still-in-flight request so a slow earlier response (e.g. from a
            // previous search keystroke) can't land after and overwrite newer results.
            if (this.fetchAbortController) {
                this.fetchAbortController.abort();
            }
            const abortController = new AbortController();
            this.fetchAbortController = abortController;

            try {
                this.loading = true;

                const token = localStorage.getItem('token');
                const params = new URLSearchParams({
                    page: String(this.currentPage),
                    per_page: String(this.entriesPerPage),
                    sort_by: this.sortKey || 'created_at',
                    sort_dir: this.sortAsc ? 'asc' : 'desc',
                });
                if (this.searchText) {
                    params.set('search', this.searchText);
                }
                if (this.statusFilter) {
                    params.set('status', this.statusFilter);
                }
                if (this.onlineOnly && this.hasSuperAdminRole()) {
                    params.set('online', '1');
                }

                const response = await fetch(`${API_ENDPOINTS.USERS}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    signal: abortController.signal
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                this.users = Array.isArray(data.data) ? data.data : [];
                this.meta = {
                    total: data.meta?.total ?? this.users.length,
                    last_page: data.meta?.last_page ?? 1,
                    current_page: data.meta?.current_page ?? this.currentPage,
                    per_page: data.meta?.per_page ?? this.entriesPerPage,
                };

            } catch (error) {
                if (error?.name === 'AbortError') {
                    // Superseded by a newer request — its result will take over.
                    return;
                }
                console.error('Error fetching users:', error);
                this.users = [];
                this.meta = { total: 0, last_page: 1, current_page: 1, per_page: this.entriesPerPage };
                this.showNotification('Failed to load users. Please try again.', 'error');
            } finally {
                if (this.fetchAbortController === abortController) {
                    this.loading = false;
                }
            }
        },

        // API Function to update user status
        async updateUserStatusAPI(userId, status) {
            const token = localStorage.getItem('token');
            
            const response = await fetch(API_ENDPOINTS.USER_STATUS(userId), {
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    status: status
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `Failed to update user status`);
            }

            const result = await response.json();
            return result.data;
        },

        // Existing action methods
        addUser() {
            if (!this.$hasPermission('users-create')) {
                this.showNotification('You do not have permission to create users', 'warning');
                return;
            }
            this.$router.push('/add-user');
        },

        viewUser(userId) {
            if (!this.$hasPermission('users-list')) {
                this.showNotification('You do not have permission to view users', 'warning');
                return;
            }
            this.$router.push(`/users/${userId}`);
        },

        editUser(userId) {
            if (!this.$hasPermission('users-edit')) {
                this.showNotification('You do not have permission to edit users', 'warning');
                return;
            }
            this.$router.push(`/users/${userId}/edit`);
        },

        async deleteUser(user) {
            if (!this.$hasPermission('users-delete')) {
                this.showNotification('You do not have permission to delete users', 'warning');
                return;
            }

            const confirmed = await this.showConfirm(
                'Are you sure?', 
                `You are about to delete user "${user.name}". This action cannot be undone!`,
                'warning'
            );

            if (confirmed) {
                try {
                    this.loading = true;
                    const token = localStorage.getItem('token');
                    const response = await fetch(API_ENDPOINTS.USER_BY_ID(user.id), {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });

                    if (response.ok) {
                        this.showNotification(`User "${user.name}" has been deleted successfully.`, 'success');
                        // Re-fetch this page rather than splicing locally — `meta.total`/
                        // `last_page` need to stay accurate now that the backend paginates.
                        if (this.users.length === 1 && this.currentPage > 1) {
                            this.currentPage -= 1;
                        }
                        await this.fetchUsers();
                    } else {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to delete user');
                    }
                } catch (error) {
                    console.error('Error deleting user:', error);
                    this.showNotification(`Failed to delete user: ${error.message}`, 'error');
                } finally {
                    this.loading = false;
                }
            }
        },

        // Helper methods
        showNotification(message, type = 'info') {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
                // Fallback notification
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 5000);
            }
        },

        showConfirm(title, text, type = 'warning') {
            return new Promise((resolve) => {
                const confirmDiv = document.createElement('div');
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger', 
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-warning';
                
                confirmDiv.className = `alert ${alertClass} position-fixed`;
                confirmDiv.style.cssText = 'top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 400px;';
                confirmDiv.innerHTML = `
                    <h5 class="alert-heading">${title}</h5>
                    <p class="mb-3">${text}</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                        <button class="btn ${type === 'warning' ? 'btn-danger' : 'btn-primary'}" id="confirmOk">
                            ${type === 'warning' ? 'Delete' : 'Confirm'}
                        </button>
                    </div>
                `;
                
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998;';
                
                document.body.appendChild(overlay);
                document.body.appendChild(confirmDiv);

                document.getElementById('confirmOk').onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(true);
                };
                
                document.getElementById('confirmCancel').onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(false);
                };

                overlay.onclick = () => {
                    document.body.removeChild(overlay);
                    document.body.removeChild(confirmDiv);
                    resolve(false);
                };
            });
        },

        toggleStatusShortcut(status) {
            this.statusFilter = this.statusFilter === status ? '' : status;
            this.currentPage = 1;
            this.fetchUsers();
        },

        toggleOnlineShortcut() {
            this.onlineOnly = !this.onlineOnly;
            this.currentPage = 1;
            this.fetchUsers();
        },

        sortLatestLogin() {
            if (this.sortKey === 'last_login_at' && !this.sortAsc) {
                this.sortKey = '';
                this.sortAsc = true;
            } else {
                this.sortKey = 'last_login_at';
                this.sortAsc = false;
            }
            this.currentPage = 1;
            this.fetchUsers();
        },

        sortBy(key) {
            if (this.sortKey === key) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortKey = key;
                this.sortAsc = true;
            }
            this.currentPage = 1;
            this.fetchUsers();
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
                this.currentPage = page;
                this.fetchUsers();
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        /** Google Maps link for the user's last-login coordinates. */
        mapsLink(user) {
            return `https://www.google.com/maps/search/?api=1&query=${user.last_login_lat},${user.last_login_lng}`;
        },

        parseLoginTime(timestamp) {
            if (!timestamp) return null;
            const raw = String(timestamp).trim();
            let normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
            if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(normalized)) {
                normalized += '+04:00';
            }
            const loginTime = new Date(normalized);
            return Number.isNaN(loginTime.getTime()) ? null : loginTime;
        },

        formatLastLoginClock(timestamp) {
            const loginTime = this.parseLoginTime(timestamp);
            if (!loginTime) return 'Never';
            const yearFormat = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Dubai', year: 'numeric' });
            const sameYear = yearFormat.format(loginTime) === yearFormat.format(new Date());
            return loginTime.toLocaleString('en-US', {
                timeZone: 'Asia/Dubai',
                month: 'short',
                day: 'numeric',
                year: sameYear ? undefined : 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        },

        formatLastLogin(timestamp) {
            const loginTime = this.parseLoginTime(timestamp);
            if (!loginTime) return 'Never';
            
            const now = new Date();
            const diffMs = now - loginTime;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return loginTime.toLocaleDateString('en-US', {
                timeZone: 'Asia/Dubai',
                month: 'short',
                day: 'numeric'
            });
        },

        isUserOnline(user) {
            const lastLogin = this.parseLoginTime(user.last_login_at);
            if (!lastLogin) return false;
            const diffMinutes = (Date.now() - lastLogin.getTime()) / (1000 * 60);
            return diffMinutes >= 0 && diffMinutes <= 15;
        },

        /** Online highlight is super_admin only. */
        canSeeOnlineStatus(user) {
            return this.hasSuperAdminRole() && this.isUserOnline(user);
        },

        /**
         * Laravel UserResource uses asset('storage/...'), which often points at APP_URL
         * (e.g. http://127.0.0.1:8001) while the SPA runs on another host. Use the same
         * origin as the page for /storage/ URLs so images load in production.
         */
       resolveMediaUrl(url) {
            // Return early if url is null, undefined, or not a string
            if (!url || typeof url !== 'string') return this.defaultAvatar;
            
            try {
                // Check if it's already a valid URL
                const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : undefined);
                const path = parsed.pathname + parsed.search;
                
                if (!path.includes('/storage/')) {
                    return parsed.href;
                }
                
                const badLocal = /^(127\.0\.0\.1|localhost)$/i.test(parsed.hostname);
                const pageOrigin = typeof window !== 'undefined' ? window.location.origin : '';
                
                if (!pageOrigin) return parsed.href;
                if (badLocal || parsed.origin !== pageOrigin) {
                    return `${pageOrigin}${path}`;
                }
                return parsed.href;
            } catch (error) {
                // URL parsing failed, try to handle relative paths
                if (typeof window === 'undefined') return this.defaultAvatar;
                
                if (typeof url === 'string' && url.startsWith('/')) {
                    return `${window.location.origin}${url}`;
                }
                
                if (typeof url === 'string' && !url.startsWith('http')) {
                    return `${window.location.origin}/storage/${url.replace(/^\/+/, '')}`;
                }
                
                return this.defaultAvatar;
            }
        },

        avatarUrl(user) {
            const raw = user?.avatar;
            if (!raw) return this.defaultAvatar;
            return this.resolveMediaUrl(raw) || this.defaultAvatar;
        },

        handleImageError(event) {
            const el = event.target;
            if (el.dataset.avatarFallback === '1') return;
            el.dataset.avatarFallback = '1';
            el.src = this.defaultAvatar;
        }
    }
};
</script>

<style scoped>
/* ================= Bulk action bar ================= */
.ubb {
    position: sticky;
    top: 8px;
    z-index: 20;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 0;
    padding: 12px 14px;
    margin-bottom: 14px;
    border: 1px solid rgba(var(--color-primary-rgb, 13, 110, 253), 0.25);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(16, 24, 40, 0.08), 0 1px 2px rgba(16, 24, 40, 0.04);
}
.ubb.is-busy {
    opacity: .85;
}
.ubb-enter-active, .ubb-leave-active {
    transition: opacity .18s ease, transform .18s ease;
}
.ubb-enter-from, .ubb-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

.ubb__count {
    display: flex;
    align-items: center;
    gap: 8px;
    align-self: center;
    padding: 6px 8px 6px 6px;
    margin-right: 6px;
    border-radius: 999px;
    background: rgba(var(--color-primary-rgb, 13, 110, 253), 0.08);
    white-space: nowrap;
}
.ubb__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 13px;
    color: #fff;
    background: var(--color-primary, #0d6efd);
}
.ubb__label {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
}
.ubb__clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #6b7280;
    transition: background .15s, color .15s;
}
.ubb__clear:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #111827;
}

.ubb__section {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 14px;
    border-left: 1px solid #eef0f3;
}
.ubb__section--grow {
    flex: 1 1 340px;
}
.ubb__section-label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #9ca3af;
}
.ubb__field {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ubb__select {
    height: 34px;
    min-width: 170px;
    padding: 0 30px 0 10px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    font-size: 13px;
    color: #111827;
    text-transform: capitalize;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none;
}
.ubb__select:focus {
    outline: none;
    border-color: var(--color-primary, #0d6efd);
    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb, 13, 110, 253), 0.12);
}

.ubb__perms {
    flex: 1;
    min-width: 220px;
}
.ubb__perms :deep(.vs__dropdown-toggle) {
    min-height: 34px;
    border-color: #e2e8f0;
    border-radius: 9px;
}
.ubb__perms :deep(.vs__selected) {
    font-size: 12px;
    border-radius: 6px;
}

.ubb__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 34px;
    padding: 0 12px;
    border: 1px solid transparent;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    transition: background .15s, border-color .15s, color .15s, opacity .15s;
}
.ubb__btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}
.ubb__btn--primary {
    color: #fff;
    background: var(--color-primary, #0d6efd);
}
.ubb__btn--primary:not(:disabled):hover {
    filter: brightness(.93);
}
.ubb__btn--success {
    color: #15803d;
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.ubb__btn--success:not(:disabled):hover {
    background: #dcfce7;
}
.ubb__btn--muted {
    color: #374151;
    background: #f9fafb;
    border-color: #e5e7eb;
}
.ubb__btn--muted:not(:disabled):hover {
    background: #f3f4f6;
}
.ubb__btn--danger {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}
.ubb__btn--danger:not(:disabled):hover {
    background: #fee2e2;
}

.ubb__spinner {
    align-self: center;
    width: 18px;
    height: 18px;
    margin-left: auto;
    border: 2px solid rgba(var(--color-primary-rgb, 13, 110, 253), 0.25);
    border-top-color: var(--color-primary, #0d6efd);
    border-radius: 50%;
    animation: ubb-spin .7s linear infinite;
}
@keyframes ubb-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 1100px) {
    .ubb__section {
        padding: 0 10px;
    }
}

.users-row--selected td {
    background: rgba(var(--color-primary-rgb, 13, 110, 253), 0.05) !important;
}
.users-list.card {
    height: auto;
    min-height: 0;
    margin-bottom: 0;
}

.users-list > .card-header {
    padding: 14px 18px;
}

.users-list > .card-header .card-title {
    font-size: 16px !important;
}

.users-list > .card {
    margin-bottom: 0;
    box-shadow: none;
}

.users-list .card-header {
    padding: 12px 18px;
}

.users-list .card-body {
    padding: 8px 16px 16px;
}

.users-list .btn {
    padding: 6px 12px;
    font-size: 14px;
    min-height: 36px;
}

.users-list .form-select,
.users-list .form-control {
    height: 36px !important;
    min-height: 36px;
    font-size: 14px;
    padding-top: 4px;
    padding-bottom: 4px;
}

.users-list :deep(.bordered-table) {
    min-width: 0;
    width: 100%;
}

.users-list :deep(.bordered-table thead tr th),
.users-list :deep(.bordered-table tbody tr td) {
    padding: 10px 12px !important;
    font-size: 14px;
    line-height: 1.35;
}

.users-list :deep(.bordered-table thead tr th) {
    font-size: 13px;
    font-weight: 600;
}

.users-list .users-col-name {
    min-width: 180px;
}

.users-list :deep(.users-col-meta) {
    min-width: 110px;
    white-space: nowrap;
}

.users-search {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    min-width: 220px;
    padding: 0 12px;
    margin: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
}

.users-search:focus-within {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.12);
}

.users-search__icon {
    flex-shrink: 0;
    font-size: 16px;
    color: #94a3b8;
}

.users-search input {
    width: 160px;
    height: 100%;
    border: 0;
    outline: none;
    background: transparent;
    font-size: 14px;
    color: #0f172a;
    padding: 0;
}

.users-search input::placeholder {
    color: #94a3b8;
}

.users-list .user-name-link {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    cursor: pointer;
}

.users-list .user-name-link__avatar {
    position: relative;
    flex-shrink: 0;
    width: 32px;
    height: 32px;
}

.users-list .user-name-link__avatar img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}

.users-list .user-name-link__online {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid #fff;
}

.users-list .user-online-pill-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    display: inline-block;
    flex-shrink: 0;
}

.users-list .user-name-link__name {
    font-size: 14px;
    font-weight: 500;
    line-height: 1.2;
    color: #0f172a;
    white-space: nowrap;
}

.users-list .user-name-link:hover .user-name-link__name {
    color: #0B0736;
    text-decoration: underline;
}

.users-list .form-control-sm {
    max-width: 110px;
    height: 32px !important;
    min-height: 32px;
    padding: 4px 8px;
}

.users-list__pager {
    margin-top: 14px;
    font-size: 14px;
}

.agents-mobile__chips {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.agents-mobile__chips::-webkit-scrollbar {
    display: none;
}

.agents-mobile__chip {
    flex: 0 0 auto;
    height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid #efe6f6;
    background: #fff;
    color: #4b5563;
    font-size: 12px;
    font-weight: 700;
}

.agents-mobile__chip.is-on {
    background: var(--gradient-primary) !important;
    border-color: var(--color-primary);
    color: #ffffff !important;
}

.agents-mobile__bar {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px 12px 0;
}

.agents-mobile__search {
    width: 100%;
    min-width: 0;
    height: 46px;
    border-radius: 999px;
    border: 1px solid #ece7f2;
    padding: 0 14px 0 16px;
    background: #fff;
}

.agents-mobile__search input {
    width: 100%;
    flex: 1 1 auto;
    font-size: 16px;
}

.agents-mobile__search .users-search__icon {
    color: #f5a524;
    font-size: 18px;
    margin-left: 8px;
}

.agents-mobile__tools {
    display: flex;
    gap: 8px;
}

.agents-mobile__tool {
    flex: 1 1 0;
    height: 40px;
    border-radius: 12px;
    border: 1px solid #efe6f6;
    background: #f6f1fb;
    color: var(--color-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}

.agents-mobile__tool--add {
    background: var(--gradient-primary) !important;
    border-color: var(--color-primary);
    color: #ffffff !important;
}

.agents-mobile__list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.agents-mobile__card {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border-radius: 16px;
    border: 1px solid #f0eaf6;
    background: #fff;
    box-shadow: 0 1px 3px rgba(30, 27, 46, 0.05);
    cursor: pointer;
}

.agents-mobile__avatar {
    position: relative;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
}

.agents-mobile__avatar img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    background: #f3f4f6;
}

.agents-mobile__online {
    position: absolute;
    right: 0;
    bottom: 0;
    z-index: 1;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #bbf7d0;
}

.agents-mobile__body {
    flex: 1 1 auto;
    min-width: 0;
}

.agents-mobile__name {
    font-size: 15px;
    font-weight: 700;
    color: #1a1528;
    line-height: 1.25;
}

.agents-mobile__email {
    margin-top: 2px;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.35;
    word-break: break-word;
}

.agents-mobile__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.agents-mobile__role,
.agents-mobile__branch {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f6f1fb;
    color: var(--color-primary);
}

.agents-mobile__branch {
    background: #f4f5f7;
    color: #4b5563;
    font-weight: 500;
}

.agents-mobile__login {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #6b7280;
    line-height: 1.3;
}

.agents-mobile__login.is-online {
    color: #15803d;
    font-weight: 700;
}

.agents-mobile__login-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 3px #dcfce7;
    flex-shrink: 0;
}

.agents-mobile__side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.agents-mobile__status {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 999px;
}

.agents-mobile__status.is-active {
    background: #dcfce7;
    color: #15803d;
}

.agents-mobile__status.is-inactive {
    background: #f3f4f6;
    color: #6b7280;
}

.agents-mobile__more {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 10px;
    background: #f6f1fb;
    color: var(--color-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.status-toggle {
    position: relative;
    display: inline-block;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 24px;
    cursor: pointer;
}

.toggle-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #E6E6E6;
    transition: .4s;
    border-radius: 24px;
    border: 2px solid #E6E6E6;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background: var(--gradient-primary) !important;
    transition: .4s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.toggle-input:checked + .toggle-slider {
    background: var(--gradient-primary) !important;
    border-color: var(--color-primary);
}

.toggle-input:checked + .toggle-slider:before {
    transform: translateX(16px);
    background: #ffffff !important;
}

.toggle-input:disabled + .toggle-slider {
    opacity: 0.6;
    cursor: not-allowed;
}

.toggle-labels {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 6px;
    font-size: 9px;
    font-weight: 600;
    pointer-events: none;
}

.active-label {
    color: white;
    opacity: 0;
    transition: opacity 0.3s;
}

.inactive-label {
    color: white;
    opacity: 1;
    transition: opacity 0.3s;
}

.toggle-input:checked + .toggle-slider .active-label {
    opacity: 1;
}

.toggle-input:checked + .toggle-slider .inactive-label {
    opacity: 0;
}

.loading-spinner {
    position: absolute;
    top: 50%;
    right: -25px;
    transform: translateY(-50%);
}

.sortable {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
}

.sortable:hover {
    background-color: #f8f9fa;
}

.user-name-link h6 {
    transition: color 0.15s;
}

.user-name-link:hover h6 {
    color: #0B0736;
    text-decoration: underline;
}

.loading, .no-data {
    color: #7f8c8d;
    font-size: 16px;
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
    padding: 12px 8px;
}

.radius-8 {
    border-radius: 8px;
}

.w-32-px {
    width: 32px;
}

.h-32-px {
    height: 32px;
}

.text-sm {
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.page-link {
    transition: all 0.2s;
}

.page-link:hover {
    transform: translateY(-1px);
}

.page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.form-select, .form-control {
    border-radius: 8px;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
}

/* Dropdown styles */
.dropdown-toggle::after {
    margin-left: 0.5em;
}

.dropdown-menu {
    border-radius: 8px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.text-danger:hover {
    background-color: #f8d7da;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.gap-2 {
        flex-wrap: wrap;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .pagination {
        flex-wrap: nowrap;
        justify-content: flex-start;
        overflow-x: auto;
        max-width: 100%;
        padding-bottom: 4px;
    }

    .users-list__pager {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: calc(88px + env(safe-area-inset-bottom, 0px));
        padding-right: 56px;
    }
    
    .dropdown {
        margin-bottom: 0.5rem;
    }
}

</style>