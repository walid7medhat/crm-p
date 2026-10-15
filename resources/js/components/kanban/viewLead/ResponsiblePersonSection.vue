<template>
    <div v-if="!shouldHideSection" class="responsible-card">
        <div class="info-group">
            <div
                ref="personHoverAnchor"
                class="rp-row person-hover-anchor"
                @mouseenter="openPersonHover"
                @mouseleave="closePersonHover"
            >
                <button type="button" class="rp-person" @click.stop="openPersonProfile(lead, 'responsible', $event)">
                    <img
                        v-if="lead?.responsible_person?.avatar"
                        :src="lead?.responsible_person?.avatar"
                        alt=""
                        class="rp-avatar"
                    />
                    <span v-else class="rp-avatar rp-avatar--fallback" aria-hidden="true">
                        <iconify-icon icon="lucide:user"></iconify-icon>
                    </span>
                    <span class="rp-copy">
                        <span class="rp-kicker">Responsible</span>
                        <span class="rp-name">
                            <span class="rp-name-text">{{ lead?.responsible_person?.name || 'Unassigned' }}</span>
                            <span v-if="personRole" class="user-position-badge">{{ personRole }}</span>
                        </span>
                    </span>
                </button>
                <button
                    type="button"
                    class="rp-change"
                    :disabled="isUpdatingPerson"
                    @click="openPersonModal"
                >
                    Change
                </button>
            </div>
            <div v-if="reportsToLabel || branchLabel" class="rp-meta">
                <span v-if="reportsToLabel">Reports to {{ reportsToLabel }}</span>
                <span v-if="branchLabel">{{ branchLabel }}</span>
            </div>
            <Teleport to="body">
                <transition name="person-card-pop">
                    <div
                        v-if="showPersonCard"
                        class="person-hover-card"
                        :style="personCardStyle"
                        @mouseenter="openPersonHover"
                        @mouseleave="closePersonHover"
                        @click.stop="openPersonProfile(lead, 'responsible', $event)"
                    >
                        <div class="person-hover-head">
                            <img
                                v-if="lead?.responsible_person?.avatar"
                                :src="lead?.responsible_person?.avatar"
                                alt=""
                                class="person-hover-avatar"
                            />
                            <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                <iconify-icon icon="lucide:user"></iconify-icon>
                            </div>
                            <div class="person-hover-head-text">
                                <div class="person-hover-name">{{ lead?.responsible_person?.name || '—' }}</div>
                                <div class="person-hover-role">{{ lead?.responsible_person?.position || lead?.responsible_person?.role_name || 'Team Member' }}</div>
                            </div>
                        </div>
                        <div v-if="reportsToLabel" class="person-hover-line">
                            <span>Reports to</span>
                            <b>{{ reportsToLabel }}</b>
                        </div>
                        <div v-if="branchLabel" class="person-hover-line">
                            <span>Branch</span>
                            <b>{{ branchLabel }}</b>
                        </div>
                    </div>
                </transition>
            </Teleport>
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
                                    <span v-if="user.position || user.role_name" class="user-position-badge">{{ user.position || user.role_name }}</span>
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
import { ref, computed, watch, nextTick } from 'vue'
import { BButton, BModal, BFormInput, BSpinner } from 'bootstrap-vue-3'
import api from '@/plugins/axios'
import ProfilePopup from '../shared/ProfilePopup.vue'

const props = defineProps({ lead: Object })
const emit = defineEmits(['person-updated'])

