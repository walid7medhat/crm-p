<template>
    <div class="team-tree-node" :class="{'has-children': hasChildren}">
        <div class="node-container">
            <!-- Vertical Connector Line from Parent -->
            <div v-if="level > 0" class="connector parent-connector"></div>
            
            <!-- Main Node Card -->
            <div class="node-card" 
                 :class="{
                     'current-user': node.id === currentUserId,
                     'expanded-node': expanded,
                     'clickable': isClickable
                 }"
                 @click="handleCardClick">
                
                <!-- User Avatar and Info -->
                <div class="user-avatar">
                    <img :src="node.avatar || defaultAvatar" 
                         alt="User Avatar"
                         class="avatar-img"
                         @error="handleImageError">
                    <span v-if="isUserOnline(node)" class="online-indicator"></span>
                </div>
                
                <div class="user-info">
                    <div class="user-name">
                        <h6>{{ node.name || 'Unnamed' }}</h6>
                        <span v-if="node.id === currentUserId" class="you-badge">You</span>
                    </div>
                    
                    <div class="user-role">
                        <span class="role-badge" :class="getRoleBadgeClass(node.role_name)">
                            {{ node.role_name || 'Unknown Role' }}
                        </span>
                    </div>
                    
                    <div class="user-details">
                        <!-- العدد الإجمالي مرة واحدة -->
                        <div class="detail-item" v-if="totalMembersCount > 0">
                            <iconify-icon icon="lucide:users" width="12"></iconify-icon>
                            <span>{{ totalMembersCount }} Team Members</span>
                        </div>
                    </div>
                </div>
                
                <!-- Expand/Collapse Button - يظهر فقط لو فيه أطفال غير سالز -->
                <button v-if="hasNonSalesChildren" 
                        class="expand-btn"
                        @click.stop="toggleExpand"
                        :class="{'expanded': expanded}">
                    <iconify-icon :icon="expanded ? 'lucide:chevron-up' : 'lucide:chevron-down'"></iconify-icon>
                    <span class="children-count">{{ nonSalesChildren.length }}</span>
                </button>

                <!-- Sales Indicator - يظهر لو فيه سالز -->
                <div v-if="hasSalesMembers" class="sales-indicator">
                    <iconify-icon icon="lucide:trending-up" width="14"></iconify-icon>
                    <span class="sales-count">{{ salesMembersCount }}</span>
                </div>
            </div>

            <!-- Horizontal Connector Line to Children -->
            <div v-if="hasNonSalesChildren && expanded" class="connector children-connector"></div>
        </div>

        <!-- Children Container - يظهر فقط الأعضاء غير السالز -->
        <div v-if="expanded && hasNonSalesChildren" class="children-container">
            <div class="scroll-wrapper" ref="scrollWrapper">
                <div class="children-row">
                    <TeamTreeNode v-for="child in nonSalesChildren" 
                                :key="child.id"
                                :node="child"
                                :level="level + 1"
                                :current-user-id="currentUserId"
                                @view-profile="$emit('view-profile', $event)"
                                @open-sidebar="$emit('open-sidebar', $event)"
                                :permissions="permissions" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import defaultAvatar from "@/assets/images/user.png";

export default {
    name: 'TeamTreeNode',
    props: {
        node: {
            type: Object,
            required: true
        },
        level: {
            type: Number,
            default: 0
        },
        currentUserId: {
            type: Number,
            default: 0
        },
        permissions: {
            type: Object,
            default: () => ({})
        }
    },
    data() {
        return {
            expanded: this.shouldAutoExpand(),
            defaultAvatar,
            needsScroll: false
        };
    },
    computed: {
        hasChildren() {
            return this.node.children && this.node.children.length > 0;
        },
        
        // الأطفال غير السالز فقط (اللي هيظهروا في الشجرة)
        nonSalesChildren() {
            if (!this.node.children) return [];
            return this.node.children.filter(child => {
                const roleLower = child.role_name?.toLowerCase() || '';
                return !roleLower.includes('sales');
            });
        },
        
        // هل عنده أطفال غير سالز (عشان نعرض زر الـ expand)
        hasNonSalesChildren() {
            return this.nonSalesChildren.length > 0;
        },
        
        // عدد السالز تحت هذا البارنت
        salesMembersCount() {
            if (!this.node.children) return 0;
            return this.node.children.filter(child => {
                const roleLower = child.role_name?.toLowerCase() || '';
                return roleLower.includes('sales');
            }).length;
        },
        
        // هل عنده سالز
        hasSalesMembers() {
            return this.salesMembersCount > 0;
        },
        
        // العدد الإجمالي لكل الأطفال
        totalMembersCount() {
            if (!this.node.children) return 0;
            return this.node.children.length;
        },
        
        // هل الكارد يقدر يفتح حاجة لما يضغط عليه
        isClickable() {
            return this.hasNonSalesChildren || this.hasSalesMembers;
        }
    },
    methods: {
        shouldAutoExpand() {
            return this.level < 1;
        },

        handleCardClick() {
    // أولاً: إغلاق أي سايدبار مفتوحة حالياً
    this.$emit('close-all-sidebars');
    
    const wasExpanded = this.expanded;
    
    // 1. يفتح الفروع لو فيه تيم ليد
    if (this.hasNonSalesChildren) {
        this.toggleExpand();
    }
    
    // 2. يفتح السايدبار لو فيه سالز
    if (this.hasSalesMembers) {
        this.openSidebar();
    }
    
    // لو كان مفتوح وبيقفله، نقفل السايدبار
    if (wasExpanded && !this.expanded) {
        // نرسل إشارة للـ parent إنه يقفل السايدبار
        this.$emit('node-collapse');
    }
},

        toggleExpand() {
            const wasExpanded = this.expanded;
            this.expanded = !this.expanded;
            
            if (this.expanded) {
                this.$nextTick(() => {
                    this.checkScrollNeeded();
                });
            } else {
                // لو بيقفل الفروع، نقفل السايدبار
                this.$emit('node-collapse');
            }
        },
        checkScrollNeeded() {
            this.$nextTick(() => {
                const wrapper = this.$refs.scrollWrapper;
                if (wrapper) {
                    this.needsScroll = wrapper.scrollWidth > wrapper.clientWidth;
                }
            });
        },

        getRoleBadgeClass(role) {
            const roleLower = role?.toLowerCase() || '';
            
            if (roleLower.includes('admin')) return 'role-admin';
            if (roleLower.includes('manager')) return 'role-manager';
            if (roleLower.includes('team') || roleLower.includes('lead')) return 'role-team-lead';
            if (roleLower.includes('sales')) return 'role-sales';
            return 'role-agent';
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

        openSidebar() {
            console.log('Opening sidebar for:', this.node.name);
            this.$emit('open-sidebar', this.node);
        },

        handleImageError(event) {
            event.target.src = this.defaultAvatar;
        }
    },
    mounted() {
        this.checkScrollNeeded();
        window.addEventListener('resize', this.checkScrollNeeded);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.checkScrollNeeded);
    }
};
</script>

