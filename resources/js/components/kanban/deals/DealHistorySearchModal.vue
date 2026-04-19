<template>
  <div class="deal-history-search-modal">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Search</label>
        <input v-model="form.search" type="text" class="form-control" placeholder="Search history" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Event</label>
        <SearchableSelect
          v-model="form.action"
          :options="eventSelectOptions"
          option-label="label"
          option-value="value"
          :clearable="true"
          inline
          class="form-control p-0 border-0"
          placeholder="Any event"
        />
      </div>
      <div class="col-md-6">
        <label class="form-label">User</label>
        <SearchableSelect
          v-model="form.user"
          :options="userSelectOptions"
          option-label="label"
          option-value="value"
          :clearable="true"
          inline
          class="form-control p-0 border-0"
          placeholder="Any user"
        />
      </div>
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <input v-model="form.dateFrom" type="date" class="form-control" />
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <input v-model="form.dateTo" type="date" class="form-control" />
      </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="button" class="btn btn-light btn-sm" @click="reset">Reset</button>
      <button type="button" class="btn btn-primary btn-sm" @click="apply">Search</button>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'

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

const eventSelectOptions = computed(() => [
  { value: '', label: 'Any Event' },
  ...(props.eventTypeOptions || []).map((o) => ({ value: o.value, label: o.label })),
])

const userSelectOptions = computed(() => [
  { value: '', label: 'Any User' },
  ...(props.users || []).map((u) => ({ value: String(u.id), label: u.name })),
])

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
