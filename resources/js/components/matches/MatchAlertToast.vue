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
                        {{ alert.lead_id ? 'Show' : 'See clients' }}
                    </button>
                    <button type="button" class="mat__btn" @click="dismiss">Later</button>
                </div>
            </div>
        </transition>

        <!-- The offer PDF is built; the agent's click here sends it (browsers need a fresh click). -->
        <transition name="mat">
            <div v-if="pendingPdfShare" class="mat-share" role="status">
                <iconify-icon icon="lucide:file-text" width="22" height="22" class="mat-share__icon" />
                <div class="min-w-0">
                    <b>Sales offer {{ pendingPdfShare.offerNumber }} is ready<template v-if="pendingPdfShare.clientName"> for {{ pendingPdfShare.clientName }}</template></b>
                    <small>{{ shareHint }}</small>
                </div>
                <button type="button" class="mat-share__btn" @click="send">
                    <iconify-icon :icon="pendingPdfShare.mode === 'share' || pendingPdfShare.whatsappUrl ? 'mdi:whatsapp' : 'lucide:download'" width="16" height="16" />
                    {{ shareLabel }}
                </button>
                <button type="button" class="mat__x mat-share__x" aria-label="Dismiss" @click="dismissShare">
                    <iconify-icon icon="lucide:x" width="15" height="15" />
                </button>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import MatchScoreRing from './MatchScoreRing.vue'
import { pendingPdfShare, sharePendingPdf } from '@/composables/useMatchStudio.js'
import { openMatchesPanel } from '@/composables/useShortcutPanels.js'
import { openLeadMatching } from '@/composables/useLeadViewModal.js'

/** Live match alert. Show opens the lead at the matching carousel. */
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

const shareLabel = computed(() => {
    const p = pendingPdfShare.value
    if (!p) return ''
    if (p.mode === 'share') return 'Share PDF'
    return p.whatsappUrl ? 'Send on WhatsApp' : 'Download PDF'
})

const shareHint = computed(() => {
    const p = pendingPdfShare.value
    if (!p) return ''
    if (p.mode === 'share') return 'Share the PDF, then pick WhatsApp and your client.'
    return p.whatsappUrl
        ? 'Opens the client\'s WhatsApp chat with the message; drag the downloaded PDF in.'
        : 'No WhatsApp number on this lead: the PDF downloads and the message is copied.'
})

async function send() {
    const outcome = await sharePendingPdf()
    if (outcome === 'opened') notify('WhatsApp is open. Drag the downloaded PDF into the chat.')
    else if (outcome === 'copied') notify('PDF downloaded and message copied.')
}

function notify(message) {
    if (window.$showNotification) window.$showNotification(message, 'success')
}

function dismissShare() {
    pendingPdfShare.value = null
}

function dismiss() {
    window.clearTimeout(hideTimer)
    alert.value = null
}

/** One client: open the lead scrolled to matching. Several: the dock's Matches panel. */
function open() {
    const n = alert.value
    dismiss()
    if (!n) return
    if (n.lead_id) {
        openLeadMatching(n.lead_id, {
            highlightListingId: n.listing_id || n.listing_match_id || null,
        })
    } else {
        openMatchesPanel()
    }
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
    box-shadow: 0 18px 40px rgba(var(--color-primary-rgb, 115, 62, 135), 0.18);
    --match-ring-track: var(--color-primary-soft);
    --match-ring-text: var(--color-primary);
}
.min-w-0 { min-width: 0; }
.mat-enter-active { transition: transform 0.5s cubic-bezier(0.2, 0.9, 0.25, 1.2), opacity 0.3s; }
.mat-leave-active { transition: transform 0.2s, opacity 0.2s; }
.mat-enter-from, .mat-leave-to { opacity: 0; transform: translateY(-16px) scale(0.96); }

.mat__x { position: absolute; top: 10px; right: 10px; border: 0; background: transparent; padding: 4px; border-radius: 8px; color: var(--color-text-muted); }
.mat__top { display: flex; align-items: center; gap: 14px; padding-right: 18px; }
.mat__icon { width: 50px; height: 50px; flex: none; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, var(--color-primary-bright, #8f5aa3) 0%, var(--color-primary, #733e87) 100%); color: var(--color-on-primary, #fff); }
.mat__kicker {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-primary, #733e87);
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
    background: linear-gradient(135deg, var(--color-primary-bright, #8f5aa3) 0%, var(--color-primary, #733e87) 100%);
    color: var(--color-on-primary);
    box-shadow: 0 8px 18px rgba(var(--color-primary-rgb), 0.28);
}
.mat__btn--primary:hover { background: linear-gradient(135deg, var(--color-primary, #7d4a91) 0%, var(--color-primary-hover, #5c3270) 100%); }

.mat-share {
    position: fixed;
    z-index: 12650;
    left: max(12px, env(safe-area-inset-left, 0px));
    right: max(12px, env(safe-area-inset-right, 0px));
    bottom: calc(84px + env(safe-area-inset-bottom, 0px));
    max-width: 520px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 12px 12px 14px;
    border-radius: 18px;
    background: var(--color-surface);
    border: 1px solid rgba(37, 163, 90, 0.4);
    box-shadow: 0 18px 40px rgba(23, 12, 28, 0.25);
}
.mat-share__icon { flex: none; color: #219653; }
.mat-share b { display: block; font-size: 13.5px; color: var(--color-text-primary); }
.mat-share small { display: block; font-size: 12px; color: var(--color-text-secondary); }
.mat-share__btn {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 40px;
    padding: 0 14px;
    border: 0;
    border-radius: 12px;
    background: linear-gradient(180deg, #2fbf6b 0%, #219653 100%);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 2px 0 #17703d;
}
.mat-share__x { position: static; flex: none; }

@media (max-width: 640px) {
    .mat { left: max(16px, env(safe-area-inset-left, 0px)); width: auto; }
}
@media (prefers-reduced-motion: reduce) {
    .mat-enter-active, .mat-leave-active { transition: none; }
}
</style>
