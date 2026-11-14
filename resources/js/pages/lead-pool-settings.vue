<template>
  <div class="dashboard-main-body lead-pool-settings-page">
    <Breadcrumb title="Lead Pool Settings" :breadcrumbs="[{ name: 'Lead Pool Settings' }]" />

    <div class="lps-shell">
      <div class="lps-toolbar">
        <input
          v-model="search"
          type="search"
          class="lps-input lps-search"
          placeholder="Search agent by name or email"
        />
        <p class="lps-hint">
          Choose who can see the Lead Pool and how many leads each agent can take with
          "Assign To Me" — per day and per hour (default {{ defaults.daily_limit }} / {{ defaults.hourly_limit }}).
          <span v-if="scope === 'branch'">You can only manage agents in your branch.</span>
        </p>
      </div>

      <!-- Apply to selected -->
      <div v-if="selectedIds.length" class="lps-bulk">
        <strong>{{ selectedIds.length }} selected</strong>
        <label class="lps-toggle">
          <input v-model="bulk.lead_pool_enabled" type="checkbox" class="lps-toggle__input" />
          <span class="lps-toggle__track" aria-hidden="true"><span class="lps-toggle__thumb" /></span>
          <span class="lps-toggle__label">Show Lead Pool</span>
        </label>
        <label class="lps-field">
          Per day
          <input v-model.number="bulk.daily_limit" type="number" min="1" :max="defaults.max_daily_limit" class="lps-input lps-num" />
        </label>
        <label class="lps-field">
          Per hour
          <input v-model.number="bulk.hourly_limit" type="number" min="1" :max="defaults.max_hourly_limit" class="lps-input lps-num" />
        </label>
        <button type="button" class="lps-btn lps-btn--primary" :disabled="bulkSaving" @click="saveBulk">
          {{ bulkSaving ? 'Saving…' : 'Apply to selected' }}
        </button>
        <button type="button" class="lps-btn lps-btn--ghost" :disabled="bulkSaving" @click="selectedIds = []">Clear</button>
      </div>

      <div v-if="error" class="lps-error">
        <span>{{ error }}</span>
        <button type="button" class="lps-link" @click="fetchSettings">Retry</button>
      </div>

      <div v-if="loading" class="lps-empty">Loading…</div>
      <div v-else-if="!error && !filteredAgents.length" class="lps-empty">No agents found.</div>

      <div v-else-if="!error" class="lps-table-wrap">
        <table class="lps-table">
          <thead>
            <tr>
              <th class="lps-check">
                <input type="checkbox" class="lps-cb" :checked="allSelected" aria-label="Select all" @change="toggleAll" />
              </th>
              <th>Agent</th>
              <th>Branch</th>
              <th class="lps-center">Show Lead Pool</th>
              <th class="lps-center">Per day</th>
              <th class="lps-center">Per hour</th>
              <th class="lps-center">Taken today</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in filteredAgents" :key="row.id" :class="{ 'lps-row--off': !row.lead_pool_enabled }">
              <td class="lps-check">
                <input v-model="selectedIds" type="checkbox" class="lps-cb" :value="row.id" :aria-label="`Select ${row.name}`" />
              </td>
              <td>
                <div class="lps-agent">
                  <span class="lps-agent-name">{{ row.name }}</span>
                  <span class="lps-muted">{{ row.role }}<template v-if="row.email"> · {{ row.email }}</template></span>
                </div>
              </td>
              <td>{{ row.branch || '—' }}</td>
              <td class="lps-center">
                <label class="lps-toggle" :title="row.lead_pool_enabled ? 'Lead Pool is shown' : 'Lead Pool is hidden'">
                  <input
                    v-model="row.lead_pool_enabled"
                    type="checkbox"
                    class="lps-toggle__input"
                    :aria-label="`Show Lead Pool for ${row.name}`"
                    @change="markDirty(row)"
                  />
                  <span class="lps-toggle__track" aria-hidden="true"><span class="lps-toggle__thumb" /></span>
                  <span class="lps-toggle__label">{{ row.lead_pool_enabled ? 'On' : 'Off' }}</span>
                </label>
              </td>
              <td class="lps-center">
                <input
                  v-model.number="row.daily_limit"
                  type="number"
                  min="1"
                  :max="defaults.max_daily_limit"
                  class="lps-input lps-num"
                  :disabled="!row.lead_pool_enabled"
                  @input="markDirty(row)"
                />
              </td>
              <td class="lps-center">
                <input
                  v-model.number="row.hourly_limit"
                  type="number"
                  min="1"
                  :max="defaults.max_hourly_limit"
                  class="lps-input lps-num"
                  :disabled="!row.lead_pool_enabled"
                  @input="markDirty(row)"
                />
              </td>
              <td class="lps-center">{{ row.today_count }} / {{ row.daily_limit || '—' }}</td>
              <td class="lps-center">
                <button
                  type="button"
                  class="lps-btn lps-btn--primary lps-btn--sm"
                  :disabled="!dirty[row.id] || saving[row.id]"
                  @click="saveRow(row)"
                >
                  {{ saving[row.id] ? 'Saving…' : 'Save' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'

const agents = ref([])
const defaults = ref({ daily_limit: 40, hourly_limit: 10, max_daily_limit: 500, max_hourly_limit: 100 })
const scope = ref('branch')
const loading = ref(false)
const error = ref('')
const search = ref('')
const dirty = reactive({})
const saving = reactive({})
const selectedIds = ref([])
const bulkSaving = ref(false)
const bulk = reactive({ lead_pool_enabled: true, daily_limit: 40, hourly_limit: 10 })

const notify = (message, type = 'success') => {
  if (typeof window.$showNotification === 'function') window.$showNotification(message, type)
  else if (type === 'error') window.alert(message)
}

const filteredAgents = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return agents.value
  return agents.value.filter((a) =>
    String(a.name || '').toLowerCase().includes(q) ||
    String(a.email || '').toLowerCase().includes(q) ||
    String(a.branch || '').toLowerCase().includes(q)
  )
})

