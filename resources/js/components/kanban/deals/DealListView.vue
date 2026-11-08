<template>
  <div class="deal-list" :class="{ 'deal-list--mobile': isMobile }">
    <div class="deal-list__toolbar">
      <div class="board-view-switch deal-list__view-switch" role="tablist" aria-label="Deal layout">
        <button
          type="button"
          class="board-view-switch__btn"
          role="tab"
          aria-selected="false"
          @click="emit('set-board-view', 'kanban')"
        >
          <iconify-icon icon="lucide:columns-3" aria-hidden="true" />
          <span class="board-view-switch__label">Kanban</span>
        </button>
        <button
          type="button"
          class="board-view-switch__btn is-active"
          role="tab"
          aria-selected="true"
        >
          <iconify-icon icon="lucide:list" aria-hidden="true" />
          <span class="board-view-switch__label">List</span>
        </button>
      </div>

      <div
        v-if="stageTabs.length"
        class="deal-list__stage-tabs"
        role="tablist"
        aria-label="Deal stages"
      >
        <button
          v-for="tab in stageTabs"
          :key="'stage-tab-' + tab.id"
          type="button"
          class="deal-list__stage-tab"
          role="tab"
          :class="{
            'is-active': String(quick.stage_id || '') === String(tab.id),
            'is-light': isLightStageColor(tab.color),
          }"
          :aria-selected="String(quick.stage_id || '') === String(tab.id)"
          :style="stageTabStyle(tab)"
          @click="selectStageTab(tab.id)"
        >
          <span class="deal-list__stage-tab-dot" aria-hidden="true"></span>
          <span class="deal-list__stage-tab-label">{{ tab.name }}</span>
          <span v-if="tab.count != null" class="deal-list__stage-tab-count">{{ tab.count }}</span>
        </button>
      </div>

      <div class="deal-list__bar">
        <p class="deal-list__count">
          <template v-if="meta.total">
            {{ rangeStart }}–{{ rangeEnd }} of {{ meta.total }}
          </template>
          <template v-else>{{ loading ? 'Loading…' : '0 deals' }}</template>
        </p>
        <div v-if="showPager" class="deal-list__pager">
          <button
            type="button"
            class="deal-list__pager-btn"
            :disabled="page <= 1 || loading"
            @click="goPrev"
          >
            Prev
          </button>
          <span class="deal-list__pager-status">{{ page }}/{{ meta.last_page }}</span>
          <button
            type="button"
            class="deal-list__pager-btn"
            :disabled="page >= meta.last_page || loading"
            @click="goNext"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <div ref="listScrollRef" class="deal-list__scroll">
      <div v-if="loading && !deals.length" class="deal-list__skeleton" role="status" aria-label="Loading deals">
        <div v-for="n in 6" :key="'sk-' + n" class="deal-list__skeleton-row"></div>
      </div>

      <div v-else-if="error && !deals.length" class="deal-list__empty deal-list__empty--error">
        <iconify-icon icon="lucide:alert-circle" />
        <p>{{ error }}</p>
        <button type="button" class="deal-list__retry" @click="fetchDeals(true)">Try again</button>
      </div>

      <div v-else-if="!deals.length" class="deal-list__empty">
        <iconify-icon icon="lucide:inbox" />
        <p>No deals match this view.</p>
      </div>

      <div v-else class="deal-list__rows" :class="{ 'is-refreshing': loading }">
        <article
          v-for="deal in deals"
          :key="deal.id"
          class="deal-row"
          :class="{
            'deal-row--urgent': isUrgent(deal),
            'deal-row--lost': isLost(deal),
            'deal-row--won': isWon(deal),
          }"
          :style="{ '--stage-color': stageColor(deal) }"
          role="button"
          tabindex="0"
          :aria-label="`Open deal ${deal.deal_name || deal.deal_number || deal.id}`"
          @click="openDeal(deal)"
          @keydown.enter.prevent="openDeal(deal)"
          @keydown.space.prevent="openDeal(deal)"
        >
        <div class="deal-row__accent" aria-hidden="true"></div>

        <div class="deal-row__media">
          <img
            v-if="propertyThumb(deal)"
            :src="propertyThumb(deal)"
            :alt="propertyTitle(deal)"
            class="deal-row__thumb"
            loading="lazy"
          />
          <div v-else class="deal-row__thumb deal-row__thumb--fallback">
            <iconify-icon icon="lucide:building-2" />
          </div>
        </div>

        <div class="deal-row__main">
          <div class="deal-row__title-line">
            <span class="deal-row__title" :title="deal.deal_name || 'Untitled deal'">
              {{ deal.deal_name || propertyTitle(deal) || 'Untitled deal' }}
            </span>
            <span v-if="deal.deal_number" class="deal-row__ref">{{ deal.deal_number }}</span>
          </div>

          <p class="deal-row__property" :title="propertySubtitle(deal)">
            <iconify-icon icon="lucide:map-pin" aria-hidden="true" />
            <span>{{ propertySubtitle(deal) }}</span>
          </p>

          <div class="deal-row__people">
            <span v-if="clientLabel(deal)" class="deal-row__chip">
              <iconify-icon :icon="deal.deal_type === 'rental' ? 'lucide:key-round' : 'lucide:user'" />
              {{ clientLabel(deal) }}
            </span>
            <span v-if="counterpartyLabel(deal)" class="deal-row__chip deal-row__chip--muted">
              <iconify-icon icon="lucide:users" />
              {{ counterpartyLabel(deal) }}
            </span>
            <button
              v-if="deal.responsible_person?.name"
              type="button"
              class="deal-row__chip deal-row__chip--agent"
              @click.stop="emitOpenPerson(deal, $event)"
            >
              <img
                v-if="deal.responsible_person.avatar"
                :src="deal.responsible_person.avatar"
                alt=""
                class="deal-row__agent-avatar"
              />
              <iconify-icon v-else icon="lucide:user-round" />
              {{ deal.responsible_person.name }}
            </button>
            <span v-if="branchName(deal)" class="deal-row__chip deal-row__chip--muted">
              <iconify-icon icon="lucide:git-branch" />
              {{ branchName(deal) }}
            </span>
            <span v-if="externalCompany(deal)" class="deal-row__chip deal-row__chip--agency">
              <iconify-icon icon="lucide:briefcase" />
              {{ externalCompany(deal) }}
            </span>
          </div>
        </div>

        <div class="deal-row__meta">
          <div class="deal-row__badges">
            <span
              class="deal-row__stage"
              :style="{
                background: stageStyle(deal).gradient,
                color: stageTextColor(deal),
              }"
            >
              {{ deal.stage?.name || '—' }}
            </span>
            <span class="deal-row__status" :data-status="normalizeStatus(deal.status)">
              {{ formatStatus(deal.status) }}
            </span>
          </div>

          <div class="deal-row__finance">
            <div class="deal-row__value">
              <span class="deal-row__value-label">Deal value</span>
              <strong>{{ formatMoney(deal.deal_total_amount, deal.currency) }}</strong>
            </div>
            <div class="deal-row__commission">
              <span v-if="propertyPrice(deal)" class="deal-row__commission-line">
                Price {{ formatMoney(propertyPrice(deal), deal.currency) }}
              </span>
              <span class="deal-row__commission-line">
                Comm.
                <template v-if="deal.deal_commission != null && deal.deal_commission !== ''">
                  {{ formatPercent(deal.deal_commission) }}
                </template>
                <template v-else>—</template>
                <template v-if="commissionAmount(deal) != null">
                  · {{ formatMoney(commissionAmount(deal), deal.currency) }}
                </template>
              </span>
            </div>
          </div>

          <div class="deal-row__dates">
            <span :title="formatFullDate(deal.created_at)">
              <iconify-icon icon="lucide:plus" />
              {{ formatRelative(deal.created_at) }}
            </span>
            <span :title="formatFullDate(deal.updated_at)">
              <iconify-icon icon="lucide:refresh-cw" />
              {{ formatRelative(deal.updated_at) }}
            </span>
            <span v-if="closingDate(deal)" :title="formatFullDate(closingDate(deal))" :class="{ 'is-overdue': isUrgent(deal) }">
              <iconify-icon icon="lucide:calendar-clock" />
              {{ formatShortDate(closingDate(deal)) }}
            </span>
          </div>
        </div>

        <div class="deal-row__cta" aria-hidden="true">
          <iconify-icon icon="lucide:chevron-right" />
        </div>
      </article>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import axios, { getApiErrorMessage } from '@/plugins/axios'
