<template>
  <input
    ref="inputRef"
    :value="display"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    @input="onInput"
    @blur="onBlur"
  />
</template>

<script setup>
/**
 * Money input: shows thousands separators while typing ("7,100,100.50") but the
 * v-model always holds the plain number string ("7100100.50") or null, so what is
 * sent to the API never contains commas. Pasted values ("AED 7,100,100.00") are cleaned.
 * Extra attributes / classes fall through to the <input>.
 */
import { nextTick, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  /** Max digits after the decimal point. */
  decimals: { type: Number, default: 2 },
})
const emit = defineEmits(['update:modelValue'])

const inputRef = ref(null)
const display = ref(format(clean(props.modelValue)))

/** Plain number string from any input: digits + one "." + limited decimals. */
function clean(value) {
  if (value === null || value === undefined) return ''
  let s = String(value)
    .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/٫/g, '.')
    .replace(/[^0-9.]/g, '')
  const dot = s.indexOf('.')
  if (dot !== -1) {
    const intPart = s.slice(0, dot)
    const decPart = s.slice(dot + 1).replace(/\./g, '').slice(0, props.decimals)
    s = props.decimals > 0 ? `${intPart}.${decPart}` : intPart
  }
  // drop leading zeros ("007" → "7", keep "0.5")
  s = s.replace(/^0+(?=\d)/, '')
  return s
}

/** "7100100.5" → "7,100,100.5" (a trailing "." is kept while typing). */
function format(plain) {
  if (!plain) return ''
  const [intPart, decPart] = plain.split('.')
  const withCommas = (intPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return decPart !== undefined ? `${withCommas}.${decPart}` : withCommas
}

function toModel(plain) {
  if (!plain || plain === '.') return null
  return plain.endsWith('.') ? plain.slice(0, -1) : plain
}

function onInput(e) {
  const el = e.target
  const raw = el.value
  const caret = el.selectionStart ?? raw.length
  // Characters that matter (digits / dot) before the caret — keeps the caret in place
  // after commas are added or removed.
  const significantBefore = raw.slice(0, caret).replace(/[^0-9.]/g, '').length

  const plain = clean(raw)
  const formatted = format(plain)
  display.value = formatted
  el.value = formatted

  emit('update:modelValue', toModel(plain))

  nextTick(() => {
    let pos = 0
    let seen = 0
    while (pos < formatted.length && seen < significantBefore) {
      if (/[0-9.]/.test(formatted[pos])) seen += 1
      pos += 1
    }
    try { el.setSelectionRange(pos, pos) } catch (_) { /* not focusable */ }
  })
}

function onBlur() {
  // "1,000." → "1,000"
  const plain = toModel(clean(display.value)) ?? ''
  display.value = format(plain)
}

// Outside changes (form reset, value loaded from the API).
watch(
  () => props.modelValue,
  (val) => {
    const plain = clean(val)
    if (toModel(plain) !== toModel(clean(display.value))) {
      display.value = format(plain)
    }
  }
)
</script>
