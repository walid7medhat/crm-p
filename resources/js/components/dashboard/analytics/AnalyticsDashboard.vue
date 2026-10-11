<template>
  <div
    class="crm-dashboard-home crm-dashboard-home--unified ap-dashboard ap-dashboard--uni"
    :class="{ 'crm-dashboard-home--mobile': isMobileViewport }"
  >
    <header v-if="isMobileViewport" class="cilo-hero">
      <div class="cilo-hero__top">
        <div class="cilo-hero__id">
          <img
            v-if="hasUserPhoto"
            class="cilo-hero__avatar"
            :src="userAvatar"
            alt=""
            @error="avatarFailed = true"
          />
          <span v-else class="cilo-hero__avatar cilo-hero__avatar--initials" aria-hidden="true">{{ greetingInitial }}</span>
          <div class="cilo-hero__greet">
            <p class="cilo-hero__when">
              <span aria-hidden="true">{{ dayPart.mark }}</span>
              {{ dayPart.label }}
            </p>
            <h1 class="cilo-hero__name">{{ greetingName }}</h1>
          </div>
        </div>
        <div class="cilo-hero__tools">
          <button type="button" class="cilo-icon" aria-label="Search" @click="openDashboardSearch">
            <iconify-icon icon="lucide:search" width="20" height="20" />
          </button>
          <div class="cilo-icon cilo-icon--bell">
            <NotificationBell />
          </div>
        </div>
      </div>
    </header>

    <header v-else class="adx-header">
      <div class="adx-header__brand">
        <div>
          <h1 class="adx-header__title">Hello, {{ greetingName }} 👋</h1>
          <p class="adx-header__sub">Company analytics — leads, deals, listings &amp; HR</p>
        </div>
      </div>
      <div class="adx-header__actions">
        <DashboardDateRangePicker
          v-model:date-from="dateFrom"
          v-model:date-to="dateTo"
          :label="dateRangeLabel"
          picker-class="dh-header-date adx-date-pill"
          @apply="applyDateRange"
        />
      </div>
    </header>

    <div v-if="error" class="ap-error">
      <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
      <span>{{ error }}</span>
      <button type="button" @click="load(true)">Retry</button>
    </div>

    <div class="adx-uni-body">
      <section v-if="showLeads || showDeals || showListing" class="cilo-overview" aria-label="Summary">
        <div v-if="overviewLoading" class="cilo-overview__grid" aria-hidden="true">
          <div v-for="n in 4" :key="n" class="cilo-card cilo-card--skeleton" />
        </div>
        <div v-else class="cilo-overview__grid">
          <button
            v-for="card in overviewCards"
            :key="card.key"
            type="button"
            class="cilo-card"
            @click="card.open()"
          >
            <span class="cilo-card__top">
              <span class="cilo-card__label">{{ card.label }}</span>
              <iconify-icon class="cilo-card__arrow" icon="lucide:arrow-up-right" />
            </span>
            <strong class="cilo-card__value">{{ card.value }}</strong>
            <p class="cilo-card__meta">
              <span v-if="card.deltaText" class="cilo-card__delta" :class="`is-${card.tone}`">{{ card.deltaText }}</span>
              <span class="cilo-card__vs">{{ card.vs }}</span>
            </p>
          </button>
        </div>
      </section>

      <!-- 1. Leads -->
      <section v-if="showLeads" class="perf-stack">
        <article class="perf-card">
          <header class="perf-card__head">
            <h2 class="perf-card__title">Performance Overview</h2>
            <DashboardDateRangePicker
              v-model:date-from="dateFrom"
              v-model:date-to="dateTo"
              :label="dateRangeLabel"
              picker-class="perf-range"
              @apply="applyDateRange"
            />
          </header>
          <div class="perf-legend">
            <span><i class="is-leads" /> Leads</span>
            <span><i class="is-deals" /> Deals</span>
          </div>
          <div v-if="crmLoading" class="perf-chart perf-chart--loading" />
          <div v-else class="perf-chart">
            <div class="perf-chart__y">
              <span v-for="tick in perfTicks" :key="tick">{{ formatTick(tick) }}</span>
            </div>
            <div class="perf-chart__main">
              <div class="perf-chart__bars">
                <div v-for="col in perfColumns" :key="col.key" class="perf-group">
                  <i class="perf-bar" :style="{ height: col.leadH }" />
                  <i class="perf-bar is-deal" :style="{ height: col.dealH }" />
                </div>
              </div>
              <div class="perf-chart__x">
                <span v-for="col in perfColumns" :key="`${col.key}-x`">{{ col.short }}</span>
              </div>
            </div>
          </div>
          <p class="perf-trend">
            {{ perfTrendText }}
            <iconify-icon :icon="perfTrendIcon" />
          </p>
          <p class="perf-note">Showing new leads vs deals for this period</p>
        </article>

        <article class="cohort-card">
          <header class="perf-card__head">
            <h2 class="perf-card__title">Leads by stage</h2>
            <DashboardDateRangePicker
              v-model:date-from="dateFrom"
              v-model:date-to="dateTo"
              :label="dateRangeLabel"
              picker-class="perf-range"
              @apply="applyDateRange"
            />
          </header>
          <div class="cohort-table" role="table">
            <div class="cohort-table__row cohort-table__row--head" role="row">
              <span role="columnheader">Stage</span>
              <span role="columnheader">Leads</span>
              <span role="columnheader">Share</span>
              <span role="columnheader">Rate</span>
            </div>
            <button
              v-for="row in stageRows"
              :key="row.label"
              type="button"
              class="cohort-table__row"
              role="row"
              @click="go(pillLink(row.label))"
            >
              <span role="cell">{{ row.label }}</span>
              <span role="cell">{{ formatNumber(row.count) }}</span>
              <span role="cell">{{ row.share }}%</span>
              <span role="cell" class="cohort-rate" :class="`is-${row.tone}`">
                {{ row.share }}%
                <iconify-icon v-if="row.tone === 'up'" icon="lucide:arrow-up" />
                <iconify-icon v-else-if="row.tone === 'down'" icon="lucide:arrow-down" />
              </span>
            </button>
          </div>
        </article>

      </section>

      <!-- 2. Deals -->
      <section v-if="showDeals" class="adx-uni-band adx-uni-band--deals">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--deals">
              <iconify-icon icon="lucide:handshake" width="18" height="18" />
            </span>
            <div>
              <h2>Deals</h2>
              <p>Sales pipeline &amp; revenue</p>
            </div>
          </div>
          <router-link to="/kanban_deal" class="adx-uni-band__link">
            View Deals &gt;
          </router-link>
        </header>

        <div class="adx-uni-crm">
          <div class="adx-uni-crm__hero adx-uni-crm__hero--deals adx-click" role="link" tabindex="0" title="Open these deals" @click="go(dealsLink())" @keydown.enter="go(dealsLink())">
            <p class="adx-uni-eyebrow">Total deals</p>
            <p class="adx-uni-mega">{{ formatNumber(deals.total_deals) }}</p>
            <div class="adx-uni-crm__chips">
              <a href="#" class="adx-uni-chip adx-sub-link" @click.stop.prevent="go(dealTypeLink('primary'))">{{ formatNumber(deals.primary) }} primary</a>
              <a href="#" class="adx-uni-chip adx-sub-link" @click.stop.prevent="go(dealTypeLink('secondary'))">{{ formatNumber(deals.secondary) }} secondary</a>
              <a href="#" class="adx-uni-chip adx-uni-chip--gold adx-sub-link" @click.stop.prevent="go(dealTypeLink('rental'))">{{ formatNumber(deals.rental) }} rental</a>
            </div>
            <div class="adx-uni-crm__mini">
              <span><strong>{{ formatCurrency(deals.total_sale) }}</strong> sales</span>
              <span><strong>{{ formatCurrency(deals.total_commission) }}</strong> commission</span>
            </div>
          </div>

          <div class="adx-uni-crm__funnel">
            <p class="adx-uni-panel-title">Deal stages</p>
            <div v-if="dealsLoading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
            <div v-else class="adx-uni-funnel-scroll">
              <div
                v-for="(stage, i) in dealStageRows"
                :key="`deal-${i}`"
                class="adx-uni-funnel-row adx-click"
                role="link"
                tabindex="0"
                :title="`Open ${stage.label} deals`"
                @click="go(dealStageLink(stage))"
                @keydown.enter="go(dealStageLink(stage))"
              >
                <span class="adx-uni-funnel-row__dot" :style="{ background: stage.color }" />
                <span class="adx-uni-funnel-row__label">{{ stage.label }}</span>
                <div class="adx-uni-funnel-row__track">
                  <div
                    class="adx-uni-funnel-row__fill"
                    :style="{ width: `${stage.pct}%`, background: stage.color }"
                  />
                </div>
                <span class="adx-uni-funnel-row__val">{{ formatNumber(stage.value) }}</span>
              </div>
              <p v-if="!dealStageRows.length" class="adx-uni-empty">No deals in pipeline yet</p>
            </div>
          </div>

          <div class="adx-uni-crm__trend">
            <p class="adx-uni-panel-title">Deals trend</p>
            <div v-if="dealsLoading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
            <div v-else ref="dealsChartRef" class="adx-uni-chart" />
          </div>
        </div>
      </section>

      <!-- 3. Listings -->
      <section v-if="showListing" class="adx-uni-band adx-uni-band--listings">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--listings">
              <iconify-icon icon="lucide:building-2" width="18" height="18" />
            </span>
            <div>
              <h2>Listings</h2>
              <p>Properties &amp; performance</p>
            </div>
          </div>
          <router-link to="/alllisting" class="adx-uni-band__link">
            View Listings &gt;
          </router-link>
        </header>

        <div class="adx-uni-list">
          <div class="adx-uni-list__donut">
            <div v-if="listingLoading" class="adx-uni-skeleton adx-uni-skeleton--round" />
            <template v-else>
              <div ref="listingChartRef" class="adx-uni-chart adx-uni-chart--donut" />
              <div class="adx-uni-list__donut-center adx-click" role="link" tabindex="0" title="Open listings" @click="go('/alllisting')" @keydown.enter="go('/alllisting')">
                <strong>{{ formatNumber(listing.total_listings) }}</strong>
                <span>properties</span>
              </div>
            </template>
          </div>

          <div class="adx-uni-list__bars">
            <p class="adx-uni-panel-title">Status breakdown</p>
            <div
              v-for="item in listingBreakdown"
              :key="item.label"
              class="adx-uni-progress adx-click"
              role="link"
              tabindex="0"
              :title="item.label === 'Pending' ? 'Open listings waiting for approval' : 'Open listings'"
              @click="go(item.label === 'Pending' ? '/need-approve-requests' : '/alllisting')"
              @keydown.enter="go(item.label === 'Pending' ? '/need-approve-requests' : '/alllisting')"
            >
              <div class="adx-uni-progress__head">
                <span class="adx-uni-progress__dot" :style="{ background: item.color }" />
                <span class="adx-uni-progress__label">{{ item.label }}</span>
                <strong class="adx-uni-progress__val">{{ formatNumber(item.value) }}</strong>
              </div>
              <div class="adx-uni-progress__track">
                <div
                  class="adx-uni-progress__fill"
                  :style="{ width: `${item.pct}%`, background: item.color }"
                />
              </div>
            </div>
            <p v-if="!listingLoading && !listingHasData" class="adx-uni-empty">No listings in your portfolio yet</p>
          </div>

          <div class="adx-uni-list__callouts">
            <div class="adx-uni-callout adx-click" role="link" tabindex="0" title="Open listings" @click="go('/alllisting')" @keydown.enter="go('/alllisting')">
              <iconify-icon icon="lucide:eye" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.total_views) }}</strong>
                <span>Total views</span>
              </div>
            </div>
            <div class="adx-uni-callout adx-click" role="link" tabindex="0" title="Open listings" @click="go('/alllisting')" @keydown.enter="go('/alllisting')">
              <iconify-icon icon="lucide:inbox" width="22" height="22" />
              <div>
                <strong>{{ formatNumber(listing.inquiry_requests) }}</strong>
                <span>Inquiries</span>
              </div>
            </div>
            <div class="adx-uni-callout adx-click" role="link" tabindex="0" title="Open listings" @click="go('/alllisting')" @keydown.enter="go('/alllisting')">
              <iconify-icon icon="lucide:badge-check" width="22" height="22" />
              <div>
                <strong>{{ listing.conversion_rate || 0 }}%</strong>
                <span>Sold rate</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 4. HR -->
      <section v-if="showHr" class="adx-uni-band adx-uni-band--hr">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--hr">
              <iconify-icon icon="lucide:users-round" width="18" height="18" />
            </span>
            <div>
              <h2>HR</h2>
              <p>Workforce &amp; attendance</p>
            </div>
          </div>
          <router-link to="/hr" class="adx-uni-band__link">
            View HR &gt;
          </router-link>
        </header>

        <div class="adx-uni-hr">
          <div class="adx-uni-hr__ring adx-click" role="link" tabindex="0" title="Open HR" @click="go('/hr')" @keydown.enter="go('/hr')">
            <svg class="adx-uni-ring" viewBox="0 0 120 120" aria-hidden="true">
              <circle class="adx-uni-ring__bg" cx="60" cy="60" r="52" />
              <circle
                class="adx-uni-ring__fg"
                cx="60" cy="60" r="52"
                :stroke-dasharray="`${hrRingDash} 327`"
              />
            </svg>
            <div class="adx-uni-hr__ring-center">
              <strong>{{ formatNumber(hr.active_employees) }}</strong>
              <span>active / {{ formatNumber(hr.total_employees) }}</span>
              <em>{{ hrActivePct }}%</em>
            </div>
          </div>

          <div class="adx-uni-hr__presence adx-click" role="link" tabindex="0" title="Open HR" @click="go('/hr')" @keydown.enter="go('/hr')">
            <p class="adx-uni-panel-title">Today's presence</p>
            <div class="adx-uni-presence-bar">
              <div
                v-for="seg in hrPresence"
                :key="seg.label"
                class="adx-uni-presence-bar__seg"
                :style="{ flex: seg.value || 0.001, background: seg.color }"
                :title="`${seg.label}: ${seg.value}`"
              />
            </div>
            <ul class="adx-uni-presence-legend">
              <li v-for="seg in hrPresence" :key="seg.label">
                <span :style="{ background: seg.color }" />
                {{ seg.label }}
                <strong>{{ formatNumber(seg.value) }}</strong>
              </li>
            </ul>
            <div class="adx-uni-hr__prod">
              <span>Productivity</span>
              <strong>{{ hr.productivity_score || 0 }}%</strong>
            </div>
          </div>

          <div class="adx-uni-hr__attendance">
            <p class="adx-uni-panel-title">Attendance — last 7 days</p>
            <div v-if="hrLoading" class="adx-uni-skeleton adx-uni-skeleton--chart" />
            <div v-else ref="hrChartRef" class="adx-uni-chart" />
          </div>
        </div>
      </section>

      <!-- 5. Attendance (everyone who doesn't see the HR band above) -->
      <section v-if="showAttendance" class="adx-uni-band adx-uni-band--attendance">
        <header class="adx-uni-band__head">
          <div class="adx-uni-band__title">
            <span class="adx-uni-band__icon adx-uni-band__icon--attendance">
              <iconify-icon icon="lucide:calendar-check-2" width="18" height="18" />
            </span>
            <div>
              <h2>{{ isAttendanceManager ? 'Team Attendance' : 'My Attendance' }}</h2>
              <p>{{ attendancePeriodLabel || 'This month' }} so far</p>
            </div>
          </div>
          <router-link to="/view-profile" class="adx-uni-band__link">
            My Profile &gt;
          </router-link>
        </header>

        <div class="adx-uni-attendance" :class="{ 'adx-uni-attendance--manager': isAttendanceManager }">
          <div class="adx-uni-attendance__ring adx-click" role="link" tabindex="0" title="On-time + late days ÷ working days so far this month — open my profile" @click="go('/view-profile')" @keydown.enter="go('/view-profile')">
            <svg class="adx-uni-ring" viewBox="0 0 120 120" aria-hidden="true">
              <circle class="adx-uni-ring__bg" cx="60" cy="60" r="52" />
              <circle
                class="adx-uni-ring__fg adx-uni-ring__fg--attendance"
                cx="60" cy="60" r="52"
                :stroke-dasharray="`${personalRingDash} 327`"
              />
            </svg>
            <div class="adx-uni-hr__ring-center">
              <strong>{{ personalAttendanceRate }}%</strong>
              <span>my days attended</span>
            </div>
          </div>

          <div class="adx-uni-attendance__stats">
            <p class="adx-uni-panel-title">My attendance this month</p>
            <div v-if="attendanceLoading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
            <template v-else>
              <div class="adx-uni-attendance-chips adx-click" role="link" tabindex="0" title="Open my profile" @click="go('/view-profile')" @keydown.enter="go('/view-profile')">
                <div class="adx-uni-attendance-chip adx-uni-attendance-chip--good" title="Checked in at or before 9:15 AM">
                  <strong>{{ formatNumber(personalAttendance.present) }}</strong>
                  <span>Present</span>
                </div>
                <div class="adx-uni-attendance-chip adx-uni-attendance-chip--warn" title="Checked in after 9:15 AM">
                  <strong>{{ formatNumber(personalAttendance.late) }}</strong>
                  <span>Late</span>
                </div>
                <div class="adx-uni-attendance-chip adx-uni-attendance-chip--danger" title="No check-in recorded for that working day, or Sunday">
                  <strong>{{ formatNumber(personalAttendance.absent) }}</strong>
                  <span>Absent</span>
                </div>
              </div>
              <p class="adx-uni-attendance-legend">
                <iconify-icon icon="lucide:info" width="12" height="12" />
                <strong>Present</strong> = in at or before 9:15&nbsp;AM ·
                <strong>Late</strong> = in after 9:15&nbsp;AM ·
                <strong>Absent</strong> = no check-in that day
              </p>
            </template>
          </div>

          <div v-if="isAttendanceManager" class="adx-uni-attendance__team">
            <p class="adx-uni-panel-title">
              Team attendance
              <span class="adx-uni-attendance-team-count">{{ teamAttendance.member_count }} members</span>
            </p>
            <div v-if="attendanceLoading" class="adx-uni-skeleton adx-uni-skeleton--tall" />
            <template v-else>
              <div class="adx-uni-attendance-team-rate">
                <div class="adx-uni-attendance-team-rate__track">
                  <div class="adx-uni-attendance-team-rate__fill" :style="{ width: `${teamAttendanceRate}%` }" />
                </div>
                <strong>{{ teamAttendanceRate }}%</strong>
              </div>
              <ul v-if="teamAttendance.members?.length" class="adx-uni-agent-list adx-uni-attendance-list">
                <li
                  v-for="member in teamAttendance.members.slice(0, 6)"
                  :key="member.id"
                  class="adx-uni-agent"
                  :class="{ 'adx-uni-agent--top': member.is_self }"
                >
                  <img
                    v-if="member.avatar"
                    :src="member.avatar"
                    alt=""
                    class="adx-uni-attendance-avatar"
                  />
                  <span v-else class="adx-uni-attendance-avatar adx-uni-attendance-avatar--placeholder">
                    <iconify-icon icon="lucide:user-round" />
                  </span>
                  <div class="adx-uni-agent__info">
                    <span class="adx-uni-agent__name">{{ member.name }}{{ member.is_self ? ' (You)' : '' }}</span>
                    <span class="adx-uni-agent__meta">{{ member.role_name || '—' }}</span>
                  </div>
                  <span
                    class="adx-uni-attendance-status"
                    :class="`adx-uni-attendance-status--${attendanceStatusTone(member)}`"
                    title="P = Present (in at or before 9:15 AM) · L = Late · A = Absent"
                  >
                    {{ member.present }}P · {{ member.late }}L · {{ member.absent }}A
                  </span>
                </li>
              </ul>
              <p v-else class="adx-uni-empty">No team members yet</p>
            </template>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import ApexCharts from 'apexcharts'
import { kanbanFilterLink, openKanbanFilterLink } from '@/utils/kanbanUrlFilter.js'
import DashboardDateRangePicker from '@/components/dashboard/home/DashboardDateRangePicker.vue'
import NotificationBell from '@/components/NotificationBell.vue'
import userAvatarPlaceholder from '@/assets/images/users/user1.png'
import { useMobileNavigation } from '@/composables/useMobileNavigation.js'
import { useSwipeDeck } from '@/composables/useSwipeDeck.js'
import { useAnalyticsDashboard } from '@/composables/useAnalyticsDashboard.js'
import { useDashboardPermissions } from '@/composables/useDashboardPermissions.js'
import { parseToDate } from '@/composables/useAdvancedDateModel.js'
import { fetchDashboardAttendanceSummary } from '@/services/attendancesApi.js'

const PURPLE = '#6b21a8'
const PURPLE_MID = '#7c3aed'
const PURPLE_DARK = '#5b21b6'
const GOLD = '#f5c518'
const ORANGE = '#ff9f43'
const GREEN = '#22c55e'
const SLATE = '#94a3b8'

const CHART_AXIS = '#94a3b8'
const CHART_GRID = '#f1f5f9'

const FUNNEL_COLORS = ['#60a5fa', '#eab308', '#f97316', '#86efac', '#16a34a', '#ea580c']
const SOURCE_COLORS = [PURPLE_MID, GREEN, GOLD, ORANGE, '#a78bfa', SLATE]

const { isMobileViewport } = useMobileNavigation()
const leadDeck = useSwipeDeck(() => phoneLeadCards.value.length)
const leadDeckIndex = leadDeck.index
const leadDeckDragging = leadDeck.dragging
const leadDeckReduced = leadDeck.reducedMotion
const { canViewModule } = useDashboardPermissions()

const showLeads = computed(() => canViewModule('crm'))
const showDeals = computed(() => canViewModule('deals'))
const showListing = computed(() => canViewModule('listing') || canViewModule('crm'))
// HR band is for HR/admins only now — everyone else gets the Attendance band below instead.
const showHr = computed(() => canViewModule('hr'))
const showAttendance = computed(() => !showHr.value)

const {
  crmLoading, dealsLoading, listingLoading, hrLoading,
  error, crm, deals, listing, hr, dateFrom, dateTo, period, periodLabel,
  load, setCustomRange,
} = useAnalyticsDashboard()

const avatarFailed = ref(false)
const sessionUser = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    return null
  }
})
const greetingName = computed(() => (sessionUser.value?.name || 'User').split(' ')[0])
const greetingInitial = computed(() => (greetingName.value || 'U').slice(0, 1).toUpperCase())
const hasUserPhoto = computed(() => !avatarFailed.value && !!sessionUser.value?.avatar)
const userAvatar = computed(() => sessionUser.value?.avatar || userAvatarPlaceholder)
const dayPart = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return { label: 'Morning', mark: '☀️' }
  if (hour < 17) return { label: 'Afternoon', mark: '🌤️' }
  return { label: 'Evening', mark: '🌙' }
})

