<template>
    <div class="deal-created-section info-card bg-white p-3 radius-12 shadow-sm mb-3 deal-figma-ui">
        <div class="deal-created-timeline">
            <div class="timeline-date-row">
                <span class="timeline-date-text">{{ dateLabel }}</span>
            </div>
            <div class="timeline-row">
                <div class="timeline-track">
                    <div class="timeline-line"></div>
                    <div class="timeline-marker timeline-marker-info">
                        <iconify-icon icon="lucide:info" class="timeline-marker-icon"></iconify-icon>
                    </div>
                </div>
                <div class="deal-created-card">
                    <div class="deal-created-card-header">
                        <span class="deal-created-title">Deal Created</span>
                        <span class="deal-created-time">{{ timeLabel }}</span>
                    </div>
                    <div class="deal-created-card-body">
                        <div class="deal-created-row">
                            <span class="deal-created-label">Deal Name</span>
                            <span class="deal-created-value">{{ dealName }}</span>
                        </div>
                        <div v-if="source" class="deal-created-row">
                            <span class="deal-created-label">Source</span>
                            <span class="deal-created-value">{{ source }}</span>
                        </div>
                    </div>
                    <div class="deal-created-avatar" :title="creatorTooltip">
                        <img
                            v-if="creatorAvatar"
                            :src="creatorAvatar"
                            class="deal-created-avatar-img"
                            :alt="creatorName"
                        />
                        <div v-else class="deal-created-avatar-placeholder">
                            <iconify-icon icon="lucide:user" class="deal-created-avatar-icon"></iconify-icon>
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
    deal: {
        type: Object,
        default: null
    }
})

const dateLabel = computed(() => {
    const d = props.deal?.created_at ? new Date(props.deal.created_at) : null
    if (!d || isNaN(d.getTime())) return '—'
    const day = d.getDate()
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
    const month = months[d.getMonth()]
    const year = d.getFullYear()
    return `${day} ${month}, ${year}`
})

const timeLabel = computed(() => {
    const d = props.deal?.created_at ? new Date(props.deal.created_at) : null
    if (!d || isNaN(d.getTime())) return '—'
    const hours = d.getHours()
    const mins = d.getMinutes()
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const h = hours % 12 || 12
    const m = mins < 10 ? `0${mins}` : mins
    return `${h}:${m} ${ampm}`
})

const dealName = computed(() => props.deal?.project_name || props.deal?.project || props.deal?.deal_name || '—')
const source = computed(() => props.deal?.source || props.deal?.lead_source || null)
const creatorName = computed(() => props.deal?.added_by_user?.name || props.deal?.createdBy || '—')
const creatorAvatar = computed(() => props.deal?.added_by_user?.avatar || null)
const creatorTooltip = computed(() => creatorName.value !== '—' ? `Created by ${creatorName.value}` : 'Created by')
</script>

<style scoped>
.deal-created-section {
    border: 1px solid #F4F4F4;
    font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
    font-size: 14px;
    -webkit-font-smoothing: antialiased;
}

.deal-created-timeline {
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

.deal-created-card {
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

.deal-created-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.deal-created-title {
    font-size: 14px;
    font-weight: 500;
    color: #475569;
}

.deal-created-time {
    font-size: 13px;
    font-weight: 400;
    color: #64748B;
}

.deal-created-card-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.deal-created-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.deal-created-label {
    font-size: 12px;
    font-weight: 400;
    color: #64748B;
}

.deal-created-value {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.deal-created-avatar {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    cursor: default;
}

.deal-created-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.deal-created-avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #F3F4F6;
    border: 1px solid #E5E7EB;
    display: flex;
    align-items: center;
    justify-content: center;
}

.deal-created-avatar-icon {
    font-size: 16px;
    color: #9CA3AF;
}

.radius-12 {
    border-radius: 12px;
}
</style>
