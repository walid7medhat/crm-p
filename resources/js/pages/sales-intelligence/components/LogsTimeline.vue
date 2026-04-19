<template>
  <section class="lt">
    <header class="lt__head">
      <div>
        <h6 class="lt__title">Distribution logs</h6>
        <p class="lt__sub">Client-side filters on loaded page</p>
      </div>
      <button type="button" class="lt__ghost" :disabled="loading" @click="emit('reload')">Reload</button>
    </header>

    <div class="lt__filters">
      <SiCombobox
        v-model="filters.method"
        history-key="si:logs-method"
        label="Method"
        :options="methodSelectOptions"
        label-key="label"
        value-key="value"
        placeholder="All"
        :clearable="true"
      />
      <SiCombobox
        v-model="filters.agentId"
        history-key="si:logs-agent"
        label="Agent"
        :options="agentSelectOptions"
        label-key="label"
        value-key="value"
        placeholder="All"
        :clearable="true"
        :filterable="true"
      />
      <label class="lt-field">
        <span>From</span>
        <input v-model="filters.from" type="date" class="lt-input" />
      </label>
      <label class="lt-field">
        <span>To</span>
        <input v-model="filters.to" type="date" class="lt-input" />
      </label>
      <div class="lt__fil-actions">
        <button type="button" class="lt__reset" @click="resetFilters">Reset filters</button>
      </div>
    </div>

    <div v-if="loading" class="lt__sk">
      <div v-for="n in 5" :key="n" class="lt-sk" />
    </div>

    <div v-else class="lt__timeline">
      <div v-for="log in filtered" :key="log.id" class="lt-item">
        <div class="lt-item__rail" />
        <div class="lt-item__card">
          <div class="lt-item__top">
            <span class="lt-badge">{{ log.method }}</span>
            <time class="lt-time">{{ formatTs(log.created_at) }}</time>
          </div>
          <p class="lt-lead">
            Lead <strong>#{{ log.lead_id }}</strong>
            <span v-if="log.lead?.lead_name"> — {{ log.lead.lead_name }}</span>
          </p>
          <p class="lt-agent">
            → {{ log.assignee?.name || 'User #' + log.assigned_to }}
            <span class="lt-score">score {{ log.score_at_assignment ?? '—' }}</span>
          </p>
        </div>
      </div>
      <p v-if="!filtered.length" class="lt-empty">No entries match filters.</p>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import SiCombobox from './SiCombobox.vue'

const props = defineProps({
  logs: { type: Array, default: () => [] },
  agents: { type: Array, default: () => [] },
  loading: Boolean,
})

const emit = defineEmits(['reload'])

const filters = reactive({
  method: '',
  agentId: '',
  from: '',
  to: '',
})

const methodOptions = computed(() => {
  const s = new Set()
  props.logs.forEach((l) => l.method && s.add(String(l.method)))
  return [...s].sort()
})

const methodSelectOptions = computed(() => [
  { value: '', label: 'All methods' },
  ...methodOptions.value.map((m) => ({ value: m, label: m })),
])

const agentSelectOptions = computed(() => [
  { value: '', label: 'All agents' },
  ...(props.agents || []).map((a) => ({ value: String(a.id), label: a.name })),
])

watch(
  () => filters.method,
  (v) => {
    if (v == null) filters.method = ''
  }
)
watch(
  () => filters.agentId,
  (v) => {
    if (v == null) filters.agentId = ''
  }
)

const filtered = computed(() => {
  let rows = props.logs || []
  if (filters.method) {
    rows = rows.filter((r) => String(r.method || '').toLowerCase() === filters.method.toLowerCase())
  }
  if (filters.agentId) {
    rows = rows.filter((r) => String(r.assigned_to) === String(filters.agentId))
  }
  if (filters.from) {
    const t = new Date(filters.from).getTime()
    rows = rows.filter((r) => new Date(r.created_at).getTime() >= t)
  }
  if (filters.to) {
    const t = new Date(filters.to).getTime() + 86400000
    rows = rows.filter((r) => new Date(r.created_at).getTime() < t)
  }
  return rows
})

function resetFilters() {
  filters.method = ''
  filters.agentId = ''
  filters.from = ''
  filters.to = ''
}

function formatTs(iso) {
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}
</script>

<style scoped>
.lt__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 8px;
  margin-bottom: 8px;
}

.lt__title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.lt__sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #6b7280;
}

.lt__ghost {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
  font-size: 11px;
  font-weight: 600;
  padding: 6px 10px;
  cursor: pointer;
  transition: background 0.12s ease;
}

.lt__ghost:hover:not(:disabled) {
  background: #f9fafb;
}

.lt__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 8px;
  margin-bottom: 10px;
}

.lt__fil-actions {
  display: flex;
  align-items: flex-end;
  padding-bottom: 2px;
}

.lt__reset {
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}

.lt__reset:hover {
  color: #111827;
}

.lt-field span {
  display: block;
  font-size: 10px;
  color: #6b7280;
  margin-bottom: 4px;
}

.lt-input {
  width: 100%;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #111827;
  padding: 0 8px;
  font-size: 12px;
}

.lt__timeline {
  position: relative;
  padding-left: 6px;
}

.lt-item {
  position: relative;
  display: grid;
  grid-template-columns: 14px 1fr;
  gap: 8px;
  padding-bottom: 10px;
}

.lt-item__rail {
  width: 2px;
  margin: 4px auto 0;
  border-radius: 999px;
  background: #e5e7eb;
}

.lt-item__card {
  border-radius: 8px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  transition: border-color 0.12s ease, background 0.12s ease;
}

.lt-item__card:hover {
  border-color: #d1d5db;
  background: #fafafa;
}

.lt-item__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
}

.lt-badge {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 3px 6px;
  border-radius: 999px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.lt-time {
  font-size: 10px;
  color: #9ca3af;
}

.lt-lead {
  margin: 6px 0 0;
  font-size: 12px;
  color: #111827;
}

.lt-agent {
  margin: 2px 0 0;
  font-size: 12px;
  color: #4b5563;
}

.lt-score {
  margin-left: 6px;
  font-size: 11px;
  color: #9ca3af;
}

.lt-empty {
  margin: 8px 0 0 22px;
  font-size: 12px;
  color: #9ca3af;
}

.lt__sk {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lt-sk {
  height: 56px;
  border-radius: 8px;
  background: #f3f4f6;
  animation: lt-pulse 0.85s ease-in-out infinite alternate;
}

@keyframes lt-pulse {
  to {
    opacity: 0.55;
  }
}
</style>
