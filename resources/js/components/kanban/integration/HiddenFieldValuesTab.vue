<template>
    <div class="tab-content">
        <p class="main-section-title">Hidden Field Values</p>
        
        <!-- Project Field Block -->
        <div class="field-block">
            <label class="field-label">Project</label>
            
            <v-select 
                ref="projectSelect"
                v-model="selectedProject" 
                :options="computedProjectOptions" 
                :reduce="option => option.value"
                label="text"
                placeholder="Select Project"
                class="custom-v-select"
                :loading="loadingProjects"
                @option:selected="handleProjectSelect"
                @open="onDropdownOpen"
            >
                <template #open-indicator="{ attributes }">
                    <span v-bind="attributes">
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                    </span>
                </template>
                <template #option="option">
                    <div v-if="option.value === 'add-new-project'" class="add-project-option-wrapper">
                        <div class="add-project-option" @click.stop.prevent="openAddProjectModal">
                            <iconify-icon icon="lucide:plus" class="add-project-icon"></iconify-icon>
                            <span class="add-project-text">{{ option.text }}</span>
                        </div>
                    </div>
                    <div v-else>{{ option.text }}</div>
                </template>
            </v-select>
        </div>

        <!-- Add Project Modal -->
        <b-modal 
            id="add-project-modal" 
            v-model="showAddProjectModal"
            hide-header
            hide-footer
            size="md"
            centered
            body-class="p-0"
            modal-class="add-project-modal"
            backdrop="true"
            @hidden="resetAddProjectForm"
        >
            <div class="add-project-modal-content">
                <!-- Header -->
                <div class="modal-header-section">
                    <p class="modal-title">Add New Project</p>
                    <button class="close-btn" @click="closeAddProjectModal">
                        <iconify-icon icon="lucide:x" class="close-icon"></iconify-icon>
                    </button>
                </div>

                <!-- Content Area -->
                <div class="modal-body-section">
                    <label class="add-project-label">Project Name</label>
                    <b-form-input 
                        v-model="newProjectName" 
                        placeholder="Enter Project Name" 
                        class="add-project-input"
                        :disabled="addingProject"
                        @keyup.enter="handleAddProject"
                    />
                </div>

                <!-- Footer Buttons -->
                <div class="modal-footer-section">
                    <button class="footer-btn cancel-btn" @click="closeAddProjectModal" :disabled="addingProject">Cancel</button>
                    <button class="footer-btn apply-btn" @click="handleAddProject" :disabled="!newProjectName?.trim() || addingProject">
                        <span v-if="addingProject">Adding...</span>
                        <span v-else>Add</span>
                    </button>
                </div>
            </div>
        </b-modal>

        <!-- Source Leads Field Block -->
        <div class="field-block">
            <div class="source-leads-header">
                <span class="source-leads-group-label">Source Leads</span>
                <span class="source-leads-active-label">Meta Ads - Lead Form</span>
            </div>
            <v-select 
                v-model="selectedLeadValue" 
                :options="leadValueOptions" 
                :reduce="option => option.value"
                label="text"
                placeholder="Select Lead Value"
                class="custom-v-select meta-ads-select"
                :loading="loadingSources"
            >
                <template #open-indicator="{ attributes }">
                    <span v-bind="attributes">
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                    </span>
                </template>
                <template #option="{ text, value }">
                    <div class="meta-ads-option">
                        <span :class="{ 'selected-text': selectedLeadValue === value }">{{ text }}</span>
                        <iconify-icon 
                            v-if="selectedLeadValue === value" 
                            icon="lucide:check" 
                            class="check-icon"
                        ></iconify-icon>
                    </div>
                </template>
            </v-select>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue'
import { BFormInput, BModal } from 'bootstrap-vue-3'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import api from '@/plugins/axios'

const { proxy } = getCurrentInstance()

const props = defineProps({
    project: {
        type: [String, Number],
        default: null
    },
    leadSource: {
        type: [String, Number],
        default: null
    }
})

const emit = defineEmits(['update:project', 'update:leadSource'])

const selectedProject = ref(props.project)
const selectedLeadValue = ref(props.leadSource)
const showAddProjectModal = ref(false)
const newProjectName = ref('')
const projectSelect = ref(null)
const loadingProjects = ref(false)
const loadingSources = ref(false)
const addingProject = ref(false)

