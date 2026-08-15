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

    <form v-if="showForm" class="hr-set-form" @submit.prevent="submitForm">
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
            <span />
            {{ form[field.key] ? (field.onLabel || 'Enabled') : (field.offLabel || 'Disabled') }}
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
          {{ saving ? 'Saving...' : (editingItem ? 'Save changes' : 'Add') }}
        </button>
      </div>
    </form>

    <div v-if="loading" class="hr-set-empty">Loading...</div>
    <div v-else-if="!filteredItems.length" class="hr-set-empty">
      {{ search ? 'No matching items.' : emptyText }}
    </div>
    <div v-else class="hr-set-list">
      <article v-for="item in filteredItems" :key="item.id" class="hr-set-item">
        <div class="hr-set-item__body">
          <strong>{{ item[nameKey] || '—' }}</strong>
          <small v-if="subtitleFor(item)">{{ subtitleFor(item) }}</small>
        </div>
        <div class="hr-set-item__meta" v-if="badgeFor(item)">
          <span class="hr-set-badge" :class="badgeClassFor(item)">{{ badgeFor(item) }}</span>
        </div>
        <div class="hr-set-item__actions">
          <button type="button" title="Edit" @click="openEdit(item)"><iconify-icon icon="lucide:pencil" /></button>
          <button type="button" title="Delete" @click="confirmRemove(item)"><iconify-icon icon="lucide:trash-2" /></button>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'

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
const form = reactive({})

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

watch(() => props.fields, () => resetForm(), { immediate: true, deep: true })

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
  resetForm()
  showForm.value = true
}

function openEdit(item) {
  editingItem.value = item
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
  resetForm()
}

function submitForm() {
  const required = props.fields.filter((field) => field.required)
  const missing = required.find((field) => {
    const value = form[field.key]
    return value === '' || value === null || value === undefined
  })
  if (missing) {
    window.$showNotification?.(`Please fill ${missing.label}`, 'error')
    return
  }
  emit('save', { ...form }, editingItem.value)
}

function confirmRemove(item) {
  if (!window.confirm(`Delete "${item[props.nameKey] || 'this item'}"?`)) return
  emit('remove', item)
}

defineExpose({ closeForm })
</script>