function halfDelta(series) {
  const values = (series || []).map((point) => Number(point?.value) || 0)
  if (values.length < 4) return null
  const mid = Math.floor(values.length / 2)
  const previous = values.slice(0, mid).reduce((sum, value) => sum + value, 0)
  const current = values.slice(mid).reduce((sum, value) => sum + value, 0)
  if (!previous) return null
  return Math.round(((current - previous) / previous) * 1000) / 10
}

function niceTop(max) {
  const value = Math.max(Math.ceil(Number(max) || 0), 4)
  const step = Math.max(Math.ceil(value / 4), 1)
  return step * 4
}

function formatTick(n) {
  const value = Number(n) || 0
  if (value >= 1000) {
    const k = value / 1000
    return `${Number.isInteger(k) ? k : k.toFixed(1)}k`
  }
  return String(Math.round(value))
}

function shortAxisLabel(label) {
  const text = String(label || '').trim()
  const dated = text.match(/^[A-Za-z]{3}\s+(\d{1,2})$/)
  if (dated) return dated[1]
  return text.slice(0, 3)
}

const overviewLoading = computed(() => (
  (showLeads.value && crmLoading.value && crm.value?.total_leads == null)
  || (showDeals.value && dealsLoading.value && deals.value?.total_deals == null)
  || (showListing.value && listingLoading.value && listing.value?.active_listings == null)
))

