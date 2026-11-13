<template>
  <div class="mm">
    <header class="mm__header">
      <div>
        <p class="mm__eyebrow">System</p>
        <h1 class="mm__title">Maintenance Mode</h1>
        <p class="mm__sub">Lock all branches or only the ones you select.</p>
      </div>
      <div class="mm__status" :class="enabled ? 'is-on' : 'is-off'">
        <span class="mm__dot" />
        {{ statusLabel }}
      </div>
    </header>

    <p v-if="notice" class="mm__notice" :class="{ 'is-error': noticeError }" role="status">{{ notice }}</p>

    <section class="mm__panel">
      <div class="mm__toggle-row">
        <div>
          <p class="mm__label">Status</p>
          <p class="mm__help">Super Admin always bypasses.</p>
        </div>
        <button
          type="button"
          class="mm__switch"
          :class="{ 'is-on': enabled }"
          :aria-pressed="enabled"
          :disabled="busy"
          @click="onToggleClick"
        >
          <span class="mm__switch-knob" />
          <span class="mm__switch-text">{{ enabled ? 'ON' : 'OFF' }}</span>
        </button>
      </div>

      <fieldset class="mm__scope" :disabled="busy">
        <legend>Scope</legend>
        <label class="mm__radio" :class="{ 'is-active': form.scope === 'all' }">
          <input v-model="form.scope" type="radio" value="all" />
          <span>
            <strong>All branches</strong>
            <small>Full CRM lock</small>
          </span>
        </label>
        <label class="mm__radio" :class="{ 'is-active': form.scope === 'branches' }">
          <input v-model="form.scope" type="radio" value="branches" />
          <span>
            <strong>Selected branches</strong>
            <small>Only chosen branches</small>
          </span>
        </label>
      </fieldset>

      <div v-if="form.scope === 'branches'" class="mm__branches">
        <div class="mm__branches-head">
          <p class="mm__label">Branches</p>
          <div class="mm__branch-actions">
            <button type="button" class="mm__link" :disabled="busy || !branches.length" @click="selectAllBranches">
              Select all
            </button>
            <button type="button" class="mm__link" :disabled="busy || !form.branch_user_ids.length" @click="clearBranches">
              Clear
            </button>
          </div>
        </div>

        <div v-if="selectedBranches.length" class="mm__selected">
          <p class="mm__selected-label">Selected ({{ selectedBranches.length }})</p>
          <div class="mm__chips">
            <button
              v-for="b in selectedBranches"
              :key="'sel-' + b.id"
              type="button"
              class="mm__chip"
              :disabled="busy"
              :title="'Remove ' + b.name"
              @click="toggleBranch(b.id, false)"
            >
              <span>{{ b.name }}</span>
              <span class="mm__chip-x" aria-hidden="true">×</span>
            </button>
          </div>
        </div>
        <p v-else class="mm__help mm__help--tight">No branches selected yet.</p>

        <p v-if="loadingBranches" class="mm__help">Loading…</p>
        <p v-else-if="!branches.length" class="mm__help">No branches found.</p>
        <ul v-else class="mm__branch-list">
          <li
            v-for="b in branches"
            :key="b.id"
            :class="{
              'is-office': b.level === 'office',
              'is-selected': form.branch_user_ids.includes(b.id),
            }"
          >
            <label>
              <input
                type="checkbox"
                :value="b.id"
                :checked="form.branch_user_ids.includes(b.id)"
                :disabled="busy"
                @change="toggleBranch(b.id, $event.target.checked)"
              />
              <span class="mm__branch-name">{{ b.name }}</span>
              <span v-if="form.branch_user_ids.includes(b.id)" class="mm__branch-tag">Selected</span>
            </label>
          </li>
        </ul>
      </div>

      <label class="mm__field">
        <span>Title</span>
        <input v-model="form.title" type="text" maxlength="160" :disabled="busy" placeholder="We'll Be Back Soon" />
      </label>

      <label class="mm__field">
        <span>Message</span>
        <textarea
          v-model="form.message"
          rows="3"
          maxlength="2000"
          :disabled="busy"
          placeholder="Short message for users"
        />
      </label>

      <label class="mm__field">
        <span>Estimated return <em>optional</em></span>
        <input
          v-model="form.estimated_return"
          type="text"
          maxlength="255"
          :disabled="busy"
          placeholder="e.g. Back by 6:00 PM GST"
        />
      </label>

      <div class="mm__actions">
        <button type="button" class="mm__btn mm__btn--primary" :disabled="busy" @click="saveAnnouncement">
          Save
        </button>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="confirmOpen" class="mm__modal" @click.self="confirmOpen = false">
        <div class="mm__modal-card" role="dialog" aria-modal="true" aria-label="Confirm maintenance">
          <p class="mm__modal-title">Activate maintenance?</p>
          <p class="mm__modal-body">{{ confirmBody }}</p>
          <div v-if="form.scope === 'branches' && selectedBranches.length" class="mm__modal-chips">
            <span v-for="b in selectedBranches" :key="'c-' + b.id" class="mm__chip mm__chip--static">{{ b.name }}</span>
          </div>
          <div class="mm__actions">
            <button type="button" class="mm__btn mm__btn--danger" :disabled="busy" @click="confirmEnable">
              Activate
            </button>
            <button type="button" class="mm__btn" :disabled="busy" @click="confirmOpen = false">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/plugins/axios'
