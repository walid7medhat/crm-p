<template>
    <b-modal 
        id="create-lead-modal" 
        v-model="show"
        hide-header
        hide-footer
        size="xl"
        centered
        body-class="p-0"
    >
            <div class="create-lead-container d-flex">
                <!-- Left Sidebar -->
                <div class="sidebar-pills p-4 d-flex flex-column gap-3 border-end">
                    <button 
                        v-for="pill in sidebarPills" 
                        :key="pill.id"
                        class="pill-btn"
                        :class="{ 'active': activePill === pill.id }"
                        @click="activePill = pill.id"
                    >
                        {{ pill.label }}
                    </button>
                </div>

                <!-- Right Content -->
                <div class="form-content-wrapper flex-grow-1 p-4 position-relative">
                    <button class="close-btn" @click="show = false">
                        <iconify-icon icon="lucide:x"></iconify-icon>
                    </button>

                    <div class="row g-4">
                        <!-- Lead Name -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Lead Name</label>
                            <b-form-input placeholder="Enter Lead Name" class="custom-input" />
                        </div>
                        <!-- Responsible Person -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Responsible Person</label>
                            <b-form-select v-model="form.responsible" :options="personOptions" class="custom-select" />
                        </div>

                        <!-- Created On -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Created On</label>
                            <b-form-select v-model="form.createdOn" :options="dateOptions" class="custom-select" />
                        </div>
                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Status</label>
                            <b-form-select v-model="form.status" :options="statusOptions" class="custom-select" />
                        </div>

                        <!-- ID -->
                        <div class="col-md-6">
                            <label class="form-label-custom">ID</label>
                            <b-form-input placeholder="Enter ID" class="custom-input" />
                        </div>
                        <!-- Modified By -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Modified By</label>
                            <b-form-select v-model="form.modifiedBy" :options="personOptions" class="custom-select" />
                        </div>

                        <!-- Stage Changed By -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Stage Changed By</label>
                            <b-form-select v-model="form.stageChangedBy" :options="personOptions" class="custom-select" />
                        </div>
                        <!-- Date -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Date</label>
                            <b-form-select v-model="form.date" :options="dateOptions" class="custom-select" />
                        </div>

                        <!-- Source -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Source</label>
                            <b-form-select v-model="form.source" :options="personOptions" class="custom-select" />
                        </div>
                        <!-- Lead Branch Source -->
                        <div class="col-md-6">
                            <label class="form-label-custom">Lead Branch Source</label>
                            <b-form-select v-model="form.branchSource" :options="dateOptions" class="custom-select" />
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="d-flex align-items-center justify-content-between mt-5 pt-4">
                        <div class="d-flex gap-4">
                            <a href="#" class="footer-link" @click.prevent>Add Field</a>
                            <a href="#" class="footer-link text-secondary" @click.prevent>Restore default fields</a>
                        </div>
                        <div class="d-flex gap-3">
                            <button class="btn-reset" @click="resetForm">Reset</button>
                            <button class="btn-search">Search</button>
                        </div>
                    </div>
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { BModal, BFormInput, BFormSelect } from 'bootstrap-vue-3'

const props = defineProps({
    modelValue: Boolean
})

const emit = defineEmits(['update:modelValue'])

const show = ref(props.modelValue)

watch(() => props.modelValue, (val) => {
    show.value = val
})

watch(show, (val) => {
    emit('update:modelValue', val)
})

const activePill = ref('leads-in-progress')

const sidebarPills = [
    { id: 'closed-leads', label: 'Closed Leads' },
    { id: 'leads-in-progress', label: 'Leads In Progress' },
    { id: 'my-leads', label: 'My Leads' },
    { id: 'dubai', label: 'Dubai' },
    { id: 'abu-dhabi', label: 'Abu Dhabi' }
]

const form = ref({
    responsible: null,
    createdOn: null,
    status: null,
    modifiedBy: null,
    stageChangedBy: null,
    date: null,
    source: null,
    branchSource: null
})

const personOptions = [
    { value: null, text: 'Select Person' }
]

const dateOptions = [
    { value: null, text: 'Any Date' }
]

const statusOptions = [
    { value: null, text: 'Not Specified' }
]

const resetForm = () => {
    form.value = {
        responsible: null,
        createdOn: null,
        status: null,
        modifiedBy: null,
        stageChangedBy: null,
        date: null,
        source: null,
        branchSource: null
    }
}
</script>

<style scoped>
.create-lead-container {
    min-height: 600px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}

.sidebar-pills {
    width: 240px;
    background: #F8FAFC;
    padding-top: 40px !important;
}

.pill-btn {
    border: none;
    background: #fff;
    border-radius: 100px;
    font-size: 13px;
    color: #475569;
    padding: 5px 10px;
    text-align: center;
    transition: all 0.2s;
    border: 1px solid #E2E8F0;
    width: fit-content;
    margin-left: 20px;
    text-wrap: nowrap;
}

.pill-btn.active {
    background: #01062C;
    color: #fff;
    border-color: #01062C;
}

.form-content-wrapper {
    padding: 40px 60px !important;
}

.close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    border: none;
    background: transparent;
    font-size: 24px;
    color: #64748B;
    cursor: pointer;
}

.form-label-custom {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #01062C;
    margin-bottom: 8px;
}

.custom-input, .custom-select {
    height: 48px;
    border-radius: 8px;
    border: 1px solid #E2E8F0;
    font-size: 14px;
    color: #64748B;
    padding: 0 16px;
}

.custom-input::placeholder {
    color: #94A3B8;
}

.footer-link {
    font-size: 14px;
    text-decoration: underline;
    color: #3B82F6;
    font-weight: 500;
}

.btn-reset {
    background: #F1F5F9;
    border: none;
    padding: 10px 32px;
    border-radius: 100px;
    font-size: 16px;
    font-weight: 500;
    color: #01062C;
}

.btn-search {
    background: #000;
    border: none;
    padding: 10px 40px;
    border-radius: 100px;
    font-size: 16px;
    font-weight: 500;
    color: #fff;
}

</style>
<style>
    .modal-dialog {
        z-index: 1060 !important;
    }
</style>

