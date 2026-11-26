<template>
  <div ref="rootRef" class="lead-search-phone" :class="{ 'lead-search-phone--open': open, 'lead-search-phone--country-only': countryOnly }">
    <div ref="controlRef" class="lead-search-phone__control">
      <button
        v-if="showCountry"
        type="button"
        class="lead-search-phone__code"
        :class="{ 'lead-search-phone__code--intl': isInternational, 'lead-search-phone__code--set': hasSelection }"
        aria-haspopup="listbox"
        :aria-expanded="open"
        @click.stop="toggle"
        @keydown.down.prevent="openAt(0)"
        @keydown.enter.prevent="toggle"
        @keydown.esc.prevent="close"
      >
        <span v-if="isInternational" class="lead-search-phone__code-label">International</span>
        <span v-else-if="single" class="lead-search-phone__code-label">
          <span class="lead-search-phone__flag" aria-hidden="true">{{ single.flag }}</span>
          <span class="lead-search-phone__name">{{ single.name }}</span>
          <span class="lead-search-phone__dial">+{{ single.dial }}</span>
        </span>
        <span v-else-if="selected.length > 1" class="lead-search-phone__code-label">{{ selected.map((country) => `+${country.dial}`).join(', ') }}</span>
        <span v-else class="lead-search-phone__code-label lead-search-phone__code-label--any">{{ countryOnly ? (placeholder || 'e.g. +971 (comma for several)') : 'Any' }}</span>
        <span class="lead-search-phone__caret" aria-hidden="true"></span>
      </button>

      <input
        v-if="!countryOnly"
        :value="modelValue"
        class="lead-search-phone__input"
        type="tel"
        inputmode="tel"
        autocomplete="off"
        :placeholder="placeholder"
        @input="onPhoneInput"
      />

      <button
        v-if="showCountry && hasSelection"
        type="button"
        class="lead-search-phone__clear"
        aria-label="Clear country"
        @click.stop="clearCountry"
      >×</button>
    </div>

    <p v-if="isInternational" class="lead-search-phone__hint">International · Excluding UAE</p>

    <Teleport to="body">
      <div
        v-if="open"
        ref="menuRef"
        class="lead-search-phone__menu"
        :style="menuStyle"
        role="listbox"
        @mousedown.stop
        @click.stop
      >
        <input
          ref="searchRef"
          v-model="term"
          class="lead-search-phone__search"
          type="text"
          placeholder="Country, +971, 050…"
          autocomplete="off"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="chooseHighlighted"
          @keydown.esc.prevent="close"
        />
        <ul class="lead-search-phone__list">
          <li v-if="showInternationalOption">
            <button
              type="button"
              class="lead-search-phone__option lead-search-phone__option--intl"
              :class="{ 'is-active': highlight === 0, 'is-selected': isInternational }"
              role="option"
              :aria-selected="isInternational"
              @mouseenter="highlight = 0"
              @click="pickInternational"
            >
              <span class="lead-search-phone__option-flag" aria-hidden="true">🌐</span>
              <span class="lead-search-phone__option-text">
                <span class="lead-search-phone__option-name">International</span>
                <span class="lead-search-phone__option-sub">Excluding UAE</span>
              </span>
            </button>
          </li>
          <li v-for="(country, index) in filtered" :key="country.iso">
            <button
              type="button"
              class="lead-search-phone__option"
              :class="{ 'is-active': highlight === index + intlOffset, 'is-selected': isCountrySelected(country) }"
              role="option"
              :aria-selected="isCountrySelected(country)"
              @mouseenter="highlight = index + intlOffset"
              @click="pickCountry(country)"
            >
              <span class="lead-search-phone__option-flag" aria-hidden="true">{{ country.flag }}</span>
              <span class="lead-search-phone__option-name">{{ country.name }}</span>
              <span class="lead-search-phone__option-dial">+{{ country.dial }}</span>
            </button>
          </li>
          <li v-if="!showInternationalOption && filtered.length === 0" class="lead-search-phone__empty">No country found</li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { getCountries, getCountryCallingCode } from 'libphonenumber-js'

const props = defineProps({
  modelValue: { type: String, default: '' },
  country: { type: String, default: '' },
  showCountry: { type: Boolean, default: false },
  countryOnly: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Enter Phone' },
})

const emit = defineEmits(['update:modelValue', 'update:country'])