const allSelected = computed(() =>
  filteredAgents.value.length > 0 && filteredAgents.value.every((a) => selectedIds.value.includes(a.id))
)

function toggleAll() {
  if (allSelected.value) {
    const shown = new Set(filteredAgents.value.map((a) => a.id))
    selectedIds.value = selectedIds.value.filter((id) => !shown.has(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...filteredAgents.value.map((a) => a.id)])]
  }
}

function markDirty(row) {
  dirty[row.id] = true
}

/** Same rules as LeadPoolSettingsController::rules(); returns an error message or ''. */
function validate(values) {
  const daily = Number(values.daily_limit)
  const hourly = Number(values.hourly_limit)
  const maxDaily = defaults.value.max_daily_limit
  const maxHourly = defaults.value.max_hourly_limit
  if (!Number.isInteger(daily) || daily < 1 || daily > maxDaily) return `Per day must be between 1 and ${maxDaily}.`
  if (!Number.isInteger(hourly) || hourly < 1 || hourly > maxHourly) return `Per hour must be between 1 and ${maxHourly}.`
  if (hourly > daily) return 'Per hour cannot be more than per day.'
  return ''
}

function errorMessage(err, fallback) {
  const errors = err?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }
  return err?.response?.data?.message || fallback
}

async function fetchSettings() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/lead-pool/settings')
    const data = res?.data?.data || {}
    agents.value = data.agents || []
    if (data.defaults) defaults.value = data.defaults
    scope.value = data.scope || 'branch'
    bulk.daily_limit = defaults.value.daily_limit
    bulk.hourly_limit = defaults.value.hourly_limit
    Object.keys(dirty).forEach((k) => delete dirty[k])
    const ids = new Set(agents.value.map((a) => a.id))
    selectedIds.value = selectedIds.value.filter((id) => ids.has(id))
  } catch (err) {
    error.value = errorMessage(err, 'Failed to load Lead Pool settings')
  } finally {
    loading.value = false
  }
}

async function saveRow(row) {
  const problem = validate(row)
  if (problem) {
    notify(problem, 'warning')
    return
  }
  saving[row.id] = true
  try {
    const res = await api.put(`/lead-pool/settings/${row.id}`, {
      lead_pool_enabled: !!row.lead_pool_enabled,
      daily_limit: Number(row.daily_limit),
      hourly_limit: Number(row.hourly_limit),
    })
    const saved = res?.data?.data
    if (saved) Object.assign(row, saved)
    delete dirty[row.id]
    notify(`Saved for ${row.name}`)
  } catch (err) {
    notify(errorMessage(err, 'Failed to save'), 'error')
  } finally {
    delete saving[row.id]
  }
}