const overviewCards = computed(() => {
  const total = Number(crm.value?.total_leads) || 0
  const qualified = Number(crm.value?.qualified) || 0
  const qualifiedPct = total ? Math.round((qualified / total) * 1000) / 10 : 0
  const totalDeals = Number(deals.value?.total_deals) || 0
  const activeListings = Number(listing.value?.active_listings) || 0
  return [
    {
      key: 'total-leads',
      label: 'Total Leads',
      value: formatNumber(total),
      deltaText: '',
      tone: 'flat',
      vs: 'this month',
      open: () => go(leadsLink()),
    },
    {
      key: 'qualified',
      label: 'Qualified',
      value: formatNumber(qualified),
      deltaText: '',
      tone: 'flat',
      vs: total ? `${qualifiedPct}% of leads` : 'of leads',
      open: () => go(leadStageLink('qualified')),
    },
    {
      key: 'total-deals',
      label: 'Total deals',
      value: formatNumber(totalDeals),
      deltaText: '',
      tone: 'flat',
      vs: 'this month',
      open: () => go(dealsLink()),
    },
    {
      key: 'active-listing',
      label: 'Active listing',
      value: formatNumber(activeListings),
      deltaText: '',
      tone: 'flat',
      vs: 'live now',
      open: () => go('/alllisting'),
    },
  ]
})

