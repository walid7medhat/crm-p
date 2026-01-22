<template>
    <div class="tree-node" :class="{'mobile-view': isMobile}">
        <!-- Desktop View -->
        <div class="node-content d-none d-md-flex align-items-center justify-content-between p-2 border rounded mb-1"
             :class="{'bg-light': level === 0, 'bg-white': level > 0}"
             :style="{ marginLeft: level * 20 + 'px' }">
            <div class="d-flex align-items-center flex-grow-1">
                <iconify-icon :icon="getTypeIcon(node.type)" 
                             :class="'text-' + getTypeColor(node.type)"
                             width="20"
                             class="me-2 flex-shrink-0"></iconify-icon>
                <div class="flex-grow-1 min-width-0">
                    <h6 class="mb-0 fw-medium text-truncate">{{ node.name || 'Unnamed' }}</h6>
                    <small class="text-muted">
                        {{ node.type || 'unknown' }} • 
                        {{ node.children_count || 0 }} children
                    </small>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
                <!-- Expand/Collapse Button -->
                <button v-if="hasChildren"
                        class="btn btn-sm btn-outline-secondary"
                        @click="expanded = !expanded"
                        :title="expanded ? 'Collapse' : 'Expand'">
                    <iconify-icon :icon="expanded ? 'lucide:chevron-down' : 'lucide:chevron-right'"></iconify-icon>
                </button>

           
                <!-- Edit Button -->
                <button v-if="permissions.edit"
                        class="btn btn-sm btn-outline-success"
                        @click="$emit('edit', node.id)"
                        title="Edit">
                    <iconify-icon icon="lucide:edit"></iconify-icon>
                </button>

                <!-- Delete Button -->
                <button v-if="permissions.delete"
                        class="btn btn-sm btn-outline-danger"
                        @click="$emit('delete', node)"
                        title="Delete">
                    <iconify-icon icon="mingcute:delete-2-line"></iconify-icon>
                </button>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="node-content-mobile d-md-none p-2 border rounded mb-2"
             :class="{'bg-light': level === 0, 'bg-white': level > 0}">
            <!-- Header Row -->
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center flex-grow-1 min-width-0">
                    <iconify-icon :icon="getTypeIcon(node.type)" 
                                 :class="'text-' + getTypeColor(node.type)"
                                 width="18"
                                 class="me-2 flex-shrink-0"></iconify-icon>
                    <h6 class="mb-0 fw-medium text-truncate">{{ node.name || 'Unnamed' }}</h6>
                </div>
                
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <!-- Expand/Collapse Button -->
                    <button v-if="hasChildren"
                            class="btn btn-xs btn-outline-secondary"
                            @click="expanded = !expanded"
                            :title="expanded ? 'Collapse' : 'Expand'">
                        <iconify-icon :icon="expanded ? 'lucide:chevron-down' : 'lucide:chevron-right'" width="14"></iconify-icon>
                    </button>
                </div>
            </div>

            <!-- Details Row -->
            <div class="row g-2 text-sm">
                <div class="col-6">
                    <small class="text-muted d-block">Type</small>
                    <span class="badge" :class="'bg-' + getTypeColor(node.type)">
                        {{ node.type || 'unknown' }}
                    </span>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Children</small>
                    <span class="badge bg-info">
                        {{ node.children_count || 0 }}
                    </span>
                </div>
            </div>

            <!-- Actions Row -->
            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                <div class="d-flex gap-1">
                   
                    <!-- Edit Button -->
                    <button v-if="permissions.edit"
                            class="btn btn-xs btn-outline-success"
                            @click="$emit('edit', node.id)"
                            title="Edit">
                        <iconify-icon icon="lucide:edit" width="12" class="me-1"></iconify-icon>
                        <span class="d-none d-sm-inline">Edit</span>
                    </button>

                    <!-- Delete Button -->
                    <button v-if="permissions.delete"
                            class="btn btn-xs btn-outline-danger"
                            @click="$emit('delete', node)"
                            title="Delete">
                        <iconify-icon icon="mingcute:delete-2-line" width="12" class="me-1"></iconify-icon>
                        <span class="d-none d-sm-inline">Delete</span>
                    </button>
                </div>

                <!-- Level Indicator -->
                <div class="level-indicator" v-if="level > 0">
                    <small class="text-muted">Level {{ level }}</small>
                </div>
            </div>
        </div>

        <!-- Children Container -->
        <div v-if="expanded && hasChildren" 
             class="children-container"
             :class="{'mobile-children': isMobile}">
            <TreeNode v-for="child in node.children" 
                     :key="child.id"
                     :node="child"
                     :level="level + 1"
                     @edit="$emit('edit', $event)"
                     @delete="$emit('delete', $event)"
                     @view-children="$emit('view-children', $event)"
                     :permissions="permissions" />
        </div>

        <!-- Empty Children State -->
        <div v-if="expanded && (!node.children || node.children.length === 0)" 
             class="empty-children text-center py-2 px-3 text-muted">
            <small>No child areas</small>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TreeNode',
    props: {
        node: {
            type: Object,
            required: true,
            default: () => ({
                id: 0,
                name: 'Unnamed',
                type: 'unknown',
                children_count: 0,
                children: []
            })
        },
        level: {
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
            expanded: this.level < 2 // Auto-expand first two levels
        };
    },
    computed: {
        hasChildren() {
            return this.node.children && this.node.children.length > 0;
        },
        isMobile() {
            // You can use a more sophisticated mobile detection if needed
            return window.innerWidth < 768;
        }
    },
    methods: {
        getTypeIcon(type) {
            const icons = {
                country: 'lucide:globe',
                city: 'lucide:building',
                area: 'lucide:map-pin',
                community: 'lucide:users',
                sub_community: 'lucide:user-plus',
                cluster: 'lucide:group',
                building: 'lucide:home',
                faces: 'lucide:layers'
            };
            return icons[type] || 'lucide:map-pin';
        },
        
        getTypeColor(type) {
            const colors = {
                country: 'primary',
                city: 'success',
                area: 'info',
                community: 'warning',
                sub_community: 'secondary',
                cluster: 'dark',
                building: 'danger',
                faces: 'muted'
            };
            return colors[type] || 'muted';
        }
    },
    mounted() {
        // Add resize listener for responsive behavior
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    }
};
</script>

