<template>
  <div class="dashboard-main-body ofr-page" :class="{ 'ofr-page--mobile': isMobile }">
    <OwnerFinancialMobileShell
      v-if="isMobile"
      :summary="summary"
      :rows="rows"
      :pagination="pagination"
      :page="page"
      :loading="loading"
      :error="error"
      :open-deal-id="openDealId"
      :sort="sort"
      :draft="draft"
      :applied="applied"
      :options="options"
      :active-count="activeCount"
      :last-loaded="lastLoaded"
      :insights="insights"
      :insights-loading="insightsLoading"
      :insights-error="insightsError"
      :filter-summary="mobileFilterSummary"
      :exporting="exportingKind"
      :export-status="exportStatus"
      :export-status-error="exportStatusError"
      @toggle-missing="toggleMissing"
      @open-deal-id="openDeal"
      @open-deal-modal="showDeal"
      @page="goTo"
      @sort="setSort"
      @retry="fetchReport"
      @apply-filters="applyMobileFilters"
      @clear-filters="clearFilters"
      @quick-filters="applyMobileFilters"
      @search="applyMobileSearch"
      @select-agent="selectMobileAgent"
      @clear-search="clearMobileSearch"
      @load-insights="fetchInsights"
      @export-excel="exportExcelMobile"
      @export-pdf="exportPdfMobile"
      @run-report="runMobileReport"
    />

    <template v-else>
    <Breadcrumb title="Owner Financial Intelligence" :breadcrumbs="[{ name: 'Owner Financial Intelligence' }]" />

    <!-- Header -->
    <header class="ofr-header">
      <div class="ofr-header-title">
        <span class="ofr-header-icon"><iconify-icon icon="lucide:chart-column" width="22" height="22" /></span>
        <div>
          <h1 class="ofr-title">Owner Financial Intelligence</h1>
          <p class="ofr-hint">Commission revenue from stored deal data · amounts in AED</p>
        </div>
      </div>
      <div class="ofr-header-meta">
        <span class="ofr-scope-chip">{{ applied.deal_status === 'all' ? 'All deals' : 'Closed deals only' }}</span>
        <span v-if="lastLoaded" class="ofr-updated">Updated {{ lastLoaded }}</span>
      </div>
    </header>

    <!-- KPI cards -->
    <div class="ofr-kpis">
      <div class="ofr-kpi ofr-kpi--net">
        <div class="ofr-kpi-top">
          <span class="ofr-kpi-icon"><iconify-icon icon="lucide:wallet" width="18" height="18" /></span>
          <span class="ofr-kpi-label">Total Net Company Revenue</span>
        </div>
        <strong class="ofr-kpi-value" :title="aedShort(summary.net_revenue)">
          <small class="ofr-cur">AED</small>{{ figure(summary.net_revenue) }}
        </strong>
        <div class="ofr-kpi-foot">
          <span class="ofr-kpi-tag"><iconify-icon icon="lucide:trending-up" width="13" height="13" />Net revenue</span>
          <span>{{ completeFoot }}</span>
        </div>
      </div>

      <div class="ofr-kpi">
        <div class="ofr-kpi-top">
          <span class="ofr-kpi-icon"><iconify-icon icon="lucide:circle-dollar-sign" width="18" height="18" /></span>
          <span class="ofr-kpi-label">Total Gross Commission</span>
        </div>
        <strong class="ofr-kpi-value" :title="aedShort(summary.gross_commission)">
          <small class="ofr-cur">AED</small>{{ figure(summary.gross_commission) }}
        </strong>
        <div class="ofr-kpi-foot">{{ summary.gross_deals }} of {{ summary.deals }} deals have amount &amp; rate</div>
      </div>

      <div class="ofr-kpi">
        <div class="ofr-kpi-top">
          <span class="ofr-kpi-icon"><iconify-icon icon="lucide:hand-coins" width="18" height="18" /></span>
          <span class="ofr-kpi-label">Total Agent Commission</span>
        </div>
        <strong class="ofr-kpi-value" :title="aedShort(summary.agent_commission)">
          <small class="ofr-cur">AED</small>{{ figure(summary.agent_commission) }}
        </strong>
        <div class="ofr-kpi-foot">{{ completeFoot }}</div>
      </div>

      <div class="ofr-kpi">
        <div class="ofr-kpi-top">
          <span class="ofr-kpi-icon"><iconify-icon icon="lucide:briefcase-business" width="18" height="18" /></span>
          <span class="ofr-kpi-label">Total Closed Deals</span>
        </div>
        <strong class="ofr-kpi-value">{{ summary.closed_deals.toLocaleString('en-US') }}<small class="ofr-unit">Deals</small></strong>
        <div class="ofr-kpi-foot">In a "Deal Won" stage</div>
      </div>

      <button
        type="button"
        class="ofr-kpi ofr-kpi--missing"
        :class="{ 'is-active': applied.data_status === 'missing', 'is-zero': !summary.incomplete_deals }"
        :title="applied.data_status === 'missing' ? 'Show all deals again' : 'Show only deals with missing data'"
        @click="toggleMissing"
      >
        <div class="ofr-kpi-top">
          <span class="ofr-kpi-icon"><iconify-icon icon="lucide:triangle-alert" width="18" height="18" /></span>
          <span class="ofr-kpi-label">Missing Financial Data</span>
        </div>
        <strong class="ofr-kpi-value">
          {{ summary.incomplete_deals.toLocaleString('en-US') }}<small class="ofr-unit">Deals</small>
          <span v-if="summary.incomplete_deals" class="ofr-badge-incomplete">Incomplete</span>
        </strong>
        <div class="ofr-kpi-foot">{{ applied.data_status === 'missing' ? 'Filtered · click to show all' : 'Click to list these deals' }}</div>
      </button>
    </div>

    <div class="ofr-body">
      <!-- Deals table (left) -->
      <section class="ofr-panel ofr-deals">
        <div class="ofr-deals-head">
          <h2 class="ofr-h2">Deals Overview</h2>
          <div class="ofr-deals-tools">
            <span class="ofr-total">{{ pagination.total.toLocaleString('en-US') }} deals</span>
            <v-select
              v-model="perPage"
              :options="PER_PAGE_OPTIONS"
              :reduce="(o) => o.value"
              label="label"
              :clearable="false"
              :searchable="false"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs ofr-vs--sm"
            />
            <button
              type="button"
              class="ofr-btn ofr-btn--export"
              :disabled="exporting || !pagination.total"
              :title="dirty ? 'Exports the applied filters (press Apply Filters first to include your changes)' : 'Download every deal in this view as Excel'"
              @click="exportExcel"
            >
              <iconify-icon :icon="exporting ? 'lucide:loader-2' : 'lucide:download'" :class="{ 'ofr-spin': exporting }" width="15" height="15" />
              {{ exporting ? 'Exporting…' : 'Export Excel' }}
            </button>
          </div>
        </div>

        <div v-if="error" class="ofr-error">
          <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
          <span>{{ error }}</span>
          <button type="button" class="ofr-link" @click="fetchReport">Retry</button>
        </div>

        <div class="ofr-table-shell">
          <div ref="tableWrapRef" class="ofr-table-wrap" :class="{ 'is-loading': loading }" @scroll="updateScrollArrows">
            <table class="ofr-table">
              <thead>
                <tr>
                  <th v-for="col in columns" :key="col.key" :class="[col.num && 'ofr-num', col.key === 'net_revenue' && 'ofr-col-net']">
                    <button v-if="col.sort" type="button" class="ofr-sort" :class="{ 'is-active': sort === col.sort }" @click="toggleSort(col.sort)">
                      <span>{{ col.label }}</span>
                      <iconify-icon :icon="sortIcon(col.sort)" width="12" height="12" />
                    </button>
                    <template v-else>{{ col.label }}</template>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!loading && !rows.length">
                  <td :colspan="columns.length" class="ofr-empty">
                    <iconify-icon icon="lucide:search-x" width="22" height="22" />
                    <span>No deals match these filters.</span>
                  </td>
                </tr>
                <tr
                  v-for="row in rows"
                  :key="row.id"
                  class="ofr-row"
                  :class="{ 'is-incomplete': row.is_incomplete, 'is-selected': openDealId === row.id }"
                  tabindex="0"
                  @click="openDeal(row.id)"
                  @keydown.enter="openDeal(row.id)"
                >
                  <td>
                    <button
                      type="button"
                      class="ofr-cell-link ofr-trunc ofr-trunc--deal"
                      :title="row.deal_number || `#${row.id}`"
                      @click.stop="showDeal(row)"
                    >{{ row.deal_number || `#${row.id}` }}</button>
                  </td>
                  <td><span class="ofr-trunc ofr-trunc--property ofr-property" :title="row.property || ''">{{ row.property || '—' }}</span></td>
                  <td>
                    <span class="ofr-trunc" :title="row.client?.name || ''">{{ row.client?.name || '—' }}</span>
                    <span v-if="row.client?.phone" class="ofr-sub">{{ row.client.phone }}</span>
                  </td>
                  <td><span class="ofr-trunc" :title="row.agent?.name || ''">{{ row.agent?.name || '—' }}</span></td>
                  <td class="ofr-nowrap">{{ row.branch || '—' }}</td>
                  <td><span class="ofr-trunc" :title="row.source || ''">{{ row.source || '—' }}</span></td>
                  <td class="ofr-nowrap">
                    {{ formatDate(row.deal_date) }}
                    <span v-if="!row.deal_date_is_won" class="ofr-sub" title="Won date not recorded; showing the date the deal was created">created</span>
                  </td>
                  <td class="ofr-num" :class="{ 'ofr-missing': row.deal_amount === null }">{{ row.deal_amount === null ? 'Missing' : aedShort(row.deal_amount) }}</td>
                  <td class="ofr-num" :class="{ 'ofr-missing': row.gross_commission === null }">{{ row.gross_commission === null ? 'Missing' : aedShort(row.gross_commission) }}</td>
                  <td class="ofr-num">
                    {{ aedShort(row.agent_commission) }}
                    <span
                      v-if="row.agent_commission !== null"
                      class="ofr-sub"
                      :title="row.commission_source === 'breakdown' ? 'From the commission breakdown saved on the deal' : 'Agent commission % from the agent\'s user profile'"
                    >{{ row.commission_source === 'breakdown' ? 'Deal breakdown' : `${row.agent_rate}% · profile` }}</span>
                  </td>
                  <td class="ofr-num">{{ aedShort(row.external_commission) }}</td>
                  <td class="ofr-num ofr-col-net">
                    <span v-if="row.is_incomplete" class="ofr-pill" :title="row.issues.map((i) => i.label).join('\n')">
                      <iconify-icon icon="lucide:triangle-alert" width="12" height="12" />
                      Incomplete
                    </span>
                    <strong v-else>{{ aedShort(row.net_revenue) }}</strong>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="loading" class="ofr-loading"><span class="ofr-spinner" /> Loading…</div>
          </div>

          <!-- Hover zones to scroll the table sideways (same as the leads list). -->
          <div
            v-show="showLeftZone"
            class="ofr-nav-zone ofr-nav-zone--left"
            title="Move left"
            aria-label="Move left"
            @mouseenter="startScroll(-1)"
            @mouseleave="stopScroll"
          >
            <span class="ofr-nav-arrow ofr-nav-arrow--left"><iconify-icon icon="lucide:chevron-left" class="ofr-nav-arrow-icon" /></span>
          </div>
          <div
            v-show="showRightZone"
            class="ofr-nav-zone ofr-nav-zone--right"
            title="Move right"
            aria-label="Move right"
            @mouseenter="startScroll(1)"
            @mouseleave="stopScroll"
          >
            <span class="ofr-nav-arrow ofr-nav-arrow--right"><iconify-icon icon="lucide:chevron-right" class="ofr-nav-arrow-icon" /></span>
          </div>
        </div>

        <div v-if="pagination.total" class="ofr-pager">
          <span class="ofr-pager-info">Showing {{ rangeStart }}–{{ rangeEnd }} of {{ pagination.total.toLocaleString('en-US') }}</span>
          <div class="ofr-pager-controls">
            <button type="button" class="ofr-page-btn" :disabled="page <= 1 || loading" aria-label="Previous page" @click="goTo(page - 1)">
              <iconify-icon icon="lucide:chevron-left" width="15" height="15" />
            </button>
            <button
              v-for="p in pageList"
              :key="p.key"
              type="button"
              class="ofr-page-btn"
              :class="{ 'is-on': p.n === page }"
              :disabled="p.gap || loading"
              @click="!p.gap && goTo(p.n)"
            >{{ p.gap ? '…' : p.n }}</button>
            <button type="button" class="ofr-page-btn" :disabled="page >= pagination.last_page || loading" aria-label="Next page" @click="goTo(page + 1)">
              <iconify-icon icon="lucide:chevron-right" width="15" height="15" />
            </button>
          </div>
        </div>

        <p class="ofr-footnote">
          Collected amount, outstanding amount and expenses are not shown because the CRM has no payment ledger.
          Agent commission comes from the commission breakdown saved on the deal; when there is none, from the
          agent's commission % on their user profile (same as the profile's Sales Performance). Deals missing amount,
          rate or agent % are left out of the agent, external and net totals.
        </p>
      </section>

      <!-- Filters (left, fixed; two fields per row) -->
      <aside class="ofr-panel ofr-filters" :class="{ 'is-open': filtersOpen }">
        <button type="button" class="ofr-panel-head" @click="filtersOpen = !filtersOpen">
          <span>Filters &amp; Search</span>
          <span v-if="activeCount" class="ofr-count">{{ activeCount }}</span>
        </button>

        <form class="ofr-filter-form" @submit.prevent="applyFilters">
          <div class="ofr-field">
            <span>Agent Name</span>
            <v-select
              v-model="draft.agent_id"
              :options="options.agents"
              :reduce="(a) => String(a.id)"
              label="name"
              placeholder="All agents"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs"
            >
              <template #option="a">
                <div class="ofr-agent-opt">
                  <img :src="avatarUrl(a.avatar)" alt="" class="ofr-agent-avatar" @error="onAvatarError" />
                  <span>{{ a.name }}</span>
                </div>
              </template>
              <template #selected-option="a">
                <div class="ofr-agent-opt">
                  <img :src="avatarUrl(a.avatar)" alt="" class="ofr-agent-avatar" @error="onAvatarError" />
                  <span class="ofr-agent-name">{{ a.name }}</span>
                </div>
              </template>
              <template #no-options>No agents found</template>
            </v-select>
          </div>
          <label class="ofr-field">
            <span>Client Name or Phone</span>
            <input v-model="draft.client" type="search" class="ofr-input" placeholder="Search name or phone" />
          </label>
          <label class="ofr-field">
            <span>Deal Number</span>
            <input v-model="draft.deal_number" type="search" class="ofr-input" placeholder="Search number" />
          </label>

          <div class="ofr-field">
            <span>Lead Source</span>
            <v-select
              v-model="draft.source"
              :options="options.sources"
              multiple
              :close-on-select="false"
              placeholder="All sources"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs ofr-vs--multi"
            >
              <template #no-options>No sources</template>
            </v-select>
          </div>

          <!-- Deal date: From and To on one row -->
          <div class="ofr-field ofr-field--full">
            <span>Deal Date</span>
            <div class="ofr-pair">
              <AdvancedDatePicker v-model="draft.date_from" date-only placeholder="From" class="ofr-date" />
              <AdvancedDatePicker v-model="draft.date_to" date-only placeholder="To" class="ofr-date" />
            </div>
          </div>

          <div class="ofr-field">
            <span>Branch</span>
            <v-select
              v-model="draft.branch_id"
              :options="branchOptions"
              :reduce="(o) => o.value"
              label="label"
              placeholder="All branches"
              :searchable="false"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs"
            />
          </div>
          <div class="ofr-field">
            <span>Deal Type</span>
            <v-select
              v-model="draft.deal_type"
              :options="dealTypeOptions"
              :reduce="(o) => o.value"
              label="label"
              placeholder="All types"
              :searchable="false"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs"
            />
          </div>

          <div class="ofr-field ofr-field--full">
            <span>Deal Amount (AED)</span>
            <div class="ofr-pair">
              <input v-model="draft.min_amount" type="number" min="0" class="ofr-input" placeholder="Min" />
              <input v-model="draft.max_amount" type="number" min="0" class="ofr-input" placeholder="Max" />
            </div>
          </div>
          <div class="ofr-field ofr-field--full">
            <span>Gross Commission (AED)</span>
            <div class="ofr-pair">
              <input v-model="draft.min_commission" type="number" min="0" class="ofr-input" placeholder="Min" />
              <input v-model="draft.max_commission" type="number" min="0" class="ofr-input" placeholder="Max" />
            </div>
          </div>

          <div class="ofr-field">
            <span>Deal Status</span>
            <div class="ofr-segment" role="group">
              <button type="button" :class="{ 'is-on': draft.deal_status === 'closed' }" @click="draft.deal_status = 'closed'">Closed</button>
              <button type="button" :class="{ 'is-on': draft.deal_status === 'all' }" @click="draft.deal_status = 'all'">All</button>
            </div>
          </div>

          <div class="ofr-field">
            <span>Financial Data</span>
            <v-select
              v-model="draft.data_status"
              :options="DATA_STATUS_OPTIONS"
              :reduce="(o) => o.value"
              label="label"
              :clearable="false"
              :searchable="false"
              :append-to-body="true"
              :calculate-position="placeMenu"
              class="ofr-vs"
            />
          </div>

          <div class="ofr-filter-actions ofr-field--full">
            <button type="submit" class="ofr-btn ofr-btn--primary" :disabled="loading">
              Apply Filters
              <span v-if="dirty" class="ofr-dirty-dot" title="Changes not applied yet" />
            </button>
            <button type="button" class="ofr-btn ofr-btn--ghost" :disabled="!activeCount && !dirty" @click="clearFilters">
              Clear Filters
            </button>
          </div>
        </form>
      </aside>
    </div>

    </template>

    <!-- Shared on mobile + desktop (desktop table / mobile cards both open it). -->
    <DealFinancialBreakdown
      :deal-id="openDealId"
      @close="openDealId = null"
      @open-deal="showDeal"
      @open-lead="showLead"
    />

    <!-- Same deal popup as the deals board (shared useDealViewModal state). -->
    <ViewDealModal
      v-if="showDealViewModal"
      v-model="showDealViewModal"
      :deal="dealViewPayload"
      :auto-edit-section="dealViewAutoEditSection"
      @deal-updated="fetchReport"
      @deal-deleted="fetchReport"
    />
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import DealFinancialBreakdown from '@/components/owner-financial/DealFinancialBreakdown.vue'
import OwnerFinancialMobileShell from '@/components/owner-financial/mobile/OwnerFinancialMobileShell.vue'
import { useOfrMobile } from '@/components/owner-financial/mobile/useOfrMobile.js'
import { aedShort, formatDate } from '@/components/owner-financial/format.js'
import { closeDealView, openDealView, useDealViewModal } from '@/composables/useDealViewModal.js'
import { openLeadView } from '@/composables/useLeadViewModal.js'
import api from '@/plugins/axios'

