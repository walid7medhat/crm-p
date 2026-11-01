<template>
    <div class="mm-page">
        <div class="mm-wrap">
            <div
                class="mm-seg"
                :class="{ 'is-team': view === 'team', 'is-single': !canViewTeam }"
                role="tablist"
                aria-label="Matches view"
            >
                <span class="mm-seg__thumb" aria-hidden="true"></span>
                <button type="button" role="tab" :class="{ on: view === 'mine' }" :aria-selected="view === 'mine'" @click="setView('mine')">
                    <iconify-icon icon="lucide:sparkles" width="15" height="15" />
                    My Matches
                    <span v-if="clients.length" class="mm-seg__count">{{ clients.length }}</span>
                </button>
                <button v-if="canViewTeam" type="button" role="tab" :class="{ on: view === 'team' }" :aria-selected="view === 'team'" @click="setView('team')">
                    <iconify-icon icon="lucide:trophy" width="15" height="15" />
                    Team
                </button>
            </div>

            <!-- ============ My Matches ============ -->
            <template v-if="view === 'mine'">
                <section class="mm-hero">
                    <div class="mm-hero__text">
                        <div class="mm-kicker"><iconify-icon icon="lucide:sparkles" width="12" height="12" />{{ today }}</div>
                        <h1 v-if="loading && !queue">Finding homes for your clients…</h1>
                        <h1 v-else-if="clients.length">{{ greeting }}, {{ firstName }}. {{ plural(clients.length, 'client has', 'clients have') }} homes waiting.</h1>
                        <h1 v-else>All clear for today, {{ firstName }}.</h1>
                        <p v-if="clients.length">Review them one after another. It takes about {{ summary.minutes }} minutes.</p>
                        <p v-else-if="!loading">Every client with a match has been handled. New matches show up here as listings and leads change.</p>
                        <div class="mm-stats">
                            <div><b>{{ summary.homes_waiting || 0 }}</b><span>homes to review</span></div>
                            <div><b>{{ summary.clients_waiting || 0 }}</b><span>clients waiting</span></div>
                            <div><b>{{ summary.sent_today || 0 }}</b><span>sent today</span></div>
                            <div v-if="summary.best_score"><b>{{ summary.best_score }}%</b><span>best match</span></div>
                        </div>
                    </div>
                    <div class="mm-hero__cta">
                        <button type="button" class="mm-start" :disabled="!clients.length" @click="startReview(0)">
                            <iconify-icon icon="lucide:play" width="16" height="16" />
                            {{ clients.length ? `Start review · ${plural(clients.length, 'client')}` : 'Nothing waiting' }}
                        </button>
                        <small>Only your own leads are shown</small>
                    </div>
                </section>

                <div v-if="error" class="mm-error">
                    {{ error }} <button type="button" @click="loadQueue">Try again</button>
                </div>

                <section v-for="bucket in buckets" :key="bucket.key" class="mm-bucket">
                    <div class="mm-bucket__head">
                        <span class="mm-bi" :class="`mm-bi--${bucket.key}`"><iconify-icon :icon="bucket.icon" width="14" height="14" /></span>
                        {{ bucket.title }}
                        <small>· {{ bucket.note }}</small>
                    </div>
                    <div class="mm-list">
                        <button
                            v-for="c in bucket.clients"
                            :key="c.lead.id"
                            type="button"
                            class="mm-row"
                            @click="openOne(c)"
                        >
                            <span class="mm-avatar">{{ c.lead.initials }}</span>
                            <span class="mm-row__text">
                                <b>{{ c.lead.name }}</b>
                                <small>{{ c.reason }}</small>
                            </span>
                            <span class="mm-thumbs" aria-hidden="true">
                                <MatchPhoto v-for="(t, i) in c.thumbs" :key="i" class="mm-thumb" :src="t" :seed="c.lead.id + i" />
                            </span>
                            <span class="mm-row__end">
                                <span class="mm-pct">{{ c.best_score }}%</span>
                                <span class="mm-st mm-st--wait">{{ plural(c.matches_count, 'match', 'matches') }}</span>
                            </span>
                        </button>
                    </div>
                </section>

                <section v-if="sentToday.length" class="mm-bucket">
                    <div class="mm-bucket__head">
                        <span class="mm-bi mm-bi--sent"><iconify-icon icon="lucide:check" width="14" height="14" /></span>
                        Sent today
                    </div>
                    <div class="mm-list">
                        <button v-for="s in sentToday" :key="s.lead_id" type="button" class="mm-row is-sent" @click="openLead(s.lead_id)">
                            <span class="mm-avatar">{{ s.initials }}</span>
                            <span class="mm-row__text"><b>{{ s.name }}</b><small>Logged on the lead's timeline</small></span>
                            <span></span>
                            <span class="mm-row__end"><span class="mm-st mm-st--sent">Sent {{ plural(s.homes, 'home') }}</span></span>
                        </button>
                    </div>
                </section>
            </template>

            <!-- ============ Team ============ -->
            <template v-else>
                <div class="mm-team-head">
                    <div>
                        <h1>Match leaders this week</h1>
                        <p>Top {{ team.agents.length || 5 }} agents, ranked by clients reached with matches in the last 7 days</p>
                    </div>
                </div>
                <div v-if="teamLoading && !team.agents.length" class="mm-board mm-board--loading">Loading the team…</div>
                <div v-else-if="teamError" class="mm-error">{{ teamError }} <button type="button" @click="loadTeam">Try again</button></div>
                <section v-else class="mm-board">
                    <div class="mm-board__cols" aria-hidden="true">
                        <span>#</span><span></span><span>Agent</span><span>Waiting</span><span>Sent</span><span>Reached</span><span></span>
                    </div>
                    <div v-if="!team.agents.length" class="mm-board__empty">No matches in your team yet. They appear as leads and listings come in.</div>
                    <div v-for="(a, i) in team.agents" :key="a.id" class="mm-arow" :class="{ open: openAgent === a.id }">
                        <button type="button" class="mm-arow__main" :aria-expanded="openAgent === a.id" @click="openAgent = openAgent === a.id ? null : a.id">
                            <span class="mm-rank" :class="{ 'is-first': i === 0 }">{{ i + 1 }}</span>
                            <span class="mm-avatar">{{ a.initials }}</span>
                            <span class="mm-aname">
                                <b>{{ a.name }} <span v-if="a.is_you" class="mm-you">You</span></b>
                                <small>{{ plural(a.waiting_clients, 'client') }} with homes waiting</small>
                            </span>
                            <span class="mm-metric" :class="{ hot: a.waiting_homes >= 5 }"><b>{{ a.waiting_homes }}</b><span>waiting</span></span>
                            <span class="mm-metric"><b>{{ a.sent_homes }}</b><span>sent</span></span>
                            <span class="mm-metric"><b>{{ a.reached_clients }}</b><span>reached</span></span>
                            <iconify-icon icon="lucide:chevron-down" class="mm-chev" width="16" height="16" />
                        </button>
                        <div v-if="openAgent === a.id" class="mm-arow__leads">
                            <button v-for="l in a.leads" :key="l.lead_id" type="button" class="mm-lead-chip" @click="openLead(l.lead_id)">
                                <span class="mm-avatar mm-avatar--sm">{{ l.initials }}</span>
                                <b>{{ l.name }}</b>
                                <small>{{ plural(l.homes, 'match', 'matches') }} waiting</small>
                                <span class="mm-pct">{{ l.best }}%</span>
                            </button>
                            <div v-if="!a.leads.length" class="mm-lead-chip is-empty">No matches waiting. Everything has been sent.</div>
                        </div>
                    </div>
                    <div v-if="team.unsent_alert.length" class="mm-board__foot">
                        <iconify-icon icon="lucide:alert-circle" width="14" height="14" />
                        <span>
                            <b>{{ team.unsent_alert.map((a) => a.name.split(' ')[0]).join(', ') }}</b>
                            {{ team.unsent_alert.length === 1 ? 'has' : 'have' }}
                            {{ team.unsent_alert.reduce((s, a) => s + a.homes, 0) }} matches nobody has sent yet.
                        </span>
                    </div>
                </section>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import MatchPhoto from '@/components/matches/MatchPhoto.vue'
