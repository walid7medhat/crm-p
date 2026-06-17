<template>
  <div
    class="crm-phone-input"
    :class="[
      {
        'is-invalid': showInvalid,
        'crm-phone-input--disabled': disabled,
        'crm-phone-input--dropdown-open': countryDropdownOpen,
        'crm-phone-input--collapse-country': collapseCountryInactive,
      },
    ]"
    @click.stop
  >
    <VueTelInput
      :key="vtiKey"
      ref="telInputRef"
      :model-value="displayValue"
      mode="international"
      :auto-format="false"
      :auto-default-country="useIpLocationDefault"
      :default-country="bindingDefaultCountry"
      :disabled="disabled"
      :dropdown-options="effectiveDropdownOptions"
      :input-options="effectiveInputOptions"
      :valid-characters-only="false"
      @update:model-value="onUpdate"
      @validate="onValidate"
      @country-changed="onCountryChanged"
      @focus="onFocus"
      @blur="onBlur"
      @open="countryDropdownOpen = true"
      @close="countryDropdownOpen = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { VueTelInput } from 'vue-tel-input'
import 'vue-tel-input/vue-tel-input.css'
import { parsePhoneNumberFromString } from 'libphonenumber-js'

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  defaultCountry: { type: [String, Number], default: 971 },
  invalid: { type: Boolean, default: false },
  showErrors: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Enter phone number' },
  inferCountryFromIp: { type: Boolean, default: false },
  autoFormat: { type: Boolean, default: false },
  collapseCountryWhenEmpty: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'country-changed'])

const telInputRef = ref(null)
const lastLibValid = ref(true)
const countryDropdownOpen = ref(false)
const isFocused = ref(false)
const hasExplicitCountryChoice = ref(false)
const currentDialCode = ref('971') // Store the dial code (default UAE)
const localNumber = ref('') // Store only the local number (without dial code)
// ISO2 country derived from the incoming modelValue, e.g. 'EG' for '+201234567890'.
// Drives the flag shown by vue-tel-input so reopening the modal restores the saved country
// instead of falling back to the default UAE.
const detectedCountryIso = ref('')

const normalizedModelValue = computed(() => (props.modelValue == null ? '' : String(props.modelValue)))

// Extract local number from full international number
const extractLocalNumber = (fullNumber) => {
  if (!fullNumber) return ''
  // If starts with +, remove the dial code
  if (fullNumber.startsWith('+')) {
    for (let i = 1; i <= 4; i++) {
      const possibleCode = fullNumber.substring(1, i + 1)
      if (possibleCode === currentDialCode.value || 
          (currentDialCode.value && possibleCode === currentDialCode.value)) {
        return fullNumber.substring(i + 1).replace(/^0+/, '')
      }
    }
  }
  return fullNumber.replace(/\s/g, '')
}

// What the user sees (only local number, no dial code)
const displayValue = computed(() => {
  if (!localNumber.value && !normalizedModelValue.value) return ''
  if (localNumber.value) return localNumber.value
  return extractLocalNumber(normalizedModelValue.value)
})

const dropdownOptions = {
  showFlags: true,
  showDialCodeInList: true,
  showDialCodeInSelection: true,
  showSearchBox: true,
  searchBoxPlaceholder: 'Search country',
}

const mergedInputOptions = computed(() => ({
  placeholder: props.placeholder,
  showDialCode: false, // IMPORTANT: Hide dial code from input
  autocomplete: 'tel',
  type: 'tel',
  name: 'telephone',
}))

const trimmedValue = computed(() => displayValue.value.trim())

const useIpLocationDefault = computed(() => props.inferCountryFromIp)

const resolvedDefaultCountry = computed(() => {
  const raw = String(props.defaultCountry ?? '').trim()
  if (!raw) return 971
  if (/^\d+$/.test(raw)) return Number(raw)
  return raw.toLowerCase()
})

const bindingDefaultCountry = computed(() => {
  if (props.inferCountryFromIp) return ''
  // Honor the country we parsed out of the saved modelValue first, otherwise fall back
  // to the prop default. Without this, reopening with `+201234567890` would show the UAE
  // flag (the static default) instead of Egypt.
  if (detectedCountryIso.value) return detectedCountryIso.value
  return resolvedDefaultCountry.value
})