const ViewDealModal = defineAsyncComponent(() => import('@/components/kanban/deals/ViewDealModal.vue'))
const { showDealViewModal, dealViewPayload, dealViewAutoEditSection } = useDealViewModal()
const { isMobile } = useOfrMobile()

const DEFAULT_FILTERS = Object.freeze({
  search: '',
  agent_id: '',
  client: '',
  deal_number: '',
  date_from: '',
  date_to: '',
  source: [],
  branch_id: '',
  deal_type: '',
  deal_status: 'closed',
  min_amount: '',
  max_amount: '',
  min_commission: '',
  max_commission: '',
  data_status: 'all',
})

const PER_PAGE_OPTIONS = [25, 50, 100].map((n) => ({ value: n, label: `${n} / page` }))
const DATA_STATUS_OPTIONS = [
  { value: 'all', label: 'All' },
  { value: 'complete', label: 'Complete only' },
  { value: 'missing', label: 'Missing data only' },
]

const columns = [
  { key: 'deal_number', label: 'Deal Number', sort: 'deal_number' },
  { key: 'property', label: 'Property' },
  { key: 'client', label: 'Client' },
  { key: 'agent', label: 'Agent' },
  { key: 'branch', label: 'Branch' },
  { key: 'source', label: 'Lead Source' },
  { key: 'deal_date', label: 'Deal Date', sort: 'deal_date' },
  { key: 'deal_amount', label: 'Deal Amount', sort: 'deal_amount', num: true },
  { key: 'gross_commission', label: 'Gross Commission', sort: 'gross_commission', num: true },
  { key: 'agent_commission', label: 'Agent Commission', sort: 'agent_commission', num: true },
  { key: 'external_commission', label: 'External Commission', sort: 'external_commission', num: true },
  { key: 'net_revenue', label: 'Net Company Revenue', sort: 'net_revenue', num: true },
]