import { matchStudioVersion, openMatchStudio, startMatchQueue } from '@/composables/useMatchStudio.js'
import { openLeadView } from '@/composables/useLeadViewModal.js'

const route = useRoute()
const router = useRouter()

const me = (() => {
    try { return JSON.parse(localStorage.getItem('user') || 'null') } catch { return null }
})()
const firstName = (me?.name || '').split(/\s+/)[0] || 'there'

/** /matches/mine or /matches/team, so the header tabs and this switch stay in step. */
const view = computed(() => (route.params.view === 'team' ? 'team' : 'mine'))
const loading = ref(false)
const error = ref('')
const queue = ref(null)
const teamLoading = ref(false)
const teamError = ref('')
const team = ref({ agents: [], unsent_alert: [] })
const openAgent = ref(null)

const summary = computed(() => queue.value?.summary || {})
const clients = computed(() => queue.value?.clients || [])
const sentToday = computed(() => queue.value?.sent_today || [])
const canViewTeam = computed(() => !!queue.value?.can_view_team)

const BUCKETS = [
    { key: 'price_drop', title: 'Price drops', note: 'a home moved into budget', icon: 'lucide:trending-down' },
    { key: 'new', title: 'New matches', note: 'fresh leads and new stock', icon: 'lucide:sparkles' },
    { key: 'revive', title: 'Revive quiet clients', note: 'no contact for 90+ days', icon: 'lucide:snowflake' },
]
const buckets = computed(() => BUCKETS
    .map((b) => ({ ...b, clients: clients.value.filter((c) => c.bucket === b.key) }))
    .filter((b) => b.clients.length))