// vue-tel-input reads `default-country` only on mount — once the flag is set, prop changes
// don't update it. Re-keying the child remounts it so the restored country (from
// `detectedCountryIso`) actually shows up when the modal reopens with a saved phone.
const vtiKey = computed(() => `vti-${bindingDefaultCountry.value || 'default'}`)

const collapseCountryInactive = computed(
  () =>
    props.collapseCountryWhenEmpty &&
    trimmedValue.value.length === 0 &&
    !isFocused.value &&
    !hasExplicitCountryChoice.value,
)

const effectiveDropdownOptions = computed(() => ({
  ...dropdownOptions,
  showDialCodeInSelection: !collapseCountryInactive.value,
}))

const effectiveInputOptions = computed(() => ({
  ...mergedInputOptions.value,
  showDialCode: false, // Always false to hide code
}))

const showInvalid = computed(() => {
  const v = trimmedValue.value
  const formatInvalid = props.showErrors && v.length > 0 && !lastLibValid.value
  return props.invalid || formatInvalid
})

function onValidate(phoneObject) {
  const raw = trimmedValue.value
  if (!raw) {
    lastLibValid.value = true
    return
  }
  const valid = phoneObject?.valid === true || phoneObject?.isValid === true
  lastLibValid.value = valid
}

// Remove spaces and non-digits from input
function cleanInput(value) {
  if (!value) return ''
  // Keep only digits
  return value.replace(/\D/g, '')
}
const isTyping = ref(false)
function onUpdate(v) {
    isTyping.value = true

  // Clean the input value (remove spaces and non-digits)
  const cleaned = cleanInput(v ?? '')
  localNumber.value = cleaned
  
  // Build the full international number to send to backend
  let fullNumber = ''
  if (cleaned) {
    fullNumber = `+${currentDialCode.value}${cleaned}`
  }
  
  // Emit the full number to parent/backend
  emit('update:modelValue', fullNumber)
  nextTick(() => {
    isTyping.value = false
  })
}

function onFocus() {
  isFocused.value = true
}

function onBlur() {
  isFocused.value = false
}

function onCountryChanged(country) {
  // Always update the dial code on country change — the previous early-return for
  // `!collapseCountryWhenEmpty` meant picking Egypt left the dial code at 971 and
  // the search ended up sending +971… instead of +20…
  if (props.collapseCountryWhenEmpty) {
    hasExplicitCountryChoice.value = true
  }

  // Update the dial code when country changes
  if (country) {
    if (country.dialCode) {
      currentDialCode.value = String(country.dialCode)
    } else if (typeof country === 'string') {
      const dialMatch = country.match(/\d+/)
      if (dialMatch) currentDialCode.value = dialMatch[0]
    }
    const iso = country.iso2 || country.iso || country.code
    if (iso && typeof iso === 'string') {
      detectedCountryIso.value = iso.toLowerCase()
    }
  }

  // Notify the parent about the country selection — gives them iso, dial, and name
  // even before the user types digits, so they can label/store the selection.
  emit('country-changed', {
    iso: detectedCountryIso.value || '',
    dialCode: currentDialCode.value || '',
    name: (country && (country.name || country.title)) || '',
  })

  // If there's a local number, rebuild and resend with new dial code
  if (localNumber.value) {
    const fullNumber = `+${currentDialCode.value}${localNumber.value}`
    emit('update:modelValue', fullNumber)
  }
}

