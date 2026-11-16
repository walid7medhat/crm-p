<template>
  <!-- Phones: compact pill + styled menu, fits next to the page title. -->
  <div
    v-if="visible && dropdown"
    class="branch-view-dd"
    :class="{ 'branch-view-dd--filtered': current !== '', 'branch-view-dd--open': open }"
  >
    <button
      ref="triggerRef"
      type="button"
      class="branch-view-dd__trigger"
      aria-haspopup="listbox"
      :aria-expanded="open ? 'true' : 'false'"
      aria-label="Branch filter"
      :disabled="switching"
      @click.stop="toggle"
    >
      <iconify-icon icon="lucide:map-pin" class="branch-view-dd__icon" aria-hidden="true" />
      <span class="branch-view-dd__label">{{ currentLabel }}</span>
      <iconify-icon icon="lucide:chevron-down" class="branch-view-dd__chev" aria-hidden="true" />
    </button>

    <Teleport to="body">
      <template v-if="open">
        <div class="branch-view-dd-backdrop" @click="open = false" />
        <ul
          class="branch-view-dd-menu"
          role="listbox"
          aria-label="Branch filter"
          :style="menuStyle"
        >
          <li v-for="opt in options" :key="opt.value || 'all'">
            <button
              type="button"
              role="option"
              class="branch-view-dd-menu__item"
              :class="{ active: current === opt.value }"
              :aria-selected="current === opt.value ? 'true' : 'false'"
              @click="choose(opt.value)"
            >
              <iconify-icon
                :icon="opt.value ? 'lucide:map-pin' : 'lucide:globe'"
                class="branch-view-dd-menu__icon"
                aria-hidden="true"
              />
              <span class="branch-view-dd-menu__text">{{ opt.value ? opt.label : 'All branches' }}</span>
              <iconify-icon
                v-if="current === opt.value"
                icon="lucide:check"
                class="branch-view-dd-menu__check"
                aria-hidden="true"
              />
            </button>
          </li>
        </ul>
      </template>
    </Teleport>
  </div>

  <!-- Desktop: segmented buttons. -->
  <div
    v-else-if="visible"
    class="branch-view-switch"
    :class="{ 'branch-view-switch--filtered': current !== '' }"
    role="radiogroup"
    aria-label="Branch filter"
  >
    <iconify-icon icon="lucide:map-pin" class="branch-view-switch__icon" aria-hidden="true" />
    <button
      v-for="opt in options"
      :key="opt.value || 'all'"
      type="button"
      role="radio"
      class="branch-view-switch__btn"
      :class="{ active: current === opt.value }"
      :aria-checked="current === opt.value"
      :title="opt.value ? `Show ${opt.label} only` : 'Show all branches'"
      :disabled="switching"
      @click="select(opt.value)"
    >
      {{ opt.label }}
    </button>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { isLuxvantBrand } from '@/composables/useBrand.js'
import { BRANCH_VIEW_STORAGE_KEY } from '@/plugins/axios.js'

defineProps({
  /** Render as a compact dropdown (mobile header). */
  dropdown: { type: Boolean, default: false },
})

/** Users (besides super_admin) allowed to switch — must match App\Support\Branch\BranchView. */
const EXTRA_USER_IDS = [30]

const options = [
  { value: '', label: 'All' },
  { value: 'dubai', label: 'Dubai' },
  { value: 'abu_dhabi', label: 'Abu Dhabi' },
]

function readUser() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    return null
  }
}

function readCurrent() {
  try {
    const v = localStorage.getItem(BRANCH_VIEW_STORAGE_KEY) || ''
    return v === 'abu_dhabi' || v === 'dubai' ? v : ''
  } catch {
    return ''
  }
}

const user = ref(readUser())
const current = ref(readCurrent())
const switching = ref(false)
const open = ref(false)
const triggerRef = ref(null)
const menuStyle = ref({})

const visible = computed(() => {
  if (isLuxvantBrand()) return false
  const u = user.value
  if (!u) return false
  return u.roles?.includes('super_admin') || EXTRA_USER_IDS.includes(Number(u.id))
})

const currentLabel = computed(() => options.find((o) => o.value === current.value)?.label || 'All')

function placeMenu() {
  const el = triggerRef.value
  if (!el) return
  const r = el.getBoundingClientRect()
  const width = 180
  const left = Math.max(8, Math.min(r.left, window.innerWidth - width - 8))
  menuStyle.value = { top: `${Math.round(r.bottom + 6)}px`, left: `${Math.round(left)}px`, width: `${width}px` }
}