// The modal paints first from a lightweight kanban-card seed (has stage_id, not the
// backend-computed hide_responsible_person — that only arrives after the full /leads/{id}
// fetch), so relying on hide_responsible_person alone flashed this section open for a beat
// before it disappeared. Mirror the backend rule (LeadResource::applyVisibilityRules — Lead
// Pool = stage_id 10 is admin/super_admin only) using data available from the first render.
const currentUser = (() => {
    try {
        return JSON.parse(localStorage.getItem('user') || 'null')
    } catch (error) {
        return null
    }
})()
const isAdminOrSuperAdmin = computed(() => {
    const roles = currentUser?.roles || []
    return roles.includes('super_admin') || roles.includes('admin')
})
const isLeadPoolLead = computed(() => Number(props.lead?.stage_id) === 10)
const shouldHideSection = computed(() => {
    if (props.lead?.hide_responsible_person) return true
    return isLeadPoolLead.value && !isAdminOrSuperAdmin.value
})

function presentLabel(...values) {
    for (const value of values) {
        const text = String(value ?? '').trim()
        if (text && text.toLowerCase() !== 'not specified' && text !== '—') return text
    }
    return ''
}

const personRole = computed(() => presentLabel(props.lead?.responsible_person?.position, props.lead?.responsible_person?.role_name))
const reportsToLabel = computed(() => presentLabel(
    props.lead?.responsible_person?.parent_name,
    props.lead?.responsible_person?.admin_parent_name,
    props.lead?.responsible_person?.team_lead_name,
))
const branchLabel = computed(() => presentLabel(
    props.lead?.responsible_person?.office_name,
    props.lead?.responsible_person?.branch_name,
    props.lead?.lead_branch_source,
))

const showPersonModal = ref(false)
const isLoadingPersons = ref(false)
const isUpdatingPerson = ref(false)
const personSearchQuery = ref('')
const personsList = ref([])
const selectedPersonId = ref(null)
const personUpdateError = ref('')
const showPersonCard = ref(false)
const personHoverAnchor = ref(null)
const personCardStyle = ref({})
let personHoverTimer = null

function placePersonHover() {
    const el = personHoverAnchor.value
    if (!el) return
    const rect = el.getBoundingClientRect()
    const width = 188
    const height = 86
    const gap = 8
    let left = rect.left - width - gap
    let top = rect.top + Math.round((rect.height - height) / 2)
    if (left < 8) {
        left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8)
        top = rect.top - height - gap
    }
    if (top < 8) top = rect.bottom + gap
    left = Math.max(8, Math.min(left, window.innerWidth - width - 8))
    top = Math.max(8, Math.min(top, window.innerHeight - height - 8))
    personCardStyle.value = {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
        width: `${width}px`,
        zIndex: 20000,
    }
}

function openPersonHover() {
    if (window.matchMedia('(hover: none)').matches) return
    if (personHoverTimer) clearTimeout(personHoverTimer)
    showPersonCard.value = true
    nextTick(() => placePersonHover())
}

function closePersonHover() {
    if (personHoverTimer) clearTimeout(personHoverTimer)
    personHoverTimer = setTimeout(() => {
        showPersonCard.value = false
    }, 90)
}



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


const filteredPersons = computed(() => personsList.value)

let personSearchTimer = null

const fetchAvailablePersons = async (search = '') => {
    isLoadingPersons.value = true
    personUpdateError.value = ''
    try {
        const params = { limit: 30 }
        const term = String(search || '').trim()
        if (term) params.search = term
        if (props.lead?.responsible_person?.id) params.selected_id = props.lead.responsible_person.id
        const response = await api.get('/available-responsible-persons', { params })
        personsList.value = response.data.data || response.data || []
    } catch (error) {
        personUpdateError.value = 'Failed to load persons list'
    } finally {
        isLoadingPersons.value = false
    }
}

watch(personSearchQuery, (value) => {
    clearTimeout(personSearchTimer)
    personSearchTimer = setTimeout(() => fetchAvailablePersons(value), 300)
})

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
            position: selectedPerson?.position || null,
            // "Reports To" = direct parent, "Branch" = office (getAvailableResponsiblePersons
            // already resolves branch_name from the office hierarchy, not employeeProfile).
            parent_name: selectedPerson?.parent_name,
            manager_name: selectedPerson?.parent_name,
            office_name: selectedPerson?.branch_name,
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
  margin-bottom: 8px;
  padding: 8px 10px;
  border: 1px solid #efe4f3;
  border-radius: 12px;
  background: #fff;
}

