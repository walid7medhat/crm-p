<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Layout Type' : 'Add Layout Type'" 
            :breadcrumbs="[
                { name: 'Layout Types', path: '/layout_types' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="ui-h-mini card-title mb-0">{{ isEditMode ? 'Edit Layout Type' : 'Add Layout Type' }}</h6>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <!-- Name Field Only -->
                            <div class="mb-4">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="unitViewForm.name" 
                                       :class="{'is-invalid': errors.name}"
                                       placeholder="Enter Layout Type name"
                                       autofocus>
                                <div class="invalid-feedback" v-if="errors.name">
                                    {{ errors.name[0] }}
                                </div>
                                <small class="text-muted">e.g., Sea View, Garden View, City View, etc.</small>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-end border-top pt-4 mt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()" :disabled="loading">
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading">
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
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'UnitViewForm',
    components: {
        Breadcrumb
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-layout_type');
        const unitViewId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);

        // Layout Type Form Data - Only name
        const unitViewForm = ref({
            name: ""
        });

        // Fetch Layout Type data for editing
        const fetchUnitView = async () => {
            try {
                loading.value = true;
                console.log('🔍 Fetching Layout Type data for ID:', unitViewId.value);
                
                const response = await api.get(`/listings/layout_types/${unitViewId.value}`);
                const unitViewData = response.data.data || response.data;

                if (!unitViewData) {
                    throw new Error('Layout Type not found');
                }

                // Populate form with existing data
                unitViewForm.value = {
                    name: unitViewData.name || ""
                };

                console.log('📝 Form populated with data:', unitViewForm.value);
                showNotification("✅ Layout Type data loaded", "success");

            } catch (error) {
                console.error("❌ Error fetching Layout Type:", error);
                showNotification("❌ Failed to load Layout Type.", "error");
                router.push('/layout_types');
            } finally {
                loading.value = false;
            }
        };

        // Submit Layout Type form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Validation
                if (!unitViewForm.value.name?.trim()) {
                    showNotification("❌ Please enter Layout Type name", "error");
                    loading.value = false;
                    return;
                }

                // Prepare data - only name
                const submitData = {
                    name: unitViewForm.value.name.trim()
                };

                console.log('🚀 Submitting data:', submitData);

                let response;
                
                if (isEditMode.value) {
                    response = await api.put(`/listings/layout_types/${unitViewId.value}`, submitData);
                } else {
                    response = await api.post("/listings/layout_types", submitData);
                }

                const successMessage = isEditMode.value ? "Layout Type updated successfully!" : "Layout Type created successfully!";
                showNotification(`✅ ${successMessage}`, "success");

                setTimeout(() => {
                    router.push('/layout_types');
                }, 1000);

            } catch (error) {
                console.error("❌ Error saving Layout Type:", error);
                
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("❌ Please check the form for errors.", "error");
                } else {
                    showNotification("❌ Failed to save Layout Type.", "error");
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
        onMounted(() => {
            if (isEditMode.value) {
                fetchUnitView();
            }
        });

        return {
            isEditMode,
            loading,
            unitViewForm,
            errors,
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
</style>