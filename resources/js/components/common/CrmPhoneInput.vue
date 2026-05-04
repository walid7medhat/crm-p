<template>
  <div
    class="crm-phone-input"
    :class="[
      {
        'is-invalid': showInvalid,
        'crm-phone-input--disabled': disabled,
        'crm-phone-input--dropdown-open': countryDropdownOpen,
      },
    ]"
  >
    <VueTelInput
      :model-value="modelValue"
      mode="international"
      :auto-format="true"
      :auto-default-country="true"
      :disabled="disabled"
      :dropdown-options="dropdownOptions"
      :input-options="mergedInputOptions"
      :valid-characters-only="false"
      @update:model-value="onUpdate"
      @validate="onValidate"
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
  /** Server / required-style errors (parent-driven) */
  invalid: { type: Boolean, default: false },
  /**
   * When true, show format error for non-empty values that fail lib validation
   * (e.g. after submit attempt).
   */
  showErrors: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Enter phone number' },
})

const emit = defineEmits(['update:modelValue'])

const lastLibValid = ref(true)
const countryDropdownOpen = ref(false)

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

const showInvalid = computed(() => {
  const v = props.modelValue == null ? '' : String(props.modelValue).trim()
  const formatInvalid = props.showErrors && v.length > 0 && !lastLibValid.value
  return props.invalid || formatInvalid
})

function onValidate(phoneObject) {
  const raw = props.modelValue == null ? '' : String(props.modelValue).trim()
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