const today = new Date().toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' })
const greeting = (() => {
    const h = new Date().getHours()
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'
})()

async function loadQueue() {
    loading.value = true
    error.value = ''
    try {
        const res = await api.get('/lead-matches')
        queue.value = res.data?.data || null
        if (!queue.value?.can_view_team && view.value === 'team') router.replace('/matches/mine')
    } catch {
        error.value = "Couldn't load your matches."
    } finally {
        loading.value = false
    }
}

async function loadTeam() {
    teamLoading.value = true
    teamError.value = ''
    try {
        const res = await api.get('/lead-matches/team', { params: { limit: 5 } })
        const data = res.data?.data || {}
        team.value = { agents: data.agents || [], unsent_alert: data.unsent_alert || [] }
        const mine = team.value.agents.find((a) => a.is_you)
        if (openAgent.value === null && mine) openAgent.value = mine.id
    } catch (e) {
        teamError.value = e.response?.status === 403 ? 'Only managers can see the team board.' : "Couldn't load the team board."
    } finally {
        teamLoading.value = false
    }
}

function setView(v) {
    if (v !== view.value) router.push(`/matches/${v}`)
}

watch(view, (v) => {
    if (v === 'team') loadTeam()
})

function toStudioClient(c) {
    return {
        id: c.lead.id,
        name: c.lead.name,
        first_name: c.lead.first_name,
        initials: c.lead.initials,
        matches_count: c.matches_count,
        reason: c.reason,
    }
}

/** Review order follows the page: price drops, then new matches, then quiet clients. */
function startReview(startIndex) {
    const ordered = buckets.value.flatMap((b) => b.clients)
    startMatchQueue(ordered.map(toStudioClient), startIndex)
}

function openOne(c) {
    openMatchStudio(toStudioClient(c))
}

function openLead(leadId) {
    openLeadView(leadId, null, { focus: 'matches' })
}

function plural(n, one, many) {
    return `${n} ${n === 1 ? one : many || `${one}s`}`
}

