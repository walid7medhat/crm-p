<template>
    <Teleport to="body">
        <transition name="ms-fade">
            <div
                v-if="matchStudioOpen"
                class="ms"
                :class="{ 'ms--framed': framed }"
                :style="frameStyle"
                role="dialog"
                aria-modal="true"
                aria-label="Match Studio"
            >
                <header class="ms-head">
                    <div class="ms-title">
                        <span class="ms-title__mark"><iconify-icon icon="lucide:sparkles" width="15" height="15" /></span>
                        <span class="ms-title__text">Match Studio</span>
                    </div>
                    <div v-if="lead && !done" class="ms-sub">Pick the homes to send to {{ lead.first_name }}</div>
                    <div class="ms-actions">
                        <button v-if="lead && !done" type="button" class="ms-btn" @click="viewLead">View lead</button>
                        <button v-if="queueMode && !done" type="button" class="ms-btn" @click="skipClient">Skip client</button>
                        <button type="button" class="ms-btn ms-btn--back" @click="close">
                            <iconify-icon icon="lucide:arrow-left" width="14" height="14" />
                            Back <kbd>Esc</kbd>
                        </button>
                    </div>
                </header>

                <div v-if="queueMode" class="ms-qbar">
                    <div class="ms-qbar__seg">
                        <i
                            v-for="(client, i) in matchStudioQueue"
                            :key="client.id"
                            :class="segmentClass(client, i)"
                            :title="client.name"
                        ></i>
                    </div>
                    <span v-if="!done" class="ms-qbar__label">
                        Client {{ matchStudioIndex + 1 }} of {{ matchStudioQueue.length }} · <b>{{ lead?.name || currentClient?.name }}</b>
                    </span>
                    <span v-else class="ms-qbar__label"><b>Review finished</b></span>
                </div>

                <div class="ms-main">
                    <!-- Client profile -->
                    <aside class="ms-panel ms-profile">
                        <template v-if="done">
                            <div class="ms-client">
                                <div class="ms-avatar">{{ meInitials }}</div>
                                <div><b>{{ meName }}</b><small>Your review is done</small></div>
                            </div>
                            <div class="ms-why">New matches arrive as listings and leads change. You'll get a notification.</div>
                        </template>
                        <template v-else-if="lead">
                            <div class="ms-client">
                                <div class="ms-avatar">{{ lead.initials }}</div>
                                <div class="min-w-0">
                                    <b>{{ lead.name }}</b>
                                    <small>{{ leadMeta }}</small>
                                </div>
                            </div>
                            <div v-if="whyNow" class="ms-why">{{ whyNow }}</div>
                            <div class="ms-dna">
                                <div v-for="row in lead.requirements" :key="row.label" class="ms-dna__row">
                                    <span>{{ row.label }}</span><b>{{ row.value }}</b>
                                </div>
                            </div>
                            <div class="ms-learn">
                                <template v-if="learned">Got it. <b>{{ learned }}.</b></template>
                                <template v-else>Skipping teaches the matcher. <b>Each skip changes what {{ lead.first_name }} is shown next.</b></template>
                            </div>
                        </template>
                        <div v-else class="ms-skeleton">
                            <span></span><span></span><span></span><span></span>
                        </div>
                    </aside>

                    <!-- Deck -->
                    <section class="ms-center">
                        <div class="ms-deck">
                            <div v-if="loading" class="ms-pane">
                                <div class="ms-spinner" aria-hidden="true"></div>
                                <p>Finding homes for {{ currentClient?.first_name || currentClient?.name || 'this client' }}…</p>
                            </div>

                            <div v-else-if="done" class="ms-pane">
                                <div class="ms-burst" aria-hidden="true">
                                    <i v-for="n in 14" :key="n" :style="burstDot(n)"></i>
                                    <iconify-icon icon="lucide:sparkles" width="40" height="40" />
                                </div>
                                <h3>Review cleared</h3>
                                <div class="ms-stats">
                                    <div><b>{{ doneStats.clients }}</b><span>clients reached</span></div>
                                    <div><b>{{ doneStats.homes }}</b><span>homes sent</span></div>
                                    <div><b>{{ doneStats.later }}</b><span>for later</span></div>
                                </div>
                                <p>{{ doneStats.clients ? "Replies show up on each lead's timeline." : 'Nothing was sent this round.' }}</p>
                                <button type="button" class="ms-cta" @click="close">Done</button>
                            </div>

                            <div v-else-if="pane === 'sent'" class="ms-pane">
                                <div class="ms-check"><iconify-icon icon="lucide:check" width="30" height="30" /></div>
                                <h3>Sent to {{ lead.first_name }}</h3>
                                <p>{{ plural(sentCount, 'home') }} logged on {{ lead.first_name }}'s timeline.</p>
                                <template v-if="nextClient">
                                    <div class="ms-upnext">Up next</div>
                                    <div class="ms-next">
                                        <div class="ms-avatar ms-avatar--sm">{{ nextClient.initials }}</div>
                                        <div class="min-w-0">
                                            <b>{{ nextClient.name }}</b>
                                            <small>{{ plural(nextClient.matches_count || 0, 'match', 'matches') }}<template v-if="nextClient.reason"> · {{ nextClient.reason }}</template></small>
                                        </div>
                                    </div>
                                    <button ref="paneActionEl" type="button" class="ms-cta" @click="goNext">Continue to {{ nextClient.first_name || nextClient.name }}</button>
                                </template>
                                <button v-else-if="queueMode" ref="paneActionEl" type="button" class="ms-cta" @click="goNext">Finish</button>
                                <button v-else ref="paneActionEl" type="button" class="ms-btn" @click="close">Back</button>
                            </div>

                            <div v-else-if="loadError" class="ms-pane">
                                <h3>Couldn't load matches</h3>
                                <p>{{ loadError }}</p>
                                <button type="button" class="ms-btn" @click="load">Try again</button>
                            </div>

                            <div v-else-if="lead && !hasRequirements" class="ms-pane">
                                <h3>Add what {{ lead.first_name }} wants</h3>
                                <p>Matches need at least a budget, area or bedrooms on the lead.</p>
                                <button type="button" class="ms-cta" @click="viewLead('requirements')">Add requirements</button>
                                <button v-if="queueMode" type="button" class="ms-btn" @click="skipClient">Next client</button>
                            </div>

                            <div v-else-if="lead && !deck.length && !shortlist.length" class="ms-pane">
                                <h3>No homes fit {{ lead.first_name }} right now</h3>
                                <p>You'll get a notification when a listing goes live or drops into budget.</p>
                                <button v-if="queueMode" type="button" class="ms-btn" @click="skipClient">Next client</button>
                            </div>

                            <div v-else-if="lead && pos >= deck.length" class="ms-pane">
                                <template v-if="shortlist.length">
                                    <h3>All {{ deck.length }} reviewed</h3>
                                    <p>{{ plural(shortlist.length, 'home') }} on the shortlist. Send them to {{ lead.first_name }} from the panel.</p>
                                </template>
                                <template v-else>
                                    <h3>Nothing shortlisted</h3>
                                    <p>None of these felt right for {{ lead.first_name }}. The matcher will remember that.</p>
                                    <button type="button" class="ms-btn" @click="queueMode ? skipClient() : close()">{{ queueMode ? 'Next client' : 'Back' }}</button>
                                </template>
                            </div>

                            <template v-if="showCards">
                                <article
                                    v-for="(m, k) in stackCards"
                                    :key="m.id"
                                    class="ms-card"
                                    :class="{ 'is-top': k === stackCards.length - 1, 'is-dragging': dragging && k === stackCards.length - 1 }"
                                    :style="cardStyle(k)"
                                    @pointerdown="k === stackCards.length - 1 && onPointerDown($event)"
                                    @pointermove="k === stackCards.length - 1 && onPointerMove($event)"
                                    @pointerup="k === stackCards.length - 1 && onPointerUp()"
                                    @pointercancel="k === stackCards.length - 1 && onPointerUp()"
                                >
                                    <div class="ms-card__photo">
                                        <MatchPhoto :src="m.listing.main_image" :seed="m.listing.id" />
                                        <span v-if="cardTag(m)" class="ms-card__tag">{{ cardTag(m) }}</span>
                                        <MatchScoreRing class="ms-card__ring" :score="m.score" />
                                        <template v-if="k === stackCards.length - 1">
                                            <div class="ms-stamp ms-stamp--yes" :style="{ opacity: stampYes }">SHORTLIST</div>
                                            <div class="ms-stamp ms-stamp--no" :style="{ opacity: stampNo }">SKIP</div>
                                        </template>
                                    </div>
                                    <div class="ms-card__body">
                                        <div class="ms-card__loc">
                                            <iconify-icon icon="lucide:map-pin" width="12" height="12" />
                                            {{ [m.listing.area, m.listing.title].filter(Boolean).join(' · ') }}
                                        </div>
                                        <div class="ms-card__price">
                                            <small>AED</small>{{ money(m.listing.price) }}
                                            <s v-if="m.previous_price && m.bucket === 'price_drop'">{{ money(m.previous_price) }}</s>
                                        </div>
                                        <div class="ms-card__specs">{{ specs(m.listing) }}</div>
                                        <div class="ms-chips">
                                            <span v-for="t in m.fits" :key="'f' + t" class="ms-chip ms-chip--ok">
                                                <iconify-icon icon="lucide:check" width="11" height="11" />{{ t }}
                                            </span>
                                            <span v-for="t in m.gaps" :key="'g' + t" class="ms-chip ms-chip--gap">
                                                <iconify-icon icon="lucide:alert-circle" width="11" height="11" />{{ t }}
                                            </span>
                                        </div>
                                        <button type="button" class="ms-card__open" @pointerdown.stop @click.stop="openListing(m.listing.id)">
                                            View listing
                                        </button>
                                    </div>
                                </article>
                            </template>
                        </div>

                        <div class="ms-controls">
                            <button type="button" class="ms-cbtn ms-cbtn--undo" :disabled="!canUndo" aria-label="Undo" @click="undo">
                                <iconify-icon icon="lucide:undo-2" width="18" height="18" />
                            </button>
                            <button type="button" class="ms-cbtn ms-cbtn--skip" :disabled="!canDecide" aria-label="Skip home" @click="decide(false)">
                                <iconify-icon icon="lucide:x" width="24" height="24" />
                            </button>
                            <button type="button" class="ms-cbtn ms-cbtn--like" :disabled="!canDecide" aria-label="Shortlist home" @click="decide(true)">
                                <iconify-icon icon="lucide:heart" width="28" height="28" />
                            </button>
                        </div>
                        <div v-if="deck.length && !pane && !done" class="ms-progress">
                            <i v-for="m in deck" :key="m.id" :class="{ liked: m.status === 'shortlisted', done: m.status === 'skipped' }"></i>
                            <span>&nbsp;{{ Math.min(pos + 1, deck.length) }} of {{ deck.length }}</span>
                        </div>
                        <div class="ms-keys">← skip · → shortlist · Z undo</div>
                    </section>

                    <!-- Shortlist -->
                    <aside class="ms-panel ms-tray">
                        <h3>Shortlist <span>{{ shortlist.length }}</span></h3>
                        <p>{{ lead && !done ? `Homes you will send to ${lead.first_name}.` : 'Homes you pick show up here.' }}</p>
                        <TransitionGroup name="ms-drop" tag="div" class="ms-slist">
                            <div v-for="m in shortlist" :key="m.id" class="ms-sitem">
                                <MatchPhoto class="ms-sitem__photo" :src="m.listing.main_image" :seed="m.listing.id" />
                                <div class="min-w-0">
                                    <b>{{ m.listing.title }}</b>
                                    <small>{{ shortPrice(m.listing.price) }} · {{ m.score }}% match</small>
                                </div>
                                <button type="button" :aria-label="`Remove ${m.listing.title}`" :disabled="pane === 'sent'" @click="removeFromShortlist(m)">
                                    <iconify-icon icon="lucide:x" width="14" height="14" />
                                </button>
                            </div>
                        </TransitionGroup>
                        <div v-if="!shortlist.length" class="ms-sempty">Swipe right or tap the heart to add a home here.</div>
                        <button type="button" class="ms-send" :disabled="!shortlist.length || pane === 'sent' || done" @click="openSheet">
                            <iconify-icon icon="mdi:whatsapp" width="18" height="18" />
                            {{ shortlist.length ? `Send ${shortlist.length} to ${lead?.first_name} on WhatsApp` : 'Shortlist homes to send' }}
                        </button>
                    </aside>
                </div>

                <!-- Message preview -->
                <div v-if="sheetOpen" class="ms-sheet-bg" @click.self="sheetOpen = false">
                    <div class="ms-sheet" role="dialog" aria-modal="true" :aria-label="`Message to ${lead?.first_name}`">
                        <div>
                            <h3>Message to {{ lead?.first_name }}</h3>
                            <div class="ms-sheet__to">
                                <template v-if="lead?.whatsapp">WhatsApp · +{{ lead.whatsapp }}</template>
                                <template v-else>No WhatsApp number on this lead. Copy the message and send it yourself.</template>
                            </div>
                        </div>
                        <div class="ms-chat">
                            <div class="ms-bubble">
                                <div class="ms-bubble__thumbs">
                                    <MatchPhoto v-for="m in shortlist.slice(0, 4)" :key="m.id" class="ms-bubble__thumb" :src="m.listing.main_image" :seed="m.listing.id" />
                                </div>
                                <span>{{ previewText }}</span>
                            </div>
                        </div>
                        <p class="ms-sheet__note">Photos aren't attached automatically. Add them in WhatsApp if you want.</p>
                        <div class="ms-sheet__actions">
                            <button type="button" class="ms-btn" @click="copyMessage">Copy message</button>
                            <button type="button" class="ms-cta ms-cta--grow" :disabled="sending" @click="confirmSend">
                                {{ sending ? 'Logging…' : lead?.whatsapp ? 'Open WhatsApp & log' : 'Log as sent' }}
                            </button>
                        </div>
                    </div>
                </div>

                <transition name="ms-snack">
                    <div v-if="snack" class="ms-snack" role="status">
                        <iconify-icon :icon="snack.icon" width="14" height="14" />
                        <span>{{ snack.text }}</span>
                    </div>
                </transition>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import MatchPhoto from './MatchPhoto.vue'
