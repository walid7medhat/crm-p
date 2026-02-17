<template>
    <div class="history-search-form">
        <!-- Search Input -->
        <div class="mb-3">
            <label class="form-label">Search</label>
            <div class="search-input-wrapper">
                <iconify-icon icon="lucide:search" class="input-icon"></iconify-icon>
                <input 
                    type="text" 
                    class="form-control" 
                    placeholder="Search by keyword..."
                    v-model="localSearch"
                    @input="onSearchInput"
                />
                <button 
                    v-if="localSearch" 
                    class="btn-clear-input" 
                    @click="clearSearch"
                    type="button"
                >
                    <iconify-icon icon="lucide:x"></iconify-icon>
                </button>
            </div>
        </div>

        <!-- Event Type Filter -->
        <div class="mb-3">
            <label class="form-label">Event Type</label>
            <select class="form-select" v-model="localAction">
                <option value="">All Event Types</option>
                <option value="view">View</option>
                <option value="stage_changed">Status Changed</option>
                <option value="revert">Revert</option>
                <option value="assigned">Responsible Person Changed</option>
                <option value="updated">Lead Updated</option>
                <option value="created">Lead Created</option>
            </select>
        </div>

        <!-- User Filter -->
        <div class="mb-4">
            <label class="form-label">Created By</label>
            <select class="form-select" v-model="localUser">
                <option value="">All Users</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                    {{ user.name }}
                </option>
            </select>
        </div>

        <!-- Date Range (Optional) -->
        <div class="mb-4">
            <label class="form-label">Date Range</label>
            <div class="row g-2">
                <div class="col-6">
                    <input type="date" class="form-control" v-model="dateFrom" placeholder="From" />
                </div>
                <div class="col-6">
                    <input type="date" class="form-control" v-model="dateTo" placeholder="To" />
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-outline-secondary" @click="resetFilters" type="button">
                Reset
            </button>
            <button class="btn btn-primary" @click="applySearch" type="button">
                Apply Filters
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    initialSearch: {
        type: String,
        default: ''
    },
    initialAction: {
        type: String,
        default: ''
    },
    initialUser: {
        type: String,
        default: ''
    },
    users: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['search', 'close'])

const localSearch = ref(props.initialSearch)
const localAction = ref(props.initialAction)
const localUser = ref(props.initialUser)
const dateFrom = ref('')
const dateTo = ref('')

// Watch for prop changes
watch(() => props.initialSearch, (val) => {
    localSearch.value = val
})

watch(() => props.initialAction, (val) => {
    localAction.value = val
})

watch(() => props.initialUser, (val) => {
    localUser.value = val
})

const onSearchInput = () => {
    // Auto-search could be implemented here if needed
}

const clearSearch = () => {
    localSearch.value = ''
}

const resetFilters = () => {
    localSearch.value = ''
    localAction.value = ''
    localUser.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

const applySearch = () => {
    emit('search', {
        search: localSearch.value,
        action: localAction.value,
        user: localUser.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value
    })
}
</script>

<style scoped>
.history-search-form {
    padding: 4px 0;
}

.form-label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
    display: block;
}

.search-input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9CA3AF;
    font-size: 16px;
    pointer-events: none;
}

.form-control, .form-select {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    transition: all 0.2s ease;
    height: 42px;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #FAA300;
    box-shadow: 0 0 0 3px rgba(250, 163, 0, 0.1);
}

.form-control {
    padding-left: 40px;
}

.btn-clear-input {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9CA3AF;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.btn-clear-input:hover {
    background-color: #F3F4F6;
    color: #374151;
}

.btn-outline-secondary {
    background: white;
    border: 1px solid #E5E7EB;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-outline-secondary:hover {
    background: #F3F4F6;
    border-color: #D1D5DB;
}

.btn-primary {
    background: #FAA300;
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    font-size: 14px;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: #E09100;
}
</style>