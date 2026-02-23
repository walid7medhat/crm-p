<template>
    <div class="lead-created-section info-card bg-white p-3 radius-12 shadow-sm mb-3">
        <div class="lead-created-timeline">
            <!-- Date above timeline -->
            <div class="timeline-date-row">
                <span class="timeline-date-text">{{ dateLabel }}</span>
            </div>
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
                        <span class="lead-created-title">Lead Created</span>
                        <span class="lead-created-time">{{ timeLabel }}</span>
                    </div>
                    <div class="lead-created-card-body">
                        <div class="lead-created-row">
                            <span class="lead-created-label">Lead Name</span>
                            <span class="lead-created-value">{{ leadName }}</span>
                        </div>
                        <div v-if="source" class="lead-created-row">
                            <span class="lead-created-label">Source</span>
                            <span class="lead-created-value">{{ source }}</span>
                        </div>
                    </div>
                    <div
                        class="lead-created-avatar"
                        :title="creatorTooltip"
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})

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

const leadName = computed(() => props.lead?.lead_name || '—')
const source = computed(() => props.lead?.lead_source || null)
const creatorName = computed(() => props.lead?.added_by_user?.name || '—')
const creatorAvatar = computed(() => props.lead?.added_by_user?.avatar || null)
const creatorTooltip = computed(() => creatorName.value !== '—' ? `Created by ${creatorName.value}` : 'Created by')
</script>

<style scoped>
.lead-created-section {
    border: 1px solid #F4F4F4;
}

.modal-title {
    font-size: 14px;
    font-weight: 400;
    color: #01062C;
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
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 12px 14px;
    padding-right: 52px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
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
    flex-direction: column;
    gap: 2px;
}

.lead-created-label {
    font-size: 12px;
    font-weight: 400;
    color: #64748B;
}

.lead-created-value {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.lead-created-avatar {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    cursor: default;
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
