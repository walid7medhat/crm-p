<template>
    <div class="lead-activity-timeline info-card bg-white p-3 radius-12 shadow-sm mt-3">
        <div class="activity-timeline-header d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
            <span class="modal-title">Lead Activity</span>
        </div>

        <div v-if="loading && entries.length === 0" class="loading-state py-5 text-center">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted small">Loading activity...</p>
        </div>

        <div v-else-if="entries.length === 0" class="empty-state py-5 text-center text-muted">
            <iconify-icon icon="lucide:history" class="empty-icon"></iconify-icon>
            <p class="mb-0 small">No activity yet for this lead.</p>
        </div>

        <div v-else class="activity-timeline">
            <template v-for="(group, groupIndex) in groupedByDate" :key="group.dateKey">
                <!-- Date header -->
                <div class="timeline-date-header">
                    <div class="timeline-indicator-wrapper">
                        <div class="timeline-dot" :class="group.iconClass"></div>
                    </div>
                    <span class="date-header-text">{{ group.dateLabel }}</span>
                </div>

                <!-- Activity cards for this date -->
                <div class="activity-cards-wrapper">
                    <div
                        v-for="(item, idx) in group.items"
                        :key="item.id || `${group.dateKey}-${idx}`"
                        class="activity-card-item"
                    >
                        <div class="activity-card-left">
                            <!-- Show who did the action: user avatar or fallback icon -->
                            <div class="activity-type-icon activity-icon-person" :class="item.iconClass">
                                <!--<img-->
                                <!--    v-if="item.user?.avatar"-->
                                <!--    :src="item.user.avatar"-->
                                <!--    class="activity-icon-avatar"-->
                                <!--    :alt="item.user?.name"-->
                                <!--/>-->
                                <iconify-icon :icon="item.icon" class="activity-type-icon-content"></iconify-icon>
                            </div>
                        </div>
                        <div class="activity-card-body">
                            <div class="activity-card-header">
                                <span class="activity-type-label">{{ item.typeLabel }}</span>
                                <div class="activity-meta">
                                    <span class="activity-time">{{ item.time }}</span>
                                    <img
                                        v-if="item.user?.avatar"
                                        :src="item.user.avatar"
                                        class="activity-avatar"
                                        :alt="item.user?.name"
                                        :title="item.user?.name"
                                    />
                                    <div v-else class="activity-avatar activity-avatar-placeholder">
                                        <iconify-icon icon="lucide:user"></iconify-icon>
                                    </div>
                                </div>
                            </div>
                            <div class="activity-details" v-html="item.detailsHtml"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Show older -->
        <div v-if="hasMore && !loading" class="show-older-wrapper mt-3 pt-2 border-top">
            <button type="button" class="btn-show-older" @click="loadMore">
                <span>Show older</span>
                <iconify-icon icon="lucide:chevron-down"></iconify-icon>
            </button>
        </div>
        <div v-if="loadingMore" class="text-center py-2">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    leadId: {
        type: [Number, String],
        default: null
    }
})

const instance = getCurrentInstance()
const $showNotification = (msg, type) => {
    if (instance?.appContext?.config?.globalProperties?.$showNotification) {
        instance.appContext.config.globalProperties.$showNotification(msg, type)
    } else if (window.$showNotification) {
        window.$showNotification(msg, type)
    }
}

const entries = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = 10

const hasMore = computed(() => currentPage.value < lastPage.value)

function formatDateLabel(dateStr) {
    const d = new Date(dateStr)
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
    const day = d.getDate()
    const month = months[d.getMonth()]
    const year = d.getFullYear()
    return `${day} ${month}, ${year}`
}

function formatTime(dateStr) {
    const d = new Date(dateStr)
    const hours = d.getHours()
    const mins = d.getMinutes()
    const ampm = hours >= 12 ? 'PM' : 'AM'
    const h = hours % 12 || 12
    const m = mins < 10 ? `0${mins}` : mins
    return `${h}:${m} ${ampm}`
}

function getIconAndClass(action) {
    const map = {
        created: { icon: 'lucide:info', class: 'icon-dark-blue' },
        stage_changed: { icon: 'lucide:check', class: 'icon-dark-blue' },
        assigned: { icon: 'lucide:user-plus', class: 'icon-dark-blue' },
        updated: { icon: 'lucide:pencil', class: 'icon-grey' },
        view: { icon: 'lucide:eye', class: 'icon-grey' },
        revert: { icon: 'lucide:undo', class: 'icon-orange' }
    }
    return map[action] || { icon: 'lucide:circle-dot', class: 'icon-grey' }
}

