<template>
    <div class=" p-0  mb-0">
        <div class="lead-created-timeline">
            <!-- Date above timeline -->
            <!--<div class="timeline-date-row">-->
            <!--    <span class="timeline-date-text"></span>-->
            <!--</div>-->
            <!-- Timeline line + icon + card -->
            <div class="timeline-row">
                <div class="timeline-track">
                    <div class="timeline-line"></div>
                    <div class="timeline-marker timeline-marker-info">
                        <iconify-icon icon="lucide:info" class="timeline-marker-icon"></iconify-icon>
                    </div>
                </div>
                <div class="lead-created-card">
                    <div class="lead-created-card-header">
                        <span class="lead-created-title">Lead Created :</span>
                        <span class="lead-created-time">{{ dateLabel }} {{ timeLabel }}</span>
                    </div>
                    <div class="lead-created-card-body">
                        <div class="lead-created-row lead-created-row-main">
                            <div class="d-flex align-items-center gap-1">
                                <span class="lead-created-label">Lead Name :</span>
                                <span class="lead-created-value">{{ leadName }}</span>
                            </div>
                            <div
                                class="lead-created-avatar person-hover-anchor"
                                :title="creatorTooltip"
                                @mouseenter="showCreatorCard = true"
                                @mouseleave="showCreatorCard = false"
                                @click.stop="openPersonProfile(props.lead, 'assigned', $event)"
                            >
                                <img
                                    v-if="creatorAvatar"
                                    :src="creatorAvatar"
                                    class="lead-created-avatar-img"
                                    :alt="creatorName"
                                />
                                <div v-else class="lead-created-avatar-placeholder">
                                    <iconify-icon icon="lucide:user" class="lead-created-avatar-icon"></iconify-icon>
                                </div>
                                <transition name="person-card-pop">
                                    <div v-if="showCreatorCard" class="person-hover-card">
                                        <div class="person-hover-head">
                                            <img
                                                v-if="creatorAvatar"
                                                :src="creatorAvatar"
                                                alt=""
                                                class="person-hover-avatar"
                                            />
                                            <div v-else class="person-hover-avatar person-hover-avatar-fallback">
                                                <iconify-icon icon="lucide:user" class="lead-created-avatar-icon"></iconify-icon>
                                            </div>
                                            <div>
                                                <div class="person-hover-name">{{ creatorName || '—' }}</div>
                                                <div class="person-hover-role">{{ creatorRole || 'Team Member' }}</div>
                                            </div>
                                        </div>
                                        <div class="person-hover-line">
                                            <span>Reports To</span>
                                            <b>{{ creatorManager || 'Not specified' }}</b>
                                        </div>
                                        <div class="person-hover-line">
                                            <span>Branch</span>
                                            <b>{{ creatorBranch || branch || 'Not specified' }}</b>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                        </div>
                        <div v-if="source" class="lead-created-row">
                            <span class="lead-created-label">Source :</span>
                            <span class="lead-created-value">{{ source }}</span>
                        </div>
                        <div v-if="branch" class="lead-created-row">
                            <span class="lead-created-label">Lead Branch Source :</span>
                            <span class="lead-created-value">{{ branch }}</span>
                        </div>
                        <!-- <div v-if="branchOffice" class="lead-created-row">-->
                        <!--    <span class="lead-created-label">Lead Branch :</span>-->
                        <!--    <span class="lead-created-value">{{ branchOffice }}</span>-->
                        <!--</div>-->
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
       <ProfilePopup 
        v-model="showProfilePopup"
        :user-id="profileUserId"
        @update:model-value="closeProfilePopup"
    />
</template>

<script setup>
import { computed, ref } from 'vue'
import ProfilePopup from '../shared/ProfilePopup.vue'

const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})

const showProfilePopup = ref(false)
const profileUserId = ref(null)
const profileTriggerType = ref(null)


const openPersonProfile = (task, type, event) => {
    if (event) event.stopPropagation()
    
    const person = props.lead?.added_by_user
    if (!person?.id) return
    
    profileUserId.value = person.id
    profileTriggerType.value = type
    showProfilePopup.value = true
}


const dateLabel = computed(() => {
    const d = props.lead?.created_at ? new Date(props.lead.created_at) : null
    if (!d || isNaN(d.getTime())) return '—'
    const day = d.getDate()
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
    const month = months[d.getMonth()]
    const year = d.getFullYear()
    return `${day} ${month}, ${year}`
})

