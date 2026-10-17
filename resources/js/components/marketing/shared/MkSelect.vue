<template>
  <div ref="root" class="mk-select" :class="{ 'is-open': open, 'is-set': isSet, 'is-block': block }">
    <button
      type="button"
      class="mk-select__control"
      :aria-label="ariaLabel"
      aria-haspopup="listbox"
      :aria-expanded="open ? 'true' : 'false'"
      :disabled="disabled"
      @click="toggle"
      @keydown.down.prevent="openAndFocus"
    >
      <iconify-icon v-if="icon && !current?.avatar && !current?.dot" :icon="current?.icon || icon" class="mk-select__lead" />
      <MkAvatar v-if="current?.avatar" :user="current.avatar" :size="20" />
      <span v-else-if="current?.dot" class="mk-select__dot" :style="{ background: current.dot }" />
      <span class="mk-select__label">{{ current ? current.label : placeholder }}</span>
      <iconify-icon icon="lucide:chevron-down" class="mk-select__chev" />
    </button>

    <Teleport to="body">
    <Transition name="mk-select-pop">
      <ul v-if="open" ref="menu" class="mk-select__menu" :class="[`is-${align}`, { 'is-up': openUp }]" :style="menuStyle" role="listbox" @keydown="onMenuKey">
        <li v-if="searchable" class="mk-select__search" @click.stop>
          <iconify-icon icon="lucide:search" />
          <input ref="searchInput" v-model="query" type="text" name="mk-select-query" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="Search…" @keydown.down.prevent="focusItem(0)" />
        </li>
        <li
          v-for="(opt, i) in filtered"
          :key="String(opt.value)"
          :ref="(el) => (itemEls[i] = el)"
          role="option"
          tabindex="-1"
          :aria-selected="opt.value === modelValue"
          class="mk-select__item"
          :class="{ active: opt.value === modelValue }"
          @click="pick(opt.value)"
          @keydown.enter.prevent="pick(opt.value)"
        >
          <MkAvatar v-if="opt.avatar" :user="opt.avatar" :size="22" />
          <span v-else-if="opt.dot" class="mk-select__dot" :style="{ background: opt.dot }" />
          <iconify-icon v-else-if="opt.icon" :icon="opt.icon" class="mk-select__opt-icon" />
          <span class="mk-select__opt-label">{{ opt.label }}</span>
          <iconify-icon v-if="opt.value === modelValue" icon="lucide:check" class="mk-select__tick" />
        </li>
        <li v-if="!filtered.length" class="mk-select__empty">No match</li>
      </ul>
    </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import MkAvatar from './MkAvatar.vue'

/**
 * Styled dropdown. options: [{ value, label, icon?, dot? (color), avatar? (user) }].
 * The first option is treated as the "unset" choice (e.g. "Everyone") unless `isSetWhen` says otherwise.
 */
const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, required: true },
  placeholder: { type: String, default: 'Select' },
  icon: { type: String, default: '' },
  ariaLabel: { type: String, default: '' },
  searchable: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  align: { type: String, default: 'left' },
  /** Highlight the control when a non-default value is chosen (filters). */
  highlight: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'change'])

const open = ref(false)
const query = ref('')
const root = ref(null)
const menu = ref(null)
const searchInput = ref(null)
const itemEls = ref([])

const current = computed(() => props.options.find((o) => o.value === props.modelValue))
const isSet = computed(() => props.highlight && props.modelValue !== null && props.modelValue !== '' && props.modelValue !== props.options[0]?.value)
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? props.options.filter((o) => o.label.toLowerCase().includes(q)) : props.options
})

function toggle() {
  open.value ? close() : openAndFocus()
}
const openUp = ref(false)
const menuStyle = ref({})
const MENU_SPACE = 220

function placeMenu() {
  const rect = root.value?.getBoundingClientRect()
  if (!rect) return
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top
  openUp.value = spaceBelow < MENU_SPACE && spaceAbove > spaceBelow
  const style = {
    position: 'fixed',
    // Above the create-card modal (20500). The menu is teleported to body, so a low
    // z-index leaves the stage list hidden behind that overlay.
    zIndex: 20650,
    minWidth: `${Math.max(rect.width, 148)}px`,
  }
  if (openUp.value) {
    style.top = 'auto'
    style.bottom = `${window.innerHeight - rect.top + 6}px`
  } else {
    style.top = `${rect.bottom + 6}px`
    style.bottom = 'auto'
  }
  if (props.align === 'right') {
    style.left = 'auto'
    style.right = `${window.innerWidth - rect.right}px`
  } else {
    style.left = `${rect.left}px`
    style.right = 'auto'
  }
  menuStyle.value = style
}

function openAndFocus() {
  if (props.disabled) return
  placeMenu()
  open.value = true
  query.value = ''
  itemEls.value = []
  nextTick(() => {
    if (props.searchable) searchInput.value?.focus()
    else focusItem(Math.max(0, filtered.value.findIndex((o) => o.value === props.modelValue)))
  })
}
function close() { open.value = false }