const UAE_PREFIXES = ['050', '052', '054', '055', '056', '058', '02', '03', '04', '06', '07', '09', '00971', '971']
const PREFERRED = ['AE', 'SA', 'EG', 'GB', 'US', 'IN', 'PK', 'QA', 'KW', 'BH', 'OM']
const ALIASES = {
  AE: ['uae', 'emirates'],
  SA: ['ksa', 'saudi'],
  GB: ['uk', 'britain', 'england'],
  US: ['usa', 'america'],
}

const regionNames = typeof Intl !== 'undefined' && Intl.DisplayNames
  ? new Intl.DisplayNames(['en'], { type: 'region' })
  : null

function flagEmoji(iso) {
  const code = String(iso || '').toUpperCase()
  if (!/^[A-Z]{2}$/.test(code)) return ''
  return code.replace(/./g, (char) => String.fromCodePoint(127397 + char.charCodeAt(0)))
}

function countryName(iso) {
  try {
    return regionNames?.of(iso) || iso
  } catch {
    return iso
  }
}

const countries = getCountries()
  .map((iso) => {
    let dial = ''
    try {
      dial = String(getCountryCallingCode(iso))
    } catch {
      return null
    }
    if (!dial) return null
    return {
      iso,
      dial,
      name: countryName(iso),
      flag: flagEmoji(iso),
      prefixes: iso === 'AE' ? UAE_PREFIXES : [],
      aliases: ALIASES[iso] || [],
    }
  })
  .filter(Boolean)
  .sort((a, b) => {
    const ai = PREFERRED.indexOf(a.iso)
    const bi = PREFERRED.indexOf(b.iso)
    if (ai !== -1 || bi !== -1) {
      if (ai === -1) return 1
      if (bi === -1) return -1
      return ai - bi
    }
    return a.name.localeCompare(b.name)
  })

const dialsLongest = [...new Set(countries.map((c) => c.dial))].sort((a, b) => b.length - a.length || a.localeCompare(b))

const rootRef = ref(null)
const controlRef = ref(null)
const menuRef = ref(null)
const searchRef = ref(null)
const open = ref(false)
const term = ref('')
const highlight = ref(0)
const menuStyle = ref({})
const locked = ref(false)
// Dial codes are shared (+7 is Russia and Kazakhstan, +1 is the US and Canada).
// Remember the country that was clicked so the field shows that one.
const pickedIsoByDial = ref({})

const isInternational = computed(() => String(props.country || '').trim().toLowerCase() === 'international')

const selectedDials = computed(() => {
  if (isInternational.value) return []
  return String(props.country || '')
    .split(',')
    .map((part) => part.replace(/\D/g, '').replace(/^00/, ''))
    .filter((dial) => dial && countries.some((c) => c.dial === dial))
})

const selected = computed(() => {
  const seen = new Set()
  const rows = []
  selectedDials.value.forEach((dial) => {
    if (seen.has(dial)) return
    seen.add(dial)
    const remembered = pickedIsoByDial.value[dial]
    const match = remembered
      ? countries.find((country) => country.iso === remembered && country.dial === dial)
      : null
    rows.push(match || countries.find((country) => country.dial === dial))
  })
  return rows.filter(Boolean)
})

const single = computed(() => (selected.value.length === 1 ? selected.value[0] : null))
const hasSelection = computed(() => isInternational.value || selected.value.length > 0)

const normalizedTerm = computed(() => String(term.value || '').trim().toLowerCase())

const showInternationalOption = computed(() => {
  const q = normalizedTerm.value
  if (!q) return true
  return ['international', 'intl', 'excluding', 'non-uae', 'non uae', 'foreign'].some((word) => word.startsWith(q) || q.startsWith(word))
})

const intlOffset = computed(() => (showInternationalOption.value ? 1 : 0))

const filtered = computed(() => {
  const q = normalizedTerm.value
  if (!q) return countries
  const digits = q.replace(/\D/g, '').replace(/^00/, '')
  return countries.filter((country) => {
    if (country.name.toLowerCase().includes(q)) return true
    if (country.iso.toLowerCase().includes(q)) return true
    if (country.aliases.some((alias) => alias.startsWith(q) || q.startsWith(alias))) return true
    if (q === `+${country.dial}` || q === country.dial) return true
    if (digits && (country.dial.startsWith(digits) || digits.startsWith(country.dial))) return true
    if (digits && country.prefixes.some((prefix) => prefix.startsWith(digits) || digits.startsWith(prefix))) return true
    return false
  })
})

const optionCount = computed(() => filtered.value.length + intlOffset.value)

function isDialSelected(dial) {
  return selectedDials.value.includes(dial)
}

function isCountrySelected(country) {
  return pickedIsoByDial.value[country.dial] === country.iso
}

function emitCountry(value) {
  if (String(props.country || '') === value) return
  emit('update:country', value)
}

