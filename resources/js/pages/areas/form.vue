<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Area' : 'Add Area'" 
            :breadcrumbs="[
                { name: 'Areas', path: '/areas' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Area' : 'Add Area' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <!-- Name Field -->
                            <div class="mb-4">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="areaForm.name" 
                                       :class="{'is-invalid': errors.name}"
                                       placeholder="Enter area name"
                                       autofocus>
                                <div class="invalid-feedback" v-if="errors.name">
                                    {{ errors.name[0] }}
                                </div>
                            </div>

                            <!-- Type Field -->
                            <div class="mb-4">
                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                <v-select
                                    v-model="areaForm.type"
                                    :options="availableTypes"
                                    label="label"
                                    :reduce="(type) => type.value"
                                    placeholder="Select area type"
                                    :class="{'is-invalid': errors.type}"
                                    :disabled="loading"
                                >
                                    <template #option="{ label, value, description }">
                                        <div>
                                            <div class="fw-medium">{{ label }}</div>
                                            <small class="text-muted">{{ description }}</small>
                                        </div>
                                    </template>
                                </v-select>
                                <div class="invalid-feedback" v-if="errors.type">
                                    {{ errors.type[0] }}
                                </div>
                            </div>

                            <!-- Parent Field -->
                            <div class="mb-4">
                                <label class="form-label">Parent</label>
                                <v-select
                                    v-model="areaForm.parent_id"
                                    :options="availableParentAreas"
                                    label="name"
                                    :reduce="(area) => area.id"
                                    placeholder="Select parent (optional)"
                                    :class="{'is-invalid': errors.parent_id || circularReferenceWarning}"
                                    :disabled="loading"
                                >
                                    <template #option="{ name, type, area_parents_title }">
                                        <div class="d-flex align-items-center">
                                            <iconify-icon :icon="getTypeIcon(type)" 
                                                         :class="'text-' + getTypeColor(type)"
                                                         class="me-2"></iconify-icon>
                                            <div>
                                                <div>{{ name }}</div>
                                                <small class="text-muted">{{ area_parents_title }} • {{ type }}</small>
                                            </div>
                                        </div>
                                    </template>
                                    <template #no-options>
                                        <div class="text-center text-muted py-2">
                                            No available parent areas
                                        </div>
                                    </template>
                                </v-select>
                                <div class="invalid-feedback" v-if="errors.parent_id">
                                    {{ errors.parent_id[0] }}
                                </div>
                                <div v-if="circularReferenceWarning" class="text-danger small mt-1">
                                    <iconify-icon icon="lucide:alert-triangle" class="me-1"></iconify-icon>
                                    This selection would create a circular reference
                                </div>
                                <small class="text-muted">Leave empty for top-level area (country)</small>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-end border-top pt-4 mt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()" :disabled="loading">
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading || circularReferenceWarning">
                                    <span v-if="loading">
                                        <iconify-icon icon="lucide:loader-2" class="me-2 spin"></iconify-icon>
                                        {{ isEditMode ? 'Updating...' : 'Creating...' }}
                                    </span>
                                    <span v-else>
                                        <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                        {{ isEditMode ? 'Update' : 'Create' }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'AreaForm',
    components: {
        Breadcrumb,
        vSelect
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-area');
        const areaId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const parentAreas = ref([]);
        const allAreas = ref([]);
        const currentAreaChildren = ref([]);

        // Area Types
        const availableTypes = ref([
            { value: 'country', label: 'Country', description: 'Top-level country' },
            { value: 'city', label: 'City', description: 'City within a country' },
            { value: 'area', label: 'Area', description: 'Area within a city' },
            { value: 'community', label: 'Community', description: 'Community within an area' },
            { value: 'sub_community', label: 'Sub Community', description: 'Sub-community within a community' },
            { value: 'cluster', label: 'Cluster', description: 'Cluster of buildings' },
            { value: 'building', label: 'Building', description: 'Individual building' },
            { value: 'phaces', label: 'Phaces', description: 'Building phaces/units' }
        ]);

        // Area Form Data
        const areaForm = ref({
            name: "",
            type: "",
            parent_id: null
        });

        // Computed: Filter available parent areas based on type hierarchy
        const availableParentAreas = computed(() => {
            if (!areaForm.value.type) return [];

            const typeHierarchy = {
                country: [],
                city: ['country'],
                area: ['city'],
                community: ['area'],
                sub_community: ['community'],
                cluster: ['sub_community'],
                building: ['cluster', 'sub_community'],
                phaces: ['building']
            };

            const allowedParentTypes = typeHierarchy[areaForm.value.type] || [];
            
            return parentAreas.value.filter(area => {
                // In edit mode, cannot select self or children
                if (isEditMode.value) {
                    if (area.id == areaId.value) return false;
                    if (isAreaAChild(area.id)) return false;
                }
                
                return allowedParentTypes.includes(area.type);
            });
        });

        // Computed: Check for circular reference
        const circularReferenceWarning = computed(() => {
            if (!isEditMode.value || !areaForm.value.parent_id) return false;
            
            const selectedParentId = areaForm.value.parent_id;
            return isAreaAChild(selectedParentId);
        });

        // Check if an area is a child (direct or indirect) of current area
        const isAreaAChild = (areaId) => {
            const checkChildren = (children, targetId) => {
                for (const child of children) {
                    if (child.id === targetId) return true;
                    if (child.children && checkChildren(child.children, targetId)) return true;
                }
                return false;
            };
            
            return checkChildren(currentAreaChildren.value, areaId);
        };

        // Helper functions
        const getTypeIcon = (type) => {
            const icons = {
                country: 'lucide:globe',
                city: 'lucide:building',
                area: 'lucide:map-pin',
                community: 'lucide:users',
                sub_community: 'lucide:user-plus',
                cluster: 'lucide:group',
                building: 'lucide:home',
                phaces: 'lucide:layers'
            };
            return icons[type] || 'lucide:map-pin';
        };

        const getTypeColor = (type) => {
            const colors = {
                country: 'primary',
                city: 'success',
                area: 'info',
                community: 'warning',
                sub_community: 'secondary',
                cluster: 'dark',
                building: 'danger',
                phaces: 'muted'
            };
            return colors[type] || 'muted';
        };

        // Fetch all areas
        const fetchAllAreas = async () => {
            try {
                const response = await api.get('/listings/areas');
                allAreas.value = response.data.data || response.data || [];
            } catch (error) {
                console.error("❌ Error fetching all areas:", error);
            }
        };

        // Fetch parent areas (for dropdown)
        const fetchParentAreas = async () => {
            try {
                const response = await api.get('/listings/areas');
                parentAreas.value = response.data.data || response.data || [];
                
                console.log('📁 Parent areas loaded:', parentAreas.value);
            } catch (error) {
                console.error("❌ Error fetching parent areas:", error);
                showNotification("❌ Failed to load parent areas.", "error");
            }
        };

        // Find all children of current area recursively
        const findChildrenRecursively = (parentId, allAreasList) => {
            const directChildren = allAreasList.filter(area => area.parent_id == parentId);
            let allChildren = [...directChildren];
            
            directChildren.forEach(child => {
                const grandChildren = findChildrenRecursively(child.id, allAreasList);
                allChildren = [...allChildren, ...grandChildren];
            });
            
            return allChildren;
        };

        // Fetch area data for editing
        const fetchArea = async () => {
            try {
                loading.value = true;
                console.log('🔍 Fetching area data for ID:', areaId.value);
                
                const response = await api.get(`/listings/areas/${areaId.value}`);
                const areaData = response.data.data || response.data;

                if (!areaData) {
                    throw new Error('Area not found');
                }

                // Populate form with existing data
                areaForm.value = {
                    name: areaData.name || "",
                    type: areaData.type || "",
                    parent_id: areaData.parent_id || null
                };

                // Find all children of current area for circular reference prevention
                if (allAreas.value.length > 0) {
                    currentAreaChildren.value = findChildrenRecursively(areaId.value, allAreas.value);
                    console.log('👶 Current area children:', currentAreaChildren.value);
                }

                console.log('📝 Form populated with data:', areaForm.value);
                showNotification("✅ Area data loaded", "success");

            } catch (error) {
                console.error("❌ Error fetching area:", error);
                showNotification("❌ Failed to load area.", "error");
                router.push('/areas');
            } finally {
                loading.value = false;
            }
        };

        // Submit area form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Prevent submission if circular reference detected
                if (circularReferenceWarning.value) {
                    showNotification("❌ Cannot set a child area as parent to avoid circular reference.", "error");
                    loading.value = false;
                    return;
                }

                // Validation
                if (!areaForm.value.name?.trim()) {
                    showNotification("❌ Please enter area name", "error");
                    loading.value = false;
                    return;
                }

                if (!areaForm.value.type) {
                    showNotification("❌ Please select area type", "error");
                    loading.value = false;
                    return;
                }

                // Prepare data - ensure parent_id is null if empty
                const submitData = {
                    name: areaForm.value.name.trim(),
                    type: areaForm.value.type,
                    parent_id: areaForm.value.parent_id || null
                };

                console.log('🚀 Submitting data:', submitData);

                let response;
                
                if (isEditMode.value) {
                    response = await api.put(`/listings/areas/${areaId.value}`, submitData);
                } else {
                    response = await api.post("/listings/areas", submitData);
                }

                const successMessage = isEditMode.value ? "Area updated!" : "Area created!";
                showNotification(`✅ ${successMessage}`, "success");

                setTimeout(() => {
                    router.push('/areas');
                }, 1000);

            } catch (error) {
                console.error("❌ Error saving area:", error);
                
                // Handle specific backend errors
                if (error.response?.data?.message?.includes('circular')) {
                    showNotification("❌ Cannot set this parent area as it would create a circular reference.", "error");
                }
                else if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("❌ Please check the form for errors.", "error");
                } else {
                    showNotification("❌ Failed to save area.", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Helper function for notifications
        const showNotification = (message, type = 'info') => {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
            }
        };

        // Initialize component
        onMounted(async () => {
            try {
                loading.value = true;
                
                // Fetch all data in parallel for better performance
                await Promise.all([
                    fetchAllAreas(),
                    fetchParentAreas()
                ]);

                if (isEditMode.value) {
                    await fetchArea();
                }
            } catch (error) {
                console.error('❌ Error initializing component:', error);
            } finally {
                loading.value = false;
            }
        });

        return {
            isEditMode,
            loading,
            areaForm,
            errors,
            availableTypes,
            availableParentAreas,
            circularReferenceWarning,
            getTypeIcon,
            getTypeColor,
            submitForm
        };
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.invalid-feedback {
    display: block;
}

.v-select {
    --vs-border-radius: 6px;
}

.is-invalid {
    border-color: #dc3545;
}

.border-top {
    border-top: 1px solid #dee2e6 !important;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.text-muted {
    font-size: 0.875rem;
}

.text-danger small {
    font-size: 0.8rem;
}
</style>