<style scoped>
.tree-node {
    transition: all 0.3s ease;
}

/* Desktop Styles */
.node-content {
    transition: all 0.2s ease;
    min-height: 60px;
}

.node-content:hover {
    background-color: #f8f9fa !important;
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.children-container {
    border-left: 2px solid #e9ecef;
    margin-left: 25px;
    padding-left: 15px;
    transition: all 0.3s ease;
}

/* Mobile Styles */
.node-content-mobile {
    transition: all 0.2s ease;
    border-left: 3px solid;
    border-left-color: var(--bs-border-color);
}

.node-content-mobile:hover {
    background-color: #f8f9fa !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.mobile-children {
    border-left: 2px solid #e9ecef;
    margin-left: 15px;
    padding-left: 10px;
}

/* Level-based border colors for mobile */
.node-content-mobile.bg-light {
    border-left-color: var(--bs-primary);
}

.node-content-mobile:not(.bg-light) {
    border-left-color: var(--bs-secondary);
}

/* Button Sizes */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.btn-xs {
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
    line-height: 1.2;
}

/* Text and Layout Utilities */
.text-sm {
    font-size: 0.8rem;
}

.min-width-0 {
    min-width: 0;
}

.flex-grow-1 {
    flex-grow: 1;
}

.flex-shrink-0 {
    flex-shrink: 0;
}

/* Empty Children State */
.empty-children {
    border-left: 2px dashed #dee2e6;
    margin-left: 25px;
    font-style: italic;
}

.mobile-view .empty-children {
    margin-left: 15px;
}

/* Level Indicator */
.level-indicator {
    opacity: 0.7;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .node-content-mobile {
        margin-left: 0;
    }
    
    .children-container.mobile-children {
        margin-left: 10px;
        padding-left: 8px;
    }
}

@media (max-width: 576px) {
    .node-content-mobile {
        padding: 0.75rem;
    }
    
    .btn-xs {
        padding: 0.1rem 0.3rem;
        font-size: 0.65rem;
    }
    
    .text-sm {
        font-size: 0.75rem;
    }
    
    .children-container.mobile-children {
        margin-left: 8px;
        padding-left: 6px;
    }
}

/* Animation for expand/collapse */
.children-container {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 1000px;
    }
}

/* Ensure proper text truncation */
.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Badge adjustments for mobile */
.badge {
    font-size: 0.7rem;
    padding: 0.25em 0.4em;
}

/* Icon sizing consistency */
iconify-icon {
    flex-shrink: 0;
}
</style>