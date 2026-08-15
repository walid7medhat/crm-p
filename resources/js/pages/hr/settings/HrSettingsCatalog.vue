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
      <article v-for="(item, index) in filteredItems" :key="itemKey(item, index)" class="hr-set-item">
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

    <div v-if="showForm" class="hr-set-form-overlay" @click.self="closeForm">
      <form class="hr-set-form" autocomplete="off" @submit.prevent="submitForm">
        <p class="hr-set-form__heading">{{ editingId ? 'Edit' : addLabel }}</p>
        <div class="hr-set-form__grid" :class="{ 'is-wide': fields.length > 2 }">
          <div v-for="field in fields" :key="field.key" class="hr-set-field" :class="{ 'is-full': field.full }">
            <label>{{ field.label }} <em v-if="field.required">*</em></label>
            <select v-if="field.type === 'select'" v-model="form[field.key]">
              <option value="">{{ field.placeholder || 'Select' }}</option>
              <option v-for="opt in field.options || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <textarea
              v-else-if="field.type === 'textarea'"
              v-model="form[field.key]"
              rows="3"
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
          </div>
        </div>
        <div class="hr-set-form__actions">
          <button type="button" class="hr-set-btn" @click="closeForm">Cancel</button>
          <button type="submit" class="hr-set-btn hr-set-btn--primary" :disabled="saving">
            {{ saving ? 'Saving...' : (editingId ? 'Save changes' : 'Add') }}
          </button>
        </div>
      </form>
    </div>
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
})

const emit = defineEmits(['save', 'remove'])

const search = ref('')
const showForm = ref(false)
const editingItem = ref(null)
const editingId = ref(null)
const form = reactive({})

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
.hr-set-form-overlay {
  position: fixed;
  inset: 0;
  z-index: 12500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(11, 7, 54, 0.35);
  overflow: auto;
}
.hr-set-form {
  width: min(560px, 100%);
  max-height: min(80vh, 640px);
  overflow: auto;
  margin-bottom: 0;
  background: #fff;
  border: 1px solid #eee8f4;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 18px 40px rgba(11, 7, 54, 0.18);
}
.hr-set-form__heading {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 700;
  color: #0b0736;
}
.hr-set-switch {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
}
.hr-set-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
}
.hr-set-switch__track {
  width: 44px;
  height: 24px;
  border-radius: 999px;
  background: #d1d5db;
  position: relative;
  flex-shrink: 0;
  transition: background 0.15s ease;
}
.hr-set-switch__track::after {
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
.hr-set-switch input:checked + .hr-set-switch__track {
  background: #0b0736;
}
.hr-set-switch input:checked + .hr-set-switch__track::after {
  transform: translateX(20px);
}
.hr-set-switch__label {
  font-size: 13px;
}
</style>

<style>
.hr-settings-swal {
  z-index: 20000 !important;
}
</style>