import MatchScoreRing from './MatchScoreRing.vue'
import {
    matchStudioOpen,
    matchStudioQueue,
    matchStudioIndex,
    matchStudioQueueMode,
    closeMatchStudio,
    touchMatchStudio,
} from '@/composables/useMatchStudio.js'
import { openLeadView, closeLeadView, showLeadViewModal, leadViewModalId } from '@/composables/useLeadViewModal.js'
import { openPropertyDetails } from '@/composables/usePropertyDetailsModal'

const queueMode = matchStudioQueueMode
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const loadError = ref('')
const lead = ref(null)
const hasRequirements = ref(true)
const whyNow = ref('')
const deck = ref([])
const shortlist = ref([])
const pos = ref(0)
const history = ref([])
const learned = ref('')
const pane = ref(null) // null | 'sent'
const sentCount = ref(0)
const done = ref(false)
const sheetOpen = ref(false)
const sending = ref(false)
const snack = ref(null)
const paneActionEl = ref(null)
/** Per queue client id: 'sent' | 'skipped'. */
const outcome = ref({})
const sentHomes = ref({})
const busy = ref(false)

const me = (() => {
    try { return JSON.parse(localStorage.getItem('user') || 'null') } catch { return null }
})()
const meName = me?.name || 'You'
const meInitials = meName.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('') || 'Y'