function openDashboardSearch() {
  router.push('/kanban').catch(() => {})
}

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

// ================= Clickable numbers → boards already filtered =================
// Each number opens the Leads / Deals board filtered to match it (utils/kanbanUrlFilter.js),
// using the dashboard's date range (created_from / created_to) so the counts line up.
const router = useRouter()
// Filtered board links go through openKanbanFilterLink (saves the tab / deal type first so
// the board doesn't switch tabs and wipe the filter); plain paths are pushed as-is.
const go = (location) => (location && typeof location === 'object' && location.query?.filter
  ? openKanbanFilterLink(router, location)
  : router.push(location).catch(() => {}))

const shortDate = (ymd) => {
  const d = parseToDate(ymd)
  return d ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : ymd
}

const toYmd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

// The range the dashboard numbers are counted for. Custom dates when picked; otherwise
// the default period, worked out the same way as DashboardController::resolveAnalyticsPeriod
// (the page leaves dateFrom/dateTo empty for "This month", so links had no date filter
// and the board showed every lead).
const effectiveRange = computed(() => {
  if (dateFrom.value && dateTo.value) {
    return { from: dateFrom.value, to: dateTo.value }
  }
  const now = new Date()
  switch (period.value) {
    case 'today':
      return { from: toYmd(now), to: toYmd(now) }
    case 'weekly': {
      const start = new Date(now)
      const offset = (now.getDay() + 6) % 7 // Monday-based week, like Carbon::startOfWeek
      start.setDate(now.getDate() - offset)
      const end = new Date(start)
      end.setDate(start.getDate() + 6)
      return { from: toYmd(start), to: toYmd(end) }
    }
    case 'yearly':
      return { from: `${now.getFullYear()}-01-01`, to: `${now.getFullYear()}-12-31` }
    default:
      return {
        from: toYmd(new Date(now.getFullYear(), now.getMonth(), 1)),
        to: toYmd(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
      }
  }
})

const rangeQuery = computed(() => ({ created_from: effectiveRange.value.from, created_to: effectiveRange.value.to }))

const rangeChips = computed(() => [
  { id: 'created_on', queryKey: 'created_at', label: 'Created On', value: `${shortDate(effectiveRange.value.from)} → ${shortDate(effectiveRange.value.to)}` },
])

const chip = (id, label, value) => ({ id, queryKey: id, label, value: String(value) })

function leadsLink(query = {}, chips = []) {
  return kanbanFilterLink('leads', { ...rangeQuery.value, ...query }, [...rangeChips.value, ...chips])
}

function dealsLink(query = {}, chips = [], dealType = null) {
  return kanbanFilterLink('deals', { ...rangeQuery.value, ...query }, [...rangeChips.value, ...chips], { dealType })
}

// Stage numbers (Contacted, Qualified, Converted, Lost, pipeline pills, stage lists) open
// the board with the dashboard's DATE RANGE only — no stage filter; the board shows every
// stage as its own column, so each column count matches the dashboard number.
const leadStageLink = () => leadsLink()
const leadStageByNameLink = () => leadsLink()

// "New" on the dashboard = created in the last 7 days.
function newLeadsLink() {
  const today = new Date()
  const from = new Date(today)
  from.setDate(today.getDate() - 7)
  const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  return kanbanFilterLink('leads', { created_from: ymd(from), created_to: ymd(today) }, [
    { id: 'created_on', queryKey: 'created_at', label: 'Created On', value: 'Last 7 days' },
  ])
}

const heatLink = (heat) => leadsLink({ status_lead: heat }, [chip('status_lead', 'Quality Status', heat.toUpperCase())])
const callResultLink = (result, label) => leadsLink({ interaction_result: result }, [chip('interaction_result', 'Call Result', label)])
const sourceLink = (source) => leadsLink({ source }, [chip('source', 'Source', source)])
const agentLink = (agent) => (agent?.id
  ? leadsLink({ responsible_person_id: agent.id }, [chip('responsible_person_id', 'Responsible Person', agent.name)])
  : leadsLink())

const dealTypeLink = (type) => dealsLink({}, [], type)
// Deal stage rows: the deals board on that deal type's tab, with the date range only.
const dealStageLink = (stage) => dealsLink({}, [], stage?.type || null)

// "New" isn't a stage (created in the last 7 days) — it keeps its own date filter.
const pillLink = (label) => (label === 'New' ? newLeadsLink() : leadsLink())

const formatStageCount = (n) => {
  const num = Number(n) || 0
  if (num < 10) return String(num).padStart(2, '0')
  return new Intl.NumberFormat().format(num)
}

function agentInitials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase()
}

