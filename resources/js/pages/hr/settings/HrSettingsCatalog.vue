<template>
  <section class="hr-set-catalog">
    <div class="hr-set-catalog__head">
      <div>
        <p class="hr-set-catalog__title">{{ title }}</p>
        <p v-if="description">{{ description }}</p>
      </div>
      <button type="button" class="hr-set-btn hr-set-btn--primary" @click="openCreate">
        <iconify-icon icon="lucide:plus" />
        {{ addLabel }}
      </button>
    </div>

    <label class="hr-set-search">
      <iconify-icon icon="lucide:search" />
      <input v-model="search" type="text" :placeholder="searchPlaceholder" />
    </label>

    <div v-if="loading" class="hr-set-empty">Loading...</div>
    <div v-else-if="!filteredItems.length" class="hr-set-empty">
      {{ search ? 'No matching items.' : emptyText }}
    </div>
    <div v-else class="hr-set-list">
      <article
        v-for="(item, index) in filteredItems"
        :key="itemKey(item, index)"
        class="hr-set-item"
        :class="{
          'is-selected': selectedId != null && recordId(item) === Number(selectedId),
          'is-selectable': props.selectable,
        }"
        @click="onItemClick(item)"
      >
        <div class="hr-set-item__body">
          <strong>{{ item[nameKey] || '—' }}</strong>
          <small v-if="subtitleFor(item)">{{ subtitleFor(item) }}</small>
        </div>
        <div class="hr-set-item__meta" v-if="badgeFor(item)">
          <span class="hr-set-badge" :class="badgeClassFor(item)">{{ badgeFor(item) }}</span>
        </div>
        <div class="hr-set-item__actions">
          <button type="button" title="Edit" @click.stop="openEdit(item)"><iconify-icon icon="lucide:pencil" /></button>
          <button type="button" title="Delete" @click.stop="confirmRemove(item)"><iconify-icon icon="lucide:trash-2" /></button>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showForm" class="hr-set-form-overlay" @click.self="closeForm">
        <form class="hr-set-form-dialog" autocomplete="off" @submit.prevent="submitForm" @keydown.esc="closeForm">
          <header class="hr-set-form-dialog__head">
            <div class="hr-set-form-dialog__icon">
              <iconify-icon :icon="editingId ? 'lucide:pencil' : 'lucide:plus'" />
            </div>
            <div class="hr-set-form-dialog__copy">
              <p class="hr-set-form-dialog__title">{{ editingId ? 'Edit' : addLabel }}</p>
              <p class="hr-set-form-dialog__sub">{{ formSubtitle }}</p>
            </div>
            <button type="button" class="hr-set-form-dialog__close" aria-label="Close" @click="closeForm">
              <iconify-icon icon="lucide:x" />
            </button>
          </header>

          <div class="hr-set-form-dialog__body">
            <div class="hr-set-form__grid" :class="{ 'is-wide': fields.length > 2 }">
              <div
                v-for="field in fields"
                :key="field.key"
                class="hr-set-field"
                :class="{ 'is-full': field.full || field.type === 'textarea' || field.type === 'toggle' }"
              >
                <label>
                  {{ field.label }}
                  <em v-if="field.required">*</em>
                </label>
                <select v-if="field.type === 'select'" v-model="form[field.key]">
                  <option value="">{{ field.placeholder || 'Select' }}</option>
                  <option v-for="opt in field.options || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <textarea
                  v-else-if="field.type === 'textarea'"
                  v-model="form[field.key]"
                  rows="4"
                  :placeholder="field.placeholder || ''"
                />
                <label v-else-if="field.type === 'toggle'" class="hr-set-switch">
                  <input type="checkbox" v-model="form[field.key]" />
                  <span class="hr-set-switch__track" />
                  <span class="hr-set-switch__label">
                    {{ form[field.key] ? (field.onLabel || 'Enabled') : (field.offLabel || 'Disabled') }}
                  </span>
                </label>
                <input
                  v-else
                  v-model="form[field.key]"
                  :type="field.type || 'text'"
                  :min="field.min"
                  :placeholder="field.placeholder || ''"
                />
                <small v-if="field.hint" class="hr-set-field__hint">{{ field.hint }}</small>
              </div>
            </div>
          </div>

          <footer class="hr-set-form-dialog__foot">
            <button type="button" class="hr-set-btn" @click="closeForm">Cancel</button>
            <button type="submit" class="hr-set-btn hr-set-btn--primary" :disabled="saving">
              <iconify-icon v-if="!saving" :icon="editingId ? 'lucide:check' : 'lucide:plus'" />
              {{ saving ? 'Saving...' : (editingId ? 'Save changes' : 'Add') }}
            </button>
          </footer>
        </form>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  addLabel: { type: String, default: 'Add' },
  emptyText: { type: String, default: 'Nothing here yet.' },
  searchPlaceholder: { type: String, default: 'Search' },
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  nameKey: { type: String, default: 'name' },
  fields: { type: Array, default: () => [{ key: 'name', label: 'Name', type: 'text', required: true }] },
  subtitle: { type: Function, default: null },
  badge: { type: Function, default: null },
  badgeClass: { type: Function, default: null },
  selectedId: { type: [Number, String], default: null },
  selectable: { type: Boolean, default: false },
  formHint: { type: String, default: '' },
})