async function saveBulk() {
  const problem = validate(bulk)
  if (problem) {
    notify(problem, 'warning')
    return
  }
  bulkSaving.value = true
  try {
    await api.put('/lead-pool/settings/bulk', {
      user_ids: selectedIds.value,
      lead_pool_enabled: !!bulk.lead_pool_enabled,
      daily_limit: Number(bulk.daily_limit),
      hourly_limit: Number(bulk.hourly_limit),
    })
    notify(`Saved for ${selectedIds.value.length} agent(s)`)
    selectedIds.value = []
    await fetchSettings()
  } catch (err) {
    notify(errorMessage(err, 'Failed to save'), 'error')
  } finally {
    bulkSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<style scoped>
.lead-pool-settings-page.lead-pool-settings-page {
  --lps-primary: var(--color-primary);
  --lps-primary-soft: #f4eef7;
  --lps-primary-line: #e3d6ea;
  --lps-text: #1e1b2e;
  --lps-muted: #8b8798;
  --lps-line: #ece9f1;
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.lps-shell { border: 1px solid var(--lps-line); background: #fff; border-radius: 14px; padding: 16px; }
.lps-toolbar { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
.lps-hint { margin: 0; color: var(--lps-muted); font-size: 12px; }

.lps-input { height: 36px; border: 1px solid var(--lps-line); border-radius: 8px; padding: 0 10px; color: var(--lps-text); background: #fff; box-sizing: border-box; }
.lps-input:focus { outline: none; border-color: var(--lps-primary); }
.lps-input:disabled { background: #f7f6f9; color: var(--lps-muted); }
.lps-search { width: 100%; }
.lps-num { width: 80px; text-align: center; }

.lps-btn { display: inline-flex; align-items: center; justify-content: center; height: 36px; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; }
.lps-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.lps-btn--primary { background: var(--lps-primary); color: #fff; }
.lps-btn--ghost { background: #fff; border-color: var(--lps-primary-line); color: var(--lps-primary); }
.lps-btn--sm { height: 30px; padding: 0 12px; }

.lps-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 10px 12px; margin-bottom: 12px; border: 1px solid var(--lps-primary-line); background: var(--lps-primary-soft); border-radius: 10px; font-size: 13px; color: var(--lps-text); }
.lps-field { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-weight: 600; }

/* On/off switch. The real checkbox stays for keyboard + screen readers, visually hidden;
   the track/thumb are drawn here so global theme checkbox rules can't hide them. */
.lps-toggle { position: relative; display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-weight: 600; font-size: 13px; color: var(--lps-text); user-select: none; }
.lps-toggle__input { position: absolute !important; opacity: 0 !important; width: 1px !important; height: 1px !important; margin: 0 !important; pointer-events: none; }
.lps-toggle__track { position: relative; display: inline-block; flex-shrink: 0; width: 40px; height: 22px; border-radius: 999px; background: #d9d5e0; transition: background-color 0.18s ease; }
.lps-toggle__thumb { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(30, 27, 46, 0.25); transition: transform 0.18s ease; }
.lps-toggle__input:checked + .lps-toggle__track { background: var(--lps-primary); }
.lps-toggle__input:checked + .lps-toggle__track .lps-toggle__thumb { transform: translateX(18px); }
.lps-toggle__input:focus-visible + .lps-toggle__track { outline: 2px solid var(--lps-primary); outline-offset: 2px; }
.lps-toggle__label { min-width: 24px; text-align: left; }

/* Row-select checkbox: drawn by hand (appearance: none) so it is always visible. */
.lps-cb {
  -webkit-appearance: none !important;
  appearance: none !important;
  display: inline-block !important;
  position: relative !important;
  opacity: 1 !important;
  visibility: visible !important;
  width: 18px !important;
  height: 18px !important;
  margin: 0 !important;
  padding: 0 !important;
  border: 2px solid #b9b3c6 !important;
  border-radius: 5px !important;
  background: #fff !important;
  cursor: pointer;
  vertical-align: middle;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}
.lps-cb:hover { border-color: var(--lps-primary) !important; }
.lps-cb:checked { background: var(--lps-primary) !important; border-color: var(--lps-primary) !important; }
.lps-cb:checked::after {
  content: '';
  position: absolute;
  left: 4px;
  top: 0;
  width: 6px;
  height: 11px;
  border: solid #fff;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}
.lps-cb:focus-visible { outline: 2px solid var(--lps-primary); outline-offset: 2px; }

.lps-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.lps-link { border: none; background: transparent; color: #b3261e; text-decoration: underline; cursor: pointer; margin-left: auto; }
.lps-empty { padding: 24px; text-align: center; color: var(--lps-muted); }

.lps-table-wrap { overflow-x: auto; }
.lps-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.lps-table th { text-align: left; font-size: 12px; color: var(--lps-muted); text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid var(--lps-line); white-space: nowrap; }
.lps-table td { padding: 8px 10px; border-bottom: 1px solid #f4f2f7; font-size: 13px; color: var(--lps-text); vertical-align: middle; }
.lps-center { text-align: center !important; }
.lps-check { width: 40px; text-align: center; }
.lps-agent { display: flex; flex-direction: column; min-width: 0; }
.lps-agent-name { font-weight: 600; }
.lps-muted { color: var(--lps-muted); font-size: 12px; }
.lps-row--off td { background: #fbfafc; }

@media (min-width: 768px) {
  .lead-pool-settings-page.lead-pool-settings-page { padding: 20px; }
  .lps-search { max-width: 360px; }
}
</style>