import { useMaintenanceMode } from '@/composables/useMaintenanceMode'

const { applyPayload } = useMaintenanceMode()

const enabled = ref(false)
const busy = ref(false)
const notice = ref('')
const noticeError = ref(false)
const confirmOpen = ref(false)
const loadingBranches = ref(false)
const branches = ref([])
const form = reactive({
  scope: 'all',
  branch_user_ids: [],
  title: "We'll Be Back Soon",
  message: 'The CRM is temporarily unavailable while we perform scheduled maintenance.',
  estimated_return: '',
})

const selectedBranches = computed(() =>
  branches.value.filter((b) => form.branch_user_ids.includes(Number(b.id)))
)

const statusLabel = computed(() => {
  if (!enabled.value) return 'Operational'
  if (form.scope === 'branches') {
    const n = form.branch_user_ids.length
    return `On · ${n} branch${n === 1 ? '' : 'es'}`
  }
  return 'On · All'
})

const confirmBody = computed(() => {
  if (form.scope === 'branches') {
    return 'Only users in the selected branches below will be blocked. Other branches stay online.'
  }
  return 'Everyone except Super Admin will be blocked. Webhooks and registration will stop too.'
})

function flash(text, isError = false) {
  notice.value = text
  noticeError.value = isError
}

function toggleBranch(id, checked) {
  const num = Number(id)
  if (checked) {
    if (!form.branch_user_ids.includes(num)) form.branch_user_ids.push(num)
  } else {
    form.branch_user_ids = form.branch_user_ids.filter((x) => x !== num)
  }
}

function selectAllBranches() {
  form.branch_user_ids = branches.value.map((b) => Number(b.id))
}

function clearBranches() {
  form.branch_user_ids = []
}

function applyServerData(data) {
  enabled.value = !!data.enabled
  form.scope = data.scope === 'branches' ? 'branches' : 'all'
  form.branch_user_ids = Array.isArray(data.branch_user_ids)
    ? data.branch_user_ids.map((id) => Number(id))
    : []
  form.title = data.title || form.title
  form.message = data.message || form.message
  form.estimated_return = data.estimated_return || ''
  if (Array.isArray(data.available_branches)) {
    branches.value = data.available_branches
  }
  applyPayload(data)
}

async function load() {
  busy.value = true
  loadingBranches.value = true
  try {
    const res = await api.get('/maintenance')
    applyServerData(res?.data?.data || {})
  } catch (e) {
    flash(e?.response?.data?.message || 'Failed to load settings.', true)
  } finally {
    busy.value = false
    loadingBranches.value = false
  }
}

async function put(payload) {
  busy.value = true
  notice.value = ''
  try {
    const res = await api.put('/maintenance', payload)
    applyServerData(res?.data?.data || {})
    flash('Saved.')
    confirmOpen.value = false
  } catch (e) {
    flash(e?.response?.data?.message || 'Update failed.', true)
  } finally {
    busy.value = false
  }
}

function settingsPayload(extra = {}) {
  return {
    scope: form.scope,
    branch_user_ids: form.scope === 'branches' ? form.branch_user_ids : [],
    title: form.title,
    message: form.message,
    estimated_return: form.estimated_return || null,
    ...extra,
  }
}

function onToggleClick() {
  if (busy.value) return
  if (!enabled.value) {
    if (form.scope === 'branches' && !form.branch_user_ids.length) {
      flash('Select at least one branch, or choose All branches.', true)
      return
    }
    confirmOpen.value = true
    return
  }
  put({ is_enabled: false })
}

function confirmEnable() {
  put(settingsPayload({ is_enabled: true }))
}

function saveAnnouncement() {
  if (form.scope === 'branches' && !form.branch_user_ids.length && enabled.value) {
    flash('Select at least one branch while maintenance is ON.', true)
    return
  }
  put(settingsPayload())
}

onMounted(load)
</script>

