<template>
    <div class="responsible-card bg-white p-3 radius-12 shadow-sm mt-3">
        <div class="info-section-title mb-3">Responsible Person</div>
        <div class="info-group">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label-custom">Responsible Person</label>
                <b-button
                    variant="link"
                    class="p-0 edit-person-btn"
                    @click="openPersonModal"
                    :disabled="isUpdatingPerson"
                >
                    <iconify-icon icon="lucide:edit" class="edit-icon"></iconify-icon>
                    <span>Change</span>
                </b-button>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-wrapper person-hover-anchor" @mouseenter="showPersonCard = true" @mouseleave="showPersonCard = false">
                    <img
                        v-if="lead?.responsible_person?.avatar"
                        :src="lead?.responsible_person?.avatar"
                        class="avatar-md rounded-circle"
                    />
                    <div v-else class="avatar-placeholder">
                        <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                    </div>
                    <transition name="person-card-pop">
                        <div v-if="showPersonCard" class="person-hover-card">
                            <div class="person-hover-head">
                                <img
                                    v-if="lead?.responsible_person?.avatar"
                                    :src="lead?.responsible_person?.avatar"
                                    alt=""
                                    class="person-hover-avatar"
                                />
                                <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                    <iconify-icon icon="lucide:user" class="avatar-icon"></iconify-icon>
                                </div>
                                <div>
                                    <div class="person-hover-name">{{ lead?.responsible_person?.name || '—' }}</div>
                                    <div class="person-hover-role">{{ lead?.responsible_person?.position || lead?.responsible_person?.role_name || 'Team Member' }}</div>
                                </div>
                            </div>
                            <div class="person-hover-line">
                                <span>Reports To</span>
                                <b>{{ lead?.responsible_person?.manager_name || lead?.responsible_person?.team_lead_name || 'Not specified' }}</b>
                            </div>
                            <div class="person-hover-line">
                                <span>Branch</span>
                                <b>{{ lead?.responsible_person?.branch_name || lead?.lead_branch_source || 'Not specified' }}</b>
                            </div>
                        </div>
                    </transition>
                </div>
                <div class="flex-grow-1">
                    <div class="info-value">{{ lead?.responsible_person?.name || '—' }}</div>
                </div>
            </div>
        </div>

        <b-modal
            v-model="showPersonModal"
            title="Change Responsible Person"
            hide-footer
            size="md"
            class="person-modal"
            @hidden="resetPersonModal"
        >
            <div class="person-modal-content">
                <div class="search-input-wrapper mb-3">
                    <b-form-input
                        v-model="personSearchQuery"
                        placeholder="Search Person by name or email"
                        class="person-search-input"
                    />
                    <iconify-icon icon="lucide:search" class="search-icon"></iconify-icon>
                </div>

                <div v-if="isLoadingPersons" class="text-center py-4">
                    <b-spinner small variant="warning" label="Loading..."></b-spinner>
                    <p class="mt-2 text-muted">Loading persons...</p>
                </div>

                <div v-else class="person-list-scroll">
                    <div
                        v-for="user in filteredPersons"
                        :key="user.id"
                        class="person-item d-flex align-items-center justify-content-between p-2"
                        @click="selectPerson(user)"
                        :class="{ 'selected': selectedPersonId === user.id, 'current': lead?.responsible_person?.id === user.id }"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <img
                                :src="user.avatar || 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQz_em9Ua12dTx64KMpyFSdH1sbuA2Ud5BKxQ&s'"
                                class="person-item-avatar"
                            />
                            <div class="person-item-info">
                                <div class="person-item-name">
                                    <span class="user-item-name">{{ user.name }}</span>
                                    <span v-if="user.role_name" class="user-position-badge">{{ user.role_name }}</span>
                                </div>
                                <div class="user-item-meta-line">
                                    <span class="meta-label">Parent:</span>
                                    <span class="meta-value">{{ user.parent_name }}</span>
                                    <span class="meta-divider" v-if="user.branch_name">|</span>
                                    <span class="meta-label" v-if="user.branch_name">Branch:</span>
                                    <span class="meta-value" v-if="user.branch_name">{{ user.branch_name }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="lead?.responsible_person?.id === user.id" class="current-badge">Current</span>
                            <iconify-icon v-if="selectedPersonId === user.id" icon="lucide:check" class="text-warning"></iconify-icon>
                        </div>
                    </div>
                </div>

                <div v-if="personUpdateError" class="alert alert-danger mt-3 py-2">
                    <iconify-icon icon="lucide:alert-circle" class="me-1"></iconify-icon>
                    {{ personUpdateError }}
                </div>

                <div class="modal-footer-custom mt-3">
                    <b-button variant="light" @click="showPersonModal = false" :disabled="isUpdatingPerson">Cancel</b-button>
                    <b-button
                        variant="warning"
                        @click="updateResponsiblePerson"
                        :disabled="!selectedPersonId || isUpdatingPerson || selectedPersonId === lead?.responsible_person?.id"
                    >
                        <b-spinner v-if="isUpdatingPerson" small></b-spinner>
                        <span v-else>Update Person</span>
                    </b-button>
                </div>
            </div>
        </b-modal>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { BButton, BModal, BFormInput, BSpinner } from 'bootstrap-vue-3'
import api from '@/plugins/axios'

const props = defineProps({ lead: Object })
const emit = defineEmits(['person-updated'])

const showPersonModal = ref(false)
const isLoadingPersons = ref(false)
const isUpdatingPerson = ref(false)
const personSearchQuery = ref('')
const personsList = ref([])
const selectedPersonId = ref(null)
const personUpdateError = ref('')
const showPersonCard = ref(false)

const filteredPersons = computed(() => {
    if (!personSearchQuery.value) return personsList.value
    const query = personSearchQuery.value.toLowerCase()
    return personsList.value.filter(person =>
        person.name.toLowerCase().includes(query) || person.email.toLowerCase().includes(query)
    )
})

const fetchAvailablePersons = async () => {
    isLoadingPersons.value = true
    personUpdateError.value = ''
    try {
        const response = await api.get('/available-responsible-persons')
        personsList.value = response.data.data || response.data || []
    } catch (error) {
        personUpdateError.value = 'Failed to load persons list'
    } finally {
        isLoadingPersons.value = false
    }
}

const openPersonModal = () => {
    selectedPersonId.value = props.lead?.responsible_person?.id || null
    personSearchQuery.value = ''
    personUpdateError.value = ''
    fetchAvailablePersons()
    showPersonModal.value = true
}

const selectPerson = (person) => {
    selectedPersonId.value = person.id
}

const resetPersonModal = () => {
    selectedPersonId.value = null
    personSearchQuery.value = ''
    personsList.value = []
    personUpdateError.value = ''
}

const updateResponsiblePerson = async () => {
    if (!selectedPersonId.value) return
    isUpdatingPerson.value = true
    personUpdateError.value = ''
    try {
        await api.post(`/leads/${props.lead.id}/assign-responsible-person`, {
            responsible_person_id: selectedPersonId.value
        })
        const selectedPerson = personsList.value.find(p => p.id === selectedPersonId.value)
        emit('person-updated', {
            id: selectedPersonId.value,
            name: selectedPerson?.name,
            avatar: selectedPerson?.avatar,
            role_name: selectedPerson?.role_name,
            manager_name: selectedPerson?.parent_name,
            branch_name: selectedPerson?.branch_name,
        })
        window.$showNotification?.('Responsible person updated successfully!', 'success')
        showPersonModal.value = false
    } catch (error) {
        personUpdateError.value = error.response?.data?.message || 'Failed to update responsible person'
        window.$showNotification?.(personUpdateError.value, 'error')
    } finally {
        isUpdatingPerson.value = false
    }
}
</script>

<style scoped>
.responsible-card { border: 1px solid #F4F4F4; margin-bottom: 12px; }
.info-section-title { font-size: 12px; font-weight: 700; color: #0f172a; }
.form-label-custom { font-size: 13px; font-weight: 500; color: #64748B; margin-bottom: 6px; }
.info-value { font-size: 14px; color: #1E293B; }
.avatar-wrapper { width: 48px; height: 48px; flex-shrink: 0; }
.avatar-md { width: 48px; height: 48px; object-fit: cover; }
.avatar-placeholder { width: 48px; height: 48px; border-radius: 50%; background: #F3F4F6; display: flex; align-items: center; justify-content: center; border: 1px solid #E5E7EB; }
.avatar-icon { font-size: 24px; color: #9CA3AF; }
.person-hover-anchor { position: relative; overflow: visible; }
.person-hover-card { position: absolute; top: 50%; left: calc(100% + 10px); transform: translateY(-50%); width: 200px; z-index: 1200; border-radius: 12px; border: 1px solid #dbe3ef; background: rgba(255,255,255,.97); box-shadow: 0 14px 30px rgba(15,23,42,.2); padding: 10px; }
.person-hover-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.person-hover-avatar { width: 34px; height: 34px; border-radius: 999px; object-fit: cover; border: 1px solid #e2e8f0; }
.person-hover-name { font-size: 12px; font-weight: 700; color: #0f172a; }
.person-hover-role { font-size: 11px; color: #64748b; }
.person-hover-line { display: flex; justify-content: space-between; gap: 10px; font-size: 11px; padding: 4px 0; border-top: 1px dashed #e2e8f0; }
.edit-person-btn { text-decoration: none; color: #FAA300; font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 4px; }
.edit-icon { font-size: 14px; }
.person-list-scroll { max-height: 350px; overflow-y: auto; padding-right: 5px; }
.person-item { cursor: pointer; border-radius: 8px; transition: all .2s; margin-bottom: 4px; border: 1px solid transparent; }
.person-item:hover { background: #F8FAFC; border-color: #FAA300; }
.person-item.selected { background: #FFFBEB; border-color: #FAA300; }
.person-item.current { background: #F0F9FF; }
.person-item-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.person-item-name { font-weight: 600; font-size: 14px; color: #1E293B; }
.user-position-badge { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 999px; margin-left: 10px; }
.user-item-meta-line { margin-top: 2px; display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; }
.meta-label { font-weight: 600; color: #64748b; }
.meta-value { font-weight: 500; color: #334155; }
.meta-divider { color: #cbd5e1; }
.current-badge { background: #E2E8F0; color: #475569; font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 12px; }
.modal-footer-custom { display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #E2E8F0; padding-top: 1.5rem; }
.modal-footer-custom .btn { padding: .5rem 1.5rem; border-radius: 100px; font-size: 14px; font-weight: 500; }
</style>