const formatCurrency = (n) =>
  new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(Number(n) || 0)

const crmFunnelStages = computed(() => {
  const funnel = crm.value.funnel || {}
  const labels = funnel.labels || []
  const values = funnel.values || []
  const seen = new Set()
  const rows = []
  for (let i = 0; i < labels.length && rows.length < 6; i++) {
    const label = String(labels[i] || '').trim() || `Stage ${i + 1}`
    const key = label.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    rows.push({
      label,
      value: Number(values[i]) || 0,
      color: FUNNEL_COLORS[rows.length % FUNNEL_COLORS.length],
    })
  }
  const max = Math.max(...rows.map((r) => r.value), 1)
  return rows.map((row) => ({
    ...row,
    pct: Math.round((row.value / max) * 100),
  }))
})

const leadStatusPills = computed(() => [
  { label: 'New', value: crm.value.new_leads, tone: 'new' },
  { label: 'Contacted', value: crm.value.contacted, tone: 'contacted' },
  { label: 'Qualified', value: crm.value.qualified, tone: 'qualified' },
  { label: 'Follow-up', value: crm.value.follow_up, tone: 'follow' },
  { label: 'Negotiation', value: crm.value.negotiation, tone: 'negotiation' },
  { label: 'Converted', value: crm.value.converted, tone: 'converted' },
  { label: 'Lost', value: crm.value.lost, tone: 'lost' },
])

const perfModel = computed(() => {
  const leads = (crm.value?.trend || []).slice(-6)
  const dealSeries = (deals.value?.trend || []).slice(-Math.max(leads.length, 1))
  const leadVals = leads.map((point) => Number(point.value) || 0)
  const dealVals = leads.map((_, index) => {
    const offset = leads.length - dealSeries.length
    return Number(dealSeries[index - offset]?.value) || 0
  })
  const top = niceTop(Math.max(...leadVals, ...dealVals, 1))
  return {
    ticks: [top, top * 0.75, top * 0.5, top * 0.25, 0],
    columns: leads.map((point, index) => ({
      key: `${point.label || 'd'}-${index}`,
      short: shortAxisLabel(point.label),
      leadH: `${leadVals[index] ? Math.max((leadVals[index] / top) * 100, 4) : 0}%`,
      dealH: `${dealVals[index] ? Math.max((dealVals[index] / top) * 100, 4) : 0}%`,
    })),
  }
})
const perfTicks = computed(() => perfModel.value.ticks)
const perfColumns = computed(() => perfModel.value.columns)
const perfTrend = computed(() => {
  const values = (crm.value?.trend || []).map((point) => Number(point.value) || 0)
  if (values.length < 4) return { text: 'Not enough history yet', icon: 'lucide:arrow-up-right' }
  const mid = Math.floor(values.length / 2)
  const previous = values.slice(0, mid).reduce((sum, value) => sum + value, 0)
  const current = values.slice(mid).reduce((sum, value) => sum + value, 0)
  if (!previous && !current) return { text: 'Flat this month', icon: 'lucide:arrow-up-right' }
  if (!previous) return { text: 'Trending up this month', icon: 'lucide:arrow-up-right' }
  if (!current) return { text: 'Trending down this month', icon: 'lucide:arrow-down-right' }
  const delta = Math.round(((current - previous) / previous) * 1000) / 10
  if (!delta) return { text: 'Flat this month', icon: 'lucide:arrow-up-right' }
  const up = delta > 0
  return {
    text: `Trending ${up ? 'up' : 'down'} by ${Math.abs(delta).toFixed(1)}% this month`,
    icon: up ? 'lucide:arrow-up-right' : 'lucide:arrow-down-right',
  }
})
const perfTrendText = computed(() => perfTrend.value.text)
const perfTrendIcon = computed(() => perfTrend.value.icon)
const stageRows = computed(() => {
  const total = Number(crm.value?.total_leads) || 0
  return leadStatusPills.value.map((pill) => {
    const count = Number(pill.value) || 0
    const share = total ? Math.round((count / total) * 100) : 0
    const tone = pill.tone === 'lost' ? 'down' : (pill.tone === 'converted' || pill.tone === 'qualified' ? 'up' : 'flat')
    return { ...pill, count, share, tone }
  })
})

const phoneLeadCards = computed(() => {
  const c = crm.value || {}
  const total = Number(c.total_leads) || 0
  const trend = Array.isArray(c.trend) ? c.trend.slice(-7) : []
  const maxTrend = Math.max(...trend.map((point) => Number(point.value) || 0), 1)
  const ofTotal = (count) => (total ? `${formatNumber(count)} of ${formatNumber(total)} leads` : '')
  const shareOf = (count) => (total ? Math.min(100, Math.round(((Number(count) || 0) / total) * 100)) : 0)
  return [
    {
      key: 'total',
      kind: 'total',
      label: 'Total Leads',
      value: formatNumber(c.total_leads),
      note: `${c.conversion_rate || 0}% conversion`,
      stages: leadStatusPills.value,
      spark: [],
      share: null,
      links: [],
    },
    {
      key: 'new',
      kind: 'new',
      label: 'New',
      value: formatNumber(c.new_leads),
      note: ofTotal(c.new_leads),
      stages: [],
      spark: trend.map((point) => ({
        label: point.label || '',
        value: Number(point.value) || 0,
        h: Math.round(((Number(point.value) || 0) / maxTrend) * 100),
      })),
      share: shareOf(c.new_leads),
      links: [],
    },
    {
      key: 'contacted',
      kind: 'contacted',
      label: 'Contacted',
      value: formatNumber(c.contacted),
      note: ofTotal(c.contacted),
      stages: [],
      spark: [],
      share: shareOf(c.contacted),
      links: [],
    },
    {
      key: 'qualified',
      kind: 'qualified',
      label: 'Qualified',
      value: formatNumber(c.qualified),
      note: ofTotal(c.qualified),
      stages: [],
      spark: [],
      share: shareOf(c.qualified),
      links: [],
    },
    {
      key: 'follow',
      kind: 'follow',
      label: 'Follow-ups',
      value: formatNumber(c.follow_up),
      note: c.follow_up_overdue ? `${formatNumber(c.follow_up_overdue)} overdue` : ofTotal(c.follow_up),
      stages: [],
      spark: [],
      share: shareOf(c.follow_up),
      links: [],
    },
    {
      key: 'hot',
      kind: 'hot',
      label: 'HOT',
      value: formatNumber(c.hot),
      note: ofTotal(c.hot),
      stages: [],
      spark: [],
      share: shareOf(c.hot),
      links: [
        { label: `${formatNumber(c.warm)} warm`, href: heatLink('warm') },
        { label: `${formatNumber(c.cold)} cold`, href: heatLink('cold') },
      ],
    },
    {
      key: 'converted',
      kind: 'converted',
      label: 'Converted',
      value: formatNumber(c.converted),
      note: ofTotal(c.converted),
      stages: [],
      spark: [],
      share: shareOf(c.converted),
      links: [
        { label: `${formatNumber(c.lost)} lost`, href: leadStageLink('lost') },
      ],
    },
  ]
})

function stageShare(value) {
  const max = Math.max(...leadStatusPills.value.map((stage) => Number(stage.value) || 0), 1)
  return `${Math.round(((Number(value) || 0) / max) * 100)}%`
}

