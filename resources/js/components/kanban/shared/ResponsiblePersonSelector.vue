<template>
    <div class="col-12 mt-3">
        <div class="responsible-person-card p-3">
            <span class="section-title d-block mb-3">Responsible Person</span>
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
                        <div class="info-row">
                            <span class="info-label">Name</span>
                            <span class="info-separator">:</span>
                            <span class="info-value fw-bold">{{ currentResponsiblePerson?.name || '--' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="department-badge">
                            Department : {{ department || 'Sales' }}
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
                                    Change Person
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
                                                <div class="user-item-name">{{ user.name }}</div>
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

.info-row {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.info-label {
    width: 60px;
    color: #64748B;
}

.info-separator {
    margin: 0 8px;
    color: #64748B;
}

.info-value {
    color: #01062C;
}

.department-badge {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 13px;
    color: #475569;
}

.btn-change-person {
    background:#FAA300;
    border: none;
    padding: 8px 20px;
    border-radius: 100px;
    font-size: 14px;
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

.invalid-feedback {
    font-size: 12px;
    color: #DC2626;
    margin-top: 4px;
    font-family: 'Montserrat';
}
</style>
