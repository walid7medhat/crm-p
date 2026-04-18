<template>
  <div class="hr-team-tree-panel hr-tree-orgchart">
    <div class="hr-team-tree-panel__scroll">
      <template v-if="rootsList.length === 0">
        <div class="hr-team-tree-empty">
          <iconify-icon icon="lucide:network" width="44" class="hr-team-tree-empty__icon" />
          <p class="hr-team-tree-empty__title">No team hierarchy to show</p>
          <p class="hr-team-tree-empty__hint">
            Try clearing search or set Team Filter and Status to “All”. If you expect data for this date, confirm attendance has finished loading.
          </p>
        </div>
      </template>
      <div v-else class="hr-team-tree-panel__roots">
        <HrTeamTreeNodeCard v-for="root in pagedRoots" :key="root.id" :node="root" :depth="0" @open-sales="openSalesDrawer" />
      </div>
    </div>

    <div v-if="pagerVisible" class="hr-team-tree-panel__pager" role="navigation" aria-label="Managers pagination">
      <button type="button" class="hr-tree-page-btn" :disabled="treePage <= 1" @click="goPrev">Previous</button>
      <div class="hr-tree-page-numbers">
        <template v-for="(slot, idx) in pageSlots" :key="idx">
          <span v-if="slot.type === 'dots'" class="hr-tree-page-dots">…</span>
          <button
            v-else
            type="button"
            class="hr-tree-page-num"
            :class="{ 'hr-tree-page-num--active': slot.n === treePage }"
            @click="treePage = slot.n"
          >
            {{ slot.n }}
          </button>
        </template>
      </div>
      <button type="button" class="hr-tree-page-btn" :disabled="treePage >= totalPages" @click="goNext">Next</button>
    </div>

    <Transition name="hr-sales-drawer">
      <aside v-if="salesDrawer.open" class="hr-sales-drawer" aria-label="Sales list">
        <div class="hr-sales-drawer__head">
          <div>
            <h6 class="hr-sales-drawer__title">{{ salesDrawer.lead?.name || 'Team Lead' }}</h6>
            <p class="hr-sales-drawer__sub">Sales team members</p>
          </div>
          <button type="button" class="hr-sales-drawer__close" @click="closeSalesDrawer">
            <iconify-icon icon="lucide:x" width="18" />
          </button>
        </div>
        <div class="hr-sales-drawer__list">
          <div v-for="member in salesDrawer.sales" :key="member.id" class="hr-sales-item">
            <img :src="member.avatar || '/assets/images/user.png'" alt="" class="hr-sales-item__avatar" />
            <div class="hr-sales-item__copy">
              <div class="hr-sales-item__name">{{ member.name || 'Unnamed' }}</div>
              <div class="hr-sales-item__meta">
                {{ member.employee_id_display || '—' }} · {{ member.hr_attendance?.status || 'absent' }}
              </div>
            </div>
          </div>
          <div v-if="salesDrawer.sales.length === 0" class="hr-sales-empty">No sales found for this lead.</div>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import HrTeamTreeNodeCard from './HrTeamTreeNodeCard.vue'

const ROOTS_PER_PAGE = 5

const props = defineProps({
  roots: { type: Array, default: () => [] },
  /** Same value as HR dashboard `teamFilter` (`'all'` or a team name). */
  teamFilter: { type: String, default: 'all' },
})

const treePage = ref(1)
const salesDrawer = ref({
  open: false,
  lead: null,
  sales: [],
})

const rootsList = computed(() => (Array.isArray(props.roots) ? props.roots : []))

const isAllTeams = computed(() => props.teamFilter === 'all')

const totalPages = computed(() => {
  if (!isAllTeams.value) return 1
  return Math.max(1, Math.ceil(rootsList.value.length / ROOTS_PER_PAGE))
})

const pagedRoots = computed(() => {
  if (!isAllTeams.value) return rootsList.value
  const start = (treePage.value - 1) * ROOTS_PER_PAGE
  return rootsList.value.slice(start, start + ROOTS_PER_PAGE)
})