const currentClient = computed(() => matchStudioQueue.value[matchStudioIndex.value] || null)

const nextClient = computed(() => {
    if (!queueMode.value) return null
    const list = matchStudioQueue.value
    for (let k = 1; k < list.length; k++) {
        const c = list[(matchStudioIndex.value + k) % list.length]
        if (!outcome.value[c.id]) return c
    }
    return null
})

const leadMeta = computed(() => {
    if (!lead.value) return ''
    const bits = [lead.value.stage]
    if (lead.value.quiet_days >= 90) bits.push(`no contact for ${lead.value.quiet_days} days`)
    return bits.filter(Boolean).join(' · ')
})

const doneStats = computed(() => {
    const ids = Object.keys(outcome.value)
    const sent = ids.filter((id) => outcome.value[id] === 'sent')
    return {
        clients: sent.length,
        homes: sent.reduce((s, id) => s + (sentHomes.value[id] || 0), 0),
        later: ids.filter((id) => outcome.value[id] === 'skipped').length,
    }
})

const showCards = computed(() => !loading.value && !done.value && !pane.value && !loadError.value && lead.value && hasRequirements.value)
/** Up to three cards, bottom first, so the top card is last in the DOM. */
const stackCards = computed(() => deck.value.slice(pos.value, pos.value + 3).reverse())
const canDecide = computed(() => showCards.value && pos.value < deck.value.length && !busy.value)
const canUndo = computed(() => showCards.value && history.value.length > 0 && !busy.value)

// ---------- loading ----------
async function load() {
    const client = currentClient.value
    if (!client) return
    resetClientState()
    loading.value = true
    try {
        const res = await api.get(`/leads/${client.id}/matches`)
        const data = res.data?.data || {}
        lead.value = data.lead || null
        hasRequirements.value = !!data.has_requirements
        whyNow.value = data.why_now || ''
        const matches = Array.isArray(data.matches) ? data.matches.filter((m) => m.listing) : []
        deck.value = matches.filter((m) => m.status === 'new')
        shortlist.value = matches.filter((m) => m.status === 'shortlisted')
    } catch (e) {
        loadError.value = e.response?.status === 403
            ? 'You can only open matches for your own leads.'
            : 'Check your connection and try again.'
    } finally {
        loading.value = false
    }
}

function resetClientState() {
    lead.value = null
    loadError.value = ''
    deck.value = []
    shortlist.value = []
    pos.value = 0
    history.value = []
    learned.value = ''
    pane.value = null
    sentCount.value = 0
    sheetOpen.value = false
    resetDrag()
}

watch(matchStudioOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
    document.body.classList.toggle('match-studio-open', open)
    if (open) {
        // The studio takes over from the lead popup and hands it back on close.
        returnToLeadId = showLeadViewModal.value ? Number(leadViewModalId.value) || null : null
        if (returnToLeadId) {
            closeLeadView()
            // Let the lead popup finish clearing ?lead= first, or its navigation cancels ours.
            window.setTimeout(pushStudioEntry, 150)
        } else {
            pushStudioEntry()
        }
        startFrame()
        done.value = false
        outcome.value = {}
        sentHomes.value = {}
        load()
    } else {
        stopFrame()
    }
})
watch(matchStudioIndex, () => {
    if (matchStudioOpen.value) load()
})

// ---------- deciding ----------
async function saveDecision(match, action) {
    const res = await api.post(`/lead-matches/${match.id}/decision`, { action })
    return res.data?.data || {}
}