const emit = defineEmits(['save', 'remove', 'select'])

const search = ref('')
const showForm = ref(false)
const editingItem = ref(null)
const editingId = ref(null)
const form = reactive({})

const formSubtitle = computed(() => {
  if (props.formHint) return props.formHint
  if (editingId.value) return 'Update the details below, then save your changes.'
  return 'Fill in the details below to create a new item.'
})

function recordId(item) {
  const id = Number(item?.id ?? item?.value)
  return Number.isFinite(id) && id > 0 ? id : null
}

function itemKey(item, index) {
  return recordId(item) ? `id-${recordId(item)}` : `row-${index}-${item?.[props.nameKey] || 'item'}`
}

function blankForm() {
  const next = {}
  props.fields.forEach((field) => {
    if (field.type === 'toggle') next[field.key] = field.default ?? false
    else if (field.type === 'number') next[field.key] = field.default ?? 1
    else next[field.key] = field.default ?? ''
  })
  return next
}

function resetForm(values = null) {
  const source = values || blankForm()
  Object.keys(form).forEach((key) => delete form[key])
  Object.assign(form, blankForm(), source)
}

const filteredItems = computed(() => {
  const query = search.value.trim().toLowerCase()
  const list = [...(props.items || [])].sort((a, b) =>
    String(a?.[props.nameKey] || '').localeCompare(String(b?.[props.nameKey] || ''), undefined, { sensitivity: 'base' }),
  )
  if (!query) return list
  return list.filter((item) =>
    props.fields.some((field) => String(item[field.key] ?? '').toLowerCase().includes(query))
      || String(item[props.nameKey] || '').toLowerCase().includes(query),
  )
})

function subtitleFor(item) {
  return props.subtitle ? props.subtitle(item) : ''
}

function badgeFor(item) {
  return props.badge ? props.badge(item) : ''
}

function badgeClassFor(item) {
  return props.badgeClass ? props.badgeClass(item) : ''
}

function onItemClick(item) {
  if (!props.selectable) return
  emit('select', item)
}

function openCreate() {
  editingItem.value = null
  editingId.value = null
  resetForm()
  showForm.value = true
}

function openEdit(item) {
  const id = recordId(item)
  if (!id) {
    window.$showNotification?.('This item cannot be edited.', 'error')
    return
  }
  editingItem.value = item
  editingId.value = id
  const values = {}
  props.fields.forEach((field) => {
    const raw = item[field.key]
    if (field.type === 'toggle') values[field.key] = Boolean(raw)
    else if (field.type === 'number') values[field.key] = raw ?? field.default ?? 1
    else values[field.key] = raw ?? field.default ?? ''
  })
  resetForm(values)
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingItem.value = null
  editingId.value = null
  resetForm()
}

function submitForm() {
  if (props.saving) return
  const required = props.fields.filter((field) => field.required)
  const missing = required.find((field) => {
    const value = form[field.key]
    return value === '' || value === null || value === undefined
  })
  if (missing) {
    window.$showNotification?.(`Please fill ${missing.label}`, 'error')
    return
  }
  const id = editingId.value
  const payload = { ...form }
  if (id) payload.id = id
  emit('save', payload, id ? { ...(editingItem.value || {}), id } : null)
}