const pagerVisible = computed(() => isAllTeams.value && rootsList.value.length > ROOTS_PER_PAGE)

const pageSlots = computed(() => {
  const total = totalPages.value
  if (total <= 1) return []
  if (total <= 9) {
    return Array.from({ length: total }, (_, i) => ({ type: 'page', n: i + 1 }))
  }
  const cur = treePage.value
  const nums = new Set([1, total, cur, cur - 1, cur + 1])
  const sorted = [...nums].filter((n) => n >= 1 && n <= total).sort((a, b) => a - b)
  const slots = []
  let prev = 0
  for (const n of sorted) {
    if (prev && n - prev > 1) slots.push({ type: 'dots' })
    slots.push({ type: 'page', n })
    prev = n
  }
  return slots
})

function goPrev() {
  treePage.value = Math.max(1, treePage.value - 1)
}

function goNext() {
  treePage.value = Math.min(totalPages.value, treePage.value + 1)
}

function openSalesDrawer(payload) {
  salesDrawer.value = {
    open: true,
    lead: payload?.lead || null,
    sales: Array.isArray(payload?.sales) ? payload.sales : [],
  }
}

function closeSalesDrawer() {
  salesDrawer.value.open = false
}

watch(
  () => props.teamFilter,
  () => {
    treePage.value = 1
    closeSalesDrawer()
  },
)

watch([rootsList, totalPages], () => {
  if (treePage.value > totalPages.value) treePage.value = totalPages.value
  if (treePage.value < 1) treePage.value = 1
})
</script>

<style scoped>
/* Align with HR dashboard (`hr/index.vue`): blues, navy CTA, gold tab accent, Inter/Montserrat from `app.css`. */
.hr-tree-orgchart {
  --hr-tree-font: 'Inter', 'Montserrat', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --hr-tree-accent: #1d4ed8;
  --hr-tree-accent-weak: #eef4ff;
  --hr-tree-accent-border: rgba(29, 78, 216, 0.42);
  --hr-tree-navy: #0d1f77;
  --hr-tree-gold: #f5c543;
  --hr-tree-border: #d6dff8;
  --hr-tree-border-soft: #edf1f8;
  --hr-tree-border-strong: #b8c9f0;
  --hr-tree-surface: #eef0f6;
  --hr-tree-surface-stats: #f5f7fc;
  --hr-tree-surface-footer: #eef2ff;
  --hr-tree-muted: #737373;
  --hr-tree-text: #404040;
  --hr-tree-text-strong: #171717;
  --hr-tree-line: #c7c9da;
  --hr-tree-line-soft: #dbdeea;
  --hr-tree-radius-card: 14px;
  --hr-tree-radius-card-lg: 14px;
  --hr-tree-card-shadow: 0 2px 10px rgba(17, 24, 39, 0.06);
  --hr-tree-card-shadow-root: 0 0 0 1px rgba(99, 102, 241, 0.18), 0 6px 20px rgba(79, 70, 229, 0.12);
  /* Family-tree card scale */
  --hr-oc-font-base: 13px;
  --hr-oc-font-title: 20px;
  --hr-oc-font-role: 15px;
  --hr-oc-font-meta: 12px;
  --hr-oc-font-time: 12px;
  --hr-oc-card-w: 280px;
  --hr-oc-card-w-mobile: 220px;
  --hr-oc-sibling-gap: 20px;
  --hr-oc-stem-h: 24px;
  --hr-oc-vin-h: 18px;
  font-family: var(--hr-tree-font);
  font-size: var(--hr-oc-font-base);
  line-height: 1.35;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: var(--hr-tree-text);
}

.hr-team-tree-panel {
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex: 1;
  background: var(--hr-tree-surface);
  position: relative;
}

.hr-team-tree-panel__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: auto;
  padding: 1.1rem 1rem 0.75rem;
  -webkit-overflow-scrolling: touch;
}

