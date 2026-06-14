<template>
  <div
    class="crm-dashboard-home crm-dashboard-home--analytics crm-dashboard-home--unified ap-dashboard ap-dashboard--pro"
    :class="{ 'crm-dashboard-home--mobile': isMobileViewport }"
  >
    <header class="adx-header">
      <div class="adx-header__brand">
        <div class="adx-header__logo">
          <iconify-icon icon="lucide:layout-dashboard" width="20" height="20" />
        </div>
        <div>
          <h1 class="adx-header__title">Hello, {{ greetingName }} 👋</h1>
          <p class="adx-header__sub">CRM, listings & HR — unified analytics</p>
        </div>
      </div>
      <div class="adx-header__actions">
        <span class="adx-header__period">{{ periodLabel }}</span>
        <DashboardDateRangePicker
          v-model:date-from="dateFrom"
          v-model:date-to="dateTo"
          :label="dateRangeLabel"
          :icon-only="isMobileViewport"
          picker-class="dh-header-date"
          @apply="applyDateRange"
        />
      </div>
    </header>

    <div v-if="error" class="ap-error">
      <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
      <span>{{ error }}</span>
      <button type="button" @click="load(true)">Retry</button>
    </div>

    <div class="adx-body">
      <!-- CRM -->
      <section v-if="canViewModule('crm')" class="adx-section adx-section--crm">
        <header class="adx-section__head">
          <div class="adx-section__label">
            <span class="adx-section__icon">
              <iconify-icon icon="lucide:target" width="18" height="18" />
            </span>
            <div>
              <h2 class="adx-section__title">CRM</h2>
              <p class="adx-section__desc">Pipeline & conversion</p>
            </div>
          </div>
          <router-link to="/kanban" class="adx-section__link">
            Open CRM
            <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>
        <div class="adx-stats">
          <AdxStat
            label="Total Leads"
            :value="crm.total_leads"
            icon="lucide:users"
            tone="crm"
            primary
            :badge="`${crm.conversion_rate || 0}%`"
            :sub="`${formatNumber(crm.converted)} converted`"
          />
          <AdxStat label="New" :value="crm.new_leads" icon="lucide:sparkles" tone="blue" />
          <AdxStat label="Hot" :value="crm.hot" icon="lucide:zap" tone="orange" />
          <AdxStat label="Converted" :value="crm.converted" icon="lucide:trophy" tone="green" />
          <AdxStat label="Conversion" :value="crm.conversion_rate" suffix="%" format="percent" icon="lucide:percent" tone="purple" />
        </div>
      </section>

      <!-- Listings -->
      <section v-if="showListing" class="adx-section adx-section--listings">
        <header class="adx-section__head">
          <div class="adx-section__label">
            <span class="adx-section__icon">
              <iconify-icon icon="lucide:building-2" width="18" height="18" />
            </span>
            <div>
              <h2 class="adx-section__title">Listings</h2>
              <p class="adx-section__desc">Property performance</p>
            </div>
          </div>
          <router-link to="/alllisting" class="adx-section__link">
            View Listings
            <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>
        <div class="adx-stats">
          <AdxStat
            label="Total Listings"
            :value="listing.total_listings"
            icon="lucide:layers"
            tone="listings"
            primary
            :sub="`${formatNumber(listing.active_listings)} active`"
          />
          <AdxStat label="Active" :value="listing.active_listings" icon="lucide:check-circle" tone="green" />
          <AdxStat label="Views" :value="listing.total_views" icon="lucide:eye" tone="blue" />
          <AdxStat label="Inquiries" :value="listing.inquiry_requests" icon="lucide:inbox" tone="orange" />
          <AdxStat label="Sold" :value="listing.sold_listings" icon="lucide:badge-check" tone="green" />
        </div>
      </section>

      <!-- HR -->
      <section v-if="showHr" class="adx-section adx-section--hr">
        <header class="adx-section__head">
          <div class="adx-section__label">
            <span class="adx-section__icon">
              <iconify-icon icon="lucide:users-round" width="18" height="18" />
            </span>
            <div>
              <h2 class="adx-section__title">HR</h2>
              <p class="adx-section__desc">Workforce health</p>
            </div>
          </div>
          <router-link to="/hr" class="adx-section__link">
            Open HR
            <iconify-icon icon="lucide:arrow-right" width="14" height="14" />
          </router-link>
        </header>
        <div class="adx-stats">
          <AdxStat
            label="Employees"
            :value="hr.total_employees"
            icon="lucide:users"
            tone="hr"
            primary
            :sub="`${formatNumber(hr.active_employees)} active`"
          />
          <AdxStat label="Active" :value="hr.active_employees" icon="lucide:user-check" tone="green" />
          <AdxStat label="Absent" :value="hr.absent_employees" icon="lucide:user-x" tone="red" />
          <AdxStat label="On Leave" :value="hr.on_leave" icon="lucide:calendar-days" tone="orange" />
          <AdxStat label="Productivity" :value="hr.productivity_score" suffix="%" format="percent" icon="lucide:gauge" tone="blue" />
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'
import AdxStat from './widgets/AdxStat.vue'
import { useMobileNavigation } from '@/composables/useMobileNavigation.js'
import { useAnalyticsDashboard } from '@/composables/useAnalyticsDashboard.js'
import { useDashboardPermissions } from '@/composables/useDashboardPermissions.js'
import { parseToDate } from '@/composables/useAdvancedDateModel.js'

const { isMobileViewport } = useMobileNavigation()
const { canViewModule } = useDashboardPermissions()
const showListing = computed(() => canViewModule('listing') || canViewModule('crm'))
const showHr = computed(() => canViewModule('hr') || canViewModule('crm'))

const {
  error, data, dateFrom, dateTo, periodLabel,
  load, setCustomRange,
} = useAnalyticsDashboard()

const crm = computed(() => data.value?.crm || {})
const listing = computed(() => data.value?.listing || {})
const hr = computed(() => data.value?.hr || {})

const greetingName = computed(() => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    return (user?.name || 'User').split(' ')[0]
  } catch {
    return 'User'
  }
})

const dateRangeLabel = computed(() => {
  const fmt = (ymd) => {
    const d = parseToDate(ymd)
    if (!d) return ''
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
  }
  const a = fmt(dateFrom.value)
  const b = fmt(dateTo.value)
  if (a && b) return `${a} - ${b}`
  return periodLabel.value
})

const formatNumber = (n) => new Intl.NumberFormat().format(Number(n) || 0)

function applyDateRange() {
  if (dateFrom.value && dateTo.value) {
    setCustomRange(dateFrom.value, dateTo.value)
  } else {
    load(true)
  }
}

onMounted(() => load(true))
</script>