.responsible-card .info-group {
  position: relative;
}

.rp-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.rp-person {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1 1 auto;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.rp-avatar {
  width: 32px;
  height: 32px;
  flex: 0 0 auto;
  border-radius: 999px;
  object-fit: cover;
}

.rp-avatar--fallback {
  display: grid;
  place-items: center;
  background: #f4eef7;
  color: var(--color-primary);
  font-size: 16px;
}

.rp-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.rp-kicker {
  color: #8b8498;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  line-height: 1.1;
}

.rp-name {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  color: #1c1424;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.2;
}

.rp-name-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rp-name .user-position-badge {
  margin-left: 0;
  flex: 0 1 auto;
  max-width: 42%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rp-change {
  flex: 0 0 auto;
  height: 28px;
  padding: 0 10px;
  border: 0;
  border-radius: 999px;
  background: #f7f1fa;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.rp-change:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.rp-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
  margin-top: 6px;
  padding-left: 40px;
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
}
.info-section-title { font-size: 12px; font-weight: 700; color: var(--color-primary); }
.form-label-custom { font-size: 13px; font-weight: 500; color: #64748B; margin-bottom: 6px; }
.info-value { font-size: 14px; color: #1E293B; }
.avatar-wrapper { width: 48px; height: 48px; flex-shrink: 0; }
.avatar-md { width: 48px; height: 48px; object-fit: cover; }
.avatar-placeholder { width: 48px; height: 48px; border-radius: 50%; background: #F3F4F6; display: flex; align-items: center; justify-content: center; border: 1px solid #E5E7EB; }
.avatar-icon { font-size: 24px; color: #9CA3AF; }
.person-hover-anchor { position: relative; }
.person-hover-card {
    border-radius: 11px;
    border: 1px solid #eadff0;
    background: #fff;
    box-shadow: 0 12px 28px rgba(76, 29, 110, 0.16);
    padding: 8px 9px 7px;
    font-family: 'Montserrat', sans-serif;
    pointer-events: auto;
}
.person-hover-head { display: flex; align-items: center; gap: 7px; margin-bottom: 6px; min-width: 0; }
.person-hover-head-text { min-width: 0; }
.person-hover-avatar {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 0 0 2px #f4e9f8;
}
.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7f1fa;
    color: var(--color-primary);
    font-size: 13px;
}
.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #1c1424;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.person-hover-role {
    margin-top: 1px;
    font-size: 10px;
    font-weight: 600;
    color: var(--color-primary);
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 10px;
    padding-top: 4px;
    margin-top: 4px;
    border-top: 1px solid #f3eaf6;
}
.person-hover-line span { color: #94a3b8; font-weight: 600; flex-shrink: 0; }
.person-hover-line b {
    color: #1c1424;
    font-weight: 700;
    text-align: right;
    max-width: 108px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.person-card-pop-enter-active,
.person-card-pop-leave-active { transition: opacity 0.14s ease, transform 0.14s ease; }
.person-card-pop-enter-from,
.person-card-pop-leave-to { opacity: 0; transform: translateY(4px) scale(0.98); }
.edit-person-btn { text-decoration: none; color: var(--color-primary); font-size: 12px; font-weight: 500; display: flex; align-items: center; gap: 4px; }
.edit-icon { font-size: 14px; }
.person-list-scroll { max-height: 350px; overflow-y: auto; padding-right: 5px; }
.person-item { cursor: pointer; border-radius: 8px; transition: all .2s; margin-bottom: 4px; border: 1px solid transparent; }
.person-item:hover { background: #F8FAFC; border-color: var(--color-primary); }
.person-item.selected { background: #FFFBEB; border-color: var(--color-primary); }
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
    color: #0B0736;
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
.btn-content{
        display: flex;
    align-items: center;
    gap: 2px;
}
</style>