function detectDial(raw) {
  const trimmed = String(raw || '').trim()
  if (!trimmed) return null
  const digits = trimmed.replace(/\D/g, '')
  if (!digits) return null
  const explicit = trimmed.includes('+') || digits.startsWith('00')
  let core = digits.startsWith('00') ? digits.slice(2) : digits
  if (explicit && core) {
    const hit = dialsLongest.find((code) => core === code || core.startsWith(code))
    if (hit) return hit
  }
  if (/^05(0|2|4|5|6|8)/.test(digits) && digits.length <= 10) return '971'
  if (/^0(2|3|4|6|7|9)/.test(digits) && digits.length <= 9 && !digits.startsWith('00')) return '971'
  if (digits === '971' || digits === '00971') return '971'
  return null
}

function onPhoneInput(event) {
  const value = event.target.value
  emit('update:modelValue', value)
  if (!props.showCountry || locked.value || isInternational.value) return
  if (!String(value || '').trim()) {
    emitCountry('')
    return
  }
  const dial = detectDial(value)
  if (dial) emitCountry(dial)
}

function pickInternational() {
  locked.value = true
  pickedIsoByDial.value = {}
  emitCountry('international')
  close()
}

function pickCountry(country) {
  locked.value = true
  const nextMap = { ...pickedIsoByDial.value }
  const dials = selectedDials.value.filter((dial) => dial !== country.dial)
  if (nextMap[country.dial] === country.iso) {
    delete nextMap[country.dial]
  } else {
    dials.push(country.dial)
    nextMap[country.dial] = country.iso
  }
  pickedIsoByDial.value = nextMap
  emitCountry(dials.join(','))
  close()
}

function clearCountry() {
  locked.value = false
  pickedIsoByDial.value = {}
  emitCountry('')
  close()
}

function positionMenu() {
  const el = controlRef.value
  if (!el) return
  const rect = el.getBoundingClientRect()
  const width = Math.max(rect.width, 260)
  const menuHeight = 280
  const spaceBelow = window.innerHeight - rect.bottom
  const openUp = spaceBelow < menuHeight && rect.top > menuHeight
  menuStyle.value = {
    position: 'fixed',
    top: openUp ? `${rect.top - 4}px` : `${rect.bottom + 4}px`,
    left: `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`,
    width: `${Math.min(width, window.innerWidth - 16)}px`,
    transform: openUp ? 'translateY(-100%)' : 'none',
    zIndex: 20000,
  }
}

function openMenu() {
  if (!props.showCountry) return
  open.value = true
  term.value = ''
  highlight.value = 0
  nextTick(() => {
    positionMenu()
    searchRef.value?.focus()
  })
}

function close() {
  open.value = false
  term.value = ''
}

function toggle() {
  if (open.value) close()
  else openMenu()
}

function openAt(index) {
  if (!open.value) openMenu()
  highlight.value = index
}

function move(step) {
  const count = optionCount.value
  if (!count) return
  highlight.value = (highlight.value + step + count) % count
}

function chooseHighlighted() {
  const typed = term.value.trim()
  if (typed.includes(',')) {
    const dials = []
    typed.split(',').forEach((part) => {
      const digits = part.replace(/\D/g, '').replace(/^00/, '')
      if (digits && countries.some((country) => country.dial === digits) && !dials.includes(digits)) {
        dials.push(digits)
      }
    })
    if (dials.length) {
      locked.value = true
      emitCountry(dials.join(','))
      close()
      return
    }
  }
  if (showInternationalOption.value && highlight.value === 0) {
    pickInternational()
    return
  }
  const country = filtered.value[highlight.value - intlOffset.value]
  if (country) pickCountry(country)
}

function onDocumentPointer(event) {
  if (!open.value) return
  const target = event.target
  if (rootRef.value?.contains(target) || menuRef.value?.contains(target)) return
  close()
}

function onReposition() {
  if (open.value) positionMenu()
}

watch(() => props.country, (value) => {
  if (!String(value || '').trim()) {
    locked.value = false
    pickedIsoByDial.value = {}
  }
})

watch(highlight, () => {
  if (!open.value) return
  nextTick(() => {
    menuRef.value?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' })
  })
})

watch(open, (isOpen) => {
  if (isOpen) {
    document.addEventListener('mousedown', onDocumentPointer, true)
    window.addEventListener('resize', onReposition)
    window.addEventListener('scroll', onReposition, true)
  } else {
    document.removeEventListener('mousedown', onDocumentPointer, true)
    window.removeEventListener('resize', onReposition)
    window.removeEventListener('scroll', onReposition, true)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentPointer, true)
  window.removeEventListener('resize', onReposition)
  window.removeEventListener('scroll', onReposition, true)
})
</script>