// Project options from API
const projectOptions = ref([])

// Lead source options from API /api/sources
const leadValueOptions = ref([])

// Load projects from API
const loadProjects = async () => {
    loadingProjects.value = true
    try {
        const response = await api.get('/listings/projects')
        const projects = response.data.data || response.data || []
        
        projectOptions.value = projects.map(project => ({
            value: project.id,
            text: project.name
        }))
    } catch (error) {
        console.error('Failed to load projects:', error)
        proxy?.$showNotification?.('Failed to load projects', 'error')
    } finally {
        loadingProjects.value = false
    }
}

// Load lead sources from API /api/sources
const loadSources = async () => {
    loadingSources.value = true
    try {
        const response = await api.get('/sources')
        const sources = response.data.data || response.data || []

        leadValueOptions.value = sources.map(source => ({
            value: source.name,
            text: source.name
        }))

        // Auto select Meta Lead Form
        const metaSource = sources.find(
            source => source.name === 'Lead Form'
        )

        if (metaSource) {
            selectedLeadValue.value = metaSource.name
        }

    } catch (error) {
        console.error('Failed to load sources:', error)
        proxy?.$showNotification?.('Failed to load sources', 'error')
    } finally {
        loadingSources.value = false
    }
}

// Computed options that include the "Add New Project" option
const computedProjectOptions = computed(() => {
    return [
        ...projectOptions.value,
        // { value: 'add-new-project', text: 'Add New Project' }
    ]
})

// Load data on mount
onMounted(() => {
    loadProjects()
    loadSources()
})

// Methods
const openAddProjectModal = () => {
    // Close dropdown
    nextTick(() => {
        const toggle = document.querySelector('.custom-v-select .vs__dropdown-toggle')
        if (toggle) {
            toggle.blur()
        }
    })
    
    newProjectName.value = ''
    showAddProjectModal.value = true
    
    // Focus input
    nextTick(() => {
        setTimeout(() => {
            const input = document.querySelector('.add-project-modal-content .add-project-input')
            if (input) {
                input.focus()
            }
        }, 300)
    })
}

const closeAddProjectModal = () => {
    showAddProjectModal.value = false
    resetAddProjectForm()
}

const handleProjectSelect = (option) => {
    if (option === 'add-new-project') {
        selectedProject.value = null
        openAddProjectModal()
        return false
    }
}

const handleAddProject = async () => {
    if (!newProjectName.value || !newProjectName.value.trim()) {
        return
    }
    
    addingProject.value = true
    
    try {
        // Save to database
        const response = await api.post('/listings/projects', {
            name: newProjectName.value.trim()
        })
        
        const newProject = response.data.data || response.data
        
        // Add to options list with real ID
        projectOptions.value.push({
            value: newProject.id,
            text: newProject.name
        })
        
        // Select the newly added project
        selectedProject.value = newProject.id
        
        // Show success message
        proxy?.$showNotification?.('Project added successfully', 'success')
        
        // Close modal
        closeAddProjectModal()
    } catch (error) {
        console.error('Failed to add project:', error)
        proxy?.$showNotification?.(error.response?.data?.message || 'Failed to add project', 'error')
    } finally {
        addingProject.value = false
    }
}

const onDropdownOpen = () => {
    nextTick(() => {
        const dropdownMenu = document.querySelector('.custom-v-select .vs__dropdown-menu')
        if (dropdownMenu) {
            const options = dropdownMenu.querySelectorAll('.vs__dropdown-option')
            if (options.length > 0) {
                const lastOption = options[options.length - 1]
                lastOption.classList.add('add-project-option-item')
            }
        }
    })
}

const resetAddProjectForm = () => {
    newProjectName.value = ''
}

// Watches
watch(selectedProject, (newVal) => {
    emit('update:project', newVal)
})

watch(selectedLeadValue, (newVal) => {
    emit('update:leadSource', newVal)
})
</script>


<style scoped>
.tab-content {
    padding: 0;
}

.main-section-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 16px;
    font-weight: 600;
    color: #000000;
    margin: 0 0 24px 0;
    padding: 0;
}

.field-block {
    background: #FFFFFF;
    border: 1px solid #EEEEEE;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
}

.field-label {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #000000;
    margin-bottom: 10px;
}

.source-leads-header {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 10px;
}

.source-leads-group-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 400;
    color: #AAAAAA;
}

