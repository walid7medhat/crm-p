<template>
    <Teleport to="body">
        <transition name="lpa-fade">
            <div v-if="modelValue" class="lpa-overlay" @mousedown.self="close">
                <div class="lpa-dialog" role="dialog" aria-modal="true" aria-labelledby="lpa-title">
                    <div class="lpa-head">
                        <div>
                            <h6 id="lpa-title" class="lpa-title">Assign to agent</h6>
                            <p class="lpa-sub">
                                {{ count }} lead{{ count === 1 ? '' : 's' }} selected
                            </p>
                        </div>
                        <button type="button" class="lpa-x" aria-label="Close" @click="close">
                            <iconify-icon icon="lucide:x" />
                        </button>
                    </div>

                    <div class="lpa-search">
                        <iconify-icon icon="lucide:search" />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            placeholder="Search agent by name or email"
                            @keydown.esc.stop="close"
                        />
                    </div>

                    <div class="lpa-list">
                        <div v-if="loading" class="lpa-empty">
                            <span class="spinner-border spinner-border-sm text-primary" role="status" />
                            Loading agents…
                        </div>
                        <div v-else-if="error" class="lpa-empty lpa-empty--error">{{ error }}</div>
                        <div v-else-if="!agents.length" class="lpa-empty">No agents found.</div>
                        <button
                            v-for="agent in agents"
                            v-else
                            :key="agent.id"
                            type="button"
                            class="lpa-agent"
                            @click="$emit('select', agent)"
                        >
                            <img v-if="agent.avatar" :src="agent.avatar" alt="" class="lpa-avatar" />
                            <span v-else class="lpa-avatar lpa-avatar--empty">
                                <iconify-icon icon="solar:user-bold" />
                            </span>
                            <span class="lpa-agent__text">
                                <span class="lpa-agent__name">{{ agent.name }}</span>
                                <span class="lpa-agent__meta">{{ agent.branch || agent.email }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
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
let searchTimer = null
let requestSeq = 0

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
    background: rgba(11, 7, 54, 0.35);
}

.lpa-dialog {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 440px;
    max-height: min(560px, calc(100vh - 32px));
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 18px 48px rgba(11, 7, 54, 0.22);
    font-family: Montserrat, Inter, system-ui, sans-serif;
    overflow: hidden;
}

.lpa-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 16px 8px;
}

.lpa-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #0B0736;
}

.lpa-sub {
    margin: 2px 0 0;
    font-size: 12px;
    color: #6b6880;
}

.lpa-x {
    display: inline-flex;
    padding: 4px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6b6880;
    font-size: 18px;
    cursor: pointer;
}

.lpa-x:hover {
    background: #f6f2f9;
}

.lpa-search {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 16px 8px;
    padding: 0 10px;
    border: 1px solid #ececef;
    border-radius: 8px;
    color: #6b6880;
}

.lpa-search input {
    flex: 1;
    min-width: 0;
    height: 36px;
    border: 0;
    outline: 0;
    font-size: 13px;
    background: transparent;
}

.lpa-list {
    flex: 1;
    min-height: 120px;
    overflow-y: auto;
    padding: 0 8px 12px;
}

.lpa-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 28px 8px;
    font-size: 13px;
    color: #6b6880;
}

.lpa-empty--error {
    color: #c62828;
}

.lpa-agent {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.lpa-agent:hover {
    background: #f6f2f9;
}

.lpa-avatar {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.lpa-avatar--empty {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ececef;
    color: #6b6880;
}

.lpa-agent__text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.lpa-agent__name,
.lpa-agent__meta {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.lpa-agent__name {
    font-size: 13px;
    font-weight: 600;
    color: #0B0736;
}

.lpa-agent__meta {
    font-size: 11px;
    color: #6b6880;
    text-transform: capitalize;
}

.lpa-fade-enter-active,
.lpa-fade-leave-active {
    transition: opacity 0.15s ease;
}

.lpa-fade-enter-from,
.lpa-fade-leave-to {
    opacity: 0;
}
</style>