function decide(liked) {
    if (!canDecide.value) return
    const match = deck.value[pos.value]
    busy.value = true
    flying.value = liked ? 'right' : 'left'
    window.setTimeout(() => {
        match.status = liked ? 'shortlisted' : 'skipped'
        if (liked) shortlist.value.push(match)
        history.value.push({ match, liked })
        pos.value++
        resetDrag()
        busy.value = false
    }, 330)

    saveDecision(match, liked ? 'shortlist' : 'skip')
        .then((data) => {
            if (!liked && data.learned) {
                learned.value = data.learned
                showSnack(`Learning: ${data.learned}.`, 'lucide:sparkles')
            }
        })
        .catch(() => {
            showSnack("Couldn't save that choice. It's back in the deck.", 'lucide:alert-circle')
            window.setTimeout(() => rollBack(match), 360)
        })
}

function rollBack(match) {
    const i = history.value.findIndex((h) => h.match.id === match.id)
    if (i !== -1) history.value.splice(i, 1)
    shortlist.value = shortlist.value.filter((m) => m.id !== match.id)
    match.status = 'new'
    pos.value = Math.min(pos.value, deck.value.indexOf(match))
}

function undo() {
    if (!canUndo.value) return
    const last = history.value.pop()
    const match = last.match
    shortlist.value = shortlist.value.filter((m) => m.id !== match.id)
    match.status = 'new'
    pos.value = deck.value.indexOf(match)
    saveDecision(match, 'reset').catch(() => showSnack("Couldn't undo on the server. Try again.", 'lucide:alert-circle'))
}

function removeFromShortlist(match) {
    shortlist.value = shortlist.value.filter((m) => m.id !== match.id)
    const h = history.value.find((x) => x.match.id === match.id)
    if (h) h.liked = false
    match.status = 'new'
    saveDecision(match, 'reset').catch(() => showSnack("Couldn't update the shortlist. Try again.", 'lucide:alert-circle'))
}

// ---------- drag ----------
const dragging = ref(false)
const dragX = ref(0)
const dragY = ref(0)
const flying = ref(null)
let startX = 0
let startY = 0

function resetDrag() {
    dragging.value = false
    dragX.value = 0
    dragY.value = 0
    flying.value = null
}

function onPointerDown(e) {
    if (!canDecide.value || e.button > 0) return
    if (e.target.closest('button')) return
    dragging.value = true
    startX = e.clientX
    startY = e.clientY
    e.currentTarget.setPointerCapture?.(e.pointerId)
}
function onPointerMove(e) {
    if (!dragging.value) return
    dragX.value = e.clientX - startX
    dragY.value = e.clientY - startY
}
function onPointerUp() {
    if (!dragging.value) return
    dragging.value = false
    if (Math.abs(dragX.value) > 100) decide(dragX.value > 0)
    else { dragX.value = 0; dragY.value = 0 }
}

const stampYes = computed(() => (flying.value === 'right' ? 1 : Math.max(0, Math.min(1, dragX.value / 100))))
const stampNo = computed(() => (flying.value === 'left' ? 1 : Math.max(0, Math.min(1, -dragX.value / 100))))

function cardStyle(k) {
    const depth = stackCards.value.length - 1 - k
    if (depth === 0) {
        if (flying.value) {
            const dir = flying.value === 'right' ? 1 : -1
            return { transform: `translate(${dir * 140}%, -30px) rotate(${dir * 22}deg)`, opacity: 0, zIndex: 10, transition: 'transform .4s ease-in, opacity .4s ease-in' }
        }
        if (dragging.value || dragX.value) {
            return { transform: `translate(${dragX.value}px, ${dragY.value * 0.25}px) rotate(${dragX.value / 18}deg)`, zIndex: 10 }
        }
        return { zIndex: 10 }
    }
    return { transform: `translateY(${depth * 14}px) scale(${1 - depth * 0.05})`, opacity: 1 - depth * 0.25, zIndex: 10 - depth }
}

// ---------- sending ----------
const previewText = computed(() => {
    if (!lead.value) return ''
    const n = shortlist.value.length
    const lines = shortlist.value.map((m, i) => {
        const l = m.listing
        const bits = [l.bedrooms_label, l.property_type, l.size_sqft ? `${Number(l.size_sqft).toLocaleString('en-US')} sqft` : null, l.price ? shortPrice(l.price) : null].filter(Boolean)
        const place = [l.title, l.area].filter(Boolean).join(', ')
        return `${i + 1}. ${place}: ${bits.join(' · ')}`
    })
    const sign = me?.name ? `\n${me.name}` : ''
    return `Hi ${lead.value.first_name}, I picked ${n === 1 ? 'a home that fits' : `${n} homes that fit`} what you asked for:\n\n${lines.join('\n')}\n\nWould you like to view ${n === 1 ? 'it' : 'any of them'} this week?${sign}`
})

function openSheet() {
    if (!shortlist.value.length) return
    sheetOpen.value = true
}

async function copyMessage() {
    try {
        await navigator.clipboard.writeText(previewText.value)
        showSnack('Message copied', 'lucide:copy')
    } catch {
        showSnack("Couldn't copy. Select the text and copy it yourself.", 'lucide:alert-circle')
    }
}

async function confirmSend() {
    if (sending.value || !lead.value) return
    sending.value = true
    // Open the tab inside the click so the browser doesn't block it, then point it at WhatsApp.
    const win = lead.value.whatsapp ? window.open('', '_blank') : null
    try {
        const res = await api.post(`/leads/${lead.value.id}/matches/send`, {
            match_ids: shortlist.value.map((m) => m.id),
        })
        const data = res.data?.data || {}
        if (win && data.whatsapp_url) win.location.href = data.whatsapp_url
        else if (win) win.close()
        sentCount.value = data.sent_count || shortlist.value.length
        outcome.value = { ...outcome.value, [lead.value.id]: 'sent' }
        sentHomes.value = { ...sentHomes.value, [lead.value.id]: sentCount.value }
        sheetOpen.value = false
        pane.value = 'sent'
        touchMatchStudio()
        nextTick(() => paneActionEl.value?.focus())
    } catch (e) {
        win?.close()
        showSnack(e.response?.data?.message || "Couldn't log the message. Nothing was sent.", 'lucide:alert-circle')
    } finally {
        sending.value = false
    }
}

// ---------- queue ----------
function goNext() {
    const next = nextClient.value
    if (!next) {
        done.value = true
        touchMatchStudio()
        return
    }
    matchStudioIndex.value = matchStudioQueue.value.indexOf(next)
}

function skipClient() {
    if (!queueMode.value) { close(); return }
    const id = currentClient.value?.id
    if (id && !outcome.value[id]) outcome.value = { ...outcome.value, [id]: 'skipped' }
    goNext()
}