const cloneFilters = (f) => ({ ...f, source: [...(f.source || [])] })

const DEFAULT_AVATAR = '/storage/users/user.png'
const avatarUrl = (url) => url || DEFAULT_AVATAR
const onAvatarError = (e) => { if (!e.target.src.endsWith(DEFAULT_AVATAR)) e.target.src = DEFAULT_AVATAR }

/** KPI figure without the currency (shown as a small "AED" prefix). */
const figure = (v) => aedShort(v).replace(/^AED\s/, '')

/**
 * vue-select menus are appended to <body> so the scrolling filter panel can't clip them.
 * vue-select's own placement uses window scroll, but this CRM scrolls inside its layout
 * container, so menus landed at the bottom of the page. Place them with position: fixed
 * right under the field (or above it when there's no room), and close on any scroll.
 * The class scopes this page's menu styles (centered, theme colors).
 */
function placeMenu(menu, component) {
  menu.classList.add('ofr-vs-menu')
  const anchor = component.$refs?.toggle || component.$el
  const r = anchor.getBoundingClientRect()
  const gap = 4
  const below = window.innerHeight - r.bottom - gap - 8
  const above = r.top - gap - 8
  const openUp = below < 220 && above > below

  menu.style.position = 'fixed'
  menu.style.left = `${r.left}px`
  menu.style.width = `${r.width}px`
  menu.style.maxHeight = `${Math.max(140, Math.min(300, openUp ? above : below))}px`
  if (openUp) {
    menu.style.top = 'auto'
    menu.style.bottom = `${window.innerHeight - r.top + gap}px`
  } else {
    menu.style.bottom = 'auto'
    menu.style.top = `${r.bottom + gap}px`
  }

  const closeOnScroll = (e) => {
    if (menu.contains(e.target)) return // scrolling inside the menu itself
    window.removeEventListener('scroll', closeOnScroll, true)
    component.$refs?.search?.blur()
  }
  window.addEventListener('scroll', closeOnScroll, true)
}

// draft = what the user is editing in the panel; applied = what the report shows.
const draft = reactive(cloneFilters(DEFAULT_FILTERS))
const applied = reactive(cloneFilters(DEFAULT_FILTERS))

const options = ref({ agents: [], sources: [], branches: [], deal_types: [] })
const branchOptions = computed(() => options.value.branches.map((b) => ({ value: String(b.id), label: b.name })))
const dealTypeOptions = computed(() => [
  { value: 'sale', label: 'Sale' },
  ...options.value.deal_types.map((t) => ({ value: t.value, label: t.label })),
])

const summary = ref({
  deals: 0, closed_deals: 0, incomplete_deals: 0, complete_deals: 0, gross_deals: 0,
  gross_commission: 0, agent_commission: 0, external_commission: 0, net_revenue: 0,
})
const rows = ref([])
const pagination = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
const page = ref(1)
const perPage = ref(25)
const sort = ref('deal_date')
const direction = ref('desc')
const loading = ref(false)
const error = ref('')
const openDealId = ref(null)
const filtersOpen = ref(false)
const lastLoaded = ref('')

