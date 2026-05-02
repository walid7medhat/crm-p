<template>
  <div class="deal-history-search-modal">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Search</label>
        <input v-model="form.search" type="text" class="form-control" placeholder="Search history" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Event</label>
        <select v-model="form.action" class="form-control">
          <option value="">Any Event</option>
          <option v-for="opt in eventTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">User</label>
        <select v-model="form.user" class="form-control">
          <option value="">Any User</option>
          <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <AdvancedDatePicker v-model="form.dateFrom" date-only placeholder="Select date" />
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <AdvancedDatePicker v-model="form.dateTo" date-only placeholder="Select date" />
      </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="button" class="btn btn-light btn-sm" @click="reset">Reset</button>
      <button type="button" class="btn btn-primary btn-sm" @click="apply">Search</button>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'

const props = defineProps({
  initialSearch: { type: String, default: '' },
  initialAction: { type: String, default: '' },
  initialUser: { type: [String, Number], default: '' },
  initialDateFrom: { type: String, default: '' },
  initialDateTo: { type: String, default: '' },
  users: { type: Array, default: () => [] },
  eventTypeOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['search', 'close'])

const form = reactive({
  search: '',
  action: '',
  user: '',
  dateFrom: '',
  dateTo: '',
})

const syncFromProps = () => {
  form.search = props.initialSearch || ''
  form.action = props.initialAction || ''
  form.user = props.initialUser ? String(props.initialUser) : ''
  form.dateFrom = props.initialDateFrom || ''
  form.dateTo = props.initialDateTo || ''
}

watch(
  () => [props.initialSearch, props.initialAction, props.initialUser, props.initialDateFrom, props.initialDateTo],
  syncFromProps,
  { immediate: true },
)

const apply = () => {
  emit('search', { ...form })
}

const reset = () => {
  form.search = ''
  form.action = ''
  form.user = ''
  form.dateFrom = ''
  form.dateTo = ''
  emit('search', { ...form })
}
</script>
