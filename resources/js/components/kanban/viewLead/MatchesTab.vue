<template>
    <div class="lead-matches">
        <div v-if="loading && !data" class="lm-skeleton" aria-label="Loading matches">
            <span></span><span></span><span></span>
        </div>

        <template v-else-if="data">
            <section v-if="!data.has_requirements" class="lm-empty">
                <iconify-icon icon="lucide:sparkles" class="lm-empty__icon" />
                <div>
                    <b>Add what {{ firstName }} wants to see matches</b>
                    <p>Fill in at least a budget, area or bedrooms. Matching homes from your stock show up here right away.</p>
                </div>
                <button type="button" class="lm-btn" @click="$emit('edit-requirements')">Add requirements</button>
            </section>

            <template v-else>
                <section>
                    <div class="lm-label"><span>What {{ firstName }} wants</span></div>
                    <div class="lm-wants">
                        <span v-for="row in data.lead?.requirements || []" :key="row.label" class="lm-want">
                            <small>{{ row.label }}</small>{{ row.value }}
                        </span>
                    </div>
                </section>

                <section v-if="matches.length">
                    <div class="lm-label">
                        <span>{{ matches.length }} of your listings fit</span>
                        <span class="lm-label__aside">Sorted by match</span>
                    </div>
                    <div class="lm-strip">
                        <button
                            v-for="m in visibleMatches"
                            :key="m.id"
                            type="button"
                            class="lm-mini"
                            :class="{ 'is-shortlisted': m.status === 'shortlisted' }"
                            @click="openStudio"
                        >
                            <div class="lm-mini__photo">
                                <MatchPhoto :src="m.listing.main_image" :seed="m.listing.id" />
                                <span class="lm-mini__pct">{{ m.score }}%</span>
                                <span v-if="m.status === 'shortlisted'" class="lm-mini__flag">
                                    <iconify-icon icon="lucide:heart" width="11" height="11" />
                                </span>
                                <span v-else-if="m.bucket === 'price_drop'" class="lm-mini__flag lm-mini__flag--drop">
                                    <iconify-icon icon="lucide:arrow-down" width="11" height="11" />
                                </span>
                            </div>
                            <div class="lm-mini__info">
                                <b>{{ m.listing.title }}</b>
                                <small>{{ shortPrice(m.listing.price) }}<template v-if="m.listing.bedrooms_label"> · {{ m.listing.bedrooms_label }}</template></small>
                            </div>
                        </button>
                        <button v-if="hiddenCount" type="button" class="lm-mini lm-more" @click="openStudio">
                            <span class="lm-more__stack" aria-hidden="true">
                                <MatchPhoto v-for="m in hiddenPreview" :key="m.id" class="lm-more__thumb" :src="m.listing.main_image" :seed="m.listing.id" />
                            </span>
                            <b>+{{ hiddenCount }} more</b>
                            <small>See them all in Match Studio</small>
                        </button>
                    </div>
                </section>

                <section v-if="matches.length" class="lm-cta">
                    <p>
                        <b>Pick the best ones in Match Studio</b>
                        Swipe through the matches, shortlist the good ones, and send them to {{ firstName }} on WhatsApp in one message.
                    </p>
                    <button type="button" class="lm-btn lm-btn--primary" @click="openStudio">
                        <iconify-icon icon="lucide:sparkles" width="14" height="14" />
                        Open Match Studio
                    </button>
                </section>

                <section v-else class="lm-empty">
                    <iconify-icon icon="lucide:search-check" class="lm-empty__icon" />
                    <div>
                        <b>No homes fit {{ firstName }} right now</b>
                        <p>When a listing goes live or drops into budget, it shows up here and you get a notification.</p>
                    </div>
                </section>

                <section v-if="data.history?.length">
                    <div class="lm-label"><span>Already handled</span></div>
                    <ul class="lm-history">
                        <li v-for="m in data.history" :key="m.id">
                            <span class="lm-status" :class="`lm-status--${m.status}`">{{ m.status === 'sent' ? 'Sent' : 'Skipped' }}</span>
                            <span class="lm-history__name">{{ m.listing.title }}<template v-if="m.listing.area">, {{ m.listing.area }}</template></span>
                            <span class="lm-history__meta">{{ shortPrice(m.listing.price) }} · {{ m.score }}%<template v-if="m.sent_at"> · {{ formatDate(m.sent_at) }}</template></span>
                        </li>
                    </ul>
                </section>
            </template>
        </template>

        <section v-else class="lm-empty">
            <div>
                <b>Couldn't load matches</b>
                <p>Check your connection and try again.</p>
            </div>
            <button type="button" class="lm-btn" @click="$emit('refresh')">Try again</button>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import MatchPhoto from '@/components/matches/MatchPhoto.vue'
import { openMatchStudio } from '@/composables/useMatchStudio.js'

const props = defineProps({
    lead: { type: Object, required: true },
    data: { type: Object, default: null },
    loading: { type: Boolean, default: false },
})
defineEmits(['refresh', 'edit-requirements'])

const firstName = computed(() => props.data?.lead?.first_name || props.lead?.first_name || 'this client')
const matches = computed(() => props.data?.matches || [])

/** Six tiles at most: the best five plus a "+N more" tile when there are more than six. */
const MAX_TILES = 6
const visibleMatches = computed(() => matches.value.slice(0, matches.value.length > MAX_TILES ? MAX_TILES - 1 : MAX_TILES))
const hiddenCount = computed(() => matches.value.length - visibleMatches.value.length)
const hiddenPreview = computed(() => matches.value.slice(visibleMatches.value.length, visibleMatches.value.length + 3))