// Initialize from props.modelValue
watch(
  () => props.modelValue,
  (newVal) => {
    if (isTyping.value) return
    if (newVal && typeof newVal === 'string') {
      // First try libphonenumber-js to recover both the country and the national digits
      // from an international number (`+201234567890` → country EG, national 1234567890).
      // This is what lets the modal reopen with the right flag instead of the static default.
      const trimmed = newVal.trim()
      const parsed = trimmed.startsWith('+') ? parsePhoneNumberFromString(trimmed) : null
      if (parsed && parsed.countryCallingCode) {
        currentDialCode.value = String(parsed.countryCallingCode)
        if (parsed.country) {
          detectedCountryIso.value = parsed.country.toLowerCase()
        }
        const national = String(parsed.nationalNumber || '').replace(/^0+/, '')
        if (national !== localNumber.value) {
          localNumber.value = national
        }
      } else {
        // Fallback to the legacy extractor when libphonenumber can't parse (partial input,
        // unknown format) — keeps editing-in-progress values intact.
        const extracted = extractLocalNumber(trimmed)
        if (extracted !== localNumber.value) {
          localNumber.value = extracted
        }
      }
    } else if (!newVal) {
      localNumber.value = ''
      detectedCountryIso.value = ''
    }
    if (!newVal) lastLibValid.value = true
  },
  { immediate: true }
)

// Set initial dial code from default country
watch(
  () => bindingDefaultCountry.value,
  async (newCountry) => {
    if (newCountry && !currentDialCode.value) {
      if (newCountry === 971 || newCountry === '971' || newCountry === 'ae') {
        currentDialCode.value = '971'
      }
    }
  },
  { immediate: true }
)

// Expose the current selection imperatively. A parent that does
// `const phone = ref(null)` then `phone.value.getCountry()` will get
// `{ iso, dialCode }` for the currently-flagged country.
defineExpose({
  getCountry: () => ({
    iso: detectedCountryIso.value || '',
    dialCode: currentDialCode.value || '',
  }),
})
</script>

<style scoped>
.crm-phone-input {
  width: 100%;
  position: relative;
  overflow: visible;
}

.crm-phone-input--dropdown-open {
  z-index: 10060;
}

.crm-phone-input :deep(.vue-tel-input) {
  border-radius: var(--deal-input-r, 10px);
  border: 1px solid #e2e8f0;
  overflow: visible;
  background: #fff;
}

.crm-phone-input :deep(.vue-tel-input:focus-within) {
  border-color: #94a3b8;
  box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.06);
}

.crm-phone-input.is-invalid :deep(.vue-tel-input) {
  border-color: #dc3545;
}

.crm-phone-input--disabled :deep(.vue-tel-input) {
  opacity: 0.65;
  pointer-events: none;
  background: #f8fafc;
}

.crm-phone-input :deep(.vti__dropdown) {
  border: none;
  border-right: 1px solid #e2e8f0;
  border-radius: var(--deal-input-r, 10px) 0 0 var(--deal-input-r, 10px);
  min-width: 60px;
  padding: 0 4px;
}

/* Hide the dial code from the dropdown selection area */
.crm-phone-input :deep(.vti__selection .vti__country-code) {
  display: none !important;
}

.crm-phone-input :deep(.vti__selection) {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.crm-phone-input :deep(.vti__selection .vti__flag) {
  display: inline-block;
  flex-shrink: 0;
  vertical-align: middle;
}

/* Hide dial code when collapsed */
.crm-phone-input--collapse-country :deep(.vti__selection .vti__flag),
.crm-phone-input--collapse-country :deep(.vti__selection .vti__country-code) {
  display: none !important;
}

/* Input field styling - no dial code appears here */
.crm-phone-input :deep(.vti__input) {
  border: none !important;
  min-height: 44px !important;
  height: 44px !important;
  font-size: 14px !important;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  padding-left: 10px !important;
  background: transparent !important;
}

/* Remove any default text that might appear */
.crm-phone-input :deep(.vti__input::placeholder) {
  color: #94a3b8;
}

/* Dropdown list styling */
.crm-phone-input :deep(.vti__dropdown-list) {
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
  background: #fff !important;
  border: 1px solid #e2e8f0;
  z-index: 10060 !important;
}

.crm-phone-input :deep(.vti__dropdown-item) {
  font-size: 14px;
  color: #0f172a !important;
  background: #fff !important;
}

.crm-phone-input :deep(.vti__dropdown-item.highlighted),
.crm-phone-input :deep(.vti__dropdown-item:hover) {
  background: #f1f5f9 !important;
  color: #0f172a !important;
}

.crm-phone-input :deep(.vti__search_box) {
  color: #0f172a !important;
  background: #f8fafc !important;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
</style>