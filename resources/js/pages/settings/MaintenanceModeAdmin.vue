<template>
  <div class="mm">
    <header class="mm__header">
      <div>
        <p class="mm__eyebrow">System</p>
        <h1 class="mm__title">Maintenance Mode</h1>
        <p class="mm__sub">
          Lock the whole CRM or only selected branches. Super Admin always bypasses.
        </p>
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
          <p class="mm__label">Maintenance</p>
          <p class="mm__help">
            When ON, affected non–Super Admin users are blocked with HTTP 503.
            Scope “All” also pauses public webhooks and registration.
          </p>
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
        <legend>Who is under maintenance?</legend>
        <label class="mm__radio">
          <input v-model="form.scope" type="radio" value="all" />
          <span>
            <strong>All branches</strong>
            <small>Everyone except Super Admin (full system lock)</small>
          </span>
        </label>
        <label class="mm__radio">
          <input v-model="form.scope" type="radio" value="branches" />
          <span>
            <strong>Selected branches</strong>
            <small>Only users in the branches you tick below</small>
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
            <button type="button" class="mm__link" :disabled="busy || !form.branch_user_ids.length" @click="form.branch_user_ids = []">
              Clear
            </button>
          </div>
        </div>
        <p v-if="loadingBranches" class="mm__help">Loading branches…</p>
        <p v-else-if="!branches.length" class="mm__help">No branches found.</p>
        <ul v-else class="mm__branch-list">
          <li v-for="b in branches" :key="b.id" :class="{ 'is-office': b.level === 'office' }">
            <label>
              <input
                type="checkbox"
                :value="b.id"
                :checked="form.branch_user_ids.includes(b.id)"
                :disabled="busy"
                @change="toggleBranch(b.id, $event.target.checked)"
              />
              <span>{{ b.level === 'office' ? '— ' : '' }}{{ b.name }}</span>
            </label>
          </li>
        </ul>
      </div>

      <label class="mm__field">
        <span>Announcement title</span>
        <input v-model="form.title" type="text" maxlength="160" :disabled="busy" placeholder="We'll Be Back Soon" />
      </label>

      <label class="mm__field">
        <span>Maintenance message</span>
        <textarea
          v-model="form.message"
          rows="4"
          maxlength="2000"
          :disabled="busy"
          placeholder="Describe what users will see during maintenance."
        />
      </label>

      <label class="mm__field">
        <span>Estimated return <em>(optional)</em></span>
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
          Save settings
        </button>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="confirmOpen" class="mm__modal" @click.self="confirmOpen = false">
        <div class="mm__modal-card" role="dialog" aria-modal="true" aria-label="Confirm maintenance">
          <p class="mm__title">Activate maintenance?</p>
          <p class="mm__modal-body">{{ confirmBody }}</p>
          <div class="mm__actions">
            <button type="button" class="mm__btn mm__btn--danger" :disabled="busy" @click="confirmEnable">
              Activate now
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

const statusLabel = computed(() => {
  if (!enabled.value) return 'System OPERATIONAL'
  if (form.scope === 'branches') {
    const n = form.branch_user_ids.length
    return `UNDER MAINTENANCE · ${n} branch${n === 1 ? '' : 'es'}`
  }
  return 'System UNDER MAINTENANCE'
})