function pick(value) {
  emit('update:modelValue', value)
  if (value !== props.modelValue) emit('change', value)
  close()
  root.value?.querySelector('.mk-select__control')?.focus()
}

function focusItem(i) {
  const n = filtered.value.length
  if (!n) return
  itemEls.value[(i + n) % n]?.focus()
}
function onMenuKey(e) {
  const i = itemEls.value.indexOf(document.activeElement)
  if (e.key === 'ArrowDown') { e.preventDefault(); focusItem(i + 1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); focusItem(i - 1) }
  else if (e.key === 'Escape') { e.stopPropagation(); close(); root.value?.querySelector('.mk-select__control')?.focus() }
  else if (e.key === 'Tab') close()
}

function onDoc(e) {
  if (open.value && root.value && !root.value.contains(e.target) && !menu.value?.contains(e.target)) close()
}
function onReflow() {
  if (open.value) placeMenu()
}
onMounted(() => {
  document.addEventListener('mousedown', onDoc)
  window.addEventListener('scroll', onReflow, true)
  window.addEventListener('resize', onReflow)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDoc)
  window.removeEventListener('scroll', onReflow, true)
  window.removeEventListener('resize', onReflow)
})
</script>

<style scoped>
.mk-select { position: relative; display: inline-block; min-width: 0; }
.mk-select.is-block { display: block; width: 100%; }

.mk-select__control {
  width: 100%;
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px 0 12px;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  background: var(--neutral-50);
  color: var(--text-primary-light);
  font-size: 13px !important;
  font-weight: 500 !important;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.mk-select.is-block .mk-select__control { min-height: 42px; border-radius: 12px; background: var(--white); font-size: 14px !important; }
.mk-select__control:hover:not(:disabled) { border-color: color-mix(in srgb, var(--color-primary) 45%, var(--border-color)); background: var(--white); }
.mk-select.is-open .mk-select__control { border-color: var(--color-primary); background: var(--white); box-shadow: 0 0 0 3px var(--color-focus-ring); }
.mk-select__control:focus-visible { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-focus-ring); }
.mk-select__control:disabled { opacity: 0.6; cursor: not-allowed; }
.mk-select.is-set .mk-select__control {
  border-color: color-mix(in srgb, var(--color-primary) 40%, var(--border-color));
  background: rgba(var(--color-primary-rgb), 0.06);
  color: var(--color-primary);
  font-weight: 600 !important;
}
.mk-select__lead { font-size: 15px !important; color: var(--text-secondary-light); flex-shrink: 0; }
.mk-select.is-set .mk-select__lead { color: var(--color-primary); }
.mk-select__label { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mk-select__chev { font-size: 15px !important; color: var(--text-secondary-light); transition: transform 0.2s; flex-shrink: 0; }
.mk-select.is-open .mk-select__chev { transform: rotate(180deg); }
.mk-select__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.mk-select__menu {
  position: absolute;
  z-index: 20650;
  top: calc(100% + 6px);
  min-width: 100%;
  width: max-content;
  max-width: 280px;
  max-height: 300px;
  overflow-y: auto;
  margin: 0;
  padding: 5px;
  list-style: none;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--white);
  box-shadow: 0 16px 36px -12px rgba(15, 10, 30, 0.28), 0 2px 6px rgba(15, 10, 30, 0.06);
}
.mk-select__menu.is-up { top: auto; bottom: calc(100% + 6px); }
.mk-select__menu.is-left { left: 0; }
.mk-select__menu.is-right { right: 0; }
.mk-select.is-block .mk-select__menu { width: 100%; max-width: none; }

.mk-select__search {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  padding: 6px 9px;
  border-radius: 8px;
  background: var(--neutral-50);
  color: var(--text-secondary-light);
}
.mk-select__search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 13px !important; color: var(--text-primary-light); }

.mk-select__item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 9px;
  border-radius: 8px;
  font-size: 13px !important;
  color: var(--text-primary-light);
  cursor: pointer;
  outline: none;
  white-space: nowrap;
}
.mk-select__item:hover, .mk-select__item:focus { background: var(--neutral-100); }
.mk-select__item.active { font-weight: 600 !important; }
.mk-select__opt-icon { font-size: 15px !important; color: var(--text-secondary-light); }
.mk-select__opt-label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.mk-select__tick { color: var(--color-primary); font-size: 15px !important; }
.mk-select__empty { padding: 8px; font-size: 12px !important; text-align: center; color: var(--text-secondary-light); }

.mk-select-pop-enter-active, .mk-select-pop-leave-active { transition: opacity 0.12s, transform 0.12s; }
.mk-select-pop-enter-from, .mk-select-pop-leave-to { opacity: 0; transform: translateY(-4px); }
@media (prefers-reduced-motion: reduce) {
  .mk-select-pop-enter-active, .mk-select-pop-leave-active { transition: none; }
}
</style>
