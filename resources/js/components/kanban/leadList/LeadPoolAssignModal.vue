<template>
    <Teleport to="body">
        <transition name="lpa-fade">
            <div v-if="modelValue" class="lpa-overlay" @mousedown.self="close">
                <div class="lpa-dialog" role="dialog" aria-modal="true" aria-labelledby="lpa-title">
                    <div class="lpa-head">
                        <div class="lpa-head__left">
                            <span class="lpa-icon" aria-hidden="true">
                                <iconify-icon icon="solar:user-plus-bold" />
                            </span>
                            <div class="lpa-head__copy">
                                <p id="lpa-title" class="lpa-title">Assign to agent</p>
                                <p class="lpa-sub">
                                    <span class="lpa-badge">{{ count }}</span>
                                    lead{{ count === 1 ? '' : 's' }} selected
                                </p>
                            </div>
                        </div>
                        <button type="button" class="lpa-x" aria-label="Close" @click="close">
                            <iconify-icon icon="lucide:x" />
                        </button>
                    </div>

                    <div class="lpa-search" :class="{ 'lpa-search--focus': searchFocused }">
                        <iconify-icon icon="lucide:search" class="lpa-search__icon" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            placeholder="Search by name or email…"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
                            @keydown.esc.stop="close"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="lpa-search__clear"
                            aria-label="Clear search"
                            @click="search = ''"
                        >
                            <iconify-icon icon="lucide:x" />
                        </button>
                    </div>

                    <div class="lpa-list" role="listbox" aria-label="Agents">
                        <div v-if="loading" class="lpa-empty">
                            <span class="spinner-border spinner-border-sm text-primary" role="status" />
                            Loading agents…
                        </div>
                        <div v-else-if="error" class="lpa-empty lpa-empty--error">
                            <iconify-icon icon="lucide:alert-circle" />
                            {{ error }}
                        </div>
                        <div v-else-if="!agents.length" class="lpa-empty">
                            <iconify-icon icon="solar:users-group-rounded-linear" class="lpa-empty__icon" />
                            <span>No agents found</span>
                            <span v-if="search" class="lpa-empty__hint">Try a different search</span>
                        </div>
                        <button
                            v-for="agent in agents"
                            v-else
                            :key="agent.id"
                            type="button"
                            class="lpa-agent"
                            role="option"
                            @click="$emit('select', agent)"
                        >
                            <img
                                v-if="agent.avatar && !brokenAvatars[agent.id]"
                                :src="agent.avatar"
                                alt=""
                                class="lpa-avatar"
                                @error="brokenAvatars[agent.id] = true"
                            />
                            <span
                                v-else
                                class="lpa-avatar lpa-avatar--initials"
                                :style="{ background: avatarColor(agent) }"
                            >
                                {{ initials(agent.name) }}
                            </span>
                            <span class="lpa-agent__text">
                                <span class="lpa-agent__name">{{ agent.name }}</span>
                                <span class="lpa-agent__meta">{{ agent.branch || agent.email }}</span>
                            </span>
                            <span class="lpa-agent__action">
                                Assign
                                <iconify-icon icon="lucide:chevron-right" />
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { reactive, ref, watch, nextTick } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    count: { type: Number, default: 0 },
})
const emit = defineEmits(['update:modelValue', 'select'])

const agents = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const searchInput = ref(null)
const searchFocused = ref(false)
const brokenAvatars = reactive({})
let searchTimer = null
let requestSeq = 0

const AVATAR_PALETTE = [
    '#733E87',
    '#5c3270',
    '#3f2148',
    '#8f5aa3',
    '#4a285c',
    '#6b4a7a',
    '#9a6aad',
    '#553066',
]

function initials(name) {
    const parts = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
    if (!parts.length) return '?'
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function avatarColor(agent) {
    const key = String(agent?.id ?? agent?.name ?? '')
    let hash = 0
    for (let i = 0; i < key.length; i++) hash = (hash * 31 + key.charCodeAt(i)) >>> 0
    return AVATAR_PALETTE[hash % AVATAR_PALETTE.length]
}

async function loadAgents() {
    const seq = ++requestSeq
    loading.value = true
    error.value = ''
    try {
        const response = await api.get('/lead-pool/assignable-agents', {
            params: search.value.trim() ? { search: search.value.trim() } : {},
        })
        if (seq !== requestSeq) return
        agents.value = Array.isArray(response?.data?.data) ? response.data.data : []
    } catch (e) {
        if (seq !== requestSeq) return
        agents.value = []
        error.value = e?.response?.data?.message || 'Could not load agents.'
    } finally {
        if (seq === requestSeq) loading.value = false
    }
}

function close() {
    emit('update:modelValue', false)
}

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        search.value = ''
        Object.keys(brokenAvatars).forEach((k) => delete brokenAvatars[k])
        loadAgents()
        nextTick(() => searchInput.value?.focus())
    },
)

watch(search, () => {
    if (!props.modelValue) return
    clearTimeout(searchTimer)
    searchTimer = setTimeout(loadAgents, 300)
})

defineExpose({ reload: loadAgents })
</script>