.source-leads-active-label {
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #000000;
}

/* Custom v-select styles matching CreateLeadModal.vue */
:deep(.custom-v-select) {
    font-family: 'Montserrat';
}

:deep(.custom-v-select .vs__dropdown-toggle) {
    height: 42px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    background: #fff;
    padding: 0 8px;
}

:deep(.custom-v-select .vs__selected-options) {
    flex-wrap: nowrap;
    overflow: hidden;
    max-width: calc(100% - 30px);
}

:deep(.custom-v-select .vs__selected) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    max-width: 100%;
    line-height: 40px; 
}

:deep(.custom-v-select .vs__search) {
    font-size: 13px;
    color: #64748B;
    margin: 0;
    padding: 0;
}

:deep(.custom-v-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.meta-ads-select .vs__search::placeholder) {
    color: #94A3B8;
}

:deep(.custom-v-select .vs__actions) {
    padding: 0 8px;
}

:deep(.custom-v-select .vs__open-indicator-icon) {
    font-size: 16px;
    color: #64748B;
}

:deep(svg) {
    vertical-align: middle !important;
}

:deep(.custom-v-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 8px 0;
    margin-top: 4px;
    z-index: 1100;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
    
    /* Add these properties for scrolling */
    max-height: 300px !important; /* Fixed max height */
    overflow-y: auto !important; /* Enable vertical scrolling */
    overflow-x: hidden; /* Hide horizontal scroll */
}

:deep(.meta-ads-select .vs__dropdown-menu) {
    border: none;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.15);
    padding: 4px 0;
    margin-top: 4px;
    z-index: 1100;
    border-radius: 8px;
    overflow: hidden;
    background: #FFFFFF;
    
    /* Add these properties for scrolling */
    max-height: 250px !important; /* Fixed max height */
    overflow-y: auto !important; /* Enable vertical scrolling */
    overflow-x: hidden; /* Hide horizontal scroll */
}

:deep(.custom-v-select .vs__dropdown-option) {
    padding: 8px 12px;
    font-size: 13px;
    color: #000000;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
    font-weight: 400;
    background: transparent;
}

:deep(.custom-v-select .vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
    color: #000000 !important;
}

:deep(.custom-v-select .vs__dropdown-option--selected) {
    background: transparent;
    color: #000000;
}

/* Meta Ads Select Specific Styling */
.meta-ads-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.meta-ads-option .selected-text {
    font-weight: 700;
    color: #000000;
}

.meta-ads-option .check-icon {
    font-size: 16px;
    color: #3B82F6;
    flex-shrink: 0;
}

:deep(.meta-ads-select .vs__dropdown-option) {
    padding: 10px 16px;
    font-size: 13px;
    color: #000000;
    font-weight: 400;
    background: transparent;
    cursor: pointer;
}

:deep(.meta-ads-select .vs__dropdown-option:hover) {
    background: #F8FAFC !important;
    color: #000000 !important;
}

:deep(.meta-ads-select .vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
    color: #000000 !important;
    font-weight: 400;
}

:deep(.meta-ads-select .vs__dropdown-option--selected) {
    background: transparent !important;
    color: #000000;
}

/* Remove border and special styles from last option in Meta Ads select */
:deep(.meta-ads-select .vs__dropdown-option:last-child) {
    padding: 10px 16px !important;
    border-top: none !important;
    border-bottom: none !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    padding-top: 10px !important;
    padding-bottom: 10px !important;
}

:deep(.meta-ads-select .vs__dropdown-option:last-child:hover) {
    background: #F8FAFC !important;
}

:deep(.meta-ads-select .vs__dropdown-option:last-child.vs__dropdown-option--highlight) {
    background: #F8FAFC !important;
}

/* Ensure Meta Ads select options don't inherit border styles from general custom-v-select */
:deep(.meta-ads-select .vs__dropdown-option) {
    border-top: none !important;
    border-bottom: none !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* Add New Project Option Styling */
.add-project-option-wrapper {
    padding: 0;
    margin: 0;
    border-top: 1px solid #E2E8F0;
    margin-top: 8px;
    padding-top: 8px;
    width: 100%;
}

/* Target the option that contains add-project-option-wrapper - using last-child as fallback */
/* Exclude Meta Ads select from these styles */
:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option:last-child),
:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option.add-project-option-item) {
    padding: 0 !important;
    border-top: 1px solid #E2E8F0;
    margin-top: 8px;
    padding-top: 8px !important;
}

:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option:last-child:hover),
:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option.add-project-option-item:hover) {
    background: transparent !important;
}

:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option:last-child.vs__dropdown-option--highlight),
:deep(.custom-v-select:not(.meta-ads-select) .vs__dropdown-option.add-project-option-item.vs__dropdown-option--highlight) {
    background: transparent !important;
}

.add-project-option {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    margin: 0 8px 8px 8px;
    background: #F4F4F4;
    border: 1px solid #E5E7EB;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Montserrat', sans-serif;
    user-select: none;
    -webkit-user-select: none;
    pointer-events: auto;
}

.add-project-option:hover {
    background: #E5E7EB;
    border-color: #CBD5E1;
}

.add-project-icon {
    font-size: 16px;
    color: #FAA300;
    flex-shrink: 0;
}

.add-project-text {
    font-size: 13px;
    font-weight: 400;
    color: #000000;
    white-space: nowrap;
}

.add-project-option .add-project-icon {
    font-size: 16px;
    color: #FAA300;
    flex-shrink: 0;
}

.add-project-option .add-project-text {
    font-size: 13px;
    font-weight: 400;
    color: #000000;
    white-space: nowrap;
}

.add-project-label {
    display: block;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #000000;
    margin-bottom: 12px;
    line-height: 1.5;
}

.add-project-input {
    width: 100%;
    height: 42px;
    padding: 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    color: #1E293B;
    background: #FFFFFF;
    transition: all 0.2s;
}

.add-project-input:focus {
    border-color: #01062C;
    box-shadow: 0 0 0 3px rgba(1, 6, 44, 0.1);
    outline: none;
}

.add-project-input::placeholder {
    color: #94A3B8;
    opacity: 1;
    font-size: 13px;
}

/* Add Project Modal Styles */
:deep(.add-project-modal) {
    z-index: 1055 !important;
}

:deep(.add-project-modal.show .modal-backdrop) {
    background-color: rgba(0, 0, 0, 0.5) !important;
    z-index: 1050 !important;
    opacity: 1 !important;
}

:deep(.add-project-modal .modal-backdrop.show) {
    background-color: rgba(0, 0, 0, 0.5) !important;
    opacity: 1 !important;
}

:deep(.add-project-modal .modal-dialog) {
    max-width: 500px;
    margin: 1.75rem auto;
    z-index: 1055 !important;
    position: relative;
}

:deep(.add-project-modal .modal-content) {
    border-radius: 16px;
    border: none;
    box-shadow: 0px 10px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    position: relative;
    z-index: 1055 !important;
}

:deep(.add-project-modal .modal-body) {
    padding: 0;
}

:deep(.add-project-modal.show) {
    display: block !important;
}

:deep(.add-project-modal.show .modal-dialog) {
    transform: none !important;
}

.add-project-modal-content {
    background: #FFFFFF;
    display: flex;
    flex-direction: column;
    border-radius: 10px;
}

.add-project-modal-content .modal-header-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 32px;
    border-bottom: 1px solid #E2E8F0;
}

.add-project-modal-content .modal-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    color: #01062C;
    margin: 0;
}

.add-project-modal-content .close-btn {
    background: transparent;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    border-radius: 4px;
    transition: all 0.2s;
}

.add-project-modal-content .close-btn:hover {
    background: #F1F5F9;
    color: #01062C;
}

.add-project-modal-content .close-icon {
    font-size: 20px;
}

.add-project-modal-content .modal-body-section {
    padding: 24px 32px;
}

.add-project-modal-content .modal-footer-section {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 32px;
    border-top: 1px solid #E2E8F0;
    background: #FFFFFF;
}

.add-project-modal-content .footer-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.add-project-modal-content .footer-btn.cancel-btn {
    background: #FFFFFF;
    color: #64748B;
    border: 1px solid #E2E8F0;
}

.add-project-modal-content .footer-btn.cancel-btn:hover {
    background: #F8FAFC;
    border-color: #CBD5E1;
}

.add-project-modal-content .footer-btn.apply-btn {
    background: #01062C;
    color: #FFFFFF;
}

.add-project-modal-content .footer-btn.apply-btn:hover {
    background: #020A3D;
}
</style>