async function confirmRemove(item) {
  const label = item[props.nameKey] || 'this item'
  const result = await Swal.fire({
    title: 'Delete this item?',
    text: `Delete "${label}"? This cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel',
    customClass: { container: 'hr-settings-swal' },
  })
  if (!result.isConfirmed) return
  emit('remove', item)
}

defineExpose({ closeForm })
</script>

<style scoped>
.hr-set-catalog {
  position: relative;
}
.hr-set-item {
  cursor: default;
}
.hr-set-item.is-selectable {
  cursor: pointer;
}
.hr-set-item.is-selected {
  border-color: #0b0736 !important;
  background: #f4f2f8;
  box-shadow: inset 3px 0 0 #0b0736;
}
</style>

<style>
.hr-settings-swal {
  z-index: 20000 !important;
}

.hr-set-form-overlay {
  position: fixed;
  inset: 0;
  z-index: 13000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(11, 7, 54, 0.45);
  backdrop-filter: blur(6px);
  overflow: auto;
}

.hr-set-form-dialog {
  width: min(480px, 100%);
  margin: auto;
  background: #fff;
  border: 1px solid #eee8f4;
  border-radius: 18px;
  box-shadow: 0 24px 64px rgba(11, 7, 54, 0.28);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: min(88vh, 720px);
}

.hr-set-form-dialog__head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 18px 14px;
  border-bottom: 1px solid #f0ecf5;
  background: linear-gradient(180deg, #faf8fc 0%, #fff 100%);
}

.hr-set-form-dialog__icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #0b0736;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 18px;
}

.hr-set-form-dialog__copy {
  min-width: 0;
  flex: 1;
  padding-top: 2px;
}

.hr-set-form-dialog__title {
  margin: 0 !important;
  font-size: 15px !important;
  font-weight: 700 !important;
  color: #0b0736 !important;
  line-height: 1.3 !important;
}

.hr-set-form-dialog__sub {
  margin: 4px 0 0 !important;
  font-size: 12px !important;
  color: #6b7280 !important;
  line-height: 1.4 !important;
}

.hr-set-form-dialog__close {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 999px;
  background: #f3f4f6;
  color: #4b5563;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
}

.hr-set-form-dialog__close:hover {
  background: #e5e7eb;
  color: #111827;
}

.hr-set-form-dialog__body {
  padding: 16px 18px;
  overflow-y: auto;
}

.hr-set-form-dialog__body .hr-set-form__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

.hr-set-form-dialog__body .hr-set-form__grid.is-wide {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.hr-set-form-dialog__body .hr-set-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.hr-set-form-dialog__body .hr-set-field.is-full {
  grid-column: 1 / -1;
}

.hr-set-form-dialog__body .hr-set-field label {
  font-size: 12px;
  font-weight: 700;
  color: #0b0736;
}

.hr-set-form-dialog__body .hr-set-field em {
  color: #dc2626;
  font-style: normal;
}

.hr-set-form-dialog__body .hr-set-field input:not([type='checkbox']):not([type='radio']),
.hr-set-form-dialog__body .hr-set-field select,
.hr-set-form-dialog__body .hr-set-field textarea {
  height: 42px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 0 12px;
  background: #fff;
  color: #111827;
  width: 100%;
  font-size: 13px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.hr-set-form-dialog__body .hr-set-field textarea {
  height: auto;
  min-height: 96px;
  padding: 10px 12px;
  resize: vertical;
  line-height: 1.45;
}

.hr-set-form-dialog__body .hr-set-field input:focus,
.hr-set-form-dialog__body .hr-set-field select:focus,
.hr-set-form-dialog__body .hr-set-field textarea:focus {
  outline: none;
  border-color: #0b0736;
  box-shadow: 0 0 0 3px rgba(11, 7, 54, 0.1);
}

.hr-set-form-dialog__body .hr-set-field__hint {
  font-size: 11px;
  color: #9ca3af;
  line-height: 1.35;
}

.hr-set-form-dialog__body .hr-set-switch {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  padding: 8px 12px;
  border: 1px solid #eceff5;
  border-radius: 10px;
  background: #faf8fc;
}

.hr-set-form-dialog__body .hr-set-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}

.hr-set-form-dialog__body .hr-set-switch__track {
  width: 44px;
  height: 24px;
  border-radius: 999px;
  background: #d1d5db;
  position: relative;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.hr-set-form-dialog__body .hr-set-switch__track::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.25);
  transition: transform 0.15s ease;
}

.hr-set-form-dialog__body .hr-set-switch input:checked + .hr-set-switch__track {
  background: #0b0736;
}

.hr-set-form-dialog__body .hr-set-switch input:checked + .hr-set-switch__track::after {
  transform: translateX(20px);
}

.hr-set-form-dialog__body .hr-set-switch__label {
  font-size: 13px;
  line-height: 1.3;
}

.hr-set-form-dialog__foot {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 18px;
  border-top: 1px solid #f0ecf5;
  background: #faf8fc;
}

.hr-set-form-dialog__foot .hr-set-btn {
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  border-radius: 999px;
  height: 38px;
  padding: 0 16px;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.hr-set-form-dialog__foot .hr-set-btn--primary {
  background: #0b0736;
  border-color: #0b0736;
  color: #fff;
}

.hr-set-form-dialog__foot .hr-set-btn--primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
</style>