watch(matchStudioVersion, () => {
    loadQueue()
    if (view.value === 'team') loadTeam()
})

onMounted(() => {
    loadQueue()
    if (view.value === 'team') loadTeam()
})
</script>

<style scoped>
.mm-page {
    padding: 20px max(16px, env(safe-area-inset-right, 0px)) 48px max(16px, env(safe-area-inset-left, 0px));
}
.mm-wrap { max-width: 880px; margin: 0 auto; }

/* 3D switch: a pressed-in track with a raised gold-edged thumb that slides between tabs. */
.mm-seg {
    position: relative;
    display: inline-grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    padding: 5px;
    margin-bottom: 18px;
    border-radius: 18px;
    background: linear-gradient(180deg, #ebe4f0 0%, #f7f3fa 100%);
    box-shadow:
        inset 0 2px 5px rgba(63, 33, 72, 0.18),
        inset 0 -1px 0 rgba(255, 255, 255, 0.9),
        0 1px 0 rgba(255, 255, 255, 0.8);
    perspective: 600px;
    animation: mm-seg-in 0.6s cubic-bezier(0.2, 0.9, 0.25, 1.2) both;
}
@keyframes mm-seg-in { from { opacity: 0; transform: translateY(-8px) rotateX(25deg); } }
.mm-seg__thumb {
    position: absolute;
    top: 5px;
    bottom: 5px;
    left: 5px;
    width: calc(50% - 5px);
    border-radius: 13px;
    background: var(--gradient-primary);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.35) inset,
        0 -3px 0 rgba(0, 0, 0, 0.18) inset,
        0 4px 0 #4a285c,
        0 10px 18px rgba(var(--color-primary-rgb), 0.35);
    transition: transform 0.45s cubic-bezier(0.3, 1.4, 0.5, 1);
}
.mm-seg.is-single .mm-seg__thumb { width: calc(100% - 10px); }
.mm-seg.is-team .mm-seg__thumb { transform: translateX(100%); }
.mm-seg__thumb::after {
    /* light sweeping across the raised thumb */
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, 0.35) 48%, transparent 66%);
    background-size: 250% 100%;
    animation: mm-shine 3.6s ease-in-out infinite;
}
@keyframes mm-shine { 0% { background-position: 130% 0; } 60%, 100% { background-position: -130% 0; } }
.mm-seg button {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 150px;
    padding: 11px 20px 13px;
    border: 0;
    border-radius: 13px;
    background: transparent;
    font-size: 14px !important;
    font-weight: 700;
    letter-spacing: 0.01em;
    color: var(--color-text-nav);
    transition: color 0.3s, transform 0.2s;
}
.mm-seg button:hover:not(.on) { color: var(--color-primary); transform: translateY(-1px); }
.mm-seg button.on { color: var(--color-on-primary); text-shadow: 0 1px 1px rgba(0, 0, 0, 0.25); }
.mm-seg button:active { transform: translateY(2px); }
.mm-seg__count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: var(--color-primary-glow);
    color: var(--color-primary-ink);
    font-size: 11px !important;
    font-weight: 800;
    line-height: 20px;
    text-shadow: none;
    box-shadow: 0 2px 0 #a8862a;
    animation: mm-count-pulse 2.2s ease-in-out infinite;
}
@keyframes mm-count-pulse {
    0%, 100% { box-shadow: 0 2px 0 #a8862a, 0 0 0 0 rgba(212, 175, 55, 0.6); }
    50% { box-shadow: 0 2px 0 #a8862a, 0 0 0 6px rgba(212, 175, 55, 0); }
}

.mm-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px;
    padding: 22px;
    border-radius: 22px;
    border: 1px solid var(--color-border);
    background:
        radial-gradient(120% 140% at 0% 0%, rgba(var(--color-primary-rgb), 0.16) 0%, rgba(255, 255, 255, 0) 46%),
        linear-gradient(180deg, #f3fbf8 0%, #ffffff 100%);
    box-shadow: 0 18px 40px rgba(63, 33, 72, 0.1);
}
.mm-hero__text { flex: 1 1 300px; min-width: 0; }
.mm-kicker { display: flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8a6d12; }
/* style.css forces h1–h6 sizes with !important, so ours need it too. */
.mm-hero h1 { margin: 4px 0 6px; font-size: 24px !important; font-weight: 800; line-height: 1.25; color: var(--color-text-primary); text-wrap: balance; }
.mm-hero p { margin: 0; max-width: 48ch; font-size: 14px !important; color: var(--color-text-secondary); }
.mm-stats { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 16px; }
.mm-stats b { display: block; font-size: 22px; font-weight: 800; line-height: 1.1; color: var(--color-primary); font-variant-numeric: tabular-nums; }
.mm-stats span { font-size: 11.5px; color: var(--color-text-secondary); }
.mm-hero__cta { display: grid; justify-items: center; gap: 6px; }
.mm-hero__cta small { font-size: 11.5px; color: var(--color-text-muted); }
.mm-start {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 22px;
    border: 0;
    border-radius: 16px;
    font-size: 14.5px;
    font-weight: 700;
    background: var(--gradient-primary);
    color: var(--color-on-primary);
    box-shadow: 0 10px 22px rgba(var(--color-primary-rgb), 0.3);
}
.mm-start:hover:not(:disabled) { background: var(--gradient-primary-hover); }
.mm-start:disabled { opacity: 0.55; box-shadow: none; cursor: default; }

.mm-error { margin-top: 16px; padding: 12px 14px; border-radius: 12px; background: #fdecee; color: var(--color-danger); font-size: 13px; }
.mm-error button { border: 0; background: none; color: inherit; font-weight: 700; text-decoration: underline; }

.mm-bucket { margin-top: 26px; }
.mm-bucket__head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; font-weight: 700; color: var(--color-text-primary); }
.mm-bucket__head small { font-weight: 500; font-size: 12px; color: var(--color-text-secondary); }
.mm-bi { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; }
.mm-bi--new { background: var(--gradient-primary); color: #fff; }
.mm-bi--price_drop, .mm-bi--sent { background: var(--color-success-soft); color: var(--color-success); }
.mm-bi--revive { background: var(--color-primary-soft); border: 1px solid var(--color-primary-border); color: var(--color-primary); }

.mm-list { display: grid; gap: 8px; }
.mm-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 12px 14px;
    text-align: left;
    border-radius: 16px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}
.mm-row:hover { transform: translateY(-2px); border-color: var(--color-primary-border); box-shadow: 0 14px 30px rgba(63, 33, 72, 0.1); }
.mm-row.is-sent { opacity: 0.8; }
.mm-row__text b { display: block; font-size: 14px; color: var(--color-text-primary); }
.mm-row__text small { display: block; font-size: 12.5px; color: var(--color-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mm-row__end { display: grid; justify-items: end; gap: 4px; }
.mm-avatar {
    width: 36px;
    height: 36px;
    flex: none;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    background: var(--color-primary-soft);
    border: 1px solid var(--color-primary-border);
    color: var(--color-primary);
}
.mm-avatar--sm { width: 28px; height: 28px; font-size: 10.5px; }
.mm-thumbs { display: flex; align-items: center; }
.mm-thumb { width: 46px !important; height: 34px !important; margin-left: -10px; border-radius: 9px; border: 2px solid var(--color-surface); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12); }
.mm-thumb:first-child { margin-left: 0; }
.mm-pct { font-size: 15px; font-weight: 800; color: var(--color-primary); font-variant-numeric: tabular-nums; }
.mm-st { font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
.mm-st--wait { background: #fbf3d9; color: #8a6d12; }
.mm-st--sent { background: var(--color-success-soft); color: var(--color-success); }

.mm-team-head { margin-bottom: 14px; }
.mm-team-head h1 { margin: 0; font-size: 22px !important; font-weight: 800; color: var(--color-text-primary); }
.mm-team-head p { margin: 2px 0 0; font-size: 13px !important; color: var(--color-text-secondary); }
.mm-board { overflow: hidden; border-radius: 20px; border: 1px solid var(--color-border); background: var(--color-surface); box-shadow: 0 18px 40px rgba(63, 33, 72, 0.08); }
.mm-board--loading, .mm-board__empty { padding: 24px; color: var(--color-text-secondary); text-align: center; }
.mm-board__cols,
.mm-arow__main {
    display: grid;
    grid-template-columns: 28px auto minmax(0, 1fr) repeat(3, 72px) 20px;
    align-items: center;
    gap: 12px;
}
.mm-board__cols { padding: 10px 16px; border-bottom: 1px solid var(--color-border); font-size: 10.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--color-text-secondary); }
.mm-board__cols span:nth-child(n + 4) { text-align: right; }
.mm-arow { border-top: 1px solid var(--color-border); }
.mm-arow:first-of-type { border-top: 0; }
.mm-arow__main { width: 100%; padding: 14px 16px; border: 0; background: transparent; text-align: left; }
.mm-arow__main:hover { background: var(--color-primary-soft); }
.mm-rank { text-align: center; font-size: 16px; font-weight: 800; color: var(--color-text-muted); font-variant-numeric: tabular-nums; }
.mm-rank.is-first { color: #8a6d12; }
.mm-aname { min-width: 0; }
.mm-aname b { display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--color-text-primary); }
.mm-aname small { font-size: 12px; color: var(--color-text-secondary); }
.mm-you { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 1px 7px; border-radius: 999px; background: var(--color-primary-soft); border: 1px solid var(--color-primary-border); color: var(--color-primary); }
.mm-metric { text-align: right; }
.mm-metric b { display: block; font-size: 16px; font-weight: 800; color: var(--color-text-primary); font-variant-numeric: tabular-nums; }
.mm-metric span { font-size: 11px; color: var(--color-text-secondary); }
.mm-metric.hot b { color: #b77900; }
.mm-chev { color: var(--color-text-muted); transition: transform 0.2s; }
.mm-arow.open .mm-chev { transform: rotate(180deg); }
.mm-arow__leads { display: grid; gap: 8px; padding: 0 16px 16px 56px; }
.mm-lead-chip {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    width: 100%;
    padding: 8px 12px;
    text-align: left;
    border-radius: 12px;
    border: 1px solid var(--color-primary-border);
    background: var(--color-primary-soft);
    font-size: 13px;
    color: var(--color-text-primary);
}
.mm-lead-chip b { font-weight: 600; }
.mm-lead-chip small { color: var(--color-text-secondary); }
.mm-lead-chip .mm-pct { margin-left: auto; font-size: 13px; }
.mm-lead-chip.is-empty { color: var(--color-text-secondary); }
.mm-board__foot { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--color-border); background: var(--color-primary-soft); font-size: 12.5px; color: var(--color-text-secondary); }
.mm-board__foot b { color: #b77900; }

@media (max-width: 680px) {
    .mm-row { grid-template-columns: auto minmax(0, 1fr) auto; }
    .mm-thumbs { display: none; }
    .mm-hero__cta, .mm-start { width: 100%; justify-content: center; }
    .mm-board__cols { display: none; }
    .mm-arow__main { display: flex; flex-wrap: wrap; row-gap: 8px; }
    .mm-aname { flex: 1 1 140px; }
    .mm-arow__main .mm-metric { text-align: left; min-width: 64px; }
    .mm-arow__leads { padding-left: 16px; }
}
@media (max-width: 680px) {
    .mm-seg { display: grid; width: 100%; }
    .mm-seg button { min-width: 0; padding: 10px 12px 12px; }
    .mm-hero h1 { font-size: 20px !important; }
}
@media (prefers-reduced-motion: reduce) {
    .mm-row, .mm-chev, .mm-seg__thumb { transition: none; }
    .mm-seg, .mm-seg__thumb::after, .mm-seg__count { animation: none; }
}
</style>
