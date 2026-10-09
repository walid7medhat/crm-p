<template>
    <div
        class="person-hover-anchor"
        @mouseenter.stop="showCard"
        @mouseleave.stop="hideCard"
        @click="$emit('click', $event)"
    >
        <slot />
        <transition name="person-hover-pop">
            <div
                v-if="visible"
                class="person-hover-card"
                :class="{ 'person-hover-card-right': align === 'right' }"
                @mouseenter.stop="cancelHide"
                @mouseleave.stop="hideCard"
                @click.stop="$emit('click', $event)"
            >
                <div v-if="loading" class="person-hover-loading">Loading…</div>
                <template v-else>
                    <div class="person-hover-head">
                        <img
                            v-if="data.avatar"
                            :src="data.avatar"
                            alt=""
                            class="person-hover-avatar"
                        />
                        <div v-else class="person-hover-avatar person-hover-avatar-fallback d-flex align-items-center justify-content-center">
                            <iconify-icon icon="solar:user-bold" class="text-neutral-600"></iconify-icon>
                        </div>
                        <div class="person-hover-head-text">
                            <div class="person-hover-name">{{ data.name }}</div>
                            <div v-if="data.position" class="person-hover-role">{{ data.position }}</div>
                        </div>
                    </div>
                    <div class="person-hover-line"><span>Reports To</span><b>{{ data.manager }}</b></div>
                    <div v-if="data.branch" class="person-hover-line"><span>Branch</span><b>{{ data.branch }}</b></div>
                </template>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    userId: { type: [Number, String], default: null },
    /** Shown immediately while the real profile loads, so the card never opens empty. */
    name: { type: String, default: '' },
    avatar: { type: String, default: '' },
    align: { type: String, default: 'left' }, // 'left' | 'right'
})

defineEmits(['click'])

// Module-level cache: the same user hovered from several rows/tables shouldn't
// refetch every time — one request per user id for the whole session.
const profileCache = PersonHoverCard_cache()
function PersonHoverCard_cache() {
    if (!window.__personHoverCardCache) {
        window.__personHoverCardCache = new Map()
    }
    return window.__personHoverCardCache
}

const visible = ref(false)
const loading = ref(false)
const data = reactive({
    name: props.name || '—',
    avatar: props.avatar || '',
    position: '',
    manager: '—',
    branch: '',
})

let hideTimer = null

function cancelHide() {
    if (hideTimer) {
        clearTimeout(hideTimer)
        hideTimer = null
    }
}

async function showCard() {
    cancelHide()
    if (!props.userId) return
    visible.value = true

    const cached = profileCache.get(props.userId)
    if (cached) {
        Object.assign(data, cached)
        return
    }

    loading.value = true
    try {
        const response = await api.get(`/users/${props.userId}`)
        const user = response.data?.data || response.data
        const resolved = {
            name: user?.name || props.name || 'Unknown',
            avatar: user?.avatar || props.avatar || '',
            position: user?.position || user?.role_name || '',
            manager: user?.parent_name || 'Not specified',
            branch: user?.branch || user?.office_name || '',
        }
        profileCache.set(props.userId, resolved)
        Object.assign(data, resolved)
    } catch (error) {
        console.error('Failed to load person hover profile:', error)
        Object.assign(data, {
            name: props.name || 'Unknown',
            avatar: props.avatar || '',
            position: '',
            manager: 'Not specified',
            branch: '',
        })
    } finally {
        loading.value = false
    }
}

function hideCard() {
    cancelHide()
    hideTimer = setTimeout(() => {
        visible.value = false
    }, 90)
}
</script>

<style scoped>
.person-hover-anchor {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.person-hover-card {
    position: absolute;
    bottom: calc(100% + 6px);
    top: auto;
    left: 0;
    width: 188px;
    z-index: 1200;
    border-radius: 11px;
    border: 1px solid #eadff0;
    background: #fff;
    box-shadow: 0 12px 28px rgba(76, 29, 110, 0.16);
    padding: 8px 9px 7px;
    cursor: default;
    font-family: 'Montserrat', sans-serif;
}

.person-hover-card-right {
    right: -10px;
    left: auto;
}

.person-hover-loading {
    font-size: 12px;
    color: #64748b;
    padding: 6px 2px;
}

.person-hover-head {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 6px;
    min-width: 0;
}

.person-hover-avatar {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    object-fit: cover;
    box-shadow: 0 0 0 2px #f4e9f8;
    flex-shrink: 0;
}

.person-hover-avatar-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7f1fa;
    color: #733e87;
    font-size: 13px;
}

.person-hover-head-text {
    min-width: 0;
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
    color: #733e87;
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

.person-hover-line span {
    color: #94a3b8;
    font-weight: 600;
    flex-shrink: 0;
}

.person-hover-line b {
    color: #1c1424;
    font-weight: 700;
    text-align: right;
    max-width: 108px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.person-hover-pop-enter-active,
.person-hover-pop-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}

.person-hover-pop-enter-from,
.person-hover-pop-leave-to {
    opacity: 0;
    transform: translateY(4px) scale(0.98);
}
</style>
