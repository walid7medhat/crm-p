<template>
    <div v-if="lead?.id" class="matching-properties-section info-section">
        <div class="info-section-title">Matching property</div>

        <div v-if="resolvingIntegration" class="matching-loading text-muted small py-2">
            Loading project from integration…
        </div>
        <div v-else-if="integrationMissingProject" class="matching-empty-hint">
            This lead is linked to an integration that has no project selected. Assign a project in Integrations so matching listings use that project only.
        </div>
        <div v-else-if="!hasSearchCriteria" class="matching-empty-hint">
            Add a project via the lead’s integration, or set location, property type, bedrooms, or budget in Client Requirement to see matching listings.
        </div>

        <div v-else-if="loading" class="matching-loading text-muted small py-2">Loading matches…</div>

        <div v-else-if="error" class="matching-error text-muted small py-2">{{ error }}</div>

        <div v-else-if="!listings.length" class="matching-empty-hint">No matching listings found.</div>

        <template v-else>
            <!-- Native scroll-snap strip (avoids vue3-carousel + Bootstrap .carousel class conflicts that collapsed slides). -->
            <div class="matching-carousel-wrap">
                <button
                    type="button"
                    class="matching-nav matching-nav--prev"
                    aria-label="Previous listings"
                    :disabled="!canScrollPrev"
                    @click="scrollMatching(-1)"
                >
                    <span class="matching-nav__inner" aria-hidden="true">
                        <iconify-icon icon="lucide:chevron-left" class="matching-nav__icon" width="20" height="20"></iconify-icon>
                    </span>
                </button>
                <div
                    ref="matchingScrollEl"
                    class="matching-scroll"
                    @scroll="onMatchingScroll"
                >
                    <router-link
                        v-for="item in listings"
                        :key="item.id"
                        :to="`/property-details/${item.id}`"
                        class="matching-card matching-card-link"
                    >
                        <div class="matching-card-media">
                            <img
                                v-if="item.main_image"
                                :src="item.main_image"
                                alt=""
                                class="matching-card-img"
                                loading="lazy"
                                @error="onImgError"
                            />
                            <div v-else class="matching-card-img matching-card-img--placeholder">
                                <iconify-icon icon="lucide:image-off" width="18" height="18"></iconify-icon>
                            </div>
                            <div class="matching-card-badges">
                                <span v-if="statusBadge(item)" class="matching-badge">{{ statusBadge(item) }}</span>
                                <span v-if="completionBadge(item)" class="matching-badge matching-badge--muted">{{ completionBadge(item) }}</span>
                            </div>
                        </div>
                        <div class="matching-card-body">
                            <div class="matching-price">{{ formatPrice(item.price) }} <span class="matching-currency">AED</span></div>
                            <div v-if="item.project?.title" class="matching-project-title">
                                {{ item.project.title }}
                            </div>
                            <div class="matching-title">{{ displayTitle(item) }}</div>
                            <div class="matching-loc">
                                <iconify-icon icon="lucide:map-pin" class="matching-loc-icon"></iconify-icon>
                                <span>{{ item.area || '—' }}</span>
                            </div>
                            <div class="matching-meta">
                                <span v-if="item.property_type" class="matching-meta-item">
                                    <iconify-icon icon="lucide:building-2" width="13" height="13"></iconify-icon>
                                    {{ item.property_type }}
                                </span>
                                <span class="matching-meta-item">
                                    <iconify-icon icon="lucide:bed-double" width="13" height="13"></iconify-icon>
                                    {{ formatBeds(item.number_of_bedrooms) }}
                                </span>
                                <span v-if="item.number_of_bathrooms != null" class="matching-meta-item">
                                    <iconify-icon icon="lucide:bath" width="13" height="13"></iconify-icon>
                                    {{ item.number_of_bathrooms }}
                                </span>
                                <span v-if="item.size_sqft" class="matching-meta-item">
                                    <iconify-icon icon="lucide:maximize-2" width="13" height="13"></iconify-icon>
                                    {{ item.size_sqft }} Sqft
                                </span>
                            </div>
                        </div>
                    </router-link>
                </div>
                <button
                    type="button"
                    class="matching-nav matching-nav--next"
                    aria-label="Next listings"
                    :disabled="!canScrollNext"
                    @click="scrollMatching(1)"
                >
                    <span class="matching-nav__inner" aria-hidden="true">
                        <iconify-icon icon="lucide:chevron-right" class="matching-nav__icon" width="20" height="20"></iconify-icon>
                    </span>
                </button>
                <div v-if="dotCount > 1" class="matching-dots" role="tablist" aria-label="Listing pages">
                    <button
                        v-for="i in dotCount"
                        :key="i"
                        type="button"
                        class="matching-dot"
                        :class="{ 'matching-dot--active': activeDot === i - 1 }"
                        :aria-label="`Go to page ${i}`"
                        :aria-current="activeDot === i - 1 ? 'true' : undefined"
                        @click="goToDot(i - 1)"
                    />
                </div>
            </div>

            <div class="matching-more-wrap">
                <router-link :to="moreRoute" class="matching-btn-more">More properties</router-link>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const matchingScrollEl = ref(null)
