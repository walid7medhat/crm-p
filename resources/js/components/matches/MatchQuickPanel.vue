<template>
    <Teleport to="#shortcut-dock">
        <button
            ref="pillRef"
            type="button"
            class="mqp-root shortcut-item shortcut-item--matches"
            :class="{ 'is-open': matchesPanelOpen, 'has-waiting': waitingCount > 0 }"
            :aria-expanded="matchesPanelOpen"
            @click="toggle"
        >
            <iconify-icon icon="lucide:sparkles" />
            <span>Matches</span>
            <b v-if="waitingCount > 0" class="mqp-badge">{{ waitingCount > 9 ? '9+' : waitingCount }}</b>
        </button>
    </Teleport>

    <Teleport to="body">
        <transition name="mqp">
            <aside v-if="matchesPanelOpen" ref="panelRef" class="mqp" :style="panelStyle" aria-label="Homes ready to send">
                <header class="mqp__head">
                    <div class="mqp__title">
                        <span class="mqp__mark"><iconify-icon icon="lucide:sparkles" width="14" height="14" /></span>
                        <div>
                            <b>Homes ready to send</b>
                            <small v-if="clients.length">{{ plural(clients.length, 'client') }} · {{ plural(homesWaiting, 'home') }}</small>
                            <small v-else-if="!loading">Nothing waiting right now</small>
                        </div>
                    </div>
                    <button type="button" class="mqp__x" aria-label="Close" @click="close">
                        <iconify-icon icon="lucide:x" width="16" height="16" />
                    </button>
                </header>

                <div v-if="loading && !queue" class="mqp__status">Finding homes for your clients…</div>
                <div v-else-if="error" class="mqp__status">
                    {{ error }} <button type="button" class="mqp__link" @click="load">Try again</button>
                </div>
                <div v-else-if="!clients.length" class="mqp__empty">
                    <iconify-icon icon="lucide:search-check" width="26" height="26" />
                    <p>When a listing fits one of your clients, it shows up here.</p>
                </div>

                <ul v-else class="mqp__list">
                    <li v-for="c in clients.slice(0, MAX_ROWS)" :key="c.lead.id" class="mqp-row">
                        <button type="button" class="mqp-row__main" :title="`Review homes for ${c.lead.name}`" @click="review(c)">
                            <span class="mqp-row__score" :class="`is-${c.bucket}`">{{ c.best_score }}%</span>
                            <span class="mqp-row__text">
                                <b>{{ c.lead.name }}</b>
                                <small>{{ c.reason }}</small>
                            </span>
                        </button>
                        <div class="mqp-row__actions">
                            <a
                                class="mqp-icon"
                                :class="{ 'is-disabled': !c.lead.whatsapp }"
                                :href="c.lead.whatsapp ? `tel:+${c.lead.whatsapp}` : undefined"
                                :aria-label="`Call ${c.lead.name}`"
                            >
                                <iconify-icon icon="lucide:phone" width="14" height="14" />
                            </a>
                        </div>
                    </li>
                </ul>

                <footer v-if="clients.length" class="mqp__foot">
                    <button type="button" class="mqp-review" @click="reviewAll">
                        <iconify-icon icon="lucide:play" width="13" height="13" />
                        Review all {{ plural(clients.length, 'client') }}
                    </button>
                </footer>
            </aside>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { matchesPanelOpen, openMatchesPanel } from '@/composables/useShortcutPanels.js'
import { matchStudioVersion, openMatchStudio, startMatchQueue } from '@/composables/useMatchStudio.js'

/**
 * Matches in the shortcut dock: the agent's clients with homes waiting. A client opens
 * Match Studio (where offers are sent); the phone button calls them.
 */
const MAX_ROWS = 6
const PANEL_WIDTH = 370
const REFRESH_MS = 5 * 60 * 1000

const pillRef = ref(null)
const panelRef = ref(null)
const queue = ref(null)
const loading = ref(false)
const error = ref('')
const panelStyle = ref({})
let refreshTimer = null

const clients = computed(() => queue.value?.clients || [])
const homesWaiting = computed(() => queue.value?.summary?.homes_waiting || 0)
const waitingCount = computed(() => queue.value?.summary?.clients_waiting || 0)