function getTypeLabel(action) {
    const map = {
        view: 'View',
        revert: 'Revert',
        stage_changed: 'Stage Changed',
        assigned: 'Responsible Person Changed',
        updated: 'Lead Updated',
        created: 'Lead Created'
    }
    return map[action] || (action ? (action.charAt(0).toUpperCase() + action.slice(1).replace(/_/g, ' ')) : 'Activity')
}

function buildDetailsHtml(entry) {
    const changes = entry.changes || {}
    const action = changes.action || entry.action
    if (changes.old_stage && changes.new_stage) {
        return `<div class="detail-stage"><span class="detail-dot"></span><span class="detail-old">${changes.old_stage}</span> <span class="detail-arrow">>></span> <span class="detail-new">${changes.new_stage}</span></div>`
    }
    if (changes.old_person && changes.new_person) {
        return `<div class="detail-stage"><span class="detail-dot"></span><span class="detail-old">${changes.old_person}</span> <span class="detail-arrow">>></span> <span class="detail-new">${changes.new_person}</span></div>`
    }
    if (action === 'created') {
        const leadName = changes.lead_name || entry.lead_name || '—'
        const source = entry.source
        const response = entry.response_person
        const createdBy = entry.createdBy
        console.log(response)
        let html = `<div class="detail-created"><span class="detail-label">Lead Name:</span> ${leadName}</div>`
        if (createdBy) html += `<div class="detail-created"><span class="detail-label">Created By:</span> ${createdBy}</div>`
        if (source) html += `<div class="detail-created"><span class="detail-label">Source:</span> ${source}</div>`
        if (response) html += `<div class="detail-created"><span class="detail-label">Responsible Person:</span> ${response}</div>`
        return html
    }
    if (changes.new_stage) return `<div class="detail-single"><span class="detail-new">${changes.new_stage}</span></div>`
    if (changes.new_person) return `<div class="detail-single"><span class="detail-new">${changes.new_person}</span></div>`
    return ''
}

function transformEntry(entry) {
    console.log(entry)
    const user = entry.user || {}
    let avatar = user.avatar || ''
    if (avatar && !avatar.startsWith('http') && !avatar.startsWith('/')) {
        avatar = `/storage/${avatar}`
    }
    console.log(avatar)
    const action = (entry.changes && entry.changes.action) || entry.action || 'updated'
    const { icon, class: iconClass } = getIconAndClass(action)
    const dateStr = entry.created_at || entry.date
    const detailsHtml = buildDetailsHtml(entry)
    return {
        id: entry.id,
        dateStr,
        dateLabel: formatDateLabel(dateStr),
        time: formatTime(dateStr),
        typeLabel: getTypeLabel(action),
        icon,
        iconClass,
        detailsHtml: detailsHtml || '<span class="text-muted">—</span>',
        user: { name: user.name || 'System', avatar }
    }
}

function parseResponse(responseData) {
    let list = []
    let pagination = null
    const data = responseData.data
    if (data && typeof data === 'object') {
        if (Array.isArray(data.items)) {
            list = data.items
            pagination = data.pagination
        } else if (data.items && Array.isArray(data.items.data)) {
            list = data.items.data
            pagination = data.pagination
        } else if (Array.isArray(data.data)) {
            list = data.data
            pagination = responseData.meta || data.pagination
        } else if (Array.isArray(data)) {
            list = data
            pagination = responseData.meta
        }
    } else if (Array.isArray(responseData.data)) {
        list = responseData.data
        pagination = responseData.meta
    }
    return { list, pagination }
}

const groupedByDate = computed(() => {
    const groups = {}
    entries.value.forEach((item) => {
        const key = item.dateLabel
        if (!groups[key]) {
            groups[key] = {
                dateKey: key,
                dateLabel: key,
                iconClass: 'dot-default',
                items: []
            }
        }
        groups[key].items.push(item)
    })
    return Object.values(groups).sort((a, b) => {
        const dA = a.items[0]?.dateStr ? new Date(a.items[0].dateStr) : new Date(0)
        const dB = b.items[0]?.dateStr ? new Date(b.items[0].dateStr) : new Date(0)
        return dB - dA
    })
})