const canScrollPrev = ref(false)
const canScrollNext = ref(false)
const activeDot = ref(0)
const dotCount = ref(1)

function getCardScrollStep() {
    const el = matchingScrollEl.value
    if (!el) return 280
    const card = el.querySelector('.matching-card')
    if (!card) return Math.max(160, el.clientWidth * 0.92)
    const gap = 10
    return card.offsetWidth + gap
}

function updateScrollUi() {
    const el = matchingScrollEl.value
    if (!el) return
    const n = listings.value.length
    dotCount.value = Math.min(24, Math.max(1, n))
    const { scrollLeft, scrollWidth, clientWidth } = el
    const max = Math.max(0, scrollWidth - clientWidth)
    const eps = 4
    canScrollPrev.value = scrollLeft > eps
    canScrollNext.value = scrollLeft < max - eps
    if (max <= 0 || n <= 1) {
        activeDot.value = 0
        return
    }
    const step = getCardScrollStep()
    activeDot.value = Math.min(
        dotCount.value - 1,
        Math.max(0, Math.round((scrollLeft + step * 0.15) / step))
    )
}

function onMatchingScroll() {
    updateScrollUi()
}

function scrollMatching(direction) {
    const el = matchingScrollEl.value
    if (!el) return
    const step = getCardScrollStep() * direction
    el.scrollBy({ left: step, behavior: 'smooth' })
}

function goToDot(index) {
    const el = matchingScrollEl.value
    if (!el || dotCount.value <= 1) return
    const step = getCardScrollStep()
    el.scrollTo({ left: step * index, behavior: 'smooth' })
}

function bindScrollResize() {
    nextTick(() => {
        updateScrollUi()
    })
}

onMounted(() => {
    window.addEventListener('resize', updateScrollUi)
})

onUnmounted(() => {
    window.removeEventListener('resize', updateScrollUi)
})

const props = defineProps({
    lead: {
        type: Object,
        default: null
    }
})

const listings = ref([])
const loading = ref(false)
const error = ref('')
/** Filled via GET /leads/{id}/integration-project when the lead payload has no project id yet. */
const resolvedProjectId = ref(null)
const resolvingIntegration = ref(false)

/** Project id from the lead JSON (integration_project_id, nested integration, or lead.project_id). */
function listingScopeProjectIdFromLead(lead) {
    if (!lead) return null
    const fromIntegration = lead.integration_project_id ?? lead.integration?.project_id
    if (fromIntegration != null && fromIntegration !== '') return Number(fromIntegration)
    if (lead.project_id != null && lead.project_id !== '') return Number(lead.project_id)
    return null
}

/** Effective project for API: lead fields first, then resolver response. */
const effectiveProjectId = computed(() => {
    const fromLead = listingScopeProjectIdFromLead(props.lead)
    if (fromLead != null) return fromLead
    if (resolvedProjectId.value != null) return resolvedProjectId.value
    return null
})

const integrationMissingProject = computed(() => {
    const l = props.lead
    if (!l?.integration_id || resolvingIntegration.value) return false
    return effectiveProjectId.value == null
})

const hasSearchCriteria = computed(() => {
    const l = props.lead
    if (!l) return false
    if (resolvingIntegration.value) return false
    if (effectiveProjectId.value != null) return true
    /** Integration lead: must have a project id before searching. */
    if (l.integration_id) return false
    return !!(
        l.area_id ||
        l.property_type_id ||
        (l.bedrooms != null && l.bedrooms !== '') ||
        l.budget_from != null ||
        l.budget_to != null ||
        (l.budget != null && l.budget !== '')
    )
})