<style scoped>
.lead-search-phone {
  position: relative;
  width: 100%;
  min-width: 0;
}

.lead-search-phone--open {
  z-index: 40;
}

.lead-search-phone__control {
  display: flex;
  align-items: center;
  width: 100%;
  height: 40px;
  min-height: 40px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  background: #fff;
  overflow: hidden;
}

.lead-search-phone__control:focus-within {
  border-color: var(--color-primary, #733e87);
  box-shadow: 0 0 0 3px rgba(115, 62, 135, 0.16);
}

.lead-search-phone__code {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex: 0 0 auto;
  height: 100%;
  max-width: 46%;
  margin: 0;
  padding: 0 8px 0 10px;
  border: 0;
  border-right: 1px solid #eadff0;
  background: #faf8fb;
  color: #2a2230;
  font: 600 12px/1 'Montserrat', 'Inter', sans-serif;
  cursor: pointer;
}

.lead-search-phone--country-only .lead-search-phone__code {
  flex: 1 1 auto;
  max-width: none;
  border-right: 0;
  background: transparent;
}

.lead-search-phone__code--intl {
  color: #6d28d9;
}

.lead-search-phone__code-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
}

.lead-search-phone__code-label--any {
  color: #a89fb2;
  font-weight: 400;
}

.lead-search-phone__name {
  overflow: hidden;
  text-overflow: ellipsis;
}

.lead-search-phone__dial {
  color: #8b7f96;
  font-weight: 600;
}

.lead-search-phone__flag {
  font-size: 14px;
  line-height: 1;
}

.lead-search-phone__caret {
  width: 0;
  height: 0;
  margin-left: 2px;
  border-left: 3px solid transparent;
  border-right: 3px solid transparent;
  border-top: 4px solid #94a3b8;
}

.lead-search-phone__input {
  flex: 1 1 auto;
  width: 0;
  height: 100%;
  min-width: 0;
  padding: 0 8px;
  border: 0;
  outline: none;
  background: transparent;
  color: #2a2230;
  font: 400 12px/1.2 'Montserrat', 'Inter', sans-serif;
}

.lead-search-phone__input::placeholder {
  color: #a89fb2;
}

.lead-search-phone__clear {
  flex: 0 0 auto;
  width: 22px;
  height: 22px;
  margin-right: 4px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #94a3b8;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
}

.lead-search-phone__clear:hover {
  color: #2a2230;
  background: #f1f5f9;
}

.lead-search-phone__hint {
  margin: 4px 2px 0;
  color: #6d28d9;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.01em;
}
</style>

<style>
.lead-search-phone__menu {
  display: flex;
  flex-direction: column;
  max-height: 280px;
  padding: 6px;
  border: 1px solid #eadff0;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 12px 32px rgba(28, 23, 48, 0.16);
}

.lead-search-phone__search {
  width: 100%;
  height: 32px;
  margin-bottom: 4px;
  padding: 0 10px;
  border: 1px solid #eadff0;
  border-radius: 8px;
  background: #faf8fb;
  color: #2a2230;
  font: 400 12px/1 'Montserrat', 'Inter', sans-serif;
  outline: none;
}

.lead-search-phone__search:focus {
  border-color: #733e87;
}

.lead-search-phone__list {
  list-style: none;
  margin: 0;
  padding: 0;
  overflow-y: auto;
}

.lead-search-phone__option {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 32px;
  padding: 4px 8px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #2a2230;
  font: 500 12px/1.3 'Montserrat', 'Inter', sans-serif;
  text-align: left;
  cursor: pointer;
}

.lead-search-phone__option.is-active,
.lead-search-phone__option:hover {
  background: #f6f1f8;
}

.lead-search-phone__option.is-selected {
  background: #f3e8ff;
}

.lead-search-phone__option--intl .lead-search-phone__option-name {
  color: #6d28d9;
}

.lead-search-phone__option-flag {
  flex: 0 0 auto;
  width: 18px;
  font-size: 14px;
  text-align: center;
}

.lead-search-phone__option-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.lead-search-phone__option-name {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.lead-search-phone__option-sub,
.lead-search-phone__option-dial {
  flex: 0 0 auto;
  color: #8b7f96;
  font-weight: 500;
}

.lead-search-phone__option-sub {
  font-size: 10px;
}

.lead-search-phone__empty {
  padding: 10px 8px;
  color: #94a3b8;
  font-size: 12px;
}
</style>
