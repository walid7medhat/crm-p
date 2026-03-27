<template>
    <div class="col-12" :class="{ 'mt-3': !hideSectionTitle }">
        <div class="responsible-person-card p-3">
            <span v-if="!hideSectionTitle" class="section-title d-block mb-3">Responsible Person</span>
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-wrapper">
                        <img 
                            :src="currentResponsiblePerson?.avatar || defaultAvatar" 
                            alt="Avatar" 
                            class="responsible-avatar" 
                        />
                    </div>
                    <div class="responsible-info">
                        <div class="info-value fw-bold">{{ currentResponsiblePerson?.name || '--' }}</div>
                        <div class="info-subline">
                            <span class="sub-key">Position:</span>
                            <span class="sub-value">{{ positionName }}</span>
                        </div>
                        <div class="info-subline">
                            <span class="sub-key">Parent:</span>
                            <span class="sub-value">{{ parentName }}</span>
                        </div>
                    </div>
                    <b-dropdown 
                        variant="link" 
                        toggle-class="text-decoration-none p-0 no-caret-custom" 
                        no-caret
                        right
                        class="change-person-dropdown"
                        :show="dropdownShow"
                        @update:show="dropdownShow = $event"
                    >
                        <template #button-content>
                            <button class="btn-change-person">
                                Change
                                <iconify-icon icon="lucide:user-plus" class="ms-1"></iconify-icon>
                            </button>
                        </template>
                        
                        <div class="dropdown-search-wrapper p-3">
                            <div class="d-flex align-items-center justify-content-between border-bottom mb-3">
                                <span class="modal-title-dropdown">Change Responsible Person</span>
                                <button class="close-btn-top" @click="dropdownShow = false">
                                    <iconify-icon icon="lucide:x"></iconify-icon>
                                </button>
                            </div>
                            <div class="search-input-wrapper mb-3">
                                <b-form-input 
                                    v-model="searchQuery" 
                                    placeholder="Search Person" 
                                    class="dropdown-search-input"
                                />
                                <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                            </div>
                            
                            <div class="user-list-scroll">
                                <div 
                                    v-for="user in filteredUsers" 
                                    :key="user.id"
                                    class="user-item d-flex align-items-center justify-content-between p-2"
                                    @click="selectUser(user)"
                                    :class="{ 'selected': modelValue === user.id }"
                                >
                                    <div class="d-flex align-items-center gap-2">
                                        <img 
                                            :src="user.avatar || defaultAvatar" 
                                            class="user-item-avatar" 
                                        />
                                        <div class="user-item-info">
                                            <div class="user-item-head">
                                                <div class="user-item-name">{{ user.name }}</div>
                                                <span v-if="getUserPosition(user) !== '—'" class="user-position-badge">
                                                    {{ getUserPosition(user) }}
                                                </span>
                                            </div>
                                            <div class="user-item-meta-line">
                                                <span class="meta-label">Parent:</span>
                                                <span class="meta-value">{{ getUserParent(user) }}</span>
                                                <span class="meta-divider">|</span>
                                                <span class="meta-label">Branch:</span>
                                                <span class="meta-value">{{ getUserBranch(user) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <iconify-icon 
                                        v-if="modelValue === user.id" 
                                        icon="lucide:check" 
                                        class="text-warning"
                                    ></iconify-icon>
                                </div>
                                <div v-if="filteredUsers.length === 0" class="text-center p-3 text-muted">
                                    No persons found
                                </div>
                            </div>
                        </div>
                    </b-dropdown>
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <div v-if="validationError" class="invalid-feedback d-block" style="margin-top: -8px;">
                        {{ validationError }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { BDropdown, BFormInput } from 'bootstrap-vue-3'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null
    },
    responsiblePerson: {
        type: Object,
        default: null
    },
    users: {
        type: Array,
        default: () => []
    },
    validationError: {
        type: String,
        default: null
    },
    department: {
        type: String,
        default: 'Sales'
    },
    hideSectionTitle: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue', 'user-selected'])

const searchQuery = ref('')
const dropdownShow = ref(false)
const defaultAvatar = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'

// Compute responsible person from users list if not provided
const currentResponsiblePerson = computed(() => {
    if (props.responsiblePerson) {
        return props.responsiblePerson
    }
    if (props.modelValue && props.users.length > 0) {
        return props.users.find(user => user.id === props.modelValue) || null
    }
    return null
})

const filteredUsers = computed(() => {
    if (!searchQuery.value) return props.users
    return props.users.filter(user => 
        user.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        user.email?.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

const selectUser = (user) => {
    emit('update:modelValue', user.id)
    emit('user-selected', user)
    dropdownShow.value = false
    searchQuery.value = ''
}
const getUserPosition = (user) => {
    return user?.position || user?.designation || user?.job_title || user?.role_name || '—'
}

const getUserParent = (user) => {
    return user?.parent_name || user?.manager_name || user?.team_lead_name || user?.parent?.name || '—'
}

const getUserBranch = (user) => {
    return user?.branch_name || user?.branch?.name || user?.office_name || user?.office?.name || '—'
}

const positionName = computed(() => {
    return currentResponsiblePerson.value?.position || currentResponsiblePerson.value?.designation || currentResponsiblePerson.value?.job_title || currentResponsiblePerson.value?.role_name || '—'
})

const parentName = computed(() => {
    return currentResponsiblePerson.value?.parent_name || currentResponsiblePerson.value?.manager_name || currentResponsiblePerson.value?.team_lead_name || currentResponsiblePerson.value?.parent?.name || '—'
})
</script>

<style scoped>
.responsible-person-card {
    background: #FFFFFF;
    border: 1px solid #F3F3F3;
    border-radius: 10px;
    box-shadow: 1px 1px 5px 5px #00000005;
}

.section-title {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 13px;
    color: #01062C;
}

.responsible-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
}

.responsible-info {
    font-family: 'Montserrat';
    font-size: 14px;
}

.info-subline {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
    font-size: 12px;
}

.sub-key {
    color: #64748B;
    font-weight: 500;
}

.sub-value {
    color: #334155;
    font-weight: 600;
}

.info-value {
    color: #01062C;
}

.btn-change-person {
    background:#FAA300;
    border: none;
    padding: 7px 14px;
    border-radius: 100px;
    font-size: 13px;
    color: #FFFFFF;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.modal-title-dropdown {
    font-family: Montserrat;
    font-weight: 500;
    font-style: Medium;
    font-size: 14px;
}

.border-bottom {
    border-bottom: 1px solid #F4F4F4;
}

.close-btn-top {
    background: transparent;
    font-size: 20px;
    color: #000;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 10px;
}

/* Dropdown Styles */
:deep(.change-person-dropdown .dropdown-toggle::after) {
    display: none !important;
}

:deep(.change-person-dropdown .dropdown-menu) {
    width: 380px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
    padding: 0;
    margin-top: 10px;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.dropdown-search-input {
    height: 45px !important;
    border-radius: 25px !important;
    padding-left: 20px !important;
    padding-right: 45px !important;
    border: 1px solid #E2E8F0 !important;
    font-size: 14px !important;
}

.search-icon {
    position: absolute;
    right: 15px;
    color: #FAA300;
    font-size: 20px;
}

.user-list-scroll {
    max-height: 300px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom Scrollbar */
.user-list-scroll::-webkit-scrollbar {
    width: 4px;
}

.user-list-scroll::-webkit-scrollbar-track {
    background: #F1F5F9;
    border-radius: 10px;
}

.user-list-scroll::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
}

.user-item {
    cursor: pointer;
    border-radius: 8px;
    transition: background 0.2s;
    margin-bottom: 4px;
}

.user-item:hover {
    background: #F8FAFC;
}

.user-item.selected {
    background: #FFFBEB;
}

.user-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.user-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #01062C;
    font-family: 'Montserrat';
}

.user-item-head {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-position-badge {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    line-height: 1;
    padding: 4px 8px;
    border-radius: 999px;
}

.user-item-meta-line {
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    line-height: 1.3;
    color: #64748b;
    font-family: 'Montserrat';
}

.meta-label {
    font-weight: 600;
    color: #64748b;
}

.meta-value {
    font-weight: 500;
    color: #334155;
}

.meta-divider {
    color: #cbd5e1;
}

.invalid-feedback {
    font-size: 12px;
    color: #DC2626;
    margin-top: 4px;
    font-family: 'Montserrat';
}
</style>