const insights = ref(null)
const insightsLoading = ref(false)
const insightsError = ref('')
let insightsSeq = 0
let insightsDirty = true

const exportingKind = ref(false)
const exportStatus = ref('')
const exportStatusError = ref(false)

const completeFoot = computed(() => {
  const s = summary.value
  if (!s.deals) return 'No deals in this view'
  return s.incomplete_deals
    ? `From ${s.complete_deals} of ${s.deals} deals with complete data`
    : `From all ${s.deals} deals`
})

const same = (a, b) => (Array.isArray(a) || Array.isArray(b)
  ? JSON.stringify([...(a || [])].sort()) === JSON.stringify([...(b || [])].sort())
  : String(a ?? '') === String(b ?? ''))
const activeCount = computed(() => Object.keys(DEFAULT_FILTERS).filter((k) => !same(applied[k], DEFAULT_FILTERS[k])).length)
const dirty = computed(() => Object.keys(DEFAULT_FILTERS).some((k) => !same(draft[k], applied[k])))

const mobileFilterSummary = computed(() => {
  const out = {}
  if (applied.search) out.Search = applied.search
  if (applied.agent_id) {
    const a = options.value.agents.find((x) => String(x.id) === String(applied.agent_id))
    out.Agent = a?.name || `#${applied.agent_id}`
  }
  if (applied.client) out.Client = applied.client
  if (applied.deal_number) out['Deal #'] = applied.deal_number
  if (applied.date_from || applied.date_to) {
    out.Period = [applied.date_from || '…', applied.date_to || '…'].join(' → ')
  }
  if (applied.branch_id) {
    const b = options.value.branches.find((x) => String(x.id) === String(applied.branch_id))
    out.Branch = b?.name || applied.branch_id
  }
  if (applied.deal_type) {
    out.Type = applied.deal_type === 'sale'
      ? 'Sale'
      : (options.value.deal_types.find((t) => t.value === applied.deal_type)?.label || applied.deal_type)
  }
  if (applied.source?.length) out.Source = applied.source.join(', ')
  if (applied.deal_status === 'all') out.Status = 'All deals'
  if (applied.data_status === 'missing') out.Data = 'Missing only'
  if (applied.data_status === 'complete') out.Data = 'Complete only'
  return out
})

const rangeStart = computed(() => (pagination.value.current_page - 1) * pagination.value.per_page + 1)
const rangeEnd = computed(() => Math.min(pagination.value.total, pagination.value.current_page * pagination.value.per_page))
const pageList = computed(() => {
  const last = pagination.value.last_page
  const cur = page.value
  const nums = new Set([1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last))
  const sorted = [...nums].sort((a, b) => a - b)
  const out = []
  sorted.forEach((n, i) => {
    if (i && n - sorted[i - 1] > 1) out.push({ key: `gap${n}`, gap: true })
    out.push({ key: `p${n}`, n })
  })
  return out
})

function params() {
  const p = { page: page.value, per_page: perPage.value, sort: sort.value, direction: direction.value }
  Object.entries(applied).forEach(([k, v]) => {
    if (Array.isArray(v)) {
      if (v.length) p[k] = v
      return
    }
    const value = typeof v === 'string' ? v.trim() : v
    if (value !== '' && value !== null && value !== undefined) p[k] = value
  })
  return p
}

let seq = 0
async function fetchReport() {
  const mine = ++seq
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/reports/owner-financial', { params: params() })
    if (mine !== seq) return
    const data = res.data?.data || {}
    summary.value = data.summary || summary.value
    rows.value = data.rows || []
    pagination.value = data.pagination || pagination.value
    lastLoaded.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    if (pagination.value.total && page.value > pagination.value.last_page) {
      page.value = pagination.value.last_page
      fetchReport()
    }
  } catch (e) {
    if (mine === seq) error.value = e?.response?.data?.message || 'Failed to load the report'
  } finally {
    if (mine === seq) {
      loading.value = false
      nextTick(updateScrollArrows)
    }
  }
}

async function fetchOptions() {
  try {
    const res = await api.get('/reports/owner-financial/options')
    options.value = { ...options.value, ...(res.data?.data || {}) }
  } catch {
    // Filter lists stay empty; the report request shows its own error.
  }
}

function markInsightsDirty() {
  insightsDirty = true
}

function applyFilters() {
  Object.assign(applied, cloneFilters(draft))
  page.value = 1
  filtersOpen.value = false
  markInsightsDirty()
  fetchReport()
}

function clearFilters() {
  Object.assign(draft, cloneFilters(DEFAULT_FILTERS))
  applyFilters()
}

function toggleMissing() {
  const next = applied.data_status === 'missing' ? 'all' : 'missing'
  draft.data_status = next
  applied.data_status = next
  page.value = 1
  markInsightsDirty()
  fetchReport()
}

function applyMobileFilters(payload) {
  const next = cloneFilters({ ...DEFAULT_FILTERS, ...payload })
  Object.assign(draft, next)
  Object.assign(applied, cloneFilters(next))
  page.value = 1
  markInsightsDirty()
  fetchReport()
}

function applyMobileSearch(q) {
  const value = String(q || '').trim()
  const replacesAgent = value !== '' && !!applied.agent_id
  if (same(applied.search, value) && !replacesAgent) return
  draft.search = value
  applied.search = value
  // Typing a free-text query replaces a pinned agent filter.
  if (value) {
    draft.agent_id = ''
    applied.agent_id = ''
  }
  page.value = 1
  markInsightsDirty()
  fetchReport()
}

function clearMobileSearch() {
  draft.search = ''
  applied.search = ''
  page.value = 1
  markInsightsDirty()
  fetchReport()
}

function selectMobileAgent(agent) {
  draft.search = ''
  applied.search = ''
  draft.agent_id = String(agent.id)
  applied.agent_id = String(agent.id)
  page.value = 1
  markInsightsDirty()
  fetchReport()
}

function runMobileReport({ sort: s, direction: d }) {
  sort.value = s || sort.value
  direction.value = d || direction.value
  page.value = 1
  fetchReport()
}

function setSort(key) {
  if (sort.value === key) {
    direction.value = direction.value === 'desc' ? 'asc' : 'desc'
  } else {
    sort.value = key
    direction.value = 'desc'
  }
  page.value = 1
  fetchReport()
}

async function fetchInsights() {
  if (!insightsDirty && insights.value) return
  const mine = ++insightsSeq
  insightsLoading.value = true
  insightsError.value = ''
  try {
    const { page: _p, per_page: _pp, ...query } = params()
    const res = await api.get('/reports/owner-financial/insights', { params: query })
    if (mine !== insightsSeq) return
    insights.value = res.data?.data || null
    insightsDirty = false
  } catch (e) {
    if (mine === insightsSeq) {
      insightsError.value = e?.response?.data?.message || 'Failed to load insights'
    }
  } finally {
    if (mine === insightsSeq) insightsLoading.value = false
  }
}

watch(perPage, () => { page.value = 1; fetchReport() })

function goTo(p) {
  page.value = p
  fetchReport()
}

function toggleSort(key) {
  setSort(key)
}

function sortIcon(key) {
  if (sort.value !== key) return 'lucide:chevrons-up-down'
  return direction.value === 'asc' ? 'lucide:arrow-up' : 'lucide:arrow-down'
}

// ---- Sideways table scrolling: hover the edge zones (same behaviour as the leads list).
const SCROLL_SPEED = 10
const SCROLL_TICK_MS = 16
const tableWrapRef = ref(null)
const showLeftZone = ref(false)
const showRightZone = ref(false)
let scrollTimer = null

function updateScrollArrows() {
  const el = tableWrapRef.value
  if (!el) return
  const canScroll = el.scrollWidth - el.clientWidth > 2
  showLeftZone.value = canScroll && el.scrollLeft > 2
  showRightZone.value = canScroll && el.scrollWidth - el.clientWidth > el.scrollLeft + 2
}

function startScroll(dir) {
  stopScroll()
  scrollTimer = setInterval(() => {
    const el = tableWrapRef.value
    if (!el) return
    el.scrollLeft += dir * SCROLL_SPEED
    updateScrollArrows()
  }, SCROLL_TICK_MS)
}