function phoneLeadCardStyle(index) {
  const current = leadDeck.index.value
  const depth = index - current
  if (depth < 0 || depth > 2) return { visibility: 'hidden', pointerEvents: 'none' }
  const drag = depth === 0 ? leadDeck.dragX.value : 0
  return {
    transform: `translate3d(${drag}px, ${depth * 16}px, 0) scale(${1 - depth * 0.045})`,
    zIndex: 12 - depth,
    opacity: depth === 2 ? 0.55 : 1,
  }
}

function openPhoneLeadCard(card) {
  if (leadDeck.consumeClick()) return
  if (card.kind === 'total') go(leadsLink())
  else if (card.kind === 'new') go(newLeadsLink())
  else if (card.kind === 'hot') go(heatLink('hot'))
  else go(leadStageLink(card.kind))
}

function openPhoneLeadPart(link) {
  if (leadDeck.consumeClick()) return
  go(link)
}

const leadSourceRows = computed(() => {
  const sources = crm.value.lead_sources || []
  return sources.map((s, i) => ({
    source: s.source || 'Unknown',
    count: Number(s.count) || 0,
    color: SOURCE_COLORS[i % SOURCE_COLORS.length],
  }))
})

const leadAgents = computed(() => (crm.value.agent_ranking || []).slice(0, 5))

const bestCloserRate = computed(() => {
  const closer = crm.value.best_closer
  if (!closer) return 0
  if (closer.rate != null) return Number(closer.rate) || 0
  if (closer.conversion_rate != null) return Number(closer.conversion_rate) || 0
  const match = leadAgents.value.find((a) => a.id === closer.id || a.name === closer.name)
  return match ? (Number(match.rate) || 0) : 0
})

const pipelineHealth = computed(() => {
  const total = Number(crm.value.total_leads) || 0
  if (!total) return 0
  const active = (Number(crm.value.qualified) || 0) + (Number(crm.value.hot) || 0) + (Number(crm.value.negotiation) || 0)
  return Math.min(100, Math.round((active / total) * 100))
})

const dealStageRows = computed(() => {
  const stages = deals.value.stages || []
  const max = Math.max(...stages.map((s) => Number(s.count) || 0), 1)
  return stages.slice(0, 6).map((s, i) => ({
    id: s.id,
    type: s.type,
    label: s.label,
    value: Number(s.count) || 0,
    pct: Math.round(((Number(s.count) || 0) / max) * 100),
    color: FUNNEL_COLORS[i % FUNNEL_COLORS.length],
  }))
})

const listingBreakdown = computed(() => {
  const items = [
    { label: 'Active', value: Number(listing.value.active_listings) || 0, color: PURPLE },
    { label: 'Sold', value: Number(listing.value.sold_listings) || 0, color: PURPLE_DARK },
    { label: 'Pending', value: Number(listing.value.pending_approval) || 0, color: GOLD },
    { label: 'Expired', value: Number(listing.value.expired_listings) || 0, color: SLATE },
  ]
  const max = Math.max(...items.map((i) => i.value), 1)
  return items.map((i) => ({ ...i, pct: Math.round((i.value / max) * 100) }))
})

const listingHasData = computed(() => {
  const l = listing.value || {}
  return ['total_listings', 'active_listings', 'sold_listings', 'pending_approval']
    .some((key) => Number(l[key]) > 0)
})

const hrActivePct = computed(() => {
  const total = Number(hr.value.total_employees) || 1
  const active = Number(hr.value.active_employees) || 0
  return Math.min(100, Math.round((active / total) * 100))
})

const hrRingDash = computed(() => Math.round((hrActivePct.value / 100) * 327))

const hrPresence = computed(() => [
  { label: 'Active', value: Number(hr.value.active_employees) || 0, color: PURPLE },
  { label: 'Absent', value: Number(hr.value.absent_employees) || 0, color: GOLD },
  { label: 'On leave', value: Number(hr.value.on_leave) || 0, color: SLATE },
])

// ── Attendance band (everyone who doesn't get the HR band) ──
const attendanceLoading = ref(true)
const attendanceData = ref({ is_manager: false, personal: null, team: null })

const personalAttendance = computed(() => attendanceData.value.personal || {})
const teamAttendance = computed(() => attendanceData.value.team)
const isAttendanceManager = computed(() => !!attendanceData.value.is_manager && !!teamAttendance.value)

function attendanceRate(present, late, total) {
  const t = Number(total) || 0
  if (!t) return 0
  const good = (Number(present) || 0) + (Number(late) || 0)
  return Math.min(100, Math.round((good / t) * 100))
}

const personalAttendanceRate = computed(() => attendanceRate(
  personalAttendance.value.present,
  personalAttendance.value.late,
  personalAttendance.value.total_working_days,
))
const personalRingDash = computed(() => Math.round((personalAttendanceRate.value / 100) * 327))

const teamAttendanceRate = computed(() => Math.min(100, Math.round(Number(teamAttendance.value?.attendance_rate) || 0)))
const teamRingDash = computed(() => Math.round((teamAttendanceRate.value / 100) * 327))

const attendancePeriodLabel = computed(() => personalAttendance.value.label || '')

function attendanceStatusTone(member) {
  if (Number(member.absent) > 0) return 'danger'
  if (Number(member.late) > 0) return 'warn'
  return 'good'
}

async function loadAttendanceSummary() {
  attendanceLoading.value = true
  try {
    attendanceData.value = await fetchDashboardAttendanceSummary()
  } catch (e) {
    attendanceData.value = { is_manager: false, personal: null, team: null }
  } finally {
    attendanceLoading.value = false
  }
}

function applyDateRange() {
  if (dateFrom.value && dateTo.value) {
    setCustomRange(dateFrom.value, dateTo.value)
  } else {
    load(true)
  }
}

const leadsChartRef = ref(null)
const leadSourcesChartRef = ref(null)
const dealsChartRef = ref(null)
const listingChartRef = ref(null)
const hrChartRef = ref(null)
let leadsChart = null
let leadSourcesChart = null
let leadSourcesChartToken = 0
let dealsChart = null
let listingChart = null
let hrChart = null

function chartHeight() {
  const w = typeof window !== 'undefined' ? window.innerWidth : 1541
  if (isMobileViewport.value || w < 641) return 120
  if (w < 1280) return 96
  return 88
}