const timeLabel = computed(() => {
    const d = props.lead?.created_at ? new Date(props.lead.created_at) : null
    if (!d || isNaN(d.getTime())) return '—'
    const hours = d.getHours()
    const mins = d.getMinutes()
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const h = hours % 12 || 12
    const m = mins < 10 ? `0${mins}` : mins
    return `${h}:${m} ${ampm}`
})

const leadName = computed(() => props.lead?.original_name || props.lead?.lead_name || '—')
const branch = computed(() => props.lead?.original_branch ||  '—')
const branchOffice=computed(() => props.lead?.office_branch ||  '—')
const source = computed(() => props.lead?.lead_source || null)
const creatorName = computed(() => props.lead?.added_by_user?.name || '—')
const creatorId = computed(() => props.lead?.added_by_user?.id || '—')
const creatorAvatar = computed(() => props.lead?.added_by_user?.avatar || null)
const creatorRole = computed(() => {
    const role = props.lead?.added_by_user?.role_name
    if (!role) return null
    return String(role).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
})
const creatorManager = computed(() => props.lead?.added_by_user?.parent_name || null)
const creatorBranch = computed(() => props.lead?.added_by_user?.branch_name || null)
const creatorTooltip = computed(() => creatorName.value !== '—' ? `Created by ${creatorName.value}` : 'Created by')
const showCreatorCard = ref(false)
</script>

<style scoped>
.lead-created-section {
    border: none;
}

.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #0B0736;
}

.lead-created-timeline {
    position: relative;
}

.timeline-date-row {
    margin-bottom: 6px;
}

.timeline-date-text {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.timeline-row {
    display: flex;
    gap: 0;
    align-items: flex-start;
}

.timeline-track {
    position: relative;
    flex-shrink: 0;
    width: 24px;
    padding-top: 8px;
}

.timeline-line {
    position: absolute;
    left: 11px;
    top: 24px;
    bottom: -8px;
    width: 2px;
    background: #E2E8F0;
    border-radius: 1px;
}

.timeline-marker {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1;
}

.timeline-marker-info {
    background: #1e3a5f;
}

.timeline-marker-icon {
    font-size: 12px;
    color: #fff;
}

.lead-created-card {
    flex: 1;
    min-width: 0;
    background: #fff;
    border: none;
    border-radius: 10px;
    padding: 12px 14px;
    padding-right: 14px;
    box-shadow: none;
    position: relative;
}

.lead-created-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.lead-created-title {
    font-size: 14px;
    font-weight: 500;
    color: #475569;
}

.lead-created-time {
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
}

.lead-created-card-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lead-created-row {
    display: flex;
    flex-direction: row;
    gap: 2px;
}

.lead-created-row-main {
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.lead-created-label {
    font-size: 12px;
    font-weight: 400;
    color: #64748B;
}

.lead-created-value {
    font-size: 13px;
    font-weight: 600;
    color: #0B0736;
}

.lead-created-avatar {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    cursor: default;
}

.person-hover-anchor {
    position: relative;
    overflow: visible;
}

.person-card-pop-enter-active,
.person-card-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-card-pop-enter-from,
.person-card-pop-leave-to {
    opacity: 0;
    transform: translateY(4px) scale(0.98);
}

.person-hover-card {
    position: absolute;
    bottom: calc(100% + 8px);
    right: 0;
    top: auto;
    left: auto;
    transform: none;
    width: 200px;
    z-index: 3000;
    border-radius: 12px;
    border: 1px solid #dbe3ef;
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.2);
    backdrop-filter: blur(8px);
    padding: 10px;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.person-hover-avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    object-fit: cover;
    border: 1px solid #e2e8f0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
}

.person-hover-name {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
}

.person-hover-role {
    margin-top: 1px;
    font-size: 11px;
    color: #64748b;
}

.person-hover-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 11px;
    padding: 4px 0;
    border-top: 1px dashed #e2e8f0;
}

.person-hover-line span {
    color: #64748b;
}

.person-hover-line b {
    color: #0f172a;
    font-weight: 700;
    text-align: right;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-created-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.lead-created-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #F3F4F6;
    border: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lead-created-avatar-icon {
    font-size: 16px;
    color: #9CA3AF;
}

.radius-12 {
    border-radius: 12px;
}
</style>
