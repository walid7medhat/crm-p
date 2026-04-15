<template>
    <div v-if="modelValue" class="deal-field-settings-overlay" @click.self="close">
      <div class="deal-field-settings-modal modal-body-content p-3" @click.stop>
        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom-light">
          <h5 class="modal-title-custom mb-0">Filter Field Settings</h5>
          <button class="close-btn-custom" @click="close">
            <iconify-icon icon="lucide:x" width="20" height="20" />
          </button>
        </div>

        <div class="d-flex gap-2 mb-4">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
            <span v-if="activeTab === tab.id" class="check-badge">
              <iconify-icon icon="lucide:check" width="10" />
            </span>
          </button>
        </div>

        <div class="settings-subsections">
          <div v-for="section in visibleSections" :key="section.id" class="settings-subsection">
            <div class="settings-subsection-head">
              <label class="d-flex align-items-center gap-2 form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  :checked="isSectionChecked(section)"
                  @change="toggleSection(section, $event.target.checked)"
                />
                <span class="settings-subsection-title">{{ section.label }}</span>
              </label>
            </div>
            <div class="fields-grid">
              <label v-for="field in section.fields" :key="field.id" class="field-item d-flex align-items-center gap-2 form-check m-0">
                <input type="checkbox" class="form-check-input" v-model="draft[field.id]" />
                <span class="field-checkbox">{{ field.label }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-4">
          <a href="#" class="text-muted text-decoration-none fs-14" @click.prevent="restoreDefaults">Default Fields</a>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-cancel rounded-pill" @click="close">Cancel</button>
            <button type="button" class="btn btn-apply rounded-pill" @click="apply">Apply</button>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  selectedFields: { type: Object, required: true },
  tabs: { type: Array, default: () => [] },
  sections: { type: Array, default: () => [] },
  defaults: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'apply'])

const localTabs = computed(() => (props.tabs.length ? props.tabs : [{ id: 'activity', label: 'Activity' }]))
const localSections = computed(() => props.sections || [])
const activeTab = ref(localTabs.value[0]?.id || 'activity')
const draft = reactive({ ...(props.defaults || {}) })

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      activeTab.value = localTabs.value[0]?.id || 'activity'
      Object.assign(draft, { ...(props.defaults || {}), ...(props.selectedFields || {}) })
    }
  },
)

const visibleSections = computed(() => {
  if (!localSections.value.length) return []
  // Match provided design where all groups are visible in one view.
  return localSections.value
})

const isSectionChecked = (section) => section.fields.every((f) => !!draft[f.id])

const toggleSection = (section, checked) => {
  section.fields.forEach((f) => {
    draft[f.id] = checked
  })
}

const close = () => emit('update:modelValue', false)

const restoreDefaults = () => {
  Object.assign(draft, props.defaults || {})
}

const apply = () => {
  emit('apply', { ...draft })
  close()
}
</script>

<style scoped>
.deal-field-settings-overlay {
  position: fixed;
  inset: 0;
  background: rgba(9, 14, 34, 0.4);
  z-index: 12000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.deal-field-settings-modal {
  width: min(960px, 96vw);
  max-height: 92vh;
  overflow: auto;
  background: #fff;
  border: 1px solid #e5eaf0;
  border-radius: 12px;
  padding: 22px 22px 18px;
  font-family: var(--deal-font, 'Montserrat', sans-serif);
}
.modal-body-content,
.modal-body-content * {
  font-family: 'Montserrat', sans-serif !important;
  font-weight: 500;
  font-size: 14px;
}
.modal-title-custom { font-size: 14px !important; font-weight: 500 !important; color: #01062c; }
.close-btn-custom { background: transparent; border: none; color: #000; padding: 0; display: flex; align-items: center; justify-content: center; }
.border-bottom-light { border-bottom: 1px solid #f1f5f9 !important; }
.tab-btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 100px;
  padding: 3px 15px;
  font-size: 13px;
  font-weight: 400;
  color: #666;
  position: relative;
}
.tab-btn.active { background: #01062c; color: #fff; border-color: #01062c; }
.check-badge {
  position: absolute;
  top: -6px;
  right: -2px;
  background: #f59e0b;
  color: #fff;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px solid #fff;
}
.settings-subsections { display: flex; flex-direction: column; gap: 14px; }
.settings-subsection {
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  padding: 12px 14px;
  background: #fff;
}
.settings-subsection-head { border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 10px; }
.settings-subsection-title { font-size: 12px; color: #01062c; font-weight: 600; }
.fields-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 24px; }
.field-checkbox { font-size: 13px !important; font-weight: 400 !important; color: #666 !important; }

::deep(.form-check-input::before) { display: none !important; }
::deep(.form-check-input) {
  width: 20px;
  height: 20px;
  margin-top: 0.15em;
  cursor: pointer;
  border-radius: 6px !important;
  border: 1.5px solid #e2e8f0;
}
::deep(.form-check-input:checked) {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 10 3 3 6-6'/%3e%3c/svg%3e") !important;
  background-color: #f59e0b !important;
  border-color: #f59e0b !important;
  background-size: 18px 18px !important;
  background-position: center !important;
  background-repeat: no-repeat !important;
  box-shadow: 0 0 5px 4px #faa30026 !important;
}
.btn-cancel {
  background: #f4f4f4;
  border: none;
  padding: 10px 25px;
  border-radius: 100px;
  font-size: 14px;
  color: #01062c;
}
.btn-apply {
  background: #000;
  border: none;
  padding: 10px 25px;
  border-radius: 100px;
  font-size: 14px;
  color: #fff;
}
.text-muted { color: #979797 !important; font-size: 13px; font-weight: 400; }
@media (max-width: 768px) {
  .fields-grid { grid-template-columns: 1fr; }
  .tab-btn, .field-checkbox, .settings-subsection-title { font-size: 13px; }
}
</style>