<style scoped>
.node-card:not(.clickable) {
    cursor: default !important;
}

.node-card:not(.clickable):hover {
    transform: none !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
    border-color: #e0e0e0 !important;
}

/* Sales Indicator بيكون مجرد إشارة مش قابلة للضغط */
.sales-indicator {
    position: absolute;
    bottom: -8px;
    right: -8px;
    background: #2596be;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    z-index: 15;
}

.sales-count {
    font-size: 0.6rem;
    font-weight: bold;
}

/* لو فيه expand button و sales indicator مع بعض، نعدل المواقع */
.node-card:has(.expand-btn) .sales-indicator {
    right: 20px;
    bottom: -10px;
}


.team-tree-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.node-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

/* Node Card Styles */
.node-card {
    position: relative;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.8rem;
    min-width: 240px;
    max-width: 280px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    cursor: pointer;
}

.node-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #2596be;
}

.current-user {
    border-color: #ffd700 !important;
    border-width: 2px !important;
}

.expanded-node {
    border-color: #2596be !important;
}

/* تنسيق خاص للفريق المبيعات */
.sales-team {
    border-left: 4px solid #2596be;
    background: #f8f9fa;
}

.sales-team:hover {
    background: #e9ecef;
}

/* User Avatar */
.user-avatar {
    position: relative;
    flex-shrink: 0;
}

.avatar-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #f8f9fa;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.online-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 10px;
    height: 10px;
    background: #28a745;
    border: 2px solid white;
    border-radius: 50%;
}

/* User Info */
.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    /* margin-bottom: 0.3rem; */
}

.user-name h6 {
    margin: 0;
    font-weight: 600 !important;
    font-size: 0.9rem !important;
    color: #212529;
}

.you-badge {
    background: #ffd700;
    color: #000;
    padding: 0.15rem 0.4rem;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 600;
}


.role-badge {
    /* padding: 0.2rem 0.5rem; */
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    /* color: white; */
}

/* .role-admin { background: #6c757d; }
.role-manager { background: #495057; }
.role-team-lead { background: #343a40; }
.role-sales { background: #2596be; }
.role-agent { background: #6c757d; } */


.detail-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.15rem;
    font-size: 0.75rem;
    color: #6c757d;
}

.email-text {
    font-size: 0.7rem;
    word-break: break-all;
}

/* Expand Button */
.expand-btn {
    position: absolute;
    bottom: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 1px solid #2596be;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    font-size: 0.7rem;
    color: #2596be;
}

.expand-btn:hover {
    background: #2596be;
    color: white;
}

.expand-btn.expanded {
    background: #2596be;
    color: white;
}

.children-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #2596be;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* Sales Indicator */
.sales-indicator {
    position: absolute;
    bottom: -8px;
    right: -8px;
    background: #2596be;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}

/* Connector Lines */
.connector {
    background: #dee2e6;
    position: absolute;
}

.parent-connector {
    width: 2px;
    height: 20px;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
}

.children-connector {
    width: 2px;
    height: 20px;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
}

/* Children Container */
.children-container {
    position: relative;
    margin-top: 2rem;
    width: 100%;
}

.scroll-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 1rem 0;
}

.scroll-wrapper::-webkit-scrollbar {
    height: 8px;
}

.scroll-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scroll-wrapper::-webkit-scrollbar-thumb {
    background: #2596be;
    border-radius: 4px;
}

.children-row {
    display: flex;
    gap: 1.5rem;
    padding: 0 1rem;
    min-width: min-content;
}

/* Responsive */
@media (max-width: 768px) {
    .node-card {
        min-width: 200px;
        max-width: 240px;
        padding: 0.6rem;
    }
    
    .children-row {
        gap: 1rem;
    }
}
</style>