function toggle() {
  if (open.value) {
    open.value = false
    return
  }
  placeMenu()
  open.value = true
}

function choose(value) {
  open.value = false
  select(value)
}

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}

function onViewportChange() {
  if (open.value) open.value = false
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  window.addEventListener('resize', onViewportChange)
  window.addEventListener('scroll', onViewportChange, true)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', onViewportChange)
  window.removeEventListener('scroll', onViewportChange, true)
})

function select(value) {
  if (switching.value || value === current.value) return
  try {
    if (value) localStorage.setItem(BRANCH_VIEW_STORAGE_KEY, value)
    else localStorage.removeItem(BRANCH_VIEW_STORAGE_KEY)
  } catch {
    return
  }
  current.value = value
  switching.value = true
  // Every board, list, count and dashboard widget reloads with the new branch.
  window.location.reload()
}
</script>

<style scoped>
/* ── Phone dropdown pill ───────────────────────────── */
.branch-view-dd {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
}

.branch-view-dd__trigger {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 32px;
  padding: 0 10px 0 9px;
  border-radius: 999px;
  background: #f4f5f7;
  border: 1px solid #e8eaef;
  color: #4b4568;
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  transition: background 150ms ease, box-shadow 150ms ease;
}

.branch-view-dd--filtered .branch-view-dd__trigger {
  background: var(--gradient-primary);
  border-color: var(--color-primary);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.28);
}

.branch-view-dd__icon,
.branch-view-dd__chev {
  font-size: 13px;
  color: var(--color-primary);
}

.branch-view-dd--filtered .branch-view-dd__icon,
.branch-view-dd--filtered .branch-view-dd__chev {
  color: #ffffff;
}

.branch-view-dd__chev {
  transition: transform 150ms ease;
}

.branch-view-dd--open .branch-view-dd__chev {
  transform: rotate(180deg);
}

.branch-view-dd__trigger:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* ── Phone dropdown menu (teleported to body) ─────── */
.branch-view-dd-backdrop {
  position: fixed;
  inset: 0;
  z-index: 3000;
  background: transparent;
}

.branch-view-dd-menu {
  position: fixed;
  z-index: 3001;
  margin: 0;
  padding: 6px;
  list-style: none;
  background: #ffffff;
  border: 1px solid #ece9f3;
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(23, 18, 41, 0.16), 0 2px 6px rgba(23, 18, 41, 0.06);
  animation: branch-view-dd-in 140ms ease-out;
}

@keyframes branch-view-dd-in {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.branch-view-dd-menu__item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 42px;
  padding: 8px 10px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #2d2742;
  font-size: 14px;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.branch-view-dd-menu__item:hover,
.branch-view-dd-menu__item:focus-visible {
  background: rgba(var(--color-primary-rgb), 0.08);
  outline: none;
}

.branch-view-dd-menu__item.active {
  background: rgba(var(--color-primary-rgb), 0.12);
  color: var(--color-primary);
  font-weight: 600;
}

.branch-view-dd-menu__icon {
  font-size: 16px;
  color: var(--color-primary);
  flex-shrink: 0;
}

.branch-view-dd-menu__text {
  flex: 1 1 auto;
}

.branch-view-dd-menu__check {
  font-size: 16px;
  color: var(--color-primary);
}

/* ── Desktop segmented switch ─────────────────────── */
.branch-view-switch {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 3px;
  border-radius: 999px;
  background: #f4f5f7;
  border: 1px solid #e8eaef;
  flex-shrink: 0;
  max-width: 100%;
}

.branch-view-switch--filtered {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb), 0.12);
}

.branch-view-switch__icon {
  font-size: 14px;
  color: var(--color-primary);
  margin: 0 4px 0 6px;
}

.branch-view-switch__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  background: transparent;
  color: #4b4568;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  cursor: pointer;
  transition: background 150ms ease, color 150ms ease, box-shadow 150ms ease;
}

.branch-view-switch__btn:hover:not(.active):not(:disabled) {
  color: var(--color-primary);
  background: rgba(var(--color-primary-rgb), 0.08);
}

.branch-view-switch__btn.active {
  background: var(--gradient-primary);
  color: #ffffff;
  border-color: var(--color-primary);
  box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.28);
}

.branch-view-switch__btn:disabled {
  cursor: progress;
  opacity: 0.7;
}

.branch-view-switch__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}
</style>
