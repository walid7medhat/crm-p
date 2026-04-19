<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Property Type' : 'Add Property Type'" 
            :breadcrumbs="[
                { name: 'Property Types', path: '/property_types' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="ui-h-mini card-title mb-0">{{ isEditMode ? 'Edit Property Type' : 'Add Property Type' }}</h6>
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
                                <input type="text" class="form-control" v-model="propertyTypeForm.name" 
                                       :class="{'is-invalid': errors.name}"
                                       placeholder="Enter property type name"
                                       autofocus>
                                <div class="invalid-feedback" v-if="errors.name">
                                    {{ errors.name[0] }}
                                </div>
                            </div>

                            <!-- Parent Field -->
                            <div class="mb-4">
                                <label class="form-label">Parent</label>
                                <v-select
                                    v-model="propertyTypeForm.parent_id"
                                    :options="availableParentCategories"
                                    label="name"
                                    :reduce="(category) => category.id"
                                    placeholder="Select parent (optional)"
                                    :class="{'is-invalid': errors.parent_id || circularReferenceWarning}"
                                    :disabled="loading"
                                >
                                    <template #option="{ name, id }">
                                        <div class="d-flex align-items-center">
                                            <iconify-icon icon="lucide:folder" class="me-2 text-muted"></iconify-icon>
                                            <span>{{ name }}</span>
                                        </div>
                                    </template>
                                    <template #no-options>
                                        <div class="text-center text-muted py-2">
                                            No available parent categories
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
                                <small class="text-muted">Leave empty for main category</small>
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
    name: 'PropertyTypeForm',
    components: {
        Breadcrumb,
        vSelect
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-property_type');
        const propertyTypeId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const parentCategories = ref([]);
        const allCategories = ref([]);
        const currentPropertyTypeChildren = ref([]);

        // Property Type Form Data
        const propertyTypeForm = ref({
            name: "",
            parent_id: null
        });

        // Computed: Filter available parent categories to prevent circular references
        const availableParentCategories = computed(() => {
            if (!isEditMode.value) return parentCategories.value;
            
            return parentCategories.value.filter(category => {
                // Cannot select self
                if (category.id == propertyTypeId.value) return false;
                
                // Cannot select any direct or indirect child
                return !isCategoryAChild(category.id);
            });
        });

        // Computed: Check for circular reference
        const circularReferenceWarning = computed(() => {
            if (!isEditMode.value || !propertyTypeForm.value.parent_id) return false;
            
            const selectedParentId = propertyTypeForm.value.parent_id;
            return isCategoryAChild(selectedParentId);
        });

        // Check if a category is a child (direct or indirect) of current property type
        const isCategoryAChild = (categoryId) => {
            const checkChildren = (children, targetId) => {
                for (const child of children) {
                    if (child.id === targetId) return true;
                    if (child.children && checkChildren(child.children, targetId)) return true;
                }
                return false;
            };
            
            return checkChildren(currentPropertyTypeChildren.value, categoryId);
        };

        // Fetch all categories for circular reference checking
        const fetchAllCategories = async () => {
            try {
                const response = await api.get('/listings/property-types');
                allCategories.value = response.data.data || response.data || [];
            } catch (error) {
                console.error("❌ Error fetching all categories:", error);
            }
        };

        // Fetch parent categories (main categories only)
        const fetchParentCategories = async () => {
            try {
                const response = await api.get('/listings/property-types?root_only=1');
                parentCategories.value = response.data.data || response.data || [];
                
                console.log('📁 Parent categories loaded:', parentCategories.value);
            } catch (error) {
                console.error("❌ Error fetching parent categories:", error);
                showNotification("❌ Failed to load parent categories.", "error");
            }
        };

        // Find all children of current property type recursively
        const findChildrenRecursively = (parentId, allCats) => {
            const directChildren = allCats.filter(cat => cat.parent_id == parentId);
            let allChildren = [...directChildren];
            
            directChildren.forEach(child => {
                const grandChildren = findChildrenRecursively(child.id, allCats);
                allChildren = [...allChildren, ...grandChildren];
            });
            
            return allChildren;
        };

        // Fetch property type data for editing
        const fetchPropertyType = async () => {
            try {
                loading.value = true;
                console.log('🔍 Fetching property type data for ID:', propertyTypeId.value);
                
                const response = await api.get(`/listings/property-types/${propertyTypeId.value}`);
                const propertyTypeData = response.data.data || response.data;

                if (!propertyTypeData) {
                    throw new Error('Property type not found');
                }

                // Populate form with existing data
                propertyTypeForm.value = {
                    name: propertyTypeData.name || "",
                    parent_id: propertyTypeData.parent_id || null
                };

                // Find all children of current property type for circular reference prevention
                if (allCategories.value.length > 0) {
                    currentPropertyTypeChildren.value = findChildrenRecursively(propertyTypeId.value, allCategories.value);
                    console.log('👶 Current type children:', currentPropertyTypeChildren.value);
                }

                console.log('📝 Form populated with data:', propertyTypeForm.value);
                showNotification("✅ Property type data loaded", "success");

            } catch (error) {
                console.error("❌ Error fetching property type:", error);
                showNotification("❌ Failed to load property type.", "error");
                router.push('/property_types');
            } finally {
                loading.value = false;
            }
        };

        // Submit property type form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Prevent submission if circular reference detected
                if (circularReferenceWarning.value) {
                    showNotification("❌ Cannot set a child category as parent to avoid circular reference.", "error");
                    loading.value = false;
                    return;
                }

                // Validation
                if (!propertyTypeForm.value.name?.trim()) {
                    showNotification("❌ Please enter property type name", "error");
                    loading.value = false;
                    return;
                }

                // Prepare data - ensure parent_id is null if empty
                const submitData = {
                    name: propertyTypeForm.value.name.trim(),
                    parent_id: propertyTypeForm.value.parent_id || null
                };

                console.log('🚀 Submitting data:', submitData);

                let response;
                
                if (isEditMode.value) {
                    response = await api.put(`/listings/property-types/${propertyTypeId.value}`, submitData);
                } else {
                    response = await api.post("/listings/property-types", submitData);
                }

                const successMessage = isEditMode.value ? "Property type updated!" : "Property type created!";
                showNotification(`✅ ${successMessage}`, "success");

                setTimeout(() => {
                    router.push('/property_types');
                }, 1000);

            } catch (error) {
                console.error("❌ Error saving property type:", error);
                
                // Handle specific backend errors
                if (error.response?.data?.message?.includes('circular')) {
                    showNotification("❌ Cannot set this parent category as it would create a circular reference.", "error");
                }
                else if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("❌ Please check the form for errors.", "error");
                } else {
                    showNotification("❌ Failed to save property type.", "error");
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
                    fetchAllCategories(),
                    fetchParentCategories()
                ]);

                if (isEditMode.value) {
                    await fetchPropertyType();
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
            propertyTypeForm,
            errors,
            availableParentCategories,
            circularReferenceWarning,
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