function segmentClass(client, i) {
    const o = outcome.value[client.id]
    if (o === 'sent') return 'is-done'
    if (o === 'skipped') return 'is-skipped'
    return !done.value && i === matchStudioIndex.value ? 'is-current' : ''
}

// ---------- misc ----------
/** Lead popup that was open when the studio started; reopened on its Matches tab when the studio closes. */
let returnToLeadId = null
/** Lead to open once the studio has closed ("View lead", "Add requirements"). */
let pendingLead = null

// ---------- browser history ----------
// The studio behaves like a page: opening it adds ?studio=<lead id | review> to the URL, so the
// browser's Back button closes it and returns to the page underneath, and a refresh on a
// single-client studio reopens it.
const STUDIO_QUERY = 'studio'
let pushedHistory = false

function withoutKeys(query, keys) {
    const next = { ...query }
    keys.forEach((k) => delete next[k])
    return next
}

function pushStudioEntry() {
    if (!matchStudioOpen.value || route.query[STUDIO_QUERY]) return
    const value = queueMode.value ? 'review' : String(currentClient.value?.id || '')
    if (!value) return
    pushedHistory = true
    // Drop ?lead= too: the lead popup has stepped aside for the studio.
    router.push({ query: { ...withoutKeys(route.query, ['lead']), [STUDIO_QUERY]: value } }).catch(() => {})
}

function close() {
    if (history.value.length || Object.keys(outcome.value).length) touchMatchStudio()
    if (route.query[STUDIO_QUERY]) {
        // The query watcher finishes closing. Opened from a link or refresh: there's no page of
        // ours to go back to, so just drop the parameter.
        if (pushedHistory) router.back()
        else router.replace({ query: withoutKeys(route.query, [STUDIO_QUERY]) }).catch(() => {})
        return
    }
    finishClose()
}

function finishClose() {
    pushedHistory = false
    closeMatchStudio()
    const next = pendingLead || (returnToLeadId ? { id: returnToLeadId, focus: 'matches' } : null)
    pendingLead = null
    returnToLeadId = null
    if (next) nextTick(() => openLeadView(next.id, null, next.focus ? { focus: next.focus } : {}))
}

watch(() => route.query[STUDIO_QUERY], (value) => {
    if (!value) {
        if (matchStudioOpen.value) finishClose()
        return
    }
    if (matchStudioOpen.value) return
    if (/^\d+$/.test(String(value))) {
        // Refresh or a shared link: reopen that client's studio on top of this page.
        pushedHistory = false
        openMatchStudio({ id: Number(value) })
    } else {
        // A review queue can't be rebuilt from the URL; send the user to My Matches.
        router.replace({ query: withoutKeys(route.query, [STUDIO_QUERY]) }).catch(() => {})
    }
}, { immediate: true })

function viewLead(focus) {
    const id = lead.value?.id
    returnToLeadId = null
    pendingLead = id ? { id, focus: typeof focus === 'string' ? focus : null } : null
    close()
}

// The property popup is lifted above the studio while it's open (see the global style below).
function openListing(id) {
    openPropertyDetails(id)
}

// ---------- layout frame ----------
// On desktop the studio fills the page area, so the sidebar and top bar stay visible around it.
const FRAME_MIN_WIDTH = 992
const frameStyle = ref({})
const framed = ref(false)
let frameObserver = null

function measureFrame() {
    const el = document.querySelector('.dashboard-main-router')
    if (!el || window.innerWidth < FRAME_MIN_WIDTH) {
        framed.value = false
        frameStyle.value = {}
        return
    }
    const r = el.getBoundingClientRect()
    // Profile, deck and shortlist need about 1000px side by side; narrower pages get the full screen.
    if (r.width < 1000) {
        framed.value = false
        frameStyle.value = {}
        return
    }
    framed.value = true
    frameStyle.value = {
        top: `${Math.max(8, Math.round(r.top))}px`,
        left: `${Math.round(r.left)}px`,
        right: `${Math.max(0, Math.round(window.innerWidth - r.right))}px`,
        bottom: '12px',
    }
}

function startFrame() {
    window.scrollTo(0, 0)
    nextTick(measureFrame)
    window.addEventListener('resize', measureFrame)
    const el = document.querySelector('.dashboard-main-router')
    if (el && 'ResizeObserver' in window) {
        frameObserver = new ResizeObserver(measureFrame)
        frameObserver.observe(el)
    }
}

function stopFrame() {
    window.removeEventListener('resize', measureFrame)
    frameObserver?.disconnect()
    frameObserver = null
}

let snackTimer = null
function showSnack(text, icon = 'lucide:check') {
    snack.value = { text, icon }
    window.clearTimeout(snackTimer)
    snackTimer = window.setTimeout(() => { snack.value = null }, 3400)
}

function cardTag(m) {
    if (m.bucket === 'price_drop' && m.previous_price && m.listing.price) {
        const pct = Math.round((1 - m.listing.price / m.previous_price) * 100)
        if (pct > 0) return `Price dropped ${pct}%`
    }
    const c = String(m.listing.completion_status || '').toLowerCase()
    if (c.includes('off') || c.includes('under')) return 'Off-plan'
    if (c.includes('ready') || c.includes('complet')) return 'Ready to move'
    const s = String(m.listing.listing_status || '').toLowerCase()
    return s.includes('rent') ? 'For rent' : ''
}

function specs(l) {
    return [l.bedrooms_label, l.property_type, l.size_sqft ? `${Number(l.size_sqft).toLocaleString('en-US')} sqft` : null]
        .filter(Boolean)
        .join(' · ')
}

function money(n) {
    if (n == null || n === '') return '—'
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 })
}

function shortPrice(n) {
    const v = Number(n)
    if (!v) return '—'
    if (v >= 1e6) return `AED ${(v / 1e6).toFixed(2).replace(/\.?0+$/, '')}M`
    if (v >= 1e3) return `AED ${Math.round(v / 1e3).toLocaleString('en-US')}K`
    return `AED ${v}`
}

function plural(n, one, many) {
    return `${n} ${n === 1 ? one : many || `${one}s`}`
}

function burstDot(n) {
    const a = (n / 14) * Math.PI * 2
    return { '--x': `${Math.cos(a) * 70}px`, '--y': `${Math.sin(a) * 70}px` }
}