function lineChartOptions(categories, values, color, name) {
  return {
    series: [{ name, data: values }],
    chart: { type: 'area', height: chartHeight(), toolbar: { show: false }, fontFamily: 'Inter, system-ui, sans-serif', sparkline: { enabled: false } },
    colors: [color],
    fill: {
      type: 'gradient',
      gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.45, opacityTo: 0.02, stops: [0, 100], colorStops: [
        { offset: 0, color, opacity: 0.45 },
        { offset: 100, color, opacity: 0.02 },
      ] },
    },
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    xaxis: {
      categories,
      labels: { style: { fontSize: '10px', colors: CHART_AXIS, fontWeight: 500 } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { labels: { style: { fontSize: '10px', colors: CHART_AXIS, fontWeight: 500 }, maxWidth: 28 } },
    grid: { borderColor: CHART_GRID, strokeDashArray: 3, padding: { left: 4, right: 4 } },
    tooltip: { theme: 'light' },
  }
}

function renderLeadsChart() {
  if (!leadsChartRef.value) return
  const trend = crm.value.trend || []
  const categories = trend.map((t) => t.label)
  const values = trend.map((t) => Number(t.value) || 0)
  if (leadsChart) leadsChart.destroy()
  const w = typeof window !== 'undefined' ? window.innerWidth : 1541
  const height = isMobileViewport.value || w < 641 ? 150 : w < 1280 ? 170 : 190
  leadsChart = new ApexCharts(leadsChartRef.value, {
    series: [{ name: 'Leads', data: values }],
    chart: {
      type: 'area',
      height,
      toolbar: { show: false },
      fontFamily: 'Inter, system-ui, sans-serif',
      zoom: { enabled: false },
    },
    colors: [PURPLE_MID],
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'light',
        type: 'vertical',
        shadeIntensity: 0.35,
        opacityFrom: 0.4,
        opacityTo: 0.04,
        stops: [0, 90, 100],
      },
    },
    stroke: {
      curve: 'smooth',
      width: 2.5,
      dashArray: 5,
    },
    markers: {
      size: 0,
      hover: { size: 7 },
      colors: ['#f59e0b'],
      strokeColors: '#ffffff',
      strokeWidth: 3,
    },
    dataLabels: { enabled: false },
    xaxis: {
      categories,
      labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 500 } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      min: 0,
      tickAmount: 7,
      labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 500 }, maxWidth: 28 },
    },
    grid: {
      borderColor: CHART_GRID,
      strokeDashArray: 3,
      padding: { left: 6, right: 8, top: 8, bottom: 0 },
    },
    tooltip: {
      theme: 'light',
      style: { fontSize: '12px' },
      x: { show: true },
      y: {
        formatter: (val) => `${Number(val) || 0} Leads`,
        title: { formatter: () => '' },
      },
      marker: { show: true },
    },
  })
  leadsChart.render()
}

async function renderLeadSourcesChart() {
  const el = leadSourcesChartRef.value
  if (!el) return

  const token = ++leadSourcesChartToken
  const rows = leadSourceRows.value
  const hasData = rows.some((r) => r.count > 0)
  const series = hasData ? rows.map((r) => r.count) : [1]
  const labels = hasData ? rows.map((r) => r.source) : ['No data']
  const colors = hasData ? rows.map((r) => r.color) : ['#e2e8f0']
  const size = isMobileViewport.value ? 96 : 120

  try {
    if (leadSourcesChart) {
      leadSourcesChart.destroy()
      leadSourcesChart = null
    }
  } catch (_) {
    leadSourcesChart = null
  }

  if (token !== leadSourcesChartToken || !leadSourcesChartRef.value) return
  const mountEl = leadSourcesChartRef.value
  mountEl.innerHTML = ''
  mountEl.style.width = `${size}px`
  mountEl.style.height = `${size}px`

  const chart = new ApexCharts(mountEl, {
    series,
    labels,
    colors,
    chart: {
      type: 'donut',
      height: size,
      width: size,
      animations: { enabled: false },
      toolbar: { show: false },
      parentHeightOffset: 0,
    },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: { size: '72%', labels: { show: false } },
      },
    },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData, theme: 'light' },
    stroke: { width: 2, colors: ['#fff'] },
  })

  leadSourcesChart = chart
  try {
    await chart.render()
  } catch (_) {
    // Ignore race when a newer render already replaced this instance
  }
  if (token !== leadSourcesChartToken && chart !== leadSourcesChart) {
    try { chart.destroy() } catch (_) { /* noop */ }
  }
}

function renderDealsChart() {
  if (!dealsChartRef.value) return
  const trend = deals.value.trend || []
  const categories = trend.map((t) => t.label)
  const values = trend.map((t) => Number(t.value) || 0)
  if (dealsChart) dealsChart.destroy()
  dealsChart = new ApexCharts(dealsChartRef.value, lineChartOptions(categories, values, PURPLE_DARK, 'Deals'))
  dealsChart.render()
}

function renderListingChart() {
  if (!listingChartRef.value || !showListing.value) return
  const raw = [
    Number(listing.value.sold_listings) || 0,
    Number(listing.value.active_listings) || 0,
    Number(listing.value.pending_approval) || 0,
    Number(listing.value.expired_listings) || 0,
  ]
  const hasData = raw.some((v) => v > 0)
  const series = hasData ? raw : [1, 1, 1, 1]
  const colors = hasData ? [PURPLE_DARK, PURPLE, GOLD, SLATE] : ['#e2e8f0', '#e2e8f0', '#e2e8f0', '#e2e8f0']
  if (listingChart) listingChart.destroy()
  listingChartRef.value.innerHTML = ''
  const size = isMobileViewport.value ? 110 : 96
  listingChart = new ApexCharts(listingChartRef.value, {
    series,
    labels: ['Sold', 'Active', 'Pending', 'Expired'],
    colors,
    chart: { type: 'donut', height: size, width: size },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: false } } } },
    legend: { show: false },
    dataLabels: { enabled: false },
    tooltip: { enabled: hasData },
    stroke: { width: 2, colors: ['#fff'] },
  })
  listingChart.render()
}

function renderHrChart() {
  if (!hrChartRef.value) return
  const trend = hr.value.attendance_trend || []
  const categories = trend.map((t) => t.label)
  const present = trend.map((t) => Number(t.present) || 0)
  const absent = trend.map((t) => Number(t.absent) || 0)
  if (hrChart) hrChart.destroy()
  hrChart = new ApexCharts(hrChartRef.value, {
    series: [
      { name: 'Present', data: present },
      { name: 'Absent', data: absent },
    ],
    chart: { type: 'bar', height: chartHeight(), toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
    colors: [PURPLE, GOLD],
    plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    xaxis: {
      categories,
      labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } },
      axisBorder: { show: false },
    },
    yaxis: { labels: { style: { fontSize: '11px', colors: CHART_AXIS, fontWeight: 600 } } },
    grid: { borderColor: CHART_GRID, strokeDashArray: 4 },
    legend: { show: true, position: 'top', fontSize: '11px', fontWeight: 600, labels: { colors: CHART_AXIS } },
    tooltip: { theme: 'light' },
  })
  hrChart.render()
}

// Each band renders as soon as its own section's data is ready — a slow Listings
// or HR fetch no longer blocks the Leads charts (or vice versa).
async function renderLeadsCharts() {
  if (crmLoading.value || !showLeads.value) return
  await nextTick()
  await nextTick()
  if (!leadSourcesChartRef.value && !leadsChartRef.value) {
    await new Promise((resolve) => requestAnimationFrame(resolve))
  }
  await Promise.all([
    Promise.resolve(renderLeadsChart()),
    renderLeadSourcesChart(),
  ])
}

async function renderDealsCharts() {
  if (dealsLoading.value || !showDeals.value) return
  await nextTick()
  requestAnimationFrame(() => renderDealsChart())
}

async function renderListingCharts() {
  if (listingLoading.value || !showListing.value) return
  await nextTick()
  requestAnimationFrame(() => renderListingChart())
}

async function renderHrCharts() {
  if (hrLoading.value || !showHr.value) return
  await nextTick()
  requestAnimationFrame(() => renderHrChart())
}

function renderAllCharts() {
  renderLeadsCharts()
  renderDealsCharts()
  renderListingCharts()
  renderHrCharts()
}

watch([crmLoading, crm, isMobileViewport], () => renderLeadsCharts(), { deep: true })
watch([dealsLoading, deals, isMobileViewport], () => renderDealsCharts(), { deep: true })
watch([listingLoading, listing, isMobileViewport, showListing], () => renderListingCharts(), { deep: true })
watch([hrLoading, hr, isMobileViewport], () => renderHrCharts(), { deep: true })

let resizeTimer = null
function onResize() {
  clearTimeout(resizeTimer)
  resizeTimer = setTimeout(() => renderAllCharts(), 150)
}