function openStudio() {
    openMatchStudio({
        id: props.lead.id,
        name: props.lead.lead_name,
        first_name: firstName.value,
    })
}

function shortPrice(n) {
    const v = Number(n)
    if (!v) return '—'
    if (v >= 1e6) return `AED ${(v / 1e6).toFixed(2).replace(/\.?0+$/, '')}M`
    if (v >= 1e3) return `AED ${Math.round(v / 1e3).toLocaleString('en-US')}K`
    return `AED ${v}`
}

function formatDate(iso) {
    const d = new Date(iso)
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
</script>

<style scoped>
.lead-matches {
    display: grid;
    gap: 20px;
    padding: 18px 20px 24px;
}

.lm-label {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--color-text-secondary);
}
.lm-label__aside { text-transform: none; letter-spacing: 0; font-weight: 500; }

.lm-wants { display: flex; flex-wrap: wrap; gap: 6px; }
.lm-want {
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 10px;
    background: var(--color-primary-soft);
    border: 1px solid var(--color-primary-border);
    font-size: 12.5px;
    font-weight: 600;
    color: var(--color-primary-ink);
}
.lm-want small { font-size: 10.5px; font-weight: 600; color: var(--color-text-secondary); }

/* Wraps into rows (3 across on the modal, 2 on phones) instead of running off the side. */
.lm-strip {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    min-width: 0;
    max-width: 100%;
}
.lead-matches > section { min-width: 0; }

.lm-more {
    display: grid;
    align-content: center;
    justify-items: center;
    gap: 4px;
    padding: 14px 10px;
    text-align: center;
    border-style: dashed;
    border-color: var(--color-primary-glow);
    background: linear-gradient(180deg, #fffdf6 0%, #f7efd6 100%);
}
.lm-more b { font-size: 15px; font-weight: 800; color: var(--color-primary-ink); }
.lm-more small { font-size: 11.5px; color: var(--color-text-secondary); }
.lm-more__stack { display: flex; margin-bottom: 6px; }
.lm-more__thumb { width: 40px !important; height: 30px !important; margin-left: -10px; border-radius: 8px; border: 2px solid #fffdf6; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12); }
.lm-more__thumb:first-child { margin-left: 0; }
@media (max-width: 480px) {
    .lm-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.lm-mini {
    padding: 0;
    text-align: left;
    border: 1px solid var(--color-border);
    border-radius: 14px;
    overflow: hidden;
    background: var(--color-surface);
    transition: transform 0.2s, box-shadow 0.2s;
}
.lm-mini:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(var(--color-primary-rgb), 0.16); }
.lm-mini.is-shortlisted { border-color: var(--color-primary-glow); }
.lm-mini__photo { position: relative; aspect-ratio: 16 / 11; }
.lm-mini__pct {
    position: absolute;
    top: 6px;
    right: 6px;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(23, 12, 28, 0.72);
    color: #f3d77a;
    font-size: 11px;
    font-weight: 800;
}
.lm-mini__flag {
    position: absolute;
    top: 6px;
    left: 6px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--color-primary-glow);
    color: var(--color-primary-ink);
}
.lm-mini__flag--drop { background: var(--color-success-soft); color: var(--color-success); }
.lm-mini__info { padding: 8px 10px 10px; }
.lm-mini__info b { display: block; font-size: 12px; color: var(--color-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lm-mini__info small { font-size: 11.5px; color: var(--color-text-secondary); font-variant-numeric: tabular-nums; }

.lm-cta,
.lm-empty {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 16px;
    border-radius: 16px;
    background: var(--color-primary-soft);
    border: 1px dashed var(--color-primary-border);
}
.lm-cta p,
.lm-empty p { margin: 0; font-size: 12.5px; color: var(--color-text-secondary); }
.lm-cta p { flex: 1 1 220px; min-width: 0; }
.lm-cta b,
.lm-empty b { display: block; font-size: 13.5px; color: var(--color-text-primary); }
.lm-empty > div { flex: 1 1 220px; min-width: 0; }
.lm-empty__icon { width: 28px; height: 28px; color: var(--color-primary); }

.lm-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 12px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}
.lm-btn--primary {
    border-color: transparent;
    background: var(--gradient-primary);
    color: var(--color-on-primary);
    box-shadow: 0 8px 18px rgba(var(--color-primary-rgb), 0.28);
}
.lm-btn--primary:hover { background: var(--gradient-primary-hover); }

.lm-history { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.lm-history li {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 8px 12px;
    border-radius: 12px;
    border: 1px solid var(--color-border);
    font-size: 12.5px;
}
.lm-history__name { font-weight: 600; color: var(--color-text-primary); min-width: 0; flex: 1 1 160px; }
.lm-history__meta { color: var(--color-text-secondary); font-variant-numeric: tabular-nums; }
.lm-status { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
.lm-status--sent { background: var(--color-success-soft); color: var(--color-success); }
.lm-status--skipped { background: var(--color-surface-muted); color: var(--color-text-muted); }

.lm-skeleton { display: grid; gap: 10px; }
.lm-skeleton span { height: 16px; border-radius: 8px; background: var(--color-surface-muted); animation: lm-pulse 1.2s ease-in-out infinite; }
.lm-skeleton span:nth-child(2) { height: 110px; }
@keyframes lm-pulse { 50% { opacity: 0.45; } }

@media (prefers-reduced-motion: reduce) {
    .lm-mini, .lm-skeleton span { transition: none; animation: none; }
}
</style>