import { resolveDealStageStyle } from '@/config/dealStageStyles.js'

const props = defineProps({
  dealType: {
    type: String,
    default: 'primary',
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  stages: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['open-deal', 'open-person', 'set-board-view'])

const isMobile = inject('kanbanIsMobile', ref(false))

const deals = ref([])
const loading = ref(false)
const error = ref(null)
const page = ref(1)
const perPage = 15
const meta = reactive({ total: 0, current_page: 1, last_page: 1 })
const quick = reactive({
  stage_id: '',
})

const stageOptions = ref([])

let abortController = null
let fetchSeq = 0

const rangeStart = computed(() => (meta.total ? (page.value - 1) * perPage + 1 : 0))
const rangeEnd = computed(() => Math.min(page.value * perPage, meta.total || 0))
const showPager = computed(() => {
  const last = Number(meta.last_page || 1)
  const total = Number(meta.total || 0)
  // Also show when the page is full — meta may be missing/malformed.
  return last > 1 || total > perPage || deals.value.length >= perPage
})

const stageTabs = computed(() => {
  const fromBoard = (props.stages || []).map((col) => {
    const id = col.stage_id || col.id
    const name = col.title || col.name || 'Stage'
    const style = resolveDealStageStyle(props.dealType, { order: col.order, name })
    return {
      id: String(id || ''),
      name,
      count: col.deals_count ?? col.total_count ?? null,
      color: col.dotColor || col.color || style.dotColor,
    }
  }).filter((tab) => tab.id)

  const source = fromBoard.length
    ? fromBoard
    : stageOptions.value.map((stage) => {
      const style = resolveDealStageStyle(props.dealType, { order: stage.order, name: stage.name })
      return {
        id: String(stage.id),
        name: stage.name,
        count: null,
        color: stage.color || style.dotColor,
      }
    })

  const totalCount = source.reduce((sum, tab) => sum + (Number(tab.count) || 0), 0)
  return [
    { id: '', name: 'All', count: totalCount || meta.total || null, color: '#733E87' },
    ...source,
  ]
})

function selectStageTab(stageId) {
  quick.stage_id = stageId == null || stageId === '' ? '' : String(stageId)
}

function parseHexColor(input) {
  const raw = String(input || '').trim()
  const short = /^#([0-9a-f]{3})$/i.exec(raw)
  if (short) {
    const [r, g, b] = short[1].split('').map((ch) => parseInt(ch + ch, 16))
    return { r, g, b }
  }
  const full = /^#([0-9a-f]{6})$/i.exec(raw)
  if (full) {
    return {
      r: parseInt(full[1].slice(0, 2), 16),
      g: parseInt(full[1].slice(2, 4), 16),
      b: parseInt(full[1].slice(4, 6), 16),
    }
  }
  const rgb = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i.exec(raw)
  if (rgb) {
    return { r: Number(rgb[1]), g: Number(rgb[2]), b: Number(rgb[3]) }
  }
  return null
}

function relativeLuminance({ r, g, b }) {
  const toLinear = (c) => {
    const n = c / 255
    return n <= 0.03928 ? n / 12.92 : ((n + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)
}

function isLightStageColor(color) {
  const rgb = parseHexColor(color)
  if (!rgb) return false
  return relativeLuminance(rgb) > 0.55
}

function darkenStageColor(color, factor = 0.45) {
  const rgb = parseHexColor(color)
  if (!rgb) return '#334155'
  const mix = (c) => Math.round(c * (1 - factor))
  const toHex = (n) => n.toString(16).padStart(2, '0')
  return `#${toHex(mix(rgb.r))}${toHex(mix(rgb.g))}${toHex(mix(rgb.b))}`
}

function softStageTint(color, alpha = 0.14) {
  const rgb = parseHexColor(color)
  if (!rgb) return 'rgba(115, 62, 135, 0.1)'
  return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`
}

function stageTabStyle(tab) {
  const color = tab?.color || '#733E87'
  const light = isLightStageColor(color)
  const ink = light ? darkenStageColor(color, 0.52) : color
  const activeFill = light ? darkenStageColor(color, 0.28) : color
  return {
    '--pill-color': color,
    '--pill-ink': ink,
    '--pill-soft': softStageTint(color, light ? 0.22 : 0.12),
    '--pill-active-fill': activeFill,
    '--pill-active-text': isLightStageColor(activeFill) ? '#0f172a' : '#ffffff',
  }
}

function buildParams() {
  const params = {
    deal_type: props.dealType || 'primary',
    per_page: perPage,
    page: page.value,
    order_by: 'created_at',
    order_dir: 'desc',
    ...props.filters,
  }

  // Stage tab overrides stage_id from the advanced search modal.
  if (quick.stage_id) params.stage_id = quick.stage_id

  // Strip empty values so Laravel when() clauses stay clean.
  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })

  return params
}

async function fetchDeals(resetPage = false) {
  if (resetPage) page.value = 1
  if (abortController) abortController.abort()
  abortController = new AbortController()
  const seq = ++fetchSeq
  loading.value = true
  error.value = null

  try {
    const response = await axios.get('/deals', {
      params: buildParams(),
      signal: abortController.signal,
    })
    if (seq !== fetchSeq) return

    const root = response.data || {}
    let payload = root.data
    // Support both flat arrays and Laravel resource `{ data: [...] }` nesting.
    if (payload && !Array.isArray(payload) && Array.isArray(payload.data)) {
      payload = payload.data
    }
    deals.value = Array.isArray(payload) ? payload : []

    const m = root.meta || root.data?.meta || {}
    const total = Number(m.total ?? root.total ?? deals.value.length ?? 0)
    const current = Number(m.current_page ?? root.current_page ?? page.value ?? 1)
    let last = Number(m.last_page ?? root.last_page ?? 0)
    if (!last) {
      last = Math.max(1, Math.ceil(total / perPage) || 1)
    }
    meta.total = total
    meta.current_page = current
    meta.last_page = last
    page.value = current

  } catch (err) {
    const canceled = err?.code === 'ERR_CANCELED'
      || err?.name === 'AbortError'
      || err?.name === 'CanceledError'
      || axios.isCancel?.(err)
    if (canceled || seq !== fetchSeq) return
    console.error('Deal list fetch failed', err)
    error.value = getApiErrorMessage(err, 'Failed to load deals.')
    deals.value = []
    meta.total = 0
    meta.last_page = 1
  } finally {
    if (seq === fetchSeq) loading.value = false
  }
}

async function loadFilterOptions() {
  try {
    const stagesRes = await axios.get('/stages', {
      params: { stage_type: 'deal', deal_type: props.dealType || 'primary' },
    })

    const stagesRaw = stagesRes.data?.data?.data || stagesRes.data?.data || stagesRes.data || []
    stageOptions.value = (Array.isArray(stagesRaw) ? stagesRaw : []).map((s) => ({
      id: s.id,
      name: s.name || s.stage_name || 'Stage',
      order: s.order,
      color: s.color,
    }))
  } catch (err) {
    console.warn('Deal list filter options failed', err)
    // Fall back to stages passed from the kanban board.
    if (props.stages?.length) {
      stageOptions.value = props.stages.map((s) => ({
        id: s.stage_id || s.id,
        name: s.title || s.name,
        order: s.order,
        color: s.color || s.dotColor,
      }))
    }
  }
}

const listScrollRef = ref(null)

function scrollListToTop() {
  const el = listScrollRef.value
  if (el) el.scrollTop = 0
}

async function goPrev() {
  if (page.value <= 1) return
  page.value -= 1
  await fetchDeals(false)
  scrollListToTop()
}

async function goNext() {
  if (page.value >= meta.last_page) return
  page.value += 1
  await fetchDeals(false)
  scrollListToTop()
}

function openDeal(deal) {
  emit('open-deal', deal)
}

function emitOpenPerson(deal, event) {
  emit('open-person', { deal, event })
}

function primaryProperty(deal) {
  const propsList = Array.isArray(deal?.properties) ? deal.properties : []
  if (!propsList.length) return null
  return [...propsList].sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))[0]
}

function propertyThumb(deal) {
  const prop = primaryProperty(deal)
  return prop?.listing?.main_image || null
}

function propertyTitle(deal) {
  const prop = primaryProperty(deal)
  if (!prop) return deal?.listing?.name || ''
  return prop.display_name || [prop.area_name, prop.unit_no].filter(Boolean).join(' · ') || prop.listing?.reference_number || ''
}

function propertySubtitle(deal) {
  const prop = primaryProperty(deal)
  if (!prop) return 'No property linked'
  const bits = [
    prop.area_name,
    prop.unit_no ? `Unit ${prop.unit_no}` : null,
    prop.property_type_name || prop.listing?.property_type?.name,
    prop.listing?.reference_number,
  ].filter(Boolean)
  return bits.join(' · ') || propertyTitle(deal) || 'Property'
}

function propertyPrice(deal) {
  const prop = primaryProperty(deal)
  if (!prop) return null
  if (deal.deal_type === 'rental') {
    return prop.rental_price ?? prop.listing?.price ?? null
  }
  return prop.purchase_price ?? prop.listing?.selling_price ?? prop.listing?.price ?? null
}

function clientLabel(deal) {
  const name = deal.client_name || deal.buyer_name || deal.tenant_name
  if (!name) return ''
  const role = deal.deal_type === 'rental' ? 'Tenant' : 'Buyer'
  return `${role}: ${name}`
}

function counterpartyLabel(deal) {
  const name = deal.counterparty_name || deal.seller_name || deal.landlord_name
  if (!name) return ''
  const role = deal.deal_type === 'rental' ? 'Landlord' : 'Seller'
  return `${role}: ${name}`
}

function branchName(deal) {
  const person = deal?.responsible_person
  return person?.branch_name || person?.branch || person?.office_name || ''
}

function externalCompany(deal) {
  const agency = deal?.commission_distribution?.external_agency
  if (!agency?.enabled) return ''
  return String(agency.name || '').trim()
}

function commissionAmount(deal) {
  const pct = Number(deal?.deal_commission)
  const total = Number(deal?.deal_total_amount)
  if (!Number.isFinite(pct) || !Number.isFinite(total) || total <= 0) {
    const agentShare = Number(deal?.agent_share)
    return Number.isFinite(agentShare) ? agentShare : null
  }
  // deal_commission is stored as a percentage in deal forms.
  return (total * pct) / 100
}

function stageColor(deal) {
  return stageStyle(deal).dotColor || deal.stage?.color || '#733E87'
}

function stageStyle(deal) {
  return resolveDealStageStyle(deal.deal_type || props.dealType, {
    order: deal.stage?.order,
    name: deal.stage?.name,
  })
}

function stageTextColor(deal) {
  const name = String(deal.stage?.name || '').toLowerCase()
  if (name.includes('eoi') || name.includes('security') || name.includes('lease') || name.includes('moved')) {
    return '#0f172a'
  }
  return '#ffffff'
}

function normalizeStatus(status) {
  return String(status || 'open').toLowerCase().replace(/\s+/g, '_')
}

function formatStatus(status) {
  const raw = String(status || 'open').replace(/_/g, ' ')
  return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : 'Open'
}

function isLost(deal) {
  const stage = String(deal.stage?.name || '').toLowerCase()
  return stage.includes('lost') || normalizeStatus(deal.status) === 'lost'
}

function isWon(deal) {
  const stage = String(deal.stage?.name || '').toLowerCase()
  return stage.includes('won') || normalizeStatus(deal.status) === 'won' || normalizeStatus(deal.status) === 'completed'
}

function closingDate(deal) {
  return deal.won_date || deal.spa_date || deal.mou_date || deal.booking_date || deal.noc_date || null
}

function isUrgent(deal) {
  if (isWon(deal) || isLost(deal)) return false
  const closing = closingDate(deal)
  if (!closing) {
    // Stale active deals (no update in 14+ days) get a subtle urgency cue.
    if (!deal.updated_at) return false
    const updated = new Date(deal.updated_at)
    if (Number.isNaN(updated.getTime())) return false
    return (Date.now() - updated.getTime()) / 86400000 >= 14
  }
  const date = new Date(closing)
  if (Number.isNaN(date.getTime())) return false
  const days = (date.getTime() - Date.now()) / 86400000
  return days < 0 || days <= 7
}

function formatMoney(amount, currency = 'AED') {
  if (amount === null || amount === undefined || amount === '') return '—'
  const n = Number(amount)
  if (!Number.isFinite(n)) return '—'
  try {
    return new Intl.NumberFormat('en-AE', {
      style: 'currency',
      currency: currency || 'AED',
      maximumFractionDigits: n % 1 === 0 ? 0 : 2,
    }).format(n)
  } catch {
    return `${currency || 'AED'} ${n.toLocaleString()}`
  }
}

function formatPercent(value) {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `${n % 1 === 0 ? n.toFixed(0) : n.toFixed(2)}%`
}

function formatRelative(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return '—'
  const minutes = Math.round((Date.now() - date.getTime()) / 60000)
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.round(hours / 24)
  if (days < 14) return `${days}d ago`
  return formatShortDate(dateString)
}

function formatShortDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatFullDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

watch(
  () => props.dealType,
  async () => {
    quick.stage_id = ''
    await loadFilterOptions()
    fetchDeals(true)
  }
)

watch(
  () => props.filters,
  (filters) => {
    // Keep stage tabs in sync when advanced kanban search sets stage_id.
    if (Object.prototype.hasOwnProperty.call(filters || {}, 'stage_id')) {
      const next = filters.stage_id
      quick.stage_id = next == null || next === '' ? '' : String(next)
    }
    fetchDeals(true)
  },
  { deep: true }
)

watch(
  () => quick.stage_id,
  () => fetchDeals(true)
)

onMounted(async () => {
  await loadFilterOptions()
  // Prefer parent stage list when API stages are empty.
  if (!stageOptions.value.length && props.stages?.length) {
    stageOptions.value = props.stages.map((s) => ({
      id: s.stage_id || s.id,
      name: s.title || s.name,
      order: s.order,
      color: s.color || s.dotColor,
    }))
  }
  await fetchDeals(true)
})

onUnmounted(() => {
  if (abortController) abortController.abort()
})

defineExpose({
  refresh: () => fetchDeals(false),
  reload: () => fetchDeals(true),
})
</script>

<style scoped>
.deal-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1 1 0;
  min-height: 0;
  height: 100%;
  max-height: 100%;
  overflow: hidden;
  padding: 0 2px;
  box-sizing: border-box;
}

.deal-list__toolbar {
  display: flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 8px;
  flex-shrink: 0;
  min-width: 0;
  padding: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.deal-list__view-switch {
  flex: 0 0 auto;
  gap: 2px;
  padding: 2px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.95);
  border: 1px solid rgba(226, 232, 240, 0.95);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.deal-list__view-switch .board-view-switch__btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 28px;
  padding: 0 10px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: #64748b;
  font-size: 12px;
  font-weight: 650;
  letter-spacing: -0.01em;
  cursor: pointer;
  white-space: nowrap;
}

.deal-list__view-switch .board-view-switch__btn iconify-icon {
  font-size: 13px;
}

.deal-list__view-switch .board-view-switch__btn.is-active {
  background: var(--gradient-primary);
  color: #fff;
  -webkit-text-fill-color: #fff;
  box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.24);
}

.deal-list__view-switch .board-view-switch__btn.is-active iconify-icon {
  color: #fff;
}

.deal-list__stage-tabs {
  display: flex;
  align-items: center;
  gap: 4px;
  flex: 1 1 auto;
  min-width: 0;
  padding: 3px;
  overflow-x: auto;
  overflow-y: hidden;
  border: 1px solid rgba(231, 220, 236, 0.9);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.92);
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.deal-list__stage-tabs::-webkit-scrollbar {
  display: none;
}

.deal-list__stage-tab {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  flex: 0 0 auto;
  height: 28px;
  padding: 0 9px 0 8px;
  border: 1px solid color-mix(in srgb, var(--pill-color, #733e87) 30%, #cbd5e1);
  border-radius: 999px;
  background: var(--pill-soft, rgba(115, 62, 135, 0.1));
  color: var(--pill-ink, #334155);
  font-size: 11.5px;
  font-weight: 650;
  letter-spacing: -0.01em;
  white-space: nowrap;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.deal-list__stage-tab-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex: 0 0 auto;
  background: var(--pill-color, #733e87);
  box-shadow: 0 0 0 1.5px rgba(255, 255, 255, 0.75);
}

.deal-list__stage-tab.is-light .deal-list__stage-tab-dot {
  background: var(--pill-ink, #0f172a);
  box-shadow: 0 0 0 1.5px color-mix(in srgb, var(--pill-color, #aae9fc) 55%, #fff);
}

.deal-list__stage-tab-label {
  color: inherit;
}

.deal-list__stage-tab.is-active {
  background: var(--pill-active-fill, var(--pill-color, #733e87));
  border-color: transparent;
  color: var(--pill-active-text, #fff);
  box-shadow: 0 4px 10px -8px var(--pill-active-fill, #733e87);
}

.deal-list__stage-tab.is-active .deal-list__stage-tab-dot {
  background: currentColor;
  box-shadow: none;
  opacity: 0.9;
}

.deal-list__stage-tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.1);
  color: inherit;
  font-size: 10px;
  font-weight: 750;
}

.deal-list__stage-tab.is-active .deal-list__stage-tab-count {
  background: rgba(255, 255, 255, 0.22);
  color: inherit;
}

.deal-list__stage-tab.is-active.is-light .deal-list__stage-tab-count {
  background: rgba(15, 23, 42, 0.12);
}

.deal-list__bar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex: 0 0 auto;
  min-height: 28px;
  padding: 0;
}

.deal-list__scroll {
  flex: 1 1 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  padding-right: 2px;
  padding-bottom: 2px;
}

.deal-list__count {
  margin: 0;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748b);
  white-space: nowrap;
}

.deal-list__pager {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.deal-list__pager-btn {
  height: 26px;
  padding: 0 9px;
  border-radius: 999px;
  border: 1px solid var(--color-border, #e8e6ee);
  background: #fff;
  color: var(--color-text-primary, #171717);
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.deal-list__pager-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.deal-list__pager-status {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
  white-space: nowrap;
}

@media (max-width: 900px) {
  .deal-list__view-switch .board-view-switch__label {
    display: none;
  }

  .deal-list__view-switch .board-view-switch__btn {
    padding: 0 8px;
  }

  .deal-list__count {
    display: none;
  }
}

.deal-list__rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: opacity 0.15s ease;
}

.deal-list__rows.is-refreshing {
  opacity: 0.72;
}

.deal-row {
  position: relative;
  display: grid;
  grid-template-columns: 4px 72px minmax(0, 1.4fr) minmax(240px, 0.9fr) 28px;
  gap: 14px;
  align-items: stretch;
  padding: 14px 14px 14px 0;
  border-radius: 16px;
  background: #fff;
  border: 1px solid rgba(232, 230, 238, 0.95);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.035);
  cursor: pointer;
  transition:
    transform 0.16s ease,
    box-shadow 0.16s ease,
    border-color 0.16s ease;
}

.deal-row:hover,
.deal-row:focus-visible {
  transform: translateY(-1px);
  border-color: rgba(var(--color-primary-rgb, 115, 62, 135), 0.35);
  box-shadow:
    0 14px 34px rgba(15, 23, 42, 0.07),
    0 0 0 3px rgba(var(--color-primary-rgb, 115, 62, 135), 0.08);
  outline: none;
}

.deal-row__accent {
  border-radius: 16px 0 0 16px;
  background: var(--stage-color, var(--color-primary, #733e87));
  opacity: 0.92;
}

.deal-row--urgent {
  border-color: rgba(244, 161, 0, 0.35);
  background:
    linear-gradient(90deg, rgba(244, 161, 0, 0.06) 0%, rgba(255, 255, 255, 0) 28%),
    #fff;
}

.deal-row--lost {
  opacity: 0.88;
}

.deal-row__media {
  display: flex;
  align-items: center;
  padding-left: 10px;
}

.deal-row__thumb {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--color-surface-muted, #f4f5f7);
  border: 1px solid rgba(232, 230, 238, 0.9);
}

.deal-row__thumb--fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--color-primary, #733e87);
  background:
    linear-gradient(145deg, rgba(var(--color-primary-rgb, 115, 62, 135), 0.08), rgba(212, 175, 55, 0.12)),
    #faf7fb;
  font-size: 22px;
}

.deal-row__main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  justify-content: center;
}

.deal-row__title-line {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.deal-row__title {
  margin: 0;
  font-size: 13.5px;
  font-weight: 650;
  letter-spacing: -0.01em;
  line-height: 1.3;
  color: var(--color-text-primary, #171717);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.deal-row__ref {
  flex: 0 0 auto;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: var(--color-primary, #733e87);
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.08);
  border-radius: 999px;
  padding: 2px 8px;
}

.deal-row__property {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 5px;
  min-width: 0;
  font-size: 12.5px;
  color: #64748b;
}

.deal-row__property span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.deal-row__people {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 2px;
}

.deal-row__chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  max-width: 100%;
  min-height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  background: #f8fafc;
  border: 1px solid #e8eef6;
  color: #334155;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.deal-row__chip--muted {
  color: #64748b;
  font-weight: 550;
}

.deal-row__chip--agency {
  background: rgba(212, 175, 55, 0.12);
  border-color: rgba(212, 175, 55, 0.35);
  color: #8a6d12;
}

.deal-row__chip--agent {
  cursor: pointer;
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.06);
  border-color: rgba(var(--color-primary-rgb, 115, 62, 135), 0.18);
  color: var(--color-primary-ink, #3f2148);
}

.deal-row__chip--agent:hover {
  background: rgba(var(--color-primary-rgb, 115, 62, 135), 0.12);
}

.deal-row__agent-avatar {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  object-fit: cover;
}

.deal-row__meta {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  min-width: 0;
  padding-right: 4px;
}

.deal-row__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}

.deal-row__stage {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  min-height: 24px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
  letter-spacing: 0.01em;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.deal-row__status {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 0 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  background: #f1f5f9;
  color: #475569;
  text-transform: capitalize;
}

.deal-row__status[data-status='approved'],
.deal-row__status[data-status='completed'],
.deal-row__status[data-status='won'] {
  background: #dcfce7;
  color: #166534;
}

.deal-row__status[data-status='draft'] {
  background: #fef3c7;
  color: #92400e;
}

.deal-row__status[data-status='lost'],
.deal-row__status[data-status='cancelled'],
.deal-row__status[data-status='canceled'] {
  background: #fee2e2;
  color: #991b1b;
}

.deal-row__finance {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  text-align: right;
}

.deal-row__value-label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #94a3b8;
}

.deal-row__value strong {
  font-size: 13.5px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--color-text-primary, #171717);
  font-variant-numeric: tabular-nums;
}

.deal-row__commission {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}

.deal-row__commission-line {
  font-size: 11.5px;
  font-weight: 600;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

.deal-row__dates {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  font-size: 11px;
  color: #94a3b8;
  font-weight: 600;
}

.deal-row__dates span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.deal-row__dates .is-overdue {
  color: #b45309;
}

.deal-row__cta {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cbd5e1;
  font-size: 18px;
  transition: color 0.15s ease, transform 0.15s ease;
}

.deal-row:hover .deal-row__cta,
.deal-row:focus-visible .deal-row__cta {
  color: var(--color-primary, #733e87);
  transform: translateX(2px);
}

.deal-list__empty,
.deal-list__skeleton {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 220px;
  height: 100%;
  border-radius: 16px;
  border: 1px dashed var(--color-primary-border, #e7dcec);
  background: rgba(255, 255, 255, 0.7);
  color: #64748b;
  text-align: center;
  padding: 24px;
}

.deal-list__empty iconify-icon {
  font-size: 28px;
  color: var(--color-primary, #733e87);
}

.deal-list__empty p {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}

.deal-list__retry {
  height: 34px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: var(--gradient-primary, var(--color-primary, #733e87));
  color: #fff;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
}

.deal-list__skeleton {
  align-items: stretch;
  gap: 10px;
  padding: 12px;
}

.deal-list__skeleton-row {
  height: 88px;
  border-radius: 14px;
  background: linear-gradient(90deg, #f3f2f6 0%, #faf7fb 50%, #f3f2f6 100%);
  background-size: 200% 100%;
  animation: deal-list-shimmer 1.2s ease-in-out infinite;
}

@keyframes deal-list-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

@media (max-width: 1100px) {
  .deal-row {
    grid-template-columns: 4px 64px minmax(0, 1fr);
    grid-template-areas:
      "accent media main"
      "accent meta meta";
  }

  .deal-row__accent { grid-area: accent; }
  .deal-row__media { grid-area: media; }
  .deal-row__main { grid-area: main; }
  .deal-row__meta {
    grid-area: meta;
    padding: 0 12px 4px 10px;
    align-items: flex-start;
  }
  .deal-row__badges,
  .deal-row__finance,
  .deal-row__dates {
    align-items: flex-start;
    justify-content: flex-start;
    text-align: left;
  }
  .deal-row__commission { align-items: flex-start; }
  .deal-row__cta { display: none; }
}

@media (max-width: 720px) {
  .deal-row {
    grid-template-columns: 4px 56px minmax(0, 1fr);
    gap: 10px;
    padding: 12px 12px 12px 0;
  }

  .deal-row__thumb {
    width: 52px;
    height: 52px;
  }

  .deal-row__value strong {
    font-size: 13px;
  }
}
</style>