function onKeydown(e) {
    if (!matchStudioOpen.value) return
    // The property popup on top handles its own keys (Escape closes it, not the studio).
    if (document.body.classList.contains('property-details-modal-open')) return
    if (e.key === 'Escape') {
        e.preventDefault()
        e.stopPropagation()
        if (sheetOpen.value) sheetOpen.value = false
        else close()
        return
    }
    if (sheetOpen.value) return
    const tag = e.target?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA') return
    if (e.key === 'ArrowRight') { e.preventDefault(); decide(true) }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); decide(false) }
    else if (e.key === 'z' || e.key === 'Z') { e.preventDefault(); undo() }
}

onMounted(() => window.addEventListener('keydown', onKeydown, true))
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown, true)
    window.clearTimeout(snackTimer)
    stopFrame()
    document.body.style.overflow = ''
    document.body.classList.remove('match-studio-open')
})
</script>

<style scoped>
/* One committed dark plum stage in every theme; brand purple carries, gold marks a match. */
.ms {
    --ms-bg: #170c1c;
    --ms-panel: rgba(255, 255, 255, 0.05);
    --ms-line: rgba(255, 255, 255, 0.1);
    --ms-ink: #f6eff9;
    --ms-muted: #bba9c7;
    --ms-ok: #86efac;
    --ms-ok-bg: rgba(74, 222, 128, 0.14);
    --ms-warn: #fcd34d;
    --ms-warn-bg: rgba(251, 191, 36, 0.15);
    --ms-no: #fda4af;
    --ms-gold: #f3d77a;
    --ms-fill: var(--gradient-primary, linear-gradient(135deg, rgba(255, 255, 255, 0.16), rgba(212, 175, 55, 0.38)), #733e87);

    position: fixed;
    inset: 0;
    z-index: 12600;
    display: flex;
    flex-direction: column;
    overflow: auto;
    color: var(--ms-ink);
    background:
        radial-gradient(60% 50% at 50% 0%, rgba(143, 90, 163, 0.45) 0%, rgba(23, 12, 28, 0) 70%),
        radial-gradient(40% 40% at 90% 100%, rgba(212, 175, 55, 0.16) 0%, rgba(23, 12, 28, 0) 70%),
        var(--ms-bg);
    padding: env(safe-area-inset-top, 0px) max(16px, env(safe-area-inset-right, 0px)) env(safe-area-inset-bottom, 0px) max(16px, env(safe-area-inset-left, 0px));
    font-size: 14px;
    line-height: 1.5;
}
.ms button { font: inherit; color: inherit; cursor: pointer; }
.ms button:disabled { cursor: default; }
.ms button:focus-visible { outline: 2px solid var(--ms-gold); outline-offset: 2px; }
.min-w-0 { min-width: 0; }

.ms-fade-enter-active, .ms-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.ms-fade-enter-from, .ms-fade-leave-to { opacity: 0; transform: scale(1.02); }

/* Desktop: sits inside the page area (inline top/left/right from measureFrame) like a stage in the layout. */
.ms--framed {
    border-radius: 22px;
    border: 1px solid rgba(212, 175, 55, 0.25);
    box-shadow: 0 30px 70px rgba(23, 12, 28, 0.35);
    padding: 0 22px;
}

.ms-head { display: flex; align-items: center; gap: 12px; padding: 16px 0 12px; flex-wrap: wrap; }

/* The "Match Studio" label: a gold-edged pill with a light sweep and gold text, easy to spot. */
.ms-title {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px 6px 6px;
    overflow: hidden;
    border-radius: 999px;
    border: 1px solid rgba(243, 215, 122, 0.55);
    background: linear-gradient(180deg, rgba(243, 215, 122, 0.16), rgba(243, 215, 122, 0.04));
    box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.08), 0 0 26px rgba(212, 175, 55, 0.28), 0 3px 0 rgba(0, 0, 0, 0.35);
    font-weight: 800;
    font-size: 18px !important;
    letter-spacing: 0.01em;
}
.ms-title::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.28) 48%, transparent 66%);
    background-size: 250% 100%;
    animation: ms-title-shine 3.4s ease-in-out infinite;
    pointer-events: none;
}
@keyframes ms-title-shine { 0% { background-position: 130% 0; } 55%, 100% { background-position: -130% 0; } }
.ms-title__text {
    background: linear-gradient(90deg, #fff6d6 0%, #f3d77a 45%, #ffffff 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.ms-title__mark {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--ms-fill);
    display: grid;
    place-items: center;
    color: #fff;
    box-shadow: 0 0 14px rgba(243, 215, 122, 0.55);
    animation: ms-title-twinkle 2.4s ease-in-out infinite;
}
@keyframes ms-title-twinkle { 0%, 100% { transform: scale(1) rotate(0); } 50% { transform: scale(1.1) rotate(12deg); } }
.ms-sub { color: var(--ms-muted); font-size: 12.5px; }
.ms-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
.ms-btn {
    border: 1px solid var(--ms-line);
    background: var(--ms-panel);
    border-radius: 12px;
    padding: 8px 12px;
    display: inline-flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    font-size: 12.5px;
}
.ms-btn:hover { background: rgba(255, 255, 255, 0.09); }
.ms-btn kbd { font-family: inherit; font-size: 10.5px; color: var(--ms-muted); background: none; border: 1px solid var(--ms-line); border-radius: 5px; padding: 0 5px; }
.ms-cta {
    border: 0;
    border-radius: 14px;
    padding: 12px 20px;
    font-weight: 700;
    font-size: 14px;
    background: var(--ms-fill);
    color: #fff;
    box-shadow: 0 10px 24px rgba(115, 62, 135, 0.35);
}
.ms-cta:hover:not(:disabled) { filter: brightness(1.08); }
.ms-cta:disabled { opacity: 0.6; }
.ms-cta--grow { flex: 1; }

.ms-qbar { display: flex; align-items: center; gap: 12px; padding-bottom: 14px; flex-wrap: wrap; }
.ms-qbar__seg { display: flex; gap: 5px; flex: 1 1 200px; }
.ms-qbar__seg i { flex: 1; height: 5px; border-radius: 5px; background: var(--ms-line); position: relative; overflow: hidden; }
.ms-qbar__seg i.is-done { background: var(--ms-ok); }
.ms-qbar__seg i.is-skipped { background: var(--ms-muted); }
.ms-qbar__seg i.is-current { background: rgba(212, 175, 55, 0.35); }
.ms-qbar__seg i.is-current::after { content: ''; position: absolute; inset: 0; width: 40%; border-radius: 5px; background: var(--color-primary-glow, #d4af37); animation: ms-slide 1.6s ease-in-out infinite; }
@keyframes ms-slide { from { transform: translateX(-100%); } to { transform: translateX(260%); } }
.ms-qbar__label { font-size: 12.5px; color: var(--ms-muted); }
.ms-qbar__label b { color: var(--ms-ink); }

.ms-main {
    flex: 1;
    display: grid;
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr) minmax(250px, 300px);
    gap: 28px;
    padding-bottom: 24px;
    min-height: 0;
}
.ms-panel { background: var(--ms-panel); border: 1px solid var(--ms-line); border-radius: 20px; padding: 18px; align-self: start; }

.ms-client { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
.ms-client b { display: block; font-size: 15px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ms-client small { color: var(--ms-muted); font-size: 12px; }
.ms-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--ms-fill); color: #fff; display: grid; place-items: center; font-weight: 800; flex: none; }
.ms-avatar--sm { width: 40px; height: 40px; font-size: 13px; }
.ms-why { font-size: 12px; color: var(--ms-gold); background: rgba(212, 175, 55, 0.1); border-radius: 10px; padding: 7px 10px; margin-bottom: 8px; }
.ms-dna__row { display: flex; justify-content: space-between; gap: 10px; font-size: 12.5px; padding: 9px 0; border-top: 1px solid var(--ms-line); }
.ms-dna__row span { color: var(--ms-muted); }
.ms-dna__row b { text-align: right; font-weight: 600; }
.ms-learn { margin-top: 14px; font-size: 12px; color: var(--ms-muted); background: rgba(212, 175, 55, 0.08); border: 1px solid rgba(212, 175, 55, 0.25); border-radius: 12px; padding: 10px 12px; }
.ms-learn b { color: var(--ms-gold); font-weight: 600; }
.ms-skeleton { display: grid; gap: 10px; }
.ms-skeleton span { height: 14px; border-radius: 6px; background: var(--ms-line); animation: ms-pulse 1.2s ease-in-out infinite; }
.ms-skeleton span:first-child { height: 46px; width: 60%; }
@keyframes ms-pulse { 50% { opacity: 0.4; } }

.ms-center { display: flex; flex-direction: column; align-items: center; gap: 18px; min-width: 0; }
.ms-deck { position: relative; width: min(380px, 100%); height: 520px; }

.ms-card {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    border-radius: 24px;
    overflow: hidden;
    background: #24152b;
    border: 1px solid var(--ms-line);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45);
    touch-action: none;
    user-select: none;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.ms-card.is-top { cursor: grab; }
.ms-card.is-dragging { transition: none; cursor: grabbing; }
.ms-card__photo { position: relative; flex: none; height: 270px; }
.ms-card__photo::after { content: ''; position: absolute; inset: auto 0 0; height: 90px; background: linear-gradient(0deg, #24152b, rgba(36, 21, 43, 0)); pointer-events: none; }
.ms-card__ring { position: absolute; top: 14px; right: 14px; border-radius: 50%; background: rgba(23, 12, 28, 0.65); backdrop-filter: blur(6px); }
.ms-card__tag { position: absolute; top: 16px; left: 16px; z-index: 1; font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 5px 10px; border-radius: 999px; background: rgba(23, 12, 28, 0.65); backdrop-filter: blur(6px); }
.ms-stamp { position: absolute; top: 90px; z-index: 2; font-weight: 800; font-size: 26px; letter-spacing: 0.08em; padding: 4px 14px; border: 3px solid; border-radius: 12px; pointer-events: none; }
.ms-stamp--yes { left: 22px; color: var(--ms-gold); border-color: var(--ms-gold); transform: rotate(-14deg); }
.ms-stamp--no { right: 22px; color: var(--ms-no); border-color: var(--ms-no); transform: rotate(14deg); }
.ms-card__body { position: relative; z-index: 1; display: grid; gap: 6px; padding: 0 20px 18px; margin-top: -30px; }
.ms-card__loc { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; color: var(--ms-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ms-card__price { font-size: 24px; font-weight: 800; font-variant-numeric: tabular-nums; }
.ms-card__price small { font-size: 13px; font-weight: 600; color: var(--ms-muted); margin-right: 4px; }
.ms-card__price s { font-size: 13px; font-weight: 600; color: var(--ms-muted); margin-left: 8px; }
.ms-card__specs { font-size: 12.5px; color: var(--ms-muted); font-variant-numeric: tabular-nums; }
.ms-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.ms-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; padding: 4px 9px; border-radius: 8px; }
.ms-chip--ok { background: var(--ms-ok-bg); color: var(--ms-ok); }
.ms-chip--gap { background: var(--ms-warn-bg); color: var(--ms-warn); }
.ms-card__open { justify-self: start; margin-top: 4px; border: 0; background: none; padding: 0; font-size: 12px; font-weight: 600; color: var(--ms-gold); text-decoration: underline; text-underline-offset: 3px; }

.ms-pane {
    position: absolute;
    inset: 0;
    display: grid;
    align-content: center;
    justify-items: center;
    gap: 12px;
    padding: 24px;
    text-align: center;
    border-radius: 24px;
    border: 1px solid var(--ms-line);
    background: rgba(255, 255, 255, 0.03);
    animation: ms-in 0.4s ease-out;
}
@keyframes ms-in { from { opacity: 0; transform: translateY(-10px) scale(0.97); } }
/* style.css forces h1–h6 sizes with !important, so the studio's headings need it too. */
.ms-pane h3 { margin: 0; font-size: 21px !important; font-weight: 800; color: var(--ms-ink); text-wrap: balance; }
.ms-pane p { margin: 0; max-width: 32ch; color: var(--ms-muted); }
.ms-spinner { width: 34px; height: 34px; border-radius: 50%; border: 3px solid var(--ms-line); border-top-color: var(--color-primary-glow, #d4af37); animation: ms-spin 0.9s linear infinite; }
@keyframes ms-spin { to { transform: rotate(360deg); } }
.ms-check { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; background: var(--ms-ok-bg); color: var(--ms-ok); animation: ms-pop 0.5s cubic-bezier(0.2, 0.9, 0.25, 1.4); }
@keyframes ms-pop { from { transform: scale(0.4); opacity: 0; } }
.ms-upnext { margin-top: 8px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ms-gold); }
.ms-next { width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px; text-align: left; border-radius: 16px; background: var(--ms-panel); border: 1px solid var(--ms-line); }
.ms-next b { display: block; font-size: 14px; }
.ms-next small { display: block; font-size: 12px; color: var(--ms-muted); }
.ms-stats { display: flex; gap: 22px; justify-content: center; }
.ms-stats b { display: block; font-size: 26px; font-weight: 800; color: var(--ms-gold); font-variant-numeric: tabular-nums; }
.ms-stats span { font-size: 11.5px; color: var(--ms-muted); }
.ms-burst { position: relative; width: 70px; height: 70px; display: grid; place-items: center; color: var(--ms-gold); animation: ms-pop 0.6s cubic-bezier(0.2, 0.9, 0.25, 1.4); }
.ms-burst i { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: var(--color-primary-glow, #d4af37); animation: ms-fly 1s ease-out forwards; }
@keyframes ms-fly { to { transform: translate(var(--x), var(--y)); opacity: 0; } }

.ms-controls { display: flex; align-items: center; gap: 16px; }
.ms-cbtn { width: 58px; height: 58px; border-radius: 50%; border: 1px solid var(--ms-line); background: var(--ms-panel); display: grid; place-items: center; transition: transform 0.15s; }
.ms-cbtn:hover:not(:disabled) { transform: scale(1.07); }
.ms-cbtn:disabled { opacity: 0.35; }
.ms-cbtn--skip { color: var(--ms-no) !important; }
.ms-cbtn--like { width: 70px; height: 70px; border-color: transparent; background: var(--ms-fill); color: #fff; box-shadow: 0 12px 30px rgba(212, 175, 55, 0.3); }
.ms-cbtn--undo { width: 44px; height: 44px; color: var(--ms-muted) !important; }
.ms-progress { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ms-muted); flex-wrap: wrap; justify-content: center; }
.ms-progress i { width: 22px; height: 4px; border-radius: 4px; background: var(--ms-line); }
.ms-progress i.done { background: var(--ms-muted); }
.ms-progress i.liked { background: var(--color-primary-glow, #d4af37); }
.ms-keys { font-size: 11.5px; color: var(--ms-muted); }

.ms-tray h3 { display: flex; justify-content: space-between; align-items: center; margin: 0 0 4px; font-size: 15px !important; font-weight: 800; color: var(--ms-ink); }
.ms-tray h3 span { font-size: 12px !important; color: #3f2148; background: var(--color-primary-glow, #d4af37); border-radius: 999px; padding: 1px 9px; }
.ms-tray > p { margin: 0 0 12px; font-size: 12px; color: var(--ms-muted); }
.ms-slist { display: grid; gap: 8px; margin-bottom: 14px; }
.ms-sitem { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 14px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--ms-line); }
.ms-sitem__photo { width: 56px !important; height: 42px !important; border-radius: 9px; flex: none; }
.ms-sitem > div { flex: 1; }
.ms-sitem b { display: block; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ms-sitem small { font-size: 11.5px; color: var(--ms-muted); font-variant-numeric: tabular-nums; }
.ms-sitem button { border: 0; background: transparent; padding: 4px; border-radius: 8px; color: var(--ms-muted); }
.ms-drop-enter-active { animation: ms-drop 0.45s cubic-bezier(0.2, 0.9, 0.25, 1.3); }
@keyframes ms-drop { from { transform: translateY(-18px) scale(0.9); opacity: 0; } }
.ms-sempty { margin-bottom: 14px; padding: 18px 12px; text-align: center; font-size: 12px; color: var(--ms-muted); border: 1px dashed var(--ms-line); border-radius: 14px; }
.ms-send { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 14px; padding: 13px 14px; font-weight: 700; font-size: 13.5px; background: #25a35a; color: #fff !important; box-shadow: 0 10px 24px rgba(37, 163, 90, 0.3); }
.ms-send:disabled { background: rgba(255, 255, 255, 0.08); color: var(--ms-muted) !important; box-shadow: none; }

.ms-sheet-bg { position: fixed; inset: 0; z-index: 12610; display: grid; place-items: center; padding: 16px; background: rgba(10, 5, 12, 0.6); backdrop-filter: blur(4px); }
.ms-sheet { width: min(440px, 100%); max-height: calc(100% - 32px); overflow: auto; display: grid; gap: 14px; padding: 18px; border-radius: 22px; background: #21132a; border: 1px solid var(--ms-line); animation: ms-in 0.35s ease-out; }
.ms-sheet h3 { margin: 0; font-size: 16px !important; font-weight: 800; color: var(--ms-ink); }
.ms-sheet__to { font-size: 12px; color: var(--ms-muted); }
.ms-sheet__note { margin: 0; font-size: 11.5px; color: var(--ms-muted); }
.ms-chat { padding: 14px; border-radius: 16px; background: #0f1f17; background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px); background-size: 14px 14px; }
.ms-bubble { margin-left: 24px; padding: 10px 12px; border-radius: 14px 14px 4px 14px; background: #1f5a3c; color: #eafff2; font-size: 13px; white-space: pre-wrap; overflow-wrap: anywhere; }
.ms-bubble__thumbs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.ms-bubble__thumb { width: 70px !important; height: 52px !important; border-radius: 8px; }
.ms-sheet__actions { display: flex; gap: 8px; flex-wrap: wrap; }

.ms-snack { position: fixed; z-index: 12620; left: 50%; bottom: calc(24px + env(safe-area-inset-bottom, 0px)); transform: translateX(-50%); display: flex; align-items: center; gap: 8px; width: max-content; max-width: calc(100vw - 32px); padding: 10px 16px; border-radius: 14px; font-size: 12.5px; background: #2b1b33; border: 1px solid var(--ms-line); box-shadow: 0 14px 30px rgba(0, 0, 0, 0.4); }
.ms-snack-enter-active, .ms-snack-leave-active { transition: opacity 0.25s, transform 0.25s; }
.ms-snack-enter-from, .ms-snack-leave-to { opacity: 0; transform: translate(-50%, 12px); }

@media (max-width: 960px) {
    .ms-main { grid-template-columns: minmax(0, 1fr); gap: 16px; }
    .ms-center { order: 1; }
    .ms-tray { order: 2; }
    .ms-panel { align-self: stretch; }
    .ms-dna { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); column-gap: 14px; }
}
@media (max-width: 640px) {
    .ms-deck { height: 490px; }
    .ms-card__photo { height: 240px; }
    .ms-sub, .ms-keys, .ms-btn kbd { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    .ms *, .ms *::before, .ms *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
}
</style>

<style>
/* "View listing" opens the shared property popup; lift it above the studio while the studio is open. */
body.match-studio-open .pdm-backdrop {
    z-index: 12700 !important;
}
</style>