const moreQuery = computed(() => {
    const l = props.lead
    const q = {}
    const pid = effectiveProjectId.value
    /**
     * Requirement: when we have a scoped project_id, "More" must show ALL properties for that project.
     * Do not add lead-specific filters (area/beds/budget) or we may hide valid units.
     */
    if (pid) {
        q.project_id = String(pid)
        return q
    }

    // Fallback (no project id): keep lead filters for a best-effort search.
    if (l?.area_id) q.area_id = String(l.area_id)
    if (l?.property_type_id) q.type_id = String(l.property_type_id)
    if (l?.bedrooms != null && l.bedrooms !== '') {
        const b = String(l.bedrooms).toLowerCase() === 'studio' ? 'Studio' : String(l.bedrooms)
        q.beds = b
    }
    const minP = l?.budget_from ?? l?.budget
    const maxP = l?.budget_to ?? l?.budget
    if (minP != null && minP !== '') q.price_from = String(minP)
    if (maxP != null && maxP !== '') q.price_to = String(maxP)
    return q
})

const moreRoute = computed(() => ({
    path: '/alllisting',
    query: moreQuery.value
}))

function buildApiParams() {
    const l = props.lead
    const params = {
        per_page: 24,
        page: 1,
        sort: 'created_at_desc'
    }
    if (!l) return params
    const pid = effectiveProjectId.value
    if (pid) {
        params.project_id = pid
        /** List only units in this project — extra lead filters often match zero rows. */
        // return params
    }
    if (l.area_id) params.area_id = l.area_id
    if (l.property_type_id) params.property_type_id = l.property_type_id
    if (l.bedrooms != null && l.bedrooms !== '') {
        const raw = l.bedrooms
        if (String(raw).toLowerCase() === 'studio') {
            params.number_of_bedrooms = 0
        } else {
            const n = Number(raw)
            if (!Number.isNaN(n)) params.number_of_bedrooms = n
        }
    }
    const minP = l.budget_from ?? l.budget
    const maxP = l.budget_to ?? l.budget
    if (minP != null && minP !== '') params.min_price = minP
    if (maxP != null && maxP !== '') params.max_price = maxP
    return params
}

async function resolveIntegrationProject() {
    resolvedProjectId.value = null
    if (!props.lead?.id || !props.lead?.integration_id) return
    if (listingScopeProjectIdFromLead(props.lead) != null) return
    resolvingIntegration.value = true
    try {
        const res = await api.get(`/leads/${props.lead.id}/integration-project`)
        const pid = res.data?.data?.project_id
        resolvedProjectId.value = pid != null && pid !== '' ? Number(pid) : null
    } catch (e) {
        console.error('MatchingPropertiesSection integration-project', e)
        resolvedProjectId.value = null
    } finally {
        resolvingIntegration.value = false
    }
}

async function fetchMatches() {
    if (!props.lead?.id || !hasSearchCriteria.value) {
        listings.value = []
        return
    }
    loading.value = true
    error.value = ''
    try {
        const params = buildApiParams()
        const res = await api.get('/listings/properties/?active=1', { params })
        const data = res.data?.data
        listings.value = Array.isArray(data) ? data.slice(0, 24) : []
    } catch (e) {
        console.error('Matching properties', e)
        listings.value = []
        error.value = e.response?.status === 403
            ? 'You do not have permission to browse listings.'
            : 'Could not load matching listings.'
    } finally {
        loading.value = false
        bindScrollResize()
    }
}

watch(
    () => [
        props.lead?.id,
        props.lead?.integration_id,
        props.lead?.integration_project_id,
        props.lead?.project_id,
        props.lead?.integration?.project_id
    ],
    () => {
        resolveIntegrationProject()
    },
    { immediate: true }
)

watch(
    () => [
        effectiveProjectId.value,
        resolvingIntegration.value,
        props.lead?.area_id,
        props.lead?.property_type_id,
        props.lead?.bedrooms,
        props.lead?.budget_from,
        props.lead?.budget_to,
        props.lead?.budget,
        props.lead?.integration_id
    ],
    () => fetchMatches(),
    { immediate: true }
)