onMounted(() => {
  load(true)
  if (showAttendance.value) {
    loadAttendanceSummary()
  }
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  clearTimeout(resizeTimer)
  leadsChart?.destroy()
  leadSourcesChart?.destroy()
  dealsChart?.destroy()
  listingChart?.destroy()
  hrChart?.destroy()
})
</script>

<style scoped>
.cilo-hero,
.cilo-overview,
.cilo-card {
  font-family: Inter, system-ui, -apple-system, sans-serif;
}

.cilo-hero {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin: 2px 2px 6px;
}

.perf-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.perf-card,
.cohort-card {
  background: #ffffff;
  border: 1px solid #f2f2f4;
  border-radius: 22px;
  box-shadow: 0 10px 24px rgba(17, 17, 17, 0.05);
  padding: 16px 14px 14px;
  font-family: Inter, system-ui, -apple-system, sans-serif;
}

.perf-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.perf-card__title {
  margin: 0;
  color: #111111;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.035em;
  line-height: 1.15;
}

.perf-range :deep(.dh-date-picker) {
  height: 34px;
  padding: 0 10px;
  border: 1px solid #ececef;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: none;
  color: #111111;
  font-size: 13px;
  font-weight: 600;
  gap: 6px;
}

.perf-range :deep(.dh-date-picker-icon) {
  display: none;
}

.perf-legend {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 14px 0 10px;
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
}

.perf-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.perf-legend i {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  background: #e9d5ff;
}

.perf-legend i.is-deals {
  background: #d9d9de;
}

.perf-chart {
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr);
  gap: 6px;
  min-height: 196px;
}

.perf-chart--loading {
  border-radius: 12px;
  background: linear-gradient(90deg, #f4f4f5 0%, #fafafa 50%, #f4f4f5 100%);
  background-size: 200% 100%;
  animation: cilo-shimmer 1.2s ease-in-out infinite;
}

.perf-chart__y {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 168px;
  color: #a1a1aa;
  font-size: 11px;
  font-weight: 500;
}

.perf-chart__bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 168px;
  border-bottom: 1px solid #eeeeee;
  background-image: repeating-linear-gradient(
    to top,
    transparent 0,
    transparent calc(25% - 1px),
    #f3f4f6 calc(25% - 1px),
    #f3f4f6 25%
  );
}

.perf-group {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 4px;
  height: 100%;
  flex: 1 1 0;
}

.perf-bar {
  width: 16px;
  border-radius: 4px 4px 0 0;
  background: #e9d5ff;
}

.perf-bar.is-deal {
  width: 14px;
  background: #e4e4e7;
}

.perf-chart__x {
  display: flex;
  justify-content: space-around;
  margin-top: 8px;
  color: #9ca3af;
  font-size: 12px;
  font-weight: 500;
}

.perf-chart__x span {
  flex: 1 1 0;
  text-align: center;
}

.perf-trend {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 14px 0 0;
  color: #111111;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.perf-trend iconify-icon {
  font-size: 16px;
}

.perf-note {
  margin: 4px 0 0;
  color: #a1a1aa;
  font-size: 12px;
  font-weight: 500;
}

.cohort-table {
  display: flex;
  flex-direction: column;
  margin-top: 8px;
}

.cohort-table__row {
  display: grid;
  grid-template-columns: 1.3fr 0.8fr 0.7fr 0.9fr;
  align-items: center;
  width: 100%;
  padding: 11px 2px;
  border: 0;
  background: transparent;
  color: #111111;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.cohort-table__row--head {
  padding-bottom: 6px;
  color: #a1a1aa;
  font-size: 12px;
  font-weight: 500;
  cursor: default;
}

.cohort-rate {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.cohort-rate.is-up {
  color: #16a34a;
}

.cohort-rate.is-down {
  color: #ef4444;
}

.cohort-rate.is-flat {
  color: #111111;
}

.cohort-rate iconify-icon {
  font-size: 13px;
}

.cilo-hero__top,
.cilo-hero__sub {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cilo-hero__id {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.cilo-hero__avatar {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  background: #ececef;
}

.cilo-hero__avatar--initials {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #e9d5ff;
  color: #6b21a8;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.cilo-hero__greet {
  min-width: 0;
}

.cilo-hero__when {
  margin: 0;
  color: #1c1c1e;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.15;
  letter-spacing: -0.01em;
}

.cilo-hero__name {
  margin: 1px 0 0;
  color: #111111;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.05;
  letter-spacing: -0.04em;
}

.cilo-hero__tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.cilo-icon {
  width: 46px;
  height: 46px;
  padding: 0;
  border: 1px solid #f1f1f3;
  border-radius: 16px;
  background: #ffffff;
  color: #161616;
  box-shadow: 0 8px 18px rgba(17, 17, 17, 0.06);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.cilo-icon iconify-icon {
  font-size: 20px;
  width: 20px;
  height: 20px;
  color: currentColor;
}

.cilo-icon--solid {
  background: #e9d5ff;
  border-color: #e9d5ff;
  color: #6b21a8;
}

.cilo-icon--bell {
  padding: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
}

.cilo-icon--bell :deep(.notification-wrapper) {
  display: flex;
}

.cilo-icon--bell :deep(.notification-bell-btn) {
  width: 46px;
  height: 46px;
  border-radius: 16px;
  border: 1px solid #f1f1f3;
  background: #ffffff;
  box-shadow: 0 8px 18px rgba(17, 17, 17, 0.06);
  color: #161616;
}

.cilo-icon--bell :deep(.notification-bell-icon) {
  width: 20px;
  height: 20px;
  font-size: 20px;
  color: #161616;
}

.cilo-icon--bell :deep(.notification-badge) {
  top: 10px;
  right: 10px;
  min-width: 8px;
  width: 8px;
  height: 8px;
  padding: 0;
  font-size: 0;
  line-height: 0;
  color: transparent;
  background: #ef4444;
  border: 0;
}

.cilo-overview {
  margin: 0 0 4px;
}

.cilo-overview__title {
  margin: 0;
  color: #1c1c1e;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.cilo-overview__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 4px;
}

.cilo-overview__title + .cilo-overview__grid {
  margin-top: 12px;
}

.cilo-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  min-height: 118px;
  padding: 14px 14px 12px;
  border: 1px solid #f2f2f4;
  border-radius: 22px;
  background: #ffffff;
  box-shadow: 0 10px 24px rgba(17, 17, 17, 0.05);
  text-align: left;
  cursor: pointer;
  color: inherit;
}

.cilo-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
}

.cilo-card__label {
  color: #8e8e96;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.2;
}

.cilo-card__arrow {
  color: #b0b0b8;
  font-size: 16px;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.cilo-card__value {
  color: #111111;
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.045em;
  line-height: 1;
}

.cilo-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 6px;
  margin: 0;
}

.cilo-card__delta {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.cilo-card__delta.is-up {
  color: #16a34a;
}

.cilo-card__delta.is-down {
  color: #ef4444;
}

.cilo-card__delta.is-flat {
  color: #8e8e96;
}

.cilo-card__vs {
  color: #a1a1aa;
  font-size: 12px;
  font-weight: 500;
}

.cilo-card--skeleton {
  min-height: 118px;
  background: linear-gradient(90deg, #f4f4f5 0%, #fafafa 50%, #f4f4f5 100%);
  background-size: 200% 100%;
  animation: cilo-shimmer 1.2s ease-in-out infinite;
  box-shadow: none;
}

@keyframes cilo-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

@media (min-width: 900px) {
  .cilo-overview__grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .cilo-card__value {
    font-size: 30px;
  }
}
</style>