<style scoped>
.mm {
  --mm-accent: var(--color-primary, #733e87);
  max-width: 560px;
  margin: 0 auto;
  padding: 16px 14px 36px;
  font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif;
  color: #1e293b;
  font-size: 12px;
}

.mm__header {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.mm__eyebrow {
  margin: 0 0 2px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #94a3b8;
}

.mm__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.mm__sub {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 11px;
  line-height: 1.4;
}

.mm__status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.mm__status.is-on {
  color: #9a3412;
  background: #ffedd5;
}

.mm__status.is-off {
  color: #166534;
  background: #dcfce7;
}

.mm__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.mm__notice {
  margin: 0 0 10px;
  padding: 7px 9px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
}

.mm__notice.is-error {
  background: #fef2f2;
  color: #991b1b;
}

.mm__panel {
  display: grid;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #e8e6ee;
}

.mm__toggle-row {
  display: flex;
  gap: 10px;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}

.mm__label {
  margin: 0;
  font-size: 11px;
  font-weight: 700;
}

.mm__help {
  margin: 2px 0 0;
  font-size: 10px;
  color: #94a3b8;
}

.mm__help--tight {
  margin: 0 0 8px;
}

.mm__switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 68px;
  height: 28px;
  padding: 0 9px 0 30px;
  border: 0;
  border-radius: 999px;
  background: #cbd5e1;
  color: #0f172a;
  font-size: 9px;
  font-weight: 800;
  cursor: pointer;
}

.mm__switch.is-on {
  background: var(--mm-accent);
  color: #fff;
  padding: 0 30px 0 9px;
}

.mm__switch-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
  transition: left 0.18s ease;
}

.mm__switch.is-on .mm__switch-knob {
  left: calc(100% - 25px);
}

.mm__scope {
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 8px;
  margin: 0;
  display: grid;
  gap: 6px;
}

.mm__scope legend {
  padding: 0 4px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
}

.mm__radio {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  cursor: pointer;
  padding: 7px 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: #fafbfc;
}

.mm__radio.is-active {
  border-color: color-mix(in srgb, var(--mm-accent) 35%, #e2e8f0);
  background: color-mix(in srgb, var(--mm-accent) 8%, #fff);
}

.mm__radio strong {
  display: block;
  font-size: 11px;
}

.mm__radio small {
  display: block;
  color: #94a3b8;
  font-size: 10px;
  margin-top: 1px;
}

.mm__branches {
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 10px;
}

.mm__branches-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.mm__branch-actions {
  display: flex;
  gap: 10px;
}

.mm__link {
  border: 0;
  background: none;
  color: var(--mm-accent);
  font-weight: 700;
  font-size: 10px;
  cursor: pointer;
  padding: 0;
}

.mm__link:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.mm__selected {
  margin-bottom: 8px;
  padding: 8px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--mm-accent) 7%, #fff);
  border: 1px solid color-mix(in srgb, var(--mm-accent) 18%, transparent);
}

.mm__selected-label {
  margin: 0 0 6px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--mm-accent);
}

.mm__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.mm__chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid color-mix(in srgb, var(--mm-accent) 30%, transparent);
  background: color-mix(in srgb, var(--mm-accent) 12%, #fff);
  color: color-mix(in srgb, var(--mm-accent) 85%, #0f172a);
  border-radius: 999px;
  padding: 3px 7px 3px 8px;
  font-size: 10px;
  font-weight: 650;
  cursor: pointer;
}

.mm__chip--static {
  cursor: default;
}

.mm__chip-x {
  font-size: 12px;
  line-height: 1;
  opacity: 0.7;
}

.mm__branch-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 220px;
  overflow: auto;
  display: grid;
  gap: 4px;
}

.mm__branch-list li label {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 6px 8px;
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid transparent;
  background: #fafbfc;
  font-size: 11px;
}

.mm__branch-list li.is-office label {
  padding-left: 18px;
  color: #64748b;
}

.mm__branch-list li.is-selected label {
  background: color-mix(in srgb, var(--mm-accent) 12%, #fff);
  border-color: color-mix(in srgb, var(--mm-accent) 35%, transparent);
  color: color-mix(in srgb, var(--mm-accent) 90%, #0f172a);
  font-weight: 650;
  box-shadow: inset 3px 0 0 var(--mm-accent);
}

.mm__branch-name {
  flex: 1;
  min-width: 0;
}

.mm__branch-tag {
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--mm-accent);
  background: color-mix(in srgb, var(--mm-accent) 14%, #fff);
  border-radius: 999px;
  padding: 2px 6px;
}

.mm__field {
  display: grid;
  gap: 4px;
  font-size: 10px;
  font-weight: 650;
  color: #64748b;
}

.mm__field em {
  font-style: normal;
  font-weight: 500;
  color: #cbd5e1;
}

.mm__field input,
.mm__field textarea {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 7px 9px;
  font: inherit;
  font-size: 11px;
  font-weight: 500;
  color: #0f172a;
  background: #fbfafc;
}

.mm__field textarea {
  resize: vertical;
  min-height: 70px;
}

.mm__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.mm__btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}

.mm__btn--primary {
  background: var(--mm-accent);
  border-color: transparent;
  color: #fff;
}

.mm__btn--danger {
  background: #c2410c;
  border-color: transparent;
  color: #fff;
}

.mm__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.mm__modal {
  position: fixed;
  inset: 0;
  z-index: 2147483001;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.42);
}

.mm__modal-card {
  width: min(360px, 100%);
  padding: 14px;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
}

.mm__modal-title {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
}

.mm__modal-body {
  margin: 8px 0 10px;
  color: #64748b;
  font-size: 11px;
  line-height: 1.45;
}

.mm__modal-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-bottom: 12px;
}
</style>