function stopScroll() {
  if (scrollTimer) {
    clearInterval(scrollTimer)
    scrollTimer = null
  }
}

let resizeObserver = null

// Guard: the layout wrapper hides horizontal overflow but can still be scrolled sideways
// (e.g. by focusing a field). Keep it at 0 so the filters are never cut off on the left.
let layoutScroller = null
const resetLayoutScroll = () => { if (layoutScroller && layoutScroller.scrollLeft !== 0) layoutScroller.scrollLeft = 0 }

const exporting = ref(false)

async function downloadBlob(path, filename, mime) {
  const { page: _p, per_page: _pp, ...query } = params()
  const res = await api.get(path, { params: query, responseType: 'blob' })
  const url = window.URL.createObjectURL(new Blob([res.data], { type: mime }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

async function parseBlobError(e, fallback = 'Export failed') {
  try { return JSON.parse(await e?.response?.data?.text())?.message || fallback } catch { return fallback }
}

/** Excel of every deal for the applied filters + sort (all pages), plus a KPI summary sheet. */
async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    await downloadBlob(
      '/reports/owner-financial/export',
      `owner-financial-report-${new Date().toISOString().slice(0, 10)}.xlsx`,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    )
  } catch (e) {
    error.value = await parseBlobError(e)
  } finally {
    exporting.value = false
  }
}

async function exportExcelMobile() {
  exportingKind.value = 'excel'
  exportStatus.value = ''
  exportStatusError.value = false
  try {
    await downloadBlob(
      '/reports/owner-financial/export',
      `owner-financial-report-${new Date().toISOString().slice(0, 10)}.xlsx`,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    )
    exportStatus.value = 'Excel downloaded'
  } catch (e) {
    exportStatus.value = await parseBlobError(e)
    exportStatusError.value = true
  } finally {
    exportingKind.value = false
  }
}

async function exportPdfMobile() {
  exportingKind.value = 'pdf'
  exportStatus.value = ''
  exportStatusError.value = false
  try {
    await downloadBlob(
      '/reports/owner-financial/pdf',
      `owner-financial-report-${new Date().toISOString().slice(0, 10)}.pdf`,
      'application/pdf',
    )
    exportStatus.value = 'PDF downloaded'
  } catch (e) {
    exportStatus.value = await parseBlobError(e, 'PDF export failed')
    exportStatusError.value = true
  } finally {
    exportingKind.value = false
  }
}

function openDeal(id) {
  openDealId.value = id
}

// Deal / lead popups. The breakdown closes first so the popup isn't hidden under it.
function showDeal(row) {
  openDealId.value = null
  openDealView({ id: row.id, deal_type: row.deal_type }, { autoEditSection: false })
}

function showLead(leadId) {
  if (!leadId) return
  openDealId.value = null
  openLeadView(leadId)
}

onMounted(() => {
  fetchOptions()
  fetchReport()
  if (window.ResizeObserver && tableWrapRef.value) {
    resizeObserver = new ResizeObserver(() => updateScrollArrows())
    resizeObserver.observe(tableWrapRef.value)
  }
  layoutScroller = tableWrapRef.value?.closest('.dashboard-main-router') || null
  layoutScroller?.addEventListener('scroll', resetLayoutScroll, { passive: true })
  resetLayoutScroll()
})

onBeforeUnmount(() => {
  stopScroll()
  resizeObserver?.disconnect()
  layoutScroller?.removeEventListener('scroll', resetLayoutScroll)
  // The deal popup state is shared app-wide; don't leave it open for the next page.
  closeDealView()
})
</script>

<style scoped>
.ofr-page.ofr-page {
  --ofr-p: var(--color-primary, #733E87);
  --ofr-p-rgb: var(--color-primary-rgb, 115, 62, 135);
  --ofr-ink: var(--color-primary-ink, #3f2148);
  --ofr-soft: var(--color-primary-soft, #f7f2f8);
  --ofr-border: var(--color-primary-border, #e7dcec);
  --ofr-muted-line: var(--color-primary-muted, #d4c0de);
  --ofr-text: var(--color-text-primary, #1e1b2e);
  --ofr-text-2: var(--color-text-secondary, #6b6678);
  --ofr-text-3: var(--color-text-muted, #8b8798);
  --ofr-warn: #c2283a;
  --ofr-warn-soft: #fdf0f2;
  --ofr-warn-line: #f5c6cd;
  --ofr-danger: var(--color-danger, #dc3545);
  background: #fff !important;
  background-image: none !important;
  min-height: 100vh !important;
  padding: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 14px !important;
  color: var(--ofr-text) !important;
}

/* Mobile shell owns spacing; avoid double page chrome. */
.ofr-page.ofr-page.ofr-page--mobile {
  gap: 0 !important;
  background: #faf8fb !important;
}

/* The layout gives pages their content width; pin this one to the wrapper so the wide
   table can never push the page (and the filters) sideways. Only the table scrolls. */
.ofr-page.ofr-page { width: 100% !important; max-width: 100% !important; min-width: 0 !important; align-self: stretch !important; box-sizing: border-box !important; overflow-x: clip !important; }
.ofr-body > * { min-width: 0 !important; max-width: 100% !important; }

/* Header */
.ofr-header { display: flex !important; flex-wrap: wrap !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; background: #fff !important; border-bottom: 1px solid var(--ofr-border) !important; padding: 0 0 12px !important; margin: 0 !important; }
.ofr-header-title { display: flex !important; align-items: center !important; gap: 12px !important; min-width: 0 !important; }
.ofr-header-icon { width: 42px !important; height: 42px !important; border-radius: 12px !important; display: grid !important; place-items: center !important; background: var(--ofr-soft) !important; color: var(--ofr-p) !important; border: 1px solid var(--ofr-border) !important; flex-shrink: 0 !important; }
.ofr-title { margin: 0 !important; font-size: 20px !important; font-weight: 700 !important; letter-spacing: .03em !important; text-transform: uppercase !important; color: var(--ofr-ink) !important; line-height: 1.2 !important; }
.ofr-hint { margin: 2px 0 0 !important; font-size: 12.5px !important; color: var(--ofr-text-3) !important; }
.ofr-header-meta { display: flex !important; align-items: center !important; gap: 10px !important; flex-wrap: wrap !important; }
.ofr-scope-chip { display: inline-flex !important; align-items: center !important; height: 28px !important; padding: 0 12px !important; border-radius: 999px !important; border: 1px solid var(--ofr-border) !important; background: var(--ofr-soft) !important; color: var(--ofr-p) !important; font-size: 12px !important; font-weight: 600 !important; }
.ofr-updated { font-size: 12px !important; color: var(--ofr-text-3) !important; }

/* KPIs */
.ofr-kpis { display: grid !important; grid-template-columns: 1fr !important; gap: 12px !important; }
.ofr-kpi { container-type: inline-size !important; position: relative !important; display: flex !important; flex-direction: column !important; gap: 10px !important; min-width: 0 !important; height: 100% !important; padding: 16px 18px !important; background: #fff !important; border: 1px solid var(--ofr-border) !important; border-radius: 16px !important; text-align: left !important; font: inherit !important; color: inherit !important; box-shadow: 0 1px 2px rgba(var(--ofr-p-rgb), 0.04) !important; transition: border-color .15s ease, box-shadow .15s ease !important; }
.ofr-kpi:hover { box-shadow: 0 8px 20px rgba(var(--ofr-p-rgb), 0.08) !important; }
.ofr-kpi-top { display: flex !important; align-items: center !important; gap: 10px !important; min-width: 0 !important; }
.ofr-kpi-icon { width: 36px !important; height: 36px !important; border-radius: 11px !important; display: grid !important; place-items: center !important; flex-shrink: 0 !important; background: var(--ofr-soft) !important; color: var(--ofr-p) !important; border: 1px solid var(--ofr-border) !important; }
.ofr-kpi-label { min-width: 0 !important; font-size: 13px !important; font-weight: 600 !important; color: var(--ofr-text-2) !important; line-height: 1.25 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }
.ofr-kpi-value { display: flex !important; align-items: baseline !important; flex-wrap: nowrap !important; gap: 5px !important; min-width: 0 !important; margin: 0 !important; font-size: clamp(15px, 8.5cqi, 20px) !important; font-weight: 800 !important; color: var(--ofr-ink) !important; font-variant-numeric: tabular-nums !important; line-height: 1.1 !important; letter-spacing: -0.02em !important; white-space: nowrap !important; }
.ofr-cur { font-size: 0.5em !important; font-weight: 700 !important; letter-spacing: .04em !important; color: var(--ofr-text-3) !important; }
.ofr-unit { font-size: 0.55em !important; font-weight: 700 !important; letter-spacing: 0 !important; color: var(--ofr-text-2) !important; }
.ofr-kpi-foot { margin-top: auto !important; display: flex !important; flex-direction: column !important; gap: 4px !important; font-size: 11.5px !important; line-height: 1.35 !important; color: var(--ofr-text-3) !important; }
.ofr-kpi-tag { display: inline-flex !important; align-items: center !important; gap: 4px !important; align-self: flex-start !important; padding: 2px 8px !important; border-radius: 999px !important; background: rgba(var(--ofr-p-rgb), 0.1) !important; font-size: 10.5px !important; font-weight: 800 !important; text-transform: uppercase !important; letter-spacing: .05em !important; color: var(--ofr-p) !important; }

.ofr-kpi--net { background: linear-gradient(135deg, #fff 0%, var(--ofr-soft) 100%) !important; border: 1.5px solid var(--ofr-muted-line) !important; box-shadow: 0 10px 26px rgba(var(--ofr-p-rgb), 0.14) !important; overflow: hidden !important; }
.ofr-kpi--net::before { content: '' !important; position: absolute !important; left: 0 !important; top: 0 !important; bottom: 0 !important; width: 4px !important; background: var(--gradient-primary, var(--ofr-p)) !important; }
.ofr-kpi--net .ofr-kpi-icon { background: var(--gradient-primary, var(--ofr-p)) !important; color: #fff !important; border-color: transparent !important; }
.ofr-kpi--net .ofr-kpi-label { color: var(--ofr-ink) !important; font-weight: 700 !important; }
.ofr-kpi--net .ofr-kpi-value { font-size: clamp(17px, 9cqi, 23px) !important; color: var(--ofr-p) !important; }
.ofr-kpi--net .ofr-cur { color: var(--ofr-p) !important; opacity: .7 !important; }

.ofr-kpi--missing { cursor: pointer !important; }
.ofr-kpi--missing .ofr-kpi-icon { background: #fdecec !important; color: var(--ofr-danger) !important; border-color: #f5c2c0 !important; }
.ofr-kpi--missing .ofr-kpi-value { color: var(--ofr-warn) !important; align-items: center !important; }
.ofr-kpi--missing:hover { border-color: var(--ofr-warn-line) !important; box-shadow: 0 8px 20px rgba(194, 40, 58, 0.1) !important; }
.ofr-kpi--missing.is-active { border-color: var(--ofr-warn) !important; box-shadow: 0 0 0 3px rgba(194, 40, 58, 0.12) !important; }
.ofr-kpi--missing.is-zero .ofr-kpi-icon { background: var(--ofr-soft) !important; color: var(--ofr-text-3) !important; border-color: var(--ofr-border) !important; }
.ofr-kpi--missing.is-zero .ofr-kpi-value { color: var(--ofr-text-2) !important; }
.ofr-badge-incomplete { margin-left: 6px !important; align-self: center !important; font-size: 9.5px !important; font-weight: 800 !important; letter-spacing: .06em !important; text-transform: uppercase !important; color: var(--ofr-danger) !important; background: #fdecec !important; border: 1px solid #f5c2c0 !important; border-radius: 999px !important; padding: 3px 8px !important; line-height: 1 !important; }

/* Body: table (left) + filters (right) */
.ofr-body { display: grid !important; grid-template-columns: 1fr !important; gap: 14px !important; align-items: start !important; min-width: 0 !important; }
.ofr-panel { background: #fff !important; border: 1px solid var(--ofr-border) !important; border-radius: 14px !important; min-width: 0 !important; }

/* Filters */
.ofr-filters { order: -1 !important; }
.ofr-panel-head { width: 100% !important; display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; padding: 14px 16px !important; border: none !important; background: transparent !important; font: inherit !important; font-size: 14px !important; font-weight: 700 !important; letter-spacing: .03em !important; text-transform: uppercase !important; color: var(--ofr-ink) !important; cursor: pointer !important; }
.ofr-count { min-width: 20px !important; height: 20px !important; border-radius: 999px !important; padding: 0 6px !important; display: inline-grid !important; place-items: center !important; font-size: 11px !important; font-weight: 700 !important; background: var(--ofr-p) !important; color: #fff !important; }
.ofr-filter-form { display: none !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 12px 10px !important; padding: 0 14px 14px !important; }
.ofr-filters.is-open .ofr-filter-form { display: grid !important; }
.ofr-field--full { grid-column: 1 / -1 !important; }

.ofr-field { display: flex !important; flex-direction: column !important; gap: 5px !important; margin: 0 !important; min-width: 0 !important; }
.ofr-field > span { font-size: 12px !important; font-weight: 600 !important; color: var(--ofr-text-2) !important; }
.ofr-pair { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 8px !important; min-width: 0 !important; }
.ofr-input { height: 38px !important; width: 100% !important; min-width: 0 !important; box-sizing: border-box !important; border: 1px solid var(--ofr-border) !important; border-radius: 10px !important; padding: 0 10px !important; font-size: 13px !important; color: var(--ofr-text) !important; background: #fff !important; text-align: center !important; transition: border-color .15s ease, box-shadow .15s ease !important; }
.ofr-input::placeholder { color: var(--ofr-text-3) !important; }
.ofr-input:focus { outline: none !important; border-color: var(--ofr-p) !important; box-shadow: 0 0 0 3px rgba(var(--ofr-p-rgb), 0.14) !important; }

/* Shared date picker, themed */
.ofr-date :deep(.advanced-date-trigger) { min-height: 38px !important; justify-content: flex-start !important; text-align: left !important; border: 1px solid var(--ofr-border) !important; border-radius: 10px !important; background: #fff !important; }
.ofr-date :deep(.advanced-date-trigger:hover) { border-color: var(--ofr-muted-line) !important; background: #fff !important; }
.ofr-date :deep(.advanced-date-icon) { color: var(--ofr-p) !important; }

/* vue-select, themed */
.ofr-vs { width: 100% !important; }
.ofr-vs :deep(.vs__dropdown-toggle) { min-height: 38px !important; padding: 0 6px !important; border: 1px solid var(--ofr-border) !important; border-radius: 10px !important; background: #fff !important; }
.ofr-vs.vs--open :deep(.vs__dropdown-toggle) { border-color: var(--ofr-p) !important; box-shadow: 0 0 0 3px rgba(var(--ofr-p-rgb), 0.14) !important; }
.ofr-vs :deep(.vs__selected-options) { justify-content: flex-start !important; flex-wrap: wrap !important; padding: 0 !important; }
.ofr-vs :deep(.vs__selected) { margin: 3px 2px !important; padding: 0 4px !important; border: none !important; background: transparent !important; color: var(--ofr-text) !important; font-size: 13px !important; font-weight: 600 !important; max-width: 100% !important; }
.ofr-vs :deep(.vs__search), .ofr-vs :deep(.vs__search:focus) { margin: 0 !important; padding: 0 4px !important; font-size: 13px !important; text-align: left !important; flex-grow: 1 !important; }
.ofr-vs :deep(.vs__search::placeholder) { color: var(--ofr-text-3) !important; text-align: left !important; }
.ofr-vs :deep(.vs__open-indicator), .ofr-vs :deep(.vs__clear), .ofr-vs :deep(.vs__deselect) { fill: var(--ofr-p) !important; }
/* Smaller dropdown arrow and clear (×). */
.ofr-vs :deep(.vs__open-indicator) { transform: scale(0.75) !important; }
.ofr-vs.vs--open :deep(.vs__open-indicator) { transform: rotate(180deg) scale(0.75) !important; }
.ofr-vs :deep(.vs__clear) { transform: scale(0.75) !important; margin-right: 4px !important; }
.ofr-vs :deep(.vs__actions) { padding: 0 6px 0 2px !important; gap: 2px !important; }
.ofr-vs--multi :deep(.vs__selected) { background: var(--ofr-soft) !important; border: 1px solid var(--ofr-border) !important; border-radius: 6px !important; padding: 1px 6px !important; color: var(--ofr-p) !important; font-size: 12px !important; }
.ofr-vs--sm { width: 120px !important; }
.ofr-vs--sm :deep(.vs__dropdown-toggle) { min-height: 32px !important; border-radius: 8px !important; }
.ofr-vs--sm :deep(.vs__selected) { font-size: 12px !important; }

.ofr-agent-opt { display: flex !important; align-items: center !important; justify-content: flex-start !important; gap: 8px !important; min-width: 0 !important; font-size: 13px !important; }
.ofr-agent-avatar { width: 22px !important; height: 22px !important; border-radius: 50% !important; object-fit: cover !important; flex-shrink: 0 !important; border: 1px solid var(--ofr-border) !important; }
.ofr-agent-name { overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; color: var(--ofr-text) !important; font-weight: 600 !important; }

/* Tabs */
.ofr-segment { display: grid !important; grid-template-columns: 1fr 1fr !important; padding: 3px !important; gap: 3px !important; background: var(--ofr-soft) !important; border: 1px solid var(--ofr-border) !important; border-radius: 10px !important; }
.ofr-segment button { display: flex !important; align-items: center !important; justify-content: center !important; text-align: center !important; height: 32px !important; border: none !important; border-radius: 8px !important; background: transparent !important; font-size: 12.5px !important; font-weight: 600 !important; color: var(--ofr-text-2) !important; cursor: pointer !important; }
.ofr-segment button.is-on { background: #fff !important; color: var(--ofr-p) !important; box-shadow: 0 1px 3px rgba(var(--ofr-p-rgb), 0.18) !important; }

.ofr-filter-actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 8px !important; margin-top: 4px !important; }
.ofr-btn { position: relative !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; height: 38px !important; border-radius: 10px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; border: 1px solid transparent !important; text-align: center !important; }
.ofr-btn:disabled { opacity: .55 !important; cursor: not-allowed !important; }
.ofr-btn--primary { background: var(--gradient-primary, var(--ofr-p)) !important; color: #fff !important; box-shadow: 0 4px 12px rgba(var(--ofr-p-rgb), 0.25) !important; }
.ofr-btn--primary:hover:not(:disabled) { filter: brightness(1.07) !important; }
.ofr-btn--ghost { background: #fff !important; color: var(--ofr-p) !important; border-color: var(--ofr-border) !important; }
.ofr-btn--ghost:hover:not(:disabled) { background: var(--ofr-soft) !important; }
.ofr-btn--export { height: 32px !important; padding: 0 12px !important; font-size: 12.5px !important; background: var(--gradient-primary, var(--ofr-p)) !important; color: #fff !important; box-shadow: 0 3px 10px rgba(var(--ofr-p-rgb), 0.22) !important; }
.ofr-btn--export:hover:not(:disabled) { filter: brightness(1.07) !important; }
.ofr-dirty-dot { width: 8px !important; height: 8px !important; border-radius: 50% !important; background: #fff !important; box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.45) !important; }
.ofr-spin { animation: ofr-spin .8s linear infinite !important; }

/* Deals panel */
.ofr-deals { display: flex !important; flex-direction: column !important; gap: 12px !important; padding: 14px !important; }
.ofr-deals-head { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 10px !important; flex-wrap: wrap !important; }
.ofr-h2 { margin: 0 !important; font-size: 14px !important; font-weight: 700 !important; letter-spacing: .03em !important; text-transform: uppercase !important; color: var(--ofr-ink) !important; }
.ofr-deals-tools { display: flex !important; align-items: center !important; gap: 10px !important; flex-wrap: wrap !important; }
.ofr-total { font-size: 12px !important; font-weight: 600 !important; color: var(--ofr-text-2) !important; }

.ofr-error { display: flex !important; align-items: center !important; gap: 8px !important; background: #fdecec !important; color: #b3261e !important; border: 1px solid #f5c2c0 !important; border-radius: 10px !important; padding: 10px 12px !important; font-size: 13px !important; }
.ofr-link { border: none !important; background: transparent !important; color: #b3261e !important; text-decoration: underline !important; cursor: pointer !important; margin-left: auto !important; font-weight: 600 !important; }

/* Table: only this area scrolls (both ways); the header row stays visible. */
.ofr-table-shell { position: relative !important; min-width: 0 !important; }
.ofr-table-wrap { position: relative !important; overflow: auto !important; max-height: calc(100vh - 170px) !important; border: 1px solid var(--ofr-border) !important; border-radius: 12px !important; scrollbar-width: thin !important; }
.ofr-table-wrap.is-loading .ofr-table { opacity: .5 !important; }
.ofr-loading { position: absolute !important; top: 56px !important; left: 50% !important; transform: translateX(-50%) !important; display: inline-flex !important; align-items: center !important; gap: 8px !important; background: #fff !important; border: 1px solid var(--ofr-border) !important; border-radius: 999px !important; padding: 6px 14px !important; font-size: 12px !important; font-weight: 600 !important; color: var(--ofr-text-2) !important; box-shadow: 0 6px 16px rgba(var(--ofr-p-rgb), 0.12) !important; }
.ofr-spinner { width: 14px !important; height: 14px !important; border-radius: 50% !important; border: 2px solid var(--ofr-border) !important; border-top-color: var(--ofr-p) !important; animation: ofr-spin .8s linear infinite !important; }
@keyframes ofr-spin { to { transform: rotate(360deg) !important; } }

.ofr-table { width: 100% !important; border-collapse: separate !important; border-spacing: 0 !important; min-width: 1120px !important; margin: 0 !important; }
.ofr-table th { position: sticky !important; top: 0 !important; z-index: 1 !important; text-align: left !important; vertical-align: bottom !important; font-size: 11.5px !important; font-weight: 700 !important; color: var(--ofr-ink) !important; padding: 9px 6px !important; background: var(--ofr-soft) !important; border-bottom: 1px solid var(--ofr-border) !important; line-height: 1.25 !important; }
.ofr-table th:first-child, .ofr-table td:first-child { padding-left: 12px !important; }
.ofr-table th:last-child, .ofr-table td:last-child { padding-right: 12px !important; }
.ofr-table td { padding: 9px 6px !important; border-bottom: 1px solid #f1ecf4 !important; font-size: 12.5px !important; color: var(--ofr-text) !important; vertical-align: middle !important; background: #fff !important; }
.ofr-table tbody tr:last-child td { border-bottom: none !important; }
.ofr-sort { display: inline-flex !important; align-items: center !important; gap: 3px !important; border: none !important; background: transparent !important; padding: 0 !important; font: inherit !important; color: inherit !important; cursor: pointer !important; text-align: inherit !important; }
.ofr-sort iconify-icon { color: var(--ofr-text-3) !important; flex-shrink: 0 !important; }
.ofr-sort.is-active, .ofr-sort.is-active iconify-icon { color: var(--ofr-p) !important; }
.ofr-num { text-align: right !important; white-space: nowrap !important; font-variant-numeric: tabular-nums !important; }
th.ofr-num .ofr-sort { justify-content: flex-end !important; }
.ofr-nowrap { white-space: nowrap !important; }
.ofr-sub { display: block !important; font-size: 10.5px !important; color: var(--ofr-text-3) !important; }

/* Truncated cells: hover shows the full text (title). */
.ofr-trunc { display: block !important; max-width: 130px !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
.ofr-trunc--deal { max-width: 105px !important; }
.ofr-trunc--property { max-width: 140px !important; }
.ofr-property { color: var(--ofr-p) !important; font-weight: 600 !important; cursor: help !important; }
.ofr-trunc--deal { cursor: pointer !important; }

.ofr-row { cursor: pointer !important; }
.ofr-row:hover td { background: #fcfafd !important; }
.ofr-row.is-selected td { background: var(--ofr-soft) !important; }
.ofr-row:focus-visible { outline: none !important; }
.ofr-row:focus-visible td { background: var(--ofr-soft) !important; }
.ofr-row.is-incomplete td:first-child { box-shadow: inset 3px 0 0 var(--ofr-warn) !important; }

.ofr-cell-link { border: none !important; background: transparent !important; padding: 0 !important; font: inherit !important; font-weight: 700 !important; color: var(--ofr-p) !important; text-decoration: underline !important; text-decoration-color: var(--ofr-muted-line) !important; text-underline-offset: 3px !important; text-align: left !important; }
.ofr-cell-link:hover { text-decoration-color: var(--ofr-p) !important; }

th.ofr-col-net { background: var(--color-primary-muted, #d4c0de) !important; color: var(--ofr-ink) !important; }
td.ofr-col-net { background: var(--ofr-soft) !important; }
.ofr-row:hover td.ofr-col-net { background: #f1e8f4 !important; }
td.ofr-col-net strong { color: var(--ofr-ink) !important; font-size: 13px !important; font-weight: 800 !important; }
.ofr-missing { color: var(--ofr-warn) !important; font-weight: 600 !important; }
.ofr-pill { display: inline-flex !important; align-items: center !important; gap: 4px !important; padding: 3px 8px !important; border-radius: 999px !important; font-size: 11px !important; font-weight: 700 !important; color: var(--ofr-warn) !important; background: var(--ofr-warn-soft) !important; border: 1px solid var(--ofr-warn-line) !important; }
.ofr-empty { text-align: center !important; padding: 36px 10px !important; color: var(--ofr-text-3) !important; }
.ofr-empty iconify-icon { display: block !important; margin: 0 auto 6px !important; color: var(--ofr-muted-line) !important; }

/* Edge hover zones (same look as the leads list arrows), theme colored. */
.ofr-nav-zone { position: absolute !important; top: 0 !important; bottom: 0 !important; z-index: 3 !important; width: 44px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; }
.ofr-nav-zone--left { left: 0 !important; }
.ofr-nav-zone--right { right: 0 !important; }
.ofr-nav-arrow { width: 34px !important; height: 68px !important; display: flex !important; align-items: center !important; justify-content: center !important; background: rgba(255, 255, 255, 0.85) !important; box-shadow: 0 2px 12px rgba(var(--ofr-p-rgb), 0.18) !important; pointer-events: none !important; transition: box-shadow .2s ease, background .2s ease !important; }
.ofr-nav-zone:hover .ofr-nav-arrow { background: #fff !important; box-shadow: 0 3px 16px rgba(var(--ofr-p-rgb), 0.28) !important; }
.ofr-nav-arrow--left { border-radius: 0 34px 34px 0 !important; padding-left: 4px !important; }
.ofr-nav-arrow--right { border-radius: 34px 0 0 34px !important; padding-right: 4px !important; }
.ofr-nav-arrow-icon { font-size: 24px !important; color: var(--ofr-p) !important; }

.ofr-pager { display: flex !important; flex-direction: column !important; gap: 8px !important; align-items: center !important; }
.ofr-pager-info { font-size: 12px !important; color: var(--ofr-text-2) !important; }
.ofr-pager-controls { display: flex !important; align-items: center !important; gap: 4px !important; flex-wrap: wrap !important; }
.ofr-page-btn { min-width: 32px !important; height: 32px !important; padding: 0 8px !important; border-radius: 8px !important; border: 1px solid var(--ofr-border) !important; background: #fff !important; color: var(--ofr-text-2) !important; font-size: 12.5px !important; font-weight: 600 !important; display: inline-grid !important; place-items: center !important; cursor: pointer !important; }
.ofr-page-btn:hover:not(:disabled) { border-color: var(--ofr-p) !important; color: var(--ofr-p) !important; }
.ofr-page-btn.is-on { background: var(--ofr-p) !important; border-color: var(--ofr-p) !important; color: #fff !important; }
.ofr-page-btn:disabled:not(.is-on) { opacity: .45 !important; cursor: not-allowed !important; }
.ofr-footnote { margin: 0 !important; font-size: 11.5px !important; color: var(--ofr-text-3) !important; }

@media (max-width: 1099px) {
  .ofr-nav-zone { display: none !important; }
}

@media (min-width: 640px) {
  .ofr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
  .ofr-kpi--net { grid-column: span 2 !important; }
  .ofr-pager { flex-direction: row !important; justify-content: space-between !important; }
}

@media (min-width: 1100px) {
  .ofr-body { grid-template-columns: 360px minmax(0, 1fr) !important; }
  /* Filters fixed on the right; only the table scrolls. */
  .ofr-filters { order: -1 !important; position: sticky !important; top: 12px !important; max-height: calc(100vh - 24px) !important; overflow-y: auto !important; scrollbar-width: thin !important; }
  .ofr-panel-head { cursor: default !important; pointer-events: none !important; position: sticky !important; top: 0 !important; z-index: 2 !important; background: #fff !important; }
  .ofr-filter-form { display: grid !important; }
}

/* 1100–1399: 3 + 2 cards so amounts stay large; 1400+: one row of five. */
@media (min-width: 1100px) and (max-width: 1399px) {
  .ofr-kpis { grid-template-columns: repeat(6, minmax(0, 1fr)) !important; }
  .ofr-kpi, .ofr-kpi--net { grid-column: span 2 !important; }
  .ofr-kpis > :nth-child(4), .ofr-kpis > :nth-child(5) { grid-column: span 3 !important; }
}

@media (min-width: 1400px) {
  .ofr-kpis { grid-template-columns: 1.3fr repeat(4, minmax(0, 1fr)) !important; }
  .ofr-kpi--net { grid-column: auto !important; }
}
</style>

<!-- vue-select menus live in <body>; placeMenu() tags this page's menus with .ofr-vs-menu. -->
<style>
.ofr-vs-menu.vs__dropdown-menu { border: 1px solid var(--color-primary-border, #e7dcec) !important; border-radius: 10px !important; box-shadow: 0 12px 28px rgba(var(--color-primary-rgb, 115, 62, 135), 0.16) !important; padding: 4px !important; z-index: 3000 !important; overflow-y: auto !important; margin: 0 !important; }
/* Lighter layout padding around this page (the router wrapper pads every page 12px 14px by default). */
body #app main.dashboard-main > .dashboard-main-router.dashboard-main-router:has(.ofr-page),
body.app-has-video-bg #app main.dashboard-main > .dashboard-main-router.dashboard-main-router:has(.ofr-page) { padding: 6px 8px 8px !important; background: #fff !important; background-image: none !important; }
body #app main.dashboard-main > .dashboard-main-router.dashboard-main-router:has(.ofr-page--mobile),
body.app-has-video-bg #app main.dashboard-main > .dashboard-main-router.dashboard-main-router:has(.ofr-page--mobile) { padding: 0 !important; background: #faf8fb !important; }
.ofr-vs-menu .vs__dropdown-option { text-align: left !important; border-radius: 7px !important; padding: 7px 10px !important; font-size: 13px !important; color: var(--color-text-primary, #1e1b2e) !important; }
.ofr-vs-menu .vs__dropdown-option--highlight { background: var(--color-primary-soft, #f7f2f8) !important; color: var(--color-primary, #733E87) !important; }
.ofr-vs-menu .vs__dropdown-option--selected { font-weight: 700 !important; color: var(--color-primary, #733E87) !important; }
.ofr-vs-menu .vs__no-options { text-align: left !important; font-size: 12.5px !important; color: var(--color-text-muted, #8b8798) !important; }
.ofr-vs-menu .ofr-agent-opt { display: flex !important; align-items: center !important; justify-content: flex-start !important; gap: 8px !important; }
.ofr-vs-menu .ofr-agent-avatar { width: 22px !important; height: 22px !important; border-radius: 50% !important; object-fit: cover !important; }
</style>