watch(listings, () => bindScrollResize(), { deep: true })

function formatPrice(n) {
    if (n == null || n === '') return '—'
    return Number(n).toLocaleString('en-US')
}

function displayTitle(item) {
    return item.title || item.reference_number || `Property #${item.id}`
}

function formatBeds(n) {
    if (n === 0 || n === '0') return 'Studio'
    if (n == null || n === '') return '—'
    return String(n)
}

function statusBadge(item) {
    const s = (item.listing_status || '').toLowerCase()
    if (s.includes('sale') || s === 'for sale') return 'For Sale'
    if (s.includes('rent') || s === 'for rent') return 'For Rent'
    return item.listing_status || ''
}

function completionBadge(item) {
    const c = item.completion_status
    if (!c) return ''
    if (String(c).toLowerCase().includes('off')) return 'Off Plan'
    if (String(c).toLowerCase().includes('ready')) return 'Ready'
    return String(c)
}

function onImgError(e) {
    e.target.style.display = 'none'
}
</script>

<style scoped>
/* Same card shell + title as LeadInfoView .info-section (scoped there; duplicated here for this child). */
.matching-properties-section.info-section {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 18px;
    margin-top: 0;
    background: #ffffff;
    overflow: visible;
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
}

.matching-properties-section .info-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    padding-bottom: 0;
    border-bottom: none;
}

.matching-empty-hint,
.matching-loading,
.matching-error {
    font-size: 12px;
    line-height: 1.4;
    color: #64748b;
}

.matching-carousel-wrap {
    position: relative;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 0 0 8px;
    margin: 0;
}

.matching-scroll {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: clamp(8px, 1.2vw, 12px);
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    scroll-snap-stop: always;
    scroll-behavior: smooth;
    scroll-padding: 0 8px;
    -webkit-overflow-scrolling: touch;
    /* Hide the native scrollbar bar (we already have dots + arrows) */
    scrollbar-width: none; /* Firefox */
    padding: 2px 2px 8px;
    box-sizing: border-box;
}

.matching-scroll::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

/*
 * One large “normal” card (~91% row) + a peek of the next listing on the right.
 * (Same width for every slide so scroll/snap stays even.)
 */
.matching-scroll .matching-card {
    /* Small screens: one readable card + peek of next */
    flex: 0 0 calc(100% - 20px);
    max-width: calc(100% - 20px);
    scroll-snap-align: start;
    min-width: 0;
}

@media (min-width: 520px) {
    .matching-scroll .matching-card {
        /* Larger screens: one card + ~half of next */
        flex: 0 0 calc((100% - 10px) / 1.55);
        max-width: calc((100% - 10px) / 1.55);
    }
}

.matching-nav {
    --nav-size: 40px;
    position: absolute;
    top: 42%;
    transform: translateY(-50%);
    z-index: 3;
    width: var(--nav-size);
    height: var(--nav-size);
    padding: 0;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    color: #0f172a;
    background: transparent;
    transition:
        transform 0.2s cubic-bezier(0.34, 1.2, 0.64, 1),
        opacity 0.2s ease;
}

.matching-nav__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.06),
        0 4px 12px rgba(15, 23, 42, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.9) inset;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition:
        background 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s cubic-bezier(0.34, 1.2, 0.64, 1);
}

.matching-nav__icon {
    display: block;
    opacity: 0.92;
    transition: opacity 0.2s ease;
}

.matching-nav:hover:not(:disabled) .matching-nav__inner {
    background: #fff;
    border-color: rgba(15, 23, 42, 0.12);
    box-shadow:
        0 2px 4px rgba(15, 23, 42, 0.06),
        0 8px 24px rgba(15, 23, 42, 0.12),
        0 0 0 1px rgba(255, 255, 255, 1) inset;
    transform: translateY(-1px);
}

.matching-nav:hover:not(:disabled) .matching-nav__icon {
    opacity: 1;
}

.matching-nav:active:not(:disabled) .matching-nav__inner {
    transform: translateY(0) scale(0.96);
    box-shadow:
        0 1px 3px rgba(15, 23, 42, 0.08),
        0 2px 8px rgba(15, 23, 42, 0.08);
}

