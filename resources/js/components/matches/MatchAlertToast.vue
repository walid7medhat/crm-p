<template>
    <Teleport to="body">
        <transition name="mat">
            <div v-if="alert" class="mat" role="status">
                <button type="button" class="mat__x" aria-label="Dismiss" @click="dismiss">
                    <iconify-icon icon="lucide:x" width="16" height="16" />
                </button>
                <div class="mat__top">
                    <MatchScoreRing v-if="alert.best_score" :score="alert.best_score" size="lg" class="mat__ring" />
                    <div class="mat__icon" v-else><iconify-icon :icon="kind.icon" width="22" height="22" /></div>
                    <div class="min-w-0">
                        <div class="mat__kicker"><iconify-icon :icon="kind.icon" width="11" height="11" />{{ kind.label }}</div>
                        <p class="mat__msg">{{ alert.message }}</p>
                    </div>
                </div>
                <div class="mat__actions">
                    <button type="button" class="mat__btn mat__btn--primary" @click="open">
                        {{ alert.lead_id ? 'Open Match Studio' : 'Open My Matches' }}
                    </button>
                    <button type="button" class="mat__btn" @click="dismiss">Later</button>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MatchScoreRing from './MatchScoreRing.vue'
import { openMatchStudio } from '@/composables/useMatchStudio.js'

/** The live pop-up for Match Studio alerts. The bell keeps the same alert for later. */
const router = useRouter()
const alert = ref(null)
let hideTimer = null

const kind = computed(() => {
    switch (alert.value?.bucket) {
        case 'price_drop': return { label: 'Price drop', icon: 'lucide:trending-down' }
        case 'revive': return { label: 'Quiet clients', icon: 'lucide:snowflake' }
        default: return { label: 'Match found', icon: 'lucide:sparkles' }
    }
})

function onNotification(event) {
    const n = event?.detail
    if (!n || n.type !== 'lead_matches') return
    alert.value = n
    window.clearTimeout(hideTimer)
    hideTimer = window.setTimeout(dismiss, 15000)
}

function dismiss() {
    window.clearTimeout(hideTimer)
    alert.value = null
}

function open() {
    const n = alert.value
    dismiss()
    if (!n) return
    if (n.lead_id) openMatchStudio({ id: Number(n.lead_id) })
    else router.push(n.url || '/matches')
}

onMounted(() => window.addEventListener('app-notification', onNotification))
onBeforeUnmount(() => {
    window.removeEventListener('app-notification', onNotification)
    window.clearTimeout(hideTimer)
})
</script>

<style scoped>
.mat {
    position: fixed;
    z-index: 12550;
    top: calc(72px + env(safe-area-inset-top, 0px));
    right: max(16px, env(safe-area-inset-right, 0px));
    width: min(390px, calc(100vw - 32px));
    display: grid;
    gap: 14px;
    padding: 16px;
    border-radius: 20px;
    background: var(--color-surface);
    border: 1px solid var(--color-primary-border);
    box-shadow: 0 18px 40px rgba(63, 33, 72, 0.18);
    --match-ring-track: var(--color-primary-soft);
    --match-ring-text: var(--color-primary);
}
.min-w-0 { min-width: 0; }
.mat-enter-active { transition: transform 0.5s cubic-bezier(0.2, 0.9, 0.25, 1.2), opacity 0.3s; }
.mat-leave-active { transition: transform 0.2s, opacity 0.2s; }
.mat-enter-from, .mat-leave-to { opacity: 0; transform: translateY(-16px) scale(0.96); }

.mat__x { position: absolute; top: 10px; right: 10px; border: 0; background: transparent; padding: 4px; border-radius: 8px; color: var(--color-text-muted); }
.mat__top { display: flex; align-items: center; gap: 14px; padding-right: 18px; }
.mat__icon { width: 50px; height: 50px; flex: none; border-radius: 16px; display: grid; place-items: center; background: var(--gradient-primary); color: #fff; }
.mat__kicker {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #8a6d12;
}
.mat__msg { margin: 3px 0 0; font-size: 14px; font-weight: 700; line-height: 1.35; color: var(--color-text-primary); }
.mat__actions { display: flex; gap: 8px; }
.mat__btn {
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    padding: 9px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}
.mat__btn--primary {
    flex: 1;
    border-color: transparent;
    background: var(--gradient-primary);
    color: var(--color-on-primary);
    box-shadow: 0 8px 18px rgba(var(--color-primary-rgb), 0.28);
}
.mat__btn--primary:hover { background: var(--gradient-primary-hover); }

@media (max-width: 640px) {
    .mat { left: max(16px, env(safe-area-inset-left, 0px)); width: auto; }
}
@media (prefers-reduced-motion: reduce) {
    .mat-enter-active, .mat-leave-active { transition: none; }
}
</style>
