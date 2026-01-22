<template>
    <div class="card basic-data-table">
        <div class="card-header">
            <div class="row">
                <div class="col-md-6 text-start">
                    <h5 class="card-title mb-0">Team Tree</h5>
                    <p class="text-muted mb-0">View your team structure in a hierarchical tree</p>
                </div>
                <div class="col-md-6 text-end">
                    <router-link to="/users" class="btn btn-outline-primary">
                        <iconify-icon icon="lucide:table" class="me-2"></iconify-icon>
                        Open Team Table
                    </router-link>
                </div>
            </div>
        </div>
        
        <div class="card">
            <!-- Filters Section -->
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3"
                style="border-bottom: none; padding-bottom: 0px;">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <!-- Role Filter -->
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm w-auto rounded-3" v-model="selectedRole"
                            style="border-radius: 10px; height: 2.4rem;" @change="applyFilters">
                            <option value="">All Roles</option>
                            <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm w-auto rounded-3" v-model="selectedStatus"
                            style="border-radius: 10px; height: 2.4rem;" @change="applyFilters">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="in_active">Inactive</option>
                        </select>
                    </div>

                    <!-- Clear Filters Button -->
                    <button v-if="hasActiveFilters" class="btn btn-sm btn-outline-secondary" @click="clearFilters">
                        <iconify-icon icon="lucide:x" class="me-1"></iconify-icon>
                        Clear Filters
                    </button>
                </div>

                <div class="d-flex gap-2">
                    <div class="icon-field d-flex align-items-center">
                        <span class="me-2">Search:</span>
                        <div class="position-relative" style="width: 100%; max-width: 240px;">
                            <input type="text" class="form-control form-control-sm w-100 px-3 pe-5" v-model="searchText"
                                style="border-radius: 10px; height: 2.5rem;" placeholder="Search team members..." 
                                @input="handleSearchInput"/>
                            <span class="icon position-absolute end-0 top-50 translate-middle-y me-3 text-muted"
                                style="pointer-events: none;">
                                <iconify-icon icon="lucide:search"></iconify-icon>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Summary -->
            <div v-if="hasActiveFilters" class="card-body py-2 border-bottom bg-light">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <small class="text-muted">Active filters:</small>
                    <span v-if="selectedRole" class="badge bg-light text-dark border">
                        Role: {{ selectedRole }}
                        <button @click="selectedRole = ''" class="btn-close ms-1" style="font-size: 0.6rem;"></button>
                    </span>
                    <span v-if="selectedStatus" class="badge bg-light text-dark border">
                        Status: {{ selectedStatus === 'active' ? 'Active' : 'Inactive' }}
                        <button @click="selectedStatus = ''" class="btn-close ms-1" style="font-size: 0.6rem;"></button>
                    </span>
                    <span v-if="searchText" class="badge bg-light text-dark border">
                        Search: "{{ searchText }}"
                        <button @click="searchText = ''" class="btn-close ms-1" style="font-size: 0.6rem;"></button>
                    </span>
                </div>
            </div>

            <!-- Team Tree Content with Scroll -->
            <div class="card-body p-0">
                <div class="team-tree-container" ref="treeContainer">
                    <div class="tree-scroll-wrapper">
                        <div class="tree-content">
                            <div v-if="filteredTeamTree.length > 0" class="tree-root">
                                <div v-for="member in filteredTeamTree" :key="member.id" class="root-node">
                                    <TeamTreeNode 
                                        :node="member" 
                                        :level="0"
                                        :current-user-id="currentUser.id"
                                        @view-profile="viewUserProfile"
                                        @open-sidebar="openSidebar"
                                          @close-all-sidebars="handleCloseAllSidebars"
                                        :permissions="{
                                            view: $hasPermission('users-list')
                                        }" />
                                </div>
                            </div>
                            
                            <!-- No team members message -->
                            <div v-else-if="!loading" class="text-center text-muted py-4">
                                <iconify-icon icon="lucide:users" width="48" class="mb-2"></iconify-icon>
                                <p v-if="hasActiveFilters">No team members match your filters</p>
                                <p v-else>No team members found</p>
                                <button v-if="$hasPermission('users-create') && !hasActiveFilters" class="btn btn-outline-primary mt-2" @click="addUser">
                                    <iconify-icon icon="lucide:plus" class="me-2"></iconify-icon>
                                    Add Team Member
                                </button>
                                <button v-if="hasActiveFilters" class="btn btn-outline-primary mt-2" @click="clearFilters">
                                    <iconify-icon icon="lucide:filter-x" class="me-2"></iconify-icon>
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Scroll Indicators -->
                    <div v-if="showScrollIndicators" class="scroll-indicators">
                        <div class="scroll-hint left-hint" v-if="canScrollLeft">
                            <iconify-icon icon="lucide:chevron-left" class="me-1"></iconify-icon>
                            <span>Scroll Left</span>
                        </div>
                        <div class="scroll-hint right-hint" v-if="canScrollRight">
                            <span>Scroll Right</span>
                            <iconify-icon icon="lucide:chevron-right" class="ms-1"></iconify-icon>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Loading team structure...</p>
            </div>
        </div>

        <!-- Team Lead Sidebar -->
          <!-- Team Lead Sidebar -->
           <!-- Team Lead Sidebar -->
        <div v-if="isTeamLeadSidebarOpen" class="sidebar-overlay" @click="closeTeamLeadSidebar"></div>
        <div class="team-lead-sidebar" :class="{'sidebar-open': isTeamLeadSidebarOpen}">
            <div class="sidebar-content">
                <div class="sidebar-header">
                    <h5>Team Lead Details</h5>
                    <button class="close-btn" @click="closeTeamLeadSidebar">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>
                
                <div class="sidebar-body" v-if="selectedTeamLead">
                    <!-- Team Lead Header -->
                    <div class="team-lead-header-simple">
                        <div class="header-content-simple">
                            <div class="avatar-section-simple">
                                <div class="user-avatar-simple">
                                    <img :src="selectedTeamLead.avatar || defaultAvatar" 
                                        alt="Team Lead Avatar"
                                        @error="handleImageError">
                                </div>
                            </div>
                            <div class="info-section-simple">
                                <h4 class="user-name-simple">{{ selectedTeamLead.name }}</h4>
                                <div class="role-section-simple">
                                    <span class="role-badge-simple">{{ selectedTeamLead.role_name }}</span>
                                </div>
                                <div class="team-size-simple">
                                    <iconify-icon icon="lucide:users" class="team-icon"></iconify-icon>
                                    <span>{{ getSalesMembersCount(selectedTeamLead) }} Sales Members</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Members Section -->
                    <div class="team-members-section-simple" v-if="getSalesMembers(selectedTeamLead).length > 0">
                        <h6 class="section-title-simple">Sales Team</h6>
                        <div class="team-members-list-simple">
                            <div v-for="member in getSalesMembers(selectedTeamLead)" 
                                :key="member.id" 
                                class="team-member-item-simple"
                                @click="openEmployeeSidebar(member)">
                                <div class="member-avatar-simple">
                                    <img :src="member.avatar || defaultAvatar" 
                                        alt="Member Avatar"
                                        @error="handleImageError">
                                    <span class="status-dot-simple" :class="{'online': isUserOnline(member)}"></span>
                                </div>
                                <div class="member-info-simple">
                                    <h6 class="member-name-simple">{{ member.name }}</h6>
                                    <span class="member-role-simple">{{ member.role_name }}</span>
                                </div>
                                <div class="member-action-simple">
                                    <iconify-icon icon="lucide:chevron-right" class="chevron-icon-simple"></iconify-icon>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Sales Members Message -->
                    <div v-else class="no-team-members-simple">
                        <div class="empty-state-simple">
                            <iconify-icon icon="lucide:users" width="48" class="empty-icon-simple"></iconify-icon>
                            <p class="empty-text-simple">No sales members assigned</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons-simple">
                        <button class="btn btn-outline-primary w-100" @click="viewFullProfile(selectedTeamLead)">
                            <iconify-icon icon="lucide:user" class="me-2"></iconify-icon>
                            View Full Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Sidebar -->
        <div v-if="isEmployeeSidebarOpen" class="sidebar-overlay" @click="closeEmployeeSidebar"></div>
        <div class="employee-sidebar" :class="{'sidebar-open': isEmployeeSidebarOpen}">
            <div class="sidebar-content">
                <div class="sidebar-header">
                    <h5>Employee Details</h5>
                    <button class="close-btn" @click="closeEmployeeSidebar">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>
                </div>
                
                <div class="sidebar-body" v-if="selectedEmployee">
                    <!-- Employee Header -->
                    <div class="user-header">
                        <div class="user-avatar-large">
                            <img :src="selectedEmployee.avatar || defaultAvatar" 
                                 alt="Employee Avatar"
                                 @error="handleImageError">
                        </div>
                        <div class="user-info-large">
                            <h4>{{ selectedEmployee.name }}</h4>
                            <span class="role-badge-large">{{ selectedEmployee.role_name }}</span>
                            <div v-if="selectedEmployee.team_members_count > 0" class="team-size-badge">
                                {{ selectedEmployee.team_members_count }} Team Members
                            </div>
                        </div>
                    </div>

                    <!-- Employee Details -->
                    <div class="user-details-section">
                        <div class="detail-row">
                            <iconify-icon icon="lucide:mail"></iconify-icon>
                            <div>
                                <label>Email</label>
                                <p>{{ selectedEmployee.email }}</p>
                            </div>
                        </div>
                        
                        <div class="detail-row" v-if="selectedEmployee.phone">
                            <iconify-icon icon="lucide:phone"></iconify-icon>
                            <div>
                                <label>Phone</label>
                                <p>{{ selectedEmployee.phone }}</p>
                            </div>
                        </div>
                        
                        <div class="detail-row" v-if="selectedEmployee.last_login_at">
                            <iconify-icon icon="lucide:clock"></iconify-icon>
                            <div>
                                <label>Last Login</label>
                                <p>{{ formatLastLogin(selectedEmployee.last_login_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="btn btn-outline-primary w-100" @click="viewFullProfile(selectedEmployee)">
                            <iconify-icon icon="lucide:user" class="me-2"></iconify-icon>
                            View Full Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '../../config/api';
import TeamTreeNode from './TeamTreeNode.vue';
import defaultAvatar from "@/assets/images/user.png";

export default {
    name: 'TeamTree',
    components: {
        TeamTreeNode
    },
    data() {
        return {
            loading: true,
            selectedRole: '',
            selectedStatus: '',
            searchText: '',
            allUsers: [],
            filteredUsers: [],
            currentUser: {},
            defaultAvatar,
            searchTimeout: null,
            showScrollIndicators: false,
            canScrollLeft: false,
            canScrollRight: false,
            isTeamLeadSidebarOpen: false,
            isEmployeeSidebarOpen: false,
            selectedTeamLead: null,
            selectedEmployee: null
        };
    },
    computed: {
        availableRoles() {
            const roles = [...new Set(this.allUsers.map(user => user.role_name).filter(Boolean))];
            return roles.sort();
        },
        
        teamTree() {
            if (!this.filteredUsers.length) return [];

            try {
                const usersMap = new Map();
                const roots = [];

                this.filteredUsers.forEach(user => {
                    usersMap.set(user.id, { 
                        ...user, 
                        children: []
                    });
                });

                this.filteredUsers.forEach(user => {
                    const node = usersMap.get(user.id);
                    if (user.parent_id && usersMap.has(user.parent_id)) {
                        const parent = usersMap.get(user.parent_id);
                        parent.children.push(node);
                    } else {
                        roots.push(node);
                    }
                });

                return roots;
            } catch (error) {
                console.error('Error building team tree:', error);
                return [];
            }
        },

        filteredTeamTree() {
            return this.teamTree;
        },

        hasActiveFilters() {
            return this.selectedRole || this.selectedStatus || this.searchText;
        }
    },
    mounted() {
        console.log('TeamTree component mounted');
        this.fetchTeamData();
        this.$nextTick(() => {
            this.setupScrollListener();
        });
    },
    methods: {
        getSalesMembers(teamLead) {
            // ترجع الأعضاء السالز فقط
            if (!teamLead || !teamLead.children) return [];
            return teamLead.children.filter(member => {
                const roleLower = member.role_name?.toLowerCase() || '';
                return roleLower.includes('sales');
            });
        },

        getSalesMembersCount(teamLead) {
            // حساب عدد الأعضاء السالز فقط
            if (!teamLead || !teamLead.children) return 0;
            return teamLead.children.filter(member => {
                const roleLower = member.role_name?.toLowerCase() || '';
                return roleLower.includes('sales');
            }).length;
        },
        async fetchTeamData() {
            try {
                this.loading = true;
                
                const token = localStorage.getItem('token');
                
                const userData = localStorage.getItem('user');
                if (userData) {
                    this.currentUser = JSON.parse(userData);
                }

                try {
                    const hierarchyResponse = await fetch(API_ENDPOINTS.TEAM_HIERARCHY, {
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    if (hierarchyResponse.ok) {
                        const hierarchyData = await hierarchyResponse.json();
                        this.processTeamData(hierarchyData);
                        return;
                    }
                } catch (hierarchyError) {
                    console.warn('Team hierarchy API failed, falling back to users API:', hierarchyError);
                }

                await this.fetchUsersData();
                
            } catch (error) {
                console.error('Error fetching team data:', error);
                this.allUsers = [];
                this.filteredUsers = [];
                this.showNotification('Failed to load team structure. Please try again.', 'error');
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    this.checkScroll();
                });
            }
        },

        async fetchUsersData() {
            try {
                const token = localStorage.getItem('token');
                
                const response = await fetch(API_ENDPOINTS.USERS, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                this.processTeamData(data);
                
            } catch (error) {
                console.error('Error fetching users data:', error);
                throw error;
            }
        },

        processTeamData(data) {
            let usersData = [];
            
            if (data.data) {
                usersData = Array.isArray(data.data) ? data.data : [];
            } else if (Array.isArray(data)) {
                usersData = data;
            } else if (data.success && data.data) {
                usersData = Array.isArray(data.data) ? data.data : [];
            }

            this.allUsers = usersData.map(user => ({
                id: user.id || 0,
                name: user.name || 'Unnamed',
                email: user.email || '',
                phone: user.phone || '',
                avatar: user.avatar || null,
                status: user.status || 'active',
                parent_id: user.parent_id || null,
                parent_name: user.parent_name || null,
                role_name: user.role_name || 'Unknown Role',
                role_id: user.role_id || null,
                team_members_count: user.team_members_count || 0,
                last_login_at: user.last_login_at || null,
                created_at: user.created_at || null,
                children: user.children || []
            }));

            const hasChildren = this.allUsers.some(user => user.children && user.children.length > 0);
            
            if (!hasChildren) {
                this.buildTreeStructure();
            }

            this.applyFilters();
        },

        buildTreeStructure() {
            const usersMap = new Map();
            
            this.allUsers.forEach(user => {
                usersMap.set(user.id, { ...user, children: [] });
            });

            this.allUsers.forEach(user => {
                if (user.parent_id && usersMap.has(user.parent_id)) {
                    const parent = usersMap.get(user.parent_id);
                    parent.children.push(usersMap.get(user.id));
                }
            });

            this.allUsers = Array.from(usersMap.values());
        },

        applyFilters() {
            let filtered = [...this.allUsers];

            if (this.selectedRole) {
                filtered = filtered.filter(user => 
                    user.role_name && user.role_name === this.selectedRole
                );
            }

            if (this.selectedStatus) {
                filtered = filtered.filter(user => 
                    user.status && user.status === this.selectedStatus
                );
            }

            if (this.searchText) {
                const search = this.searchText.toLowerCase();
                filtered = filtered.filter(user => 
                    (user.name && user.name.toLowerCase().includes(search)) ||
                    (user.email && user.email.toLowerCase().includes(search)) ||
                    (user.role_name && user.role_name.toLowerCase().includes(search)) ||
                    (user.parent_name && user.parent_name.toLowerCase().includes(search))
                );
            }

            this.filteredUsers = filtered;
            this.$nextTick(() => {
                this.checkScroll();
            });
        },

        handleSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },

        clearFilters() {
            this.selectedRole = '';
            this.selectedStatus = '';
            this.searchText = '';
            this.applyFilters();
        },

        setupScrollListener() {
            const container = this.$refs.treeContainer;
            if (container) {
                container.addEventListener('scroll', this.handleScroll);
            }
        },

        handleScroll() {
            this.checkScroll();
        },

        checkScroll() {
            const container = this.$refs.treeContainer;
            if (!container) return;

            const scrollWrapper = container.querySelector('.tree-scroll-wrapper');
            if (!scrollWrapper) return;

            const hasHorizontalScroll = scrollWrapper.scrollWidth > container.clientWidth;
            this.showScrollIndicators = hasHorizontalScroll;

            if (hasHorizontalScroll) {
                this.canScrollLeft = container.scrollLeft > 0;
                this.canScrollRight = container.scrollLeft < (scrollWrapper.scrollWidth - container.clientWidth - 10);
            } else {
                this.canScrollLeft = false;
                this.canScrollRight = false;
            }
        },
    handleCloseAllSidebars() {
        console.log('Closing all sidebars from child component');
        this.closeAllSidebars();
    },
    
    // تعديل دالة openSidebar
    openSidebar(user) {
        console.log('Opening sidebar for user:', user);
        
        // إغلاق أي سايدبار مفتوحة حالياً أولاً
        this.closeAllSidebars();
        
        // إعطاء وقت بسيط للـ DOM
        this.$nextTick(() => {
            // تحديد نوع السايدبار بناءً على دور المستخدم
            if (this.isTeamLead(user)) {
                this.selectedTeamLead = user;
                this.isTeamLeadSidebarOpen = true;
            } else {
                this.selectedEmployee = user;
                this.isEmployeeSidebarOpen = true;
            }
            
            document.body.style.overflow = 'hidden';
        });
    },


            closeAllSidebars() {
                this.isTeamLeadSidebarOpen = false;
                this.isEmployeeSidebarOpen = false;
                this.selectedTeamLead = null;
                this.selectedEmployee = null;
                document.body.style.overflow = '';
            },

            // دالة جديدة نستدعيها لما نعرف إنه بيقفل الفروع
            onNodeCollapse() {
                this.closeAllSidebars();
            },

        isTeamLead(user) {
            // تحديد إذا كان المستخدم Team Lead بناءً على الدور أو إذا كان لديه فريق
            const roleLower = user.role_name?.toLowerCase() || '';
            return roleLower.includes('team_lead') || 
                   roleLower.includes('manager') ||
                   (user.children && user.children.length > 0);
        },

        openEmployeeSidebar(member) {
            this.selectedEmployee = member;
            this.isEmployeeSidebarOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeTeamLeadSidebar() {
            console.log('Closing team lead sidebar');
            this.isTeamLeadSidebarOpen = false;
            this.selectedTeamLead = null;
            document.body.style.overflow = '';
        },

        closeEmployeeSidebar() {
            console.log('Closing employee sidebar');
            this.isEmployeeSidebarOpen = false;
            this.selectedEmployee = null;
            document.body.style.overflow = '';
        },

        getTeamMembers(teamLead) {
            // الحصول على أعضاء الفريق الخاص بال Team Lead
            if (!teamLead || !teamLead.children) return [];
            return teamLead.children;
        },

        getTeamMembersCount(teamLead) {
            // حساب عدد أعضاء الفريق
            if (!teamLead || !teamLead.children) return 0;
            return teamLead.children.length;
        },

        viewUserProfile(userId) {
            if (!this.$hasPermission('users-list')) {
                this.showNotification('You do not have permission to view users', 'warning');
                return;
            }
            this.$router.push(`/users/${userId}`);
        },

        viewFullProfile(user) {
            this.closeTeamLeadSidebar();
            this.closeEmployeeSidebar();
            this.viewUserProfile(user.id);
        },

        addUser() {
            if (!this.$hasPermission('users-create')) {
                this.showNotification('You do not have permission to create users', 'warning');
                return;
            }
            this.$router.push('/add-user');
        },

        isUserOnline(user) {
            if (!user.last_login_at) return false;
            try {
                const lastLogin = new Date(user.last_login_at);
                const now = new Date();
                const diffMinutes = (now - lastLogin) / (1000 * 60);
                return diffMinutes <= 15;
            } catch (error) {
                return false;
            }
        },

        getRoleBadgeClass(role) {
            const roleLower = role?.toLowerCase();
            if (roleLower.includes('super_admin')) return 'role-super-admin';
            if (roleLower.includes('admin')) return 'role-admin';
            if (roleLower.includes('manager')) return 'role-manager';
            if (roleLower.includes('team') || roleLower.includes('lead')) return 'role-team-lead';
            if (roleLower.includes('sales')) return 'role-sales';
            return 'role-agent';
        },

        getStatusBadgeClass(status) {
            return status === 'active' ? 'status-active' : 'status-inactive';
        },

        formatLastLogin(timestamp) {
            if (!timestamp) return 'Never';
            try {
                const now = new Date();
                const loginTime = new Date(timestamp);
                const diffMs = now - loginTime;
                const diffMins = Math.floor(diffMs / 60000);
                
                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                
                const diffHours = Math.floor(diffMins / 60);
                if (diffHours < 24) return `${diffHours}h ago`;
                
                const diffDays = Math.floor(diffHours / 24);
                return `${diffDays}d ago`;
            } catch (error) {
                return 'Invalid date';
            }
        },

        handleImageError(event) {
            event.target.src = this.defaultAvatar;
        },

        showNotification(message, type = 'info') {
            if (this.$showNotification) {
                this.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
                alert(`${type.toUpperCase()}: ${message}`);
            }
        }
        
    },
    beforeUnmount() {
        const container = this.$refs.treeContainer;
        if (container) {
            container.removeEventListener('scroll', this.handleScroll);
        }
    }
};
</script>

<style scoped>
.team-tree-container {
    position: relative;
    width: 100%;
    height: 70vh;
    min-height: 500px;
    max-height: 800px;
    overflow: auto;
    padding: 1rem;
}

.tree-scroll-wrapper {
    width: max-content;
    min-width: 100%;
    padding: 1rem;
}

.tree-content {
    display: flex;
    justify-content: center;
}

.tree-root {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.root-node {
    margin-bottom: 2rem;
}

/* Custom Scrollbar */
.team-tree-container::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

.team-tree-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 6px;
}

.team-tree-container::-webkit-scrollbar-thumb {
    background: #6c757d;
    border-radius: 6px;
    border: 2px solid #f1f1f1;
}

.team-tree-container::-webkit-scrollbar-thumb:hover {
    background: #495057;
}

.team-tree-container::-webkit-scrollbar-corner {
    background: #f1f1f1;
}

/* Scroll Indicators */
.scroll-indicators {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    transform: translateY(-50%);
    pointer-events: none;
    z-index: 10;
}

.scroll-hint {
    position: absolute;
    display: flex;
    align-items: center;
    background: rgba(108, 117, 125, 0.9);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    animation: fadeInOut 2s ease-in-out infinite;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.left-hint {
    left: 1rem;
}

.right-hint {
    right: 1rem;
}

@keyframes fadeInOut {
    0%, 100% { opacity: 0.7; transform: translateX(0); }
    50% { opacity: 1; transform: translateX(5px); }
}

.right-hint {
    animation: fadeInOutRight 2s ease-in-out infinite;
}

@keyframes fadeInOutRight {
    0%, 100% { opacity: 0.7; transform: translateX(0); }
    50% { opacity: 1; transform: translateX(-5px); }
}

.loading {
    color: #7f8c8d;
    font-size: 16px;
}

/* Sidebar Base Styles */
.team-lead-sidebar,
.employee-sidebar {
    position: fixed;
    top: 0;
    right: -450px;
    width: 450px;
    height: 100vh;
    background: white;
    box-shadow: -2px 0 20px rgba(0,0,0,0.15);
    transition: right 0.3s ease;
    z-index: 10000;
    display: flex;
    flex-direction: column;
}

.sidebar-open.team-lead-sidebar,
.sidebar-open.employee-sidebar {
    right: 0;
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-open + .sidebar-overlay {
    opacity: 1;
    visibility: visible;
}

.sidebar-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    background: white;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.sidebar-header h5 {
    margin: 0;
    color: #212529;
    font-weight: 600;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: #f8f9fa;
    color: #495057;
}

.sidebar-body {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
    background: white;
}

/* Team Lead Header */
.team-lead-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.user-avatar-large {
    position: relative;
    display: inline-block;
    margin-bottom: 1rem;
}

.user-avatar-large img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid #f8f9fa;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.user-info-large h4 {
    margin: 0 0 0.75rem 0;
    color: #212529;
    font-weight: 600;
}

.role-badge-large {
    display: inline-block;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
    background: #2596be;
    margin-bottom: 0.5rem;
}

.team-size-badge {
    display: block;
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

/* Team Members Section */
.team-members-section {
    margin: 2rem 0;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.team-members-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.team-member-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.2s ease;
}

.team-member-item:hover {
    background: #e9ecef;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.member-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid white;
}

.member-info {
    flex: 1;
}

.member-info h6 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: #212529;
}

.member-role {
    font-size: 0.75rem;
    color: #6c757d;
    background: #e9ecef;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
}

.member-status {
    display: flex;
    align-items: center;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #6c757d;
}

.status-dot.online {
    background: #28a745;
}

.no-team-members {
    text-align: center;
    padding: 2rem;
    color: #6c757d;
}

.no-team-members p {
    margin: 0;
    font-size: 0.9rem;
}

/* User Details Section */
.user-details-section {
    margin-bottom: 2rem;
}

.detail-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.detail-row iconify-icon {
    color: #2596be;
    margin-top: 0.2rem;
    flex-shrink: 0;
}

.detail-row label {
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    margin-bottom: 0.3rem;
    display: block;
}

.detail-row p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

.action-buttons {
    margin-top: auto;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.action-buttons .btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

.user-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .team-lead-sidebar,
    .employee-sidebar {
        width: 100vw;
        right: -100vw;
    }
    
    .sidebar-header {
        padding: 1.25rem;
    }
    
    .sidebar-body {
        padding: 1.25rem;
    }
    
    .team-member-item {
        padding: 0.75rem;
    }
}
.team-lead-header-new {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 1.5rem;
    margin: -1.5rem -1.5rem 2rem -1.5rem;
    border-radius: 0 0 20px 20px;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.avatar-section {
    flex-shrink: 0;
}

.user-avatar-large img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.info-section {
    flex: 1;
}

.user-name {
    margin: 0 0 1rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.role-phone-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.role-badge-large {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #667eea;
    background: white;
    text-transform: capitalize;
}

.phone-number {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.9);
}

.phone-icon {
    font-size: 1rem;
}

.team-size-badge-new {
    display: inline-flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

/* Team Lead Header البسيط */
.team-lead-header-simple {
    background: white;
    padding: 2rem 1.5rem 1.5rem;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.header-content-simple {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
}

.avatar-section-simple {
    flex-shrink: 0;
}

.user-avatar-simple img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #f8f9fa;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.info-section-simple {
    flex: 1;
}

.user-name-simple {
    margin: 0 0 0.75rem 0;
    font-size: 1.5rem !important;
    font-weight: 600;
    color: #212529;
}

.role-section-simple {
    margin-bottom: 0.75rem;
}

.role-badge-simple {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #495057;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    text-transform: capitalize;
}

.phone-section-simple {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    color: #6c757d;
    font-size: 0.9rem;
}

.phone-icon-simple {
    color: #6c757d;
    font-size: 0.9rem;
}

.phone-text {
    font-weight: 500;
}

.team-size-simple {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2596be;
    font-size: 0.9rem;
    font-weight: 600;
}

.team-icon {
    font-size: 1rem;
}

/* Team Members Section البسيطة */
.team-members-section-simple {
    margin-bottom: 2rem;
}

.section-title-simple {
    font-size: 1.1rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.team-members-list-simple {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.team-member-item-simple {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.2s ease;
}

.team-member-item-simple:hover {
    background: #f8f9fa;
    border-color: #2596be;
}

.member-avatar-simple {
    position: relative;
    flex-shrink: 0;
}

.member-avatar-simple img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: 2px solid #f8f9fa;
}

.status-dot-simple {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 10px;
    height: 10px;
    background: #6c757d;
    border: 2px solid white;
    border-radius: 50%;
}

.status-dot-simple.online {
    background: #28a745;
}

.member-info-simple {
    flex: 1;
}

.member-name-simple {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #212529;
}

.member-role-simple {
    font-size: 0.75rem;
    color: #6c757d;
    background: #f8f9fa;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-weight: 500;
}

.member-action-simple {
    color: #6c757d;
}

.chevron-icon-simple {
    font-size: 1.1rem;
}

.team-member-item-simple:hover .chevron-icon-simple {
    color: #2596be;
}

/* No Team Members البسيط */
.no-team-members-simple {
    margin: 2rem 0;
}

.empty-state-simple {
    text-align: center;
    color: #6c757d;
    padding: 2rem;
}

.empty-icon-simple {
    margin-bottom: 1rem;
    opacity: 0.4;
}

.empty-text-simple {
    margin: 0;
    font-size: 0.95rem;
}

/* Action Buttons البسيط */
.action-buttons-simple {
    margin-top: auto;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.action-buttons-simple .btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    border-radius: 8px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .team-lead-header-simple {
        padding: 1.5rem 1rem 1rem;
        margin: -1.25rem -1.25rem 1rem -1.25rem;
    }
    
    .header-content-simple {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .user-name-simple {
        font-size: 1.3rem !important;
    }
    
    .team-member-item-simple {
        padding: 0.75rem;
    }
}

@media (max-width: 480px) {
    .user-avatar-simple img {
        width: 70px;
        height: 70px;
    }
    
    .member-avatar-simple img {
        width: 40px;
        height: 40px;
    }
}
</style>