const confirmBody = computed(() => {
  if (form.scope === 'branches') {
    const names = branches.value
      .filter((b) => form.branch_user_ids.includes(b.id))
      .map((b) => b.name)
    return `This blocks users in: ${names.join(', ') || 'selected branches'}. Other branches stay online. Super Admin always bypasses.`
  }
  return 'This immediately blocks every user except Super Admin across all brands and branches. Webhooks, registration, invitations, and password reset will also stop.'
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
    flash(e?.response?.data?.message || 'Failed to load maintenance settings.', true)
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
    flash('Maintenance settings saved.')
    confirmOpen.value = false
  } catch (e) {
    flash(e?.response?.data?.message || 'Failed to update maintenance mode.', true)
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
      flash('Select at least one branch, or choose “All branches”.', true)
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
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  font-family: 'Montserrat', 'Segoe UI', system-ui, sans-serif;
  color: #0f172a;
}

.mm__header {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.mm__eyebrow {
  margin: 0 0 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #64748b;
}

.mm__title {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.mm__sub {
  margin: 8px 0 0;
  max-width: 46ch;
  color: #64748b;
  font-size: 0.92rem;
  line-height: 1.45;
}

.mm__status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
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
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
}

.mm__notice {
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #334155;
  font-size: 0.9rem;
}

.mm__notice.is-error {
  background: #fef2f2;
  color: #991b1b;
}

.mm__panel {
  display: grid;
  gap: 18px;
  padding: 22px;
  border-radius: 18px;
  background: #fff;
  border: 1px solid #e8e6ee;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
}

.mm__toggle-row {
  display: flex;
  gap: 16px;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}

.mm__label {
  margin: 0;
  font-weight: 700;
}

.mm__help {
  margin: 4px 0 0;
  font-size: 0.85rem;
  color: #64748b;
  max-width: 42ch;
}

.mm__switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  min-width: 92px;
  height: 40px;
  padding: 0 14px 0 44px;
  border: 0;
  border-radius: 999px;
  background: #cbd5e1;
  color: #0f172a;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.2s ease;
}

.mm__switch.is-on {
  background: var(--color-primary, #733e87);
  color: #fff;
  padding: 0 44px 0 14px;
}

.mm__switch-knob {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
  transition: left 0.2s ease;
}

.mm__switch.is-on .mm__switch-knob {
  left: calc(100% - 36px);
}

.mm__scope {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 14px 16px;
  margin: 0;
  display: grid;
  gap: 12px;
}

.mm__scope legend {
  padding: 0 6px;
  font-size: 0.8rem;
  font-weight: 700;
  color: #64748b;
}

.mm__radio {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  cursor: pointer;
}

.mm__radio strong {
  display: block;
  font-size: 0.95rem;
}

.mm__radio small {
  display: block;
  color: #64748b;
  font-size: 0.8rem;
  margin-top: 2px;
}

.mm__branches {
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 14px 16px;
}

.mm__branches-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.mm__branch-actions {
  display: flex;
  gap: 12px;
}

.mm__link {
  border: 0;
  background: none;
  color: var(--color-primary, #733e87);
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  padding: 0;
}

.mm__link:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.mm__branch-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 260px;
  overflow: auto;
  display: grid;
  gap: 6px;
}

.mm__branch-list li label {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 8px 10px;
  border-radius: 10px;
  cursor: pointer;
}

.mm__branch-list li label:hover {
  background: #f8fafc;
}

.mm__branch-list li.is-office label {
  padding-left: 22px;
  color: #475569;
}

.mm__field {
  display: grid;
  gap: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}

.mm__field em {
  font-style: normal;
  font-weight: 500;
  color: #94a3b8;
}

.mm__field input,
.mm__field textarea {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  font: inherit;
  font-weight: 500;
  color: #0f172a;
  background: #fbfafc;
}

.mm__field textarea {
  resize: vertical;
  min-height: 110px;
}

.mm__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.mm__btn {
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #0f172a;
  border-radius: 12px;
  padding: 10px 16px;
  font-weight: 700;
  cursor: pointer;
}

.mm__btn--primary {
  background: var(--color-primary, #733e87);
  border-color: transparent;
  color: #fff;
}

.mm__btn--danger {
  background: #c2410c;
  border-color: transparent;
  color: #fff;
}

.mm__btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.mm__modal {
  position: fixed;
  inset: 0;
  z-index: 2147483001;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.45);
}

.mm__modal-card {
  width: min(440px, 100%);
  padding: 24px;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}

.mm__modal-body {
  margin: 12px 0 20px;
  color: #475569;
  line-height: 1.5;
}
</style>