.matching-nav:focus {
    outline: none;
}

.matching-nav:focus-visible .matching-nav__inner {
    box-shadow:
        0 2px 4px rgba(15, 23, 42, 0.06),
        0 8px 24px rgba(15, 23, 42, 0.1),
        0 0 0 2px #fff,
        0 0 0 4px rgba(245, 158, 11, 0.55);
}

.matching-nav:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.matching-nav:disabled .matching-nav__inner {
    background: rgba(248, 250, 252, 0.95);
    border-color: rgba(148, 163, 184, 0.25);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.matching-nav:disabled .matching-nav__icon {
    opacity: 0.45;
}

.matching-nav--prev {
    left: 4px;
}

.matching-nav--next {
    right: 4px;
}

@media (prefers-reduced-motion: reduce) {
    .matching-nav,
    .matching-nav__inner {
        transition-duration: 0.01ms;
    }

    .matching-nav:active:not(:disabled) .matching-nav__inner {
        transform: none;
    }

    .matching-nav:hover:not(:disabled) .matching-nav__inner {
        transform: none;
    }
}

.matching-dots {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}

.matching-dot {
    width: 16px;
    height: 4px;
    padding: 0;
    border: 0;
    border-radius: 2px;
    background: #cbd5e1;
    cursor: pointer;
    transition: background 0.15s ease;
}

.matching-dot--active {
    background: #f59e0b;
}

.matching-dot:hover:not(.matching-dot--active) {
    background: #94a3b8;
}

@media (max-width: 575px) {
    .matching-nav {
        --nav-size: 36px;
    }

    .matching-nav--prev {
        left: 2px;
    }

    .matching-nav--next {
        right: 2px;
    }

    .matching-nav .matching-nav__icon {
        width: 18px;
        height: 18px;
    }
}

.matching-card {
    background: #fff;
    border: 1px solid #e8ecf1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    min-width: 0;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.matching-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
    border-color: rgba(245, 158, 11, 0.35);
}

.matching-card:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.55), 0 6px 18px rgba(15, 23, 42, 0.08);
}

.matching-card-media {
    position: relative;
    aspect-ratio: 16 / 11;
    min-height: clamp(74px, 14vw, 96px);
    background: #f1f5f9;
}

.matching-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.matching-card-img--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}

.matching-card-badges {
    position: absolute;
    top: 4px;
    left: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    max-width: calc(100% - 10px);
}

.matching-badge {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 7px;
    border-radius: 5px;
    background: #1e3a5f;
    color: #fff;
    line-height: 1;
}

.matching-badge--muted {
    background: rgba(15, 23, 42, 0.55);
}

.matching-card-body {
    padding: clamp(8px, 1.2vw, 11px);
    display: flex;
    flex-direction: column;
    gap: clamp(3px, 0.7vw, 5px);
    flex: 1;
}

.matching-price {
    font-size: clamp(0.9rem, 1.9vw, 1rem);
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}

.matching-currency {
    font-size: clamp(0.58rem, 1.3vw, 0.65rem);
    font-weight: 600;
    color: #64748b;
}

.matching-project-title {
    font-size: clamp(0.63rem, 1.5vw, 0.72rem);
    font-weight: 600;
    color: #64748b;
    line-height: 1.2;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.matching-title {
    font-size: clamp(0.72rem, 1.7vw, 0.82rem);
    font-weight: 600;
    color: #0f172a;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.matching-loc {
    display: flex;
    align-items: flex-start;
    gap: 4px;
    font-size: clamp(0.64rem, 1.5vw, 0.72rem);
    color: #64748b;
    line-height: 1.3;
}

.matching-loc span {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.matching-loc-icon {
    flex-shrink: 0;
    margin-top: 0;
    width: 12px;
    height: 12px;
    opacity: 0.85;
}

.matching-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 9px;
    font-size: clamp(0.6rem, 1.35vw, 0.66rem);
    color: #64748b;
}

.matching-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.matching-more-wrap {
    display: flex;
    justify-content: center;
    margin-top: 14px;
    padding-top: 0;
}

.matching-btn-more {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    padding: 8px 18px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e3a5f;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    text-decoration: none;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.matching-btn-more:hover {
    border-color: #f59e0b;
    color: #c2410c;
    background: #fffbeb;
}
</style>
