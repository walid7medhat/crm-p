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
  >
    <VueTelInput
      :model-value="normalizedModelValue"
      mode="international"
      :auto-format="autoFormat"
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
import { ref, computed, watch } from 'vue'
import { VueTelInput } from 'vue-tel-input'
import 'vue-tel-input/vue-tel-input.css'

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  defaultCountry: { type: [String, Number], default: 971 },
  /** Server / required-style errors (parent-driven) */
  invalid: { type: Boolean, default: false },
  /**
   * When true, show format error for non-empty values that fail lib validation
   * (e.g. after submit attempt).
   */
  showErrors: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Enter phone number' },
  /**
   * When true, default flag/dial code from visitor IP (vue-tel-input → ip2c.org).
   * When false, use `defaultCountry` (default UAE 971).
   */
  inferCountryFromIp: { type: Boolean, default: false },
  /** Disable vue-tel formatting groups/spaces while typing when needed. */
  autoFormat: { type: Boolean, default: true },
  /**
   * Keep selector compact for optional fields: hide flag/dial only while empty.
   * The flag appears again on focus, country change, or when phone has a value.
   */
  collapseCountryWhenEmpty: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const lastLibValid = ref(true)
const countryDropdownOpen = ref(false)
const isFocused = ref(false)
const hasExplicitCountryChoice = ref(false)

const normalizedModelValue = computed(() => (props.modelValue == null ? '' : String(props.modelValue)))

/* Note: do not use preferred-countries — vue-tel-input concatenates them with the
 * full list without deduping, so each preferred country appears twice in the dropdown. */

const dropdownOptions = {
  showFlags: true,
  showDialCodeInList: true,
  showDialCodeInSelection: true,
  showSearchBox: true,
  searchBoxPlaceholder: 'Search country',
}

const mergedInputOptions = computed(() => ({
  placeholder: props.placeholder,
  showDialCode: true,
  autocomplete: 'tel',
  type: 'tel',
  name: 'telephone',
}))

const trimmedValue = computed(() => normalizedModelValue.value.trim())

const useIpLocationDefault = computed(() => props.inferCountryFromIp)

const resolvedDefaultCountry = computed(() => {
  const raw = String(props.defaultCountry ?? '').trim()
  if (!raw) return 971
  if (/^\d+$/.test(raw)) return Number(raw)
  return raw.toLowerCase()
})

/** Empty default + autoDefaultCountry lets the library geo-detect; otherwise fixed default. */
const bindingDefaultCountry = computed(() =>
  props.inferCountryFromIp ? '' : resolvedDefaultCountry.value,
)

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
  showDialCode: !collapseCountryInactive.value,
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

function onUpdate(v) {
  emit('update:modelValue', v ?? '')
}

function onFocus() {
  isFocused.value = true
}

function onBlur() {
  isFocused.value = false
}

function onCountryChanged() {
  if (!props.collapseCountryWhenEmpty) return
  hasExplicitCountryChoice.value = true
}

watch(
  () => props.modelValue,
  (v) => {
    const s = v == null ? '' : String(v).trim()
    if (!s) lastLibValid.value = true
  },
)
</script>

<style scoped>
.crm-phone-input {
  width: 100%;
  position: relative;
  /* Allow vue-tel-input country list (absolute) to extend outside the control */
  overflow: visible;
}

/* Lift entire control above neighbouring fields while country list is open */
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
  min-width: 72px;
  padding: 0 6px;
}

/* Ensure flag paints in the selector (sprite + positions come from vue-tel-input CSS). */
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

.crm-phone-input--collapse-country :deep(.vti__selection .vti__flag),
.crm-phone-input--collapse-country :deep(.vti__selection .vti__country-code) {
  display: none !important;
}

.crm-phone-input :deep(.vti__input) {
  border: none !important;
  min-height: 44px !important;
  height: 44px !important;
  font-size: 14px !important;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  padding-left: 10px !important;
}

.crm-phone-input :deep(.vti__dropdown-list) {
  border-radius: 10px;
  box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
  background: #fff !important;
  border: 1px solid #e2e8f0;
  /* Bootstrap .modal is ~1055; keep list above modal content and nested UI */
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