<style scoped>
.lpa-overlay {
    position: fixed;
    inset: 0;
    z-index: 1060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(var(--color-primary-rgb), 0.28);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.lpa-dialog {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 400px;
    max-height: min(520px, calc(100vh - 32px));
    background: var(--color-surface);
    border-radius: 16px;
    border: 1px solid var(--color-border);
    box-shadow:
        0 4px 6px rgba(var(--color-primary-rgb), 0.06),
        0 24px 48px rgba(var(--color-primary-rgb), 0.18);
    font-family: Montserrat, Inter, system-ui, sans-serif;
    overflow: hidden;
}

.lpa-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 16px 12px;
    border-bottom: 1px solid var(--color-border);
}

.lpa-head__left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
}

.lpa-icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: var(--gradient-primary);
    color: var(--color-on-primary);
    font-size: 16px;
    box-shadow: 0 4px 10px rgba(var(--color-primary-rgb), 0.3);
}

.lpa-head__copy {
    min-width: 0;
    padding-top: 1px;
}

.lpa-title {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: var(--color-text-primary);
}

.lpa-sub {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 3px 0 0;
    font-size: 11.5px;
    font-weight: 500;
    color: var(--color-text-secondary);
    line-height: 1.3;
}

.lpa-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--color-primary-soft);
    color: var(--color-primary);
    font-size: 10.5px;
    font-weight: 700;
    line-height: 1;
}

.lpa-x {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: var(--color-text-muted);
    font-size: 16px;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.lpa-x:hover {
    background: var(--color-primary-soft);
    color: var(--color-text-primary);
}

.lpa-search {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 12px 16px 8px;
    padding: 0 10px;
    height: 36px;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.lpa-search--focus {
    background: var(--color-surface);
    border-color: var(--color-primary-muted);
    box-shadow: 0 0 0 3px var(--color-focus-ring);
    color: var(--color-primary);
}

.lpa-search__icon {
    flex-shrink: 0;
    font-size: 14px;
}

.lpa-search input {
    flex: 1;
    min-width: 0;
    height: 100%;
    border: 0;
    outline: 0;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--color-text-primary);
    background: transparent;
}

.lpa-search input::placeholder {
    color: var(--color-text-muted);
    font-weight: 400;
}

.lpa-search__clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: var(--color-primary-border);
    color: var(--color-text-muted);
    font-size: 12px;
    cursor: pointer;
}

.lpa-search__clear:hover {
    background: var(--color-primary-muted);
    color: var(--color-text-primary);
}

.lpa-list {
    flex: 1;
    min-height: 140px;
    overflow-y: auto;
    padding: 4px 8px 12px;
    scrollbar-width: thin;
    scrollbar-color: var(--color-primary-muted) transparent;
}

.lpa-list::-webkit-scrollbar {
    width: 5px;
}

.lpa-list::-webkit-scrollbar-thumb {
    background: var(--color-primary-muted);
    border-radius: 999px;
}

.lpa-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 36px 12px;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--color-text-secondary);
    text-align: center;
}

.lpa-empty--error {
    flex-direction: row;
    color: var(--color-danger);
}

.lpa-empty__icon {
    font-size: 28px;
    color: var(--color-primary-muted);
    margin-bottom: 2px;
}

.lpa-empty__hint {
    font-size: 11px;
    font-weight: 400;
    color: var(--color-text-muted);
}

.lpa-agent {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 10px;
    margin-bottom: 1px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s ease;
}

.lpa-agent:hover {
    background: var(--color-primary-soft);
}

.lpa-agent:active {
    background: var(--color-primary-border);
}

.lpa-avatar {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid var(--color-surface);
    box-shadow: 0 0 0 1px var(--color-border);
}

.lpa-avatar--initials {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--color-on-primary);
    box-shadow: none;
    border: 0;
}

.lpa-agent__text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    gap: 1px;
}

.lpa-agent__name,
.lpa-agent__meta {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.lpa-agent__name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--color-text-primary);
    line-height: 1.3;
}

.lpa-agent__meta {
    font-size: 11px;
    font-weight: 400;
    color: var(--color-text-secondary);
    text-transform: capitalize;
    line-height: 1.3;
}

.lpa-agent__action {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    flex-shrink: 0;
    padding: 3px 6px 3px 8px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--color-primary);
    background: transparent;
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity 0.15s ease, transform 0.15s ease, background 0.15s ease;
}

.lpa-agent:hover .lpa-agent__action {
    opacity: 1;
    transform: translateX(0);
    background: rgba(var(--color-primary-rgb), 0.1);
}

.lpa-agent__action iconify-icon {
    font-size: 13px;
}

.lpa-fade-enter-active,
.lpa-fade-leave-active {
    transition: opacity 0.18s ease;
}

.lpa-fade-enter-active .lpa-dialog,
.lpa-fade-leave-active .lpa-dialog {
    transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.18s ease;
}

.lpa-fade-enter-from,
.lpa-fade-leave-to {
    opacity: 0;
}

.lpa-fade-enter-from .lpa-dialog,
.lpa-fade-leave-to .lpa-dialog {
    transform: translateY(8px) scale(0.97);
    opacity: 0;
}
</style>