async function load() {
    loading.value = true
    error.value = ''
    try {
        const res = await api.get('/lead-matches')
        queue.value = res.data?.data || null
    } catch {
        error.value = "Couldn't load your matches."
    } finally {
        loading.value = false
    }
}

function toggle() {
    if (matchesPanelOpen.value) close()
    else openMatchesPanel()
}

function close() {
    matchesPanelOpen.value = false
}

/** Beside the dock, vertically centred on the Matches button, kept on screen. */
function place() {
    const pill = pillRef.value?.getBoundingClientRect()
    const dock = document.getElementById('shortcut-dock')?.getBoundingClientRect()
    const vw = window.innerWidth
    const vh = window.innerHeight
    // Collapsed dock (button hidden) or phone: a sheet near the bottom instead.
    if (!pill || !pill.width || vw <= 800) {
        panelStyle.value = { left: '12px', right: '12px', bottom: '84px', top: 'auto', width: 'auto' }
        return
    }
    const height = panelRef.value?.offsetHeight || 420
    const top = Math.min(vh - height - 12, Math.max(12, pill.top + pill.height / 2 - height / 2))
    const right = vw - (dock?.left ?? pill.left) + 14
    panelStyle.value = { top: `${top}px`, right: `${right}px`, width: `${Math.min(PANEL_WIDTH, vw - right - 12)}px` }
}

function review(c) {
    close()
    openMatchStudio({
        id: c.lead.id,
        name: c.lead.name,
        first_name: c.lead.first_name,
        initials: c.lead.initials,
        matches_count: c.matches_count,
        reason: c.reason,
    })
}

function reviewAll() {
    close()
    startMatchQueue(clients.value.map((c) => ({
        id: c.lead.id,
        name: c.lead.name,
        first_name: c.lead.first_name,
        initials: c.lead.initials,
        matches_count: c.matches_count,
        reason: c.reason,
    })))
}

function plural(n, one) {
    return `${n} ${one}${n === 1 ? '' : 's'}`
}

function onPointerDown(event) {
    if (!matchesPanelOpen.value) return
    const path = typeof event.composedPath === 'function' ? event.composedPath() : [event.target]
    const inside = path.some((node) => node instanceof Element && node.closest('.mqp, .mqp-root'))
    if (!inside) close()
}

function onKeydown(event) {
    if (event.key === 'Escape' && matchesPanelOpen.value) close()
}

function onNotification(event) {
    if (event?.detail?.type === 'lead_matches') load()
}

// Offers sent in Match Studio refresh the badge and list.
watch(matchStudioVersion, load)
// Opened from the dock button or from a match alert (openMatchesPanel).
watch(matchesPanelOpen, (open) => {
    if (!open) return
    load()
    nextTick(place)
})
watch(() => clients.value.length, () => { if (matchesPanelOpen.value) nextTick(place) })

onMounted(() => {
    load()
    refreshTimer = window.setInterval(() => { if (!document.hidden) load() }, REFRESH_MS)
    document.addEventListener('pointerdown', onPointerDown, true)
    document.addEventListener('keydown', onKeydown)
    window.addEventListener('resize', place)
    window.addEventListener('app-notification', onNotification)
})

onBeforeUnmount(() => {
    window.clearInterval(refreshTimer)
    document.removeEventListener('pointerdown', onPointerDown, true)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', place)
    window.removeEventListener('app-notification', onNotification)
})
</script>

<style scoped>
/* Dock button: shares the dock's 3D look (ShortcutDock.vue); a pulsing ring and badge set it apart. */
.mqp-root {
    position: relative;
    border: 0;
    cursor: pointer;
}
.mqp-root.has-waiting {
    animation: mqp-glow 2.6s ease-in-out infinite;
}
@keyframes mqp-glow {
    0%, 100% { outline: 2px solid rgba(143, 90, 163, 0.0); outline-offset: 2px; }
    50% { outline: 2px solid rgba(143, 90, 163, 0.85); outline-offset: 4px; }
}
.mqp-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    z-index: 2;
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border-radius: 999px;
    background: #8f5aa3;
    color: #fff;
    font-size: 10.5px;
    font-weight: 800;
    line-height: 19px;
    text-align: center;
    box-shadow: 0 0 0 2px #fff;
}