.hr-team-tree-panel__roots {
  width: 100%;
  max-width: 1280px;
  margin: 0 auto;
  padding-bottom: 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.hr-team-tree-empty {
  max-width: 400px;
  margin: 3rem auto;
  text-align: center;
  padding: 2rem 1.25rem;
  background: #fff;
  border-radius: var(--hr-tree-radius-card-lg);
  border: 1px solid var(--hr-tree-border);
  box-shadow: var(--hr-tree-card-shadow);
}

.hr-team-tree-empty__icon {
  color: var(--hr-tree-accent);
  opacity: 0.45;
  margin-bottom: 1rem;
}

.hr-team-tree-empty__title {
  margin: 0 0 0.5rem;
  font-size: 12px;
  font-weight: 700;
  color: var(--hr-tree-text-strong);
}

.hr-team-tree-empty__hint {
  margin: 0;
  font-size: 11px;
  line-height: 1.5;
  color: var(--hr-tree-muted);
}

.hr-team-tree-panel__pager {
  position: sticky;
  bottom: 0;
  z-index: 2;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem 0.75rem;
  padding: 0.75rem 1rem;
  background: linear-gradient(180deg, rgba(250, 251, 254, 0) 0%, var(--hr-tree-surface) 22%, #fff 100%);
  border-top: 1px solid var(--hr-tree-border-soft);
  box-shadow: 0 -10px 28px rgba(13, 31, 119, 0.05);
}

.hr-tree-page-btn {
  border: 1px solid var(--hr-tree-line-soft);
  background: #fff;
  color: var(--hr-tree-text);
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.hr-tree-page-btn:hover:not(:disabled) {
  background: var(--hr-tree-accent-weak);
  border-color: #cfdcff;
  color: var(--hr-tree-accent);
}

.hr-tree-page-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.hr-tree-page-numbers {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
}

.hr-tree-page-num {
  min-width: 26px;
  height: 26px;
  padding: 0 0.25rem;
  border: 1px solid var(--hr-tree-line-soft);
  background: #fff;
  color: var(--hr-tree-text);
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  border-radius: 50%;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.hr-tree-page-num:hover {
  background: var(--hr-tree-accent-weak);
  border-color: #cfdcff;
  color: var(--hr-tree-accent);
}

.hr-tree-page-num--active {
  background: var(--hr-tree-accent);
  border-color: var(--hr-tree-accent);
  color: #fff;
}

.hr-tree-page-dots {
  color: var(--hr-tree-muted);
  font-size: 11px;
  padding: 0 0.15rem;
  user-select: none;
}

.hr-sales-drawer {
  position: absolute;
  top: 8px;
  right: 8px;
  bottom: 8px;
  width: min(340px, 92vw);
  background: #fff;
  border: 1px solid var(--hr-tree-line-soft);
  border-radius: 14px;
  box-shadow: 0 16px 30px rgba(17, 24, 39, 0.12);
  display: flex;
  flex-direction: column;
  z-index: 4;
}

.hr-sales-drawer__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  padding: 12px;
  border-bottom: 1px solid var(--hr-tree-line-soft);
}

.hr-sales-drawer__title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.hr-sales-drawer__sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.hr-sales-drawer__close {
  width: 28px;
  height: 28px;
  border: 1px solid var(--hr-tree-line-soft);
  border-radius: 8px;
  background: #fff;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.hr-sales-drawer__list {
  overflow: auto;
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.hr-sales-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border: 1px solid #eceff5;
  border-radius: 10px;
}

.hr-sales-item__avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  object-fit: cover;
}

.hr-sales-item__name {
  font-size: 12px;
  font-weight: 700;
  color: #111827;
}

.hr-sales-item__meta {
  font-size: 11px;
  color: #6b7280;
  text-transform: capitalize;
}

.hr-sales-empty {
  font-size: 12px;
  color: #6b7280;
  padding: 6px;
}

.hr-sales-drawer-enter-active,
.hr-sales-drawer-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.hr-sales-drawer-enter-from,
.hr-sales-drawer-leave-to {
  opacity: 0;
  transform: translateX(10px);
}
</style>
