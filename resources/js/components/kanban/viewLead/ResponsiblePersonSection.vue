<template>
    <div class="responsible-card bg-white p-3 radius-12 shadow-sm mt-3">
        
        <div class="info-group">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="info-section-title mb-3">Responsible Person</div>
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
                <div class="avatar-wrapper person-hover-anchor" @mouseenter="showPersonCard = true" @mouseleave="showPersonCard = false" @click.stop="openPersonProfile(lead, 'responsible', $event)">
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
                <div class="flex-grow-1"  >
                    <div class="info-value" @mouseenter="showPersonCard = true" @mouseleave="showPersonCard = false"   >{{ lead?.responsible_person?.name || '—' }}
                             <span v-if="lead?.responsible_person?.role_name" class="user-position-badge">{{ lead?.responsible_person?.role_name }}</span>
                    </div>
                    <div class="info-subline">
                           <span class="sub-key">Reports To: </span>
                           <span class="sub-value"> {{ lead?.responsible_person?.admin_parent_name || lead?.responsible_person?.team_lead_name || 'Not specified' }}</span>
                       </div>
                    <div class="info-subline">
                        <span class="sub-key">Branch: </span>
                        <span class="sub-value"> {{ lead?.responsible_person?.office_name || lead?.lead_branch_source || 'Not specified' }}</span>
                    </div>
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
                        placeholder="search by name and phone and email"
                        class="person-search-input"
                    />
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
                                    <span class="meta-value">{{ user.parent_name }}</span>
                                    <span class="meta-divider" v-if="user.branch_name">|</span>
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
        
        <ProfilePopup 
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="closeProfilePopup"
    />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { BButton, BModal, BFormInput, BSpinner } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import ProfilePopup from '../shared/ProfilePopup.vue'

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



const showProfilePopup = ref(false)
const profileUserId = ref(null)
const profileTriggerType = ref(null)


const openPersonProfile = (task, type, event) => {
    if (event) event.stopPropagation()
    
    const person = type === 'assigned' ? task?.parent : task?.responsible_person
    if (!person?.id) return
    
    profileUserId.value = person.id
    profileTriggerType.value = type
    showProfilePopup.value = true
}


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
.responsible-card {
  position: relative;
  border: 1px solid rgba(250, 163, 0, 0.18);
  margin-bottom: 12px;
  overflow: hidden;
  background:
    linear-gradient(90deg, rgba(250, 163, 0, 0.06), rgba(255, 255, 255, 0) 58%),
    #ffffff;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.05),
    0 10px 24px rgba(15, 23, 42, 0.06);
}

.responsible-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: linear-gradient(180deg, #f8dca6, #f2b84b);
  opacity: 1;
}

.responsible-card:hover {
  border-color: rgba(250, 163, 0, 0.28);
  box-shadow:
    0 2px 4px rgba(15, 23, 42, 0.06),
    0 14px 32px rgba(15, 23, 42, 0.08);
}

.responsible-card:focus-within {
  box-shadow:
    0 0 0 2px rgba(255, 255, 255, 1),
    0 0 0 4px rgba(250, 163, 0, 0.24),
    0 14px 32px rgba(15, 23, 42, 0.08);
}

.responsible-card .info-group {
  position: relative;
  padding-left: 6px; /* keep content off the accent bar */
}
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

/* Modal polish */
:deep(.person-modal .modal-content) {
    border: 1px solid #e6edf5;
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
    overflow: hidden;
}

:deep(.person-modal .modal-header) {
    padding: 10px 14px;
    border-bottom: 1px solid #edf1f6;
    background: linear-gradient(180deg, #fcfdff 0%, #ffffff 100%);
}

:deep(.person-modal .modal-title) {
    font-size: 11px !important;
    font-weight: 700;
    color: #0f172a;
}

/* Strong override for teleported bootstrap modal header title */
:deep(.person-modal .modal-header .modal-title),
:deep(.modal.person-modal .modal-title) {
    font-size: 11px !important;
    line-height: 1.2 !important;
}

/* Directly target bootstrap h5 title element */
:deep(.person-modal .modal-header h5.modal-title),
:deep(.modal.person-modal .modal-header h5.modal-title) {
    font-size: 11px !important;
    line-height: 1.2 !important;
    margin: 0 !important;
}

:deep(.person-modal .modal-dialog) {
    max-width: 560px;
}

:deep(.person-modal .btn-close) {
    transform: scale(0.85);
}

:deep(.person-modal .modal-body) {
    padding: 10px 14px 12px;
    background: #fbfcfe;
}

.person-modal-content {
    background: #fff;
}

.person-search-input {
    height: 30px;
    border-radius: 7px;
    border: 1px solid #d3deea;
    font-size: 10px;
    padding: 0 9px;
    background: #ffffff;
    box-shadow: inset 0 1px 1px rgba(15, 23, 42, 0.03);
}

.person-search-input:focus {
    border-color: #b9cbe3;
    box-shadow: 0 0 0 2px rgba(185, 203, 227, 0.25);
}

.person-search-input::placeholder {
    font-size: 8px;
    color: #9aa9bc;
}

.person-list-scroll {
    border: 1px solid #edf1f6;
    border-radius: 10px;
    background: #fff;
    padding: 6px;
}

.person-item { padding: 8px !important; }
.person-item-name { font-size: 12px; }
.user-position-badge { font-size: 10px; padding: 2px 7px; }
.user-item-meta-line { font-size: 10px; }
.meta-value { font-size: 10px; }
.current-badge { font-size: 10px; }

.modal-footer-custom {
    border-top: 1px solid #edf1f6;
    padding-top: 12px;
}

.modal-footer-custom .btn {
    font-size: 12px;
    padding: 0.4rem 1rem;
    min-width: 96px;
}
.user-item-name {
    font-weight: 600;
    font-size: 14px;
    color: #01062C;
    font-family: 'Montserrat';
    text-transform: capitalize;
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
.person-search-input::placeholder {
    font-size: 10px !important;
}
</style>

<style>
/* Global override because bootstrap modal is teleported outside scoped root */
.person-modal .modal-header h5.modal-title {
    font-size: 17px !important;
    line-height: 1.2 !important;
    margin: 0 !important;
}
.person-search-input::placeholder {
    font-size: 10px !important;
}

</style>