/* Panel */
.mqp {
    position: fixed;
    z-index: 1500;
    display: grid;
    gap: 10px;
    max-width: calc(100vw - 24px);
    max-height: calc(100vh - 24px);
    overflow: hidden;
    padding: 14px;
    border-radius: 22px;
    border: 1px solid var(--color-primary-border, #e7dcec);
    background: var(--color-surface, #fff);
    box-shadow: 0 24px 60px rgba(40, 20, 70, 0.25);
    grid-template-rows: auto minmax(0, 1fr) auto;
}
.mqp-enter-active { transition: opacity 0.22s ease, transform 0.32s cubic-bezier(0.2, 0.9, 0.25, 1.2); }
.mqp-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.mqp-enter-from, .mqp-leave-to { opacity: 0; transform: translateX(14px) scale(0.97); }

.mqp__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.mqp__title { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mqp__title b { display: block; font-size: 14.5px; font-weight: 800; color: var(--color-text-primary, #171717); }
.mqp__title small { display: block; font-size: 12px; color: var(--color-text-secondary, #64748b); }
.mqp__mark {
    width: 32px;
    height: 32px;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #8f5aa3 0%, #733e87 100%);
    color: #fff;
    box-shadow: 0 0 0 3px rgba(143, 90, 163, 0.35);
}
.mqp__x { border: 0; background: transparent; padding: 4px; border-radius: 8px; color: var(--color-text-muted, #6b7280); }

.mqp__status { padding: 14px 4px; font-size: 13px; color: var(--color-text-secondary, #64748b); }
.mqp__empty { display: grid; justify-items: center; gap: 8px; padding: 18px 8px; text-align: center; color: var(--color-primary, #733e87); }
.mqp__empty p { margin: 0; font-size: 12.5px; color: var(--color-text-secondary, #64748b); max-width: 26ch; }

.mqp__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; overflow-y: auto; min-height: 0; }
.mqp-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 6px 6px 4px;
    border-radius: 14px;
    border: 1px solid var(--color-border, #e8e6ee);
    transition: border-color 0.15s, background 0.15s;
}
.mqp-row:hover { border-color: var(--color-primary-border, #e7dcec); background: var(--color-primary-soft, #f7f2f8); }
.mqp-row__main { flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px; padding: 2px; border: 0; background: transparent; text-align: left; }
.mqp-row__score {
    flex: none;
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 800;
    color: #ede4ff;
    background: #24152b;
    box-shadow: inset 0 0 0 2px rgba(143, 90, 163, 0.55);
}
.mqp-row__score.is-price_drop { box-shadow: inset 0 0 0 2px #2fbf6b; }
.mqp-row__score.is-revive { box-shadow: inset 0 0 0 2px #8f5aa3; }
.mqp-row__text { min-width: 0; }
.mqp-row__text b { display: block; font-size: 13px; color: var(--color-text-primary, #171717); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mqp-row__text small { display: block; font-size: 11.5px; color: var(--color-text-secondary, #64748b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mqp-row__actions { display: flex; align-items: center; gap: 5px; flex: none; }
.mqp-icon {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    border: 1px solid var(--color-border, #e8e6ee);
    color: var(--color-primary, #733e87);
    background: #fff;
}
.mqp-icon.is-disabled { opacity: 0.4; pointer-events: none; }

.mqp__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 4px; }
.mqp-review {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 38px;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, #8f5aa3 0%, #733e87 100%);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 3px 0 #4a285c;
}
.mqp-review:active { transform: translateY(2px); box-shadow: 0 1px 0 #4a285c; }
.mqp__link { border: 0; background: none; padding: 0; font-size: 12.5px; font-weight: 700; color: var(--color-primary, #733e87); text-decoration: underline; text-underline-offset: 3px; }

@media (prefers-reduced-motion: reduce) {
    .mqp-root.has-waiting { animation: none; }
    .mqp-enter-active, .mqp-leave-active { transition: none; }
}
</style>