async function fetchHistory(page = 1, append = false) {
    const id = props.leadId
    if (!id) return
    try {
        if (append) loadingMore.value = true
        else loading.value = true
        const res = await api.get(`/leads/${id}/history`, {
            params: { page, per_page: perPage }
        })
        const { list, pagination } = parseResponse(res.data)
        const withoutView = list.filter((entry) => {
            const action = (entry.changes && entry.changes.action) ?? entry.action
            return action !== 'view' && action !== 'revert'
        })
        const transformed = withoutView.map(transformEntry)
        if (append) {
            entries.value = [...entries.value, ...transformed]
        } else {
            entries.value = transformed
        }
        if (pagination) {
            currentPage.value = parseInt(pagination.current_page) || page
            lastPage.value = parseInt(pagination.last_page) || 1
        }
    } catch (err) {
        console.error('Lead activity fetch error:', err)
        if (!append) entries.value = []
        $showNotification('Failed to load lead activity', 'error')
    } finally {
        loading.value = false
        loadingMore.value = false
    }
}

function loadMore() {
    if (!hasMore.value || loadingMore.value) return
    fetchHistory(currentPage.value + 1, true)
}

watch(
    () => props.leadId,
    (id) => {
        if (id) {
            currentPage.value = 1
            fetchHistory(1)
        } else {
            entries.value = []
        }
    }
)

onMounted(() => {
    if (props.leadId) fetchHistory(1)
})
</script>

<style scoped>
.lead-activity-timeline {
    border: 1px solid #F3F3F3;
}

.modal-title {
    font-size: 14px;
    font-weight: 600;
    color: #01062C;
}

.loading-state .spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
    color: #FAA300;
}

.empty-icon {
    font-size: 32px;
    color: #94A3B8;
    margin-bottom: 8px;
}

/* Timeline structure – compact */
.activity-timeline {
    position: relative;
    padding-left: 20px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #E2E8F0;
    border-radius: 1px;
}

.timeline-date-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    position: relative;
}

.timeline-indicator-wrapper {
    position: absolute;
    left: -20px;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    z-index: 1;
}

.timeline-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #94A3B8;
}

.timeline-dot.dot-default {
    background: #64748B;
}

.date-header-text {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.activity-cards-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
}

.activity-card-item {
    display: flex;
    gap: 10px;
    background: #fff;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 10px 12px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    position: relative;
}

.activity-card-item::before {
    content: '';
    position: absolute;
    left: -18px;
    top: 16px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #E2E8F0;
    z-index: 1;
}

.activity-card-left {
    flex-shrink: 0;
}

.activity-type-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.activity-type-icon.activity-icon-person .activity-icon-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.activity-type-icon-content {
    font-size: 14px;
    color: #fff;
}

.activity-type-icon.icon-dark-blue {
    background: #1e3a5f;
}

.activity-type-icon.icon-blue {
    background: #1e3a5f;
}

.activity-type-icon.icon-grey {
    background: #64748B;
}

.activity-type-icon.icon-orange {
    background: #F59E0B;
}

.activity-card-body {
    flex: 1;
    min-width: 0;
}

.activity-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 6px;
    margin-bottom: 6px;
}

.activity-type-label {
    font-size: 12px;
    font-weight: 600;
    color: #01062C;
}

.activity-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.activity-time {
    font-size: 11px;
    color: #64748B;
}

.activity-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    object-fit: cover;
}

.activity-avatar-placeholder {
    background: #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748B;
    font-size: 12px;
}

.activity-details {
    font-size: 11px;
    color: #475569;
    line-height: 1.4;
}

.activity-details :deep(.detail-stage),
.activity-details :deep(.detail-created),
.activity-details :deep(.detail-single) {
    margin-bottom: 2px;
}

.activity-details :deep(.detail-dot) {
    display: inline-block;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #1e3a5f;
    margin-right: 5px;
    vertical-align: middle;
}

.activity-details :deep(.detail-old) {
    color: #64748B;
}

.activity-details :deep(.detail-new) {
    color: #1e3a5f;
    font-weight: 500;
}

.activity-details :deep(.detail-arrow) {
    color: #94A3B8;
    margin: 0 3px;
}

.activity-details :deep(.detail-label) {
    color: #64748B;
    margin-right: 4px;
}

.btn-show-older {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: none;
    border: none;
    padding: 4px 0;
    font-size: 12px;
    color: #1e3a5f;
    cursor: pointer;
    transition: color 0.2s;
}

.btn-show-older:hover {
    color: #0f2744;
}

.btn-show-older iconify-icon {
    font-size: 16px;
}

.radius-12 {
    border-radius: 12px;
}
</style>
