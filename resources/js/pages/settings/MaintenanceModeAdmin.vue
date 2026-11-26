<template>
  <div class="mm">
    <header class="mm__header">
      <div>
        <p class="mm__eyebrow">System</p>
        <!-- Not <h1>: global CRM CSS forces h1 { font-size: var(--h1) !important } -->
        <p class="mm__title" role="heading" aria-level="1">Maintenance Mode</p>
        <p class="mm__sub">Lock everyone, selected branches, or only the people you pick.</p>
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
          <p class="mm__help">Super Admin always stays online.</p>
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
        <label class="mm__radio" :class="{ 'is-active': form.scope === 'users' }">
          <input v-model="form.scope" type="radio" value="users" />
          <span>
            <strong>Selected people</strong>
            <small>Only the people you pick</small>
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

      <div v-if="form.scope === 'users'" class="mm__people">
        <div class="mm__branches-head">
          <p class="mm__label">People in maintenance</p>
        </div>
        <p class="mm__help mm__help--tight">
          Search by name or email and pick who should see maintenance. You can pick an individual admin.
          Super Admin always stays online.
        </p>
        <div v-if="targetPeople.length" class="mm__selected">
          <p class="mm__selected-label">Selected ({{ targetPeople.length }})</p>
          <div class="mm__chips">
            <button
              v-for="person in targetPeople"
              :key="'target-' + person.id"
              type="button"
              class="mm__chip"
              :disabled="busy"
              :title="'Remove ' + person.name"
              @click="removePerson('target', person.id)"
            >
              <span>{{ personLabel(person) }}</span>
              <span class="mm__chip-x" aria-hidden="true">×</span>
            </button>
          </div>
        </div>
        <p v-else class="mm__help mm__help--tight">No one selected yet. Maintenance will not turn on until you pick someone.</p>
        <div class="mm__search">
          <input
            v-model="userQuery"
            type="search"
            placeholder="Search name or email"
            :disabled="busy"
            @input="onSearchUsers"
          />
          <p v-if="searchingUsers" class="mm__help">Searching…</p>
          <ul v-else-if="userResults.length" class="mm__results">
            <li v-for="person in userResults" :key="'res-' + person.id">
              <button type="button" :disabled="busy" @click="addPerson('target', person)">
                <span>{{ person.name }}</span>
                <small>{{ person.email }}<template v-if="person.role"> · {{ person.role }}</template></small>
              </button>
            </li>
          </ul>
          <p v-else-if="userQuery.trim() && !searchingUsers" class="mm__help">No matching people.</p>
        </div>
      </div>

      <div v-else class="mm__people">
        <div class="mm__branches-head">
          <p class="mm__label">Who stays online</p>
        </div>
        <p class="mm__help mm__help--tight">Super Admin always stays online.</p>
        <label class="mm__check" :class="{ 'is-active': form.except_admin_role }">
          <input v-model="form.except_admin_role" type="checkbox" :disabled="busy" />
          <span>
            <strong>Except the admin role</strong>
            <small>Every user with the admin role keeps working. Search below to keep one more person online.</small>
          </span>
        </label>
        <p class="mm__label mm__label--space">Also except someone</p>
        <div v-if="exceptPeople.length" class="mm__selected">
          <p class="mm__selected-label">Excepted ({{ exceptPeople.length }})</p>
          <div class="mm__chips">
            <button
              v-for="person in exceptPeople"
              :key="'except-' + person.id"
              type="button"
              class="mm__chip"
              :disabled="busy"
              :title="'Remove ' + person.name"
              @click="removePerson('except', person.id)"
            >
              <span>{{ personLabel(person) }}</span>
              <span class="mm__chip-x" aria-hidden="true">×</span>
            </button>
          </div>
        </div>
        <div class="mm__search">
          <input
            v-model="userQuery"
            type="search"
            placeholder="Search name or email"
            :disabled="busy"
            @input="onSearchUsers"
          />
          <p v-if="searchingUsers" class="mm__help">Searching…</p>
          <ul v-else-if="userResults.length" class="mm__results">
            <li v-for="person in userResults" :key="'exres-' + person.id">
              <button type="button" :disabled="busy" @click="addPerson('except', person)">
                <span>{{ person.name }}</span>
                <small>{{ person.email }}<template v-if="person.role"> · {{ person.role }}</template></small>
              </button>
            </li>
          </ul>
          <p v-else-if="userQuery.trim() && !searchingUsers" class="mm__help">No matching people.</p>
        </div>
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
        <button type="button" class="mm__btn" :disabled="busy" @click="previewOpen = true">
          Test preview
        </button>
      </div>
    </section>

    <Teleport to="body">
      <MaintenanceScreen
        v-if="previewOpen"
        preview
        :preview-title="form.title"
        :preview-message="form.message"
        :preview-estimated-return="form.estimated_return"
        @close="previewOpen = false"
      />
    </Teleport>

    <Teleport to="body">
      <div v-if="confirmOpen" class="mm__modal" @click.self="confirmOpen = false">
        <div class="mm__modal-card" role="dialog" aria-modal="true" aria-label="Confirm maintenance">
          <p class="mm__modal-title">Activate maintenance?</p>
          <p class="mm__modal-body">{{ confirmBody }}</p>
          <div v-if="form.scope === 'branches' && selectedBranches.length" class="mm__modal-chips">
            <span v-for="b in selectedBranches" :key="'c-' + b.id" class="mm__chip mm__chip--static">{{ b.name }}</span>
          </div>
          <div v-if="form.scope === 'users' && targetPeople.length" class="mm__modal-chips">
            <span v-for="person in targetPeople" :key="'tc-' + person.id" class="mm__chip mm__chip--static">{{ personLabel(person) }}</span>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/plugins/axios'
import { useMaintenanceMode } from '@/composables/useMaintenanceMode'
import MaintenanceScreen from '@/components/maintenance/MaintenanceScreen.vue'

const { applyPayload } = useMaintenanceMode()

const enabled = ref(false)
const busy = ref(false)
const notice = ref('')
const noticeError = ref(false)
const confirmOpen = ref(false)
const previewOpen = ref(false)
const loadingBranches = ref(false)
const branches = ref([])
const form = reactive({
  scope: 'all',
  branch_user_ids: [],
  except_admin_role: false,
  title: "We'll Be Back Soon",
  message: 'The CRM is temporarily unavailable while we perform scheduled maintenance.',
  estimated_return: '',
})
const exceptPeople = ref([])
const targetPeople = ref([])
const userQuery = ref('')
const userResults = ref([])
const searchingUsers = ref(false)
let userSearchTimer = null

const selectedBranches = computed(() =>
  branches.value.filter((b) => form.branch_user_ids.includes(Number(b.id)))
)

const statusLabel = computed(() => {
  if (!enabled.value) return 'Operational'
  if (form.scope === 'branches') {
    const n = form.branch_user_ids.length
    return `On · ${n} branch${n === 1 ? '' : 'es'}`
  }
  if (form.scope === 'users') {
    const n = targetPeople.value.length
    return `On · ${n} ${n === 1 ? 'person' : 'people'}`
  }
  if (form.except_admin_role || exceptPeople.value.length) return 'On · All · exceptions'
  return 'On · All'
})

const confirmBody = computed(() => {
  if (form.scope === 'users') {
    const n = targetPeople.value.length
    return n === 1
      ? 'Only this person will see maintenance. Everyone else stays online.'
      : `Only these ${n} people will see maintenance. Everyone else stays online.`
  }
  if (form.scope === 'branches') {
    return 'Only users in the selected branches below will be blocked. People you except stay online.'
  }
  const extras = ['Super Admin']
  if (form.except_admin_role) extras.push('the admin role')
  if (exceptPeople.value.length === 1) extras.push('1 selected person')
  if (exceptPeople.value.length > 1) extras.push(`${exceptPeople.value.length} selected people`)
  const who = extras.length === 1
    ? extras[0]
    : `${extras.slice(0, -1).join(', ')} and ${extras[extras.length - 1]}`
  return `${who} will stay online. Everyone else will be blocked, including webhooks and registration.`
})

watch(() => form.scope, () => {
  userQuery.value = ''
  userResults.value = []
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

function personLabel(person) {
  return person.role ? `${person.name} · ${person.role}` : person.name
}

function applyServerData(data) {
  enabled.value = !!data.enabled
  form.scope = data.scope === 'branches' || data.scope === 'users' ? data.scope : 'all'
  form.branch_user_ids = Array.isArray(data.branch_user_ids)
    ? data.branch_user_ids.map((id) => Number(id))
    : []
  form.except_admin_role = !!data.except_admin_role
  exceptPeople.value = Array.isArray(data.except_users) ? data.except_users.map(normalizePerson) : []
  targetPeople.value = Array.isArray(data.target_users) ? data.target_users.map(normalizePerson) : []
  form.title = data.title || form.title
  form.message = data.message || form.message
  form.estimated_return = data.estimated_return || ''
  if (Array.isArray(data.available_branches)) {
    branches.value = data.available_branches
  }
  userResults.value = []
  userQuery.value = ''
  applyPayload(data)
}

function normalizePerson(person) {
  return {
    id: Number(person.id),
    name: person.name || person.email || `User ${person.id}`,
    email: person.email || '',
    role: person.role || '',
  }
}

function addPerson(listName, person) {
  const list = listName === 'target' ? targetPeople : exceptPeople
  const row = normalizePerson(person)
  if (!list.value.some((item) => item.id === row.id)) {
    list.value = [...list.value, row]
  }
  userResults.value = userResults.value.filter((item) => Number(item.id) !== row.id)
}

function removePerson(listName, id) {
  const list = listName === 'target' ? targetPeople : exceptPeople
  list.value = list.value.filter((item) => item.id !== Number(id))
}

function onSearchUsers() {
  clearTimeout(userSearchTimer)
  const term = userQuery.value.trim()
  if (term.length < 1) {
    userResults.value = []
    searchingUsers.value = false
    return
  }
  userSearchTimer = setTimeout(() => searchUsers(term), 250)
}

async function searchUsers(term) {
  searchingUsers.value = true
  try {
    const res = await api.get('/maintenance/users', { params: { search: term } })
    const selected = new Set(
      (form.scope === 'users' ? targetPeople.value : exceptPeople.value).map((person) => person.id)
    )
    userResults.value = (res?.data?.data || [])
      .map(normalizePerson)
      .filter((person) => !selected.has(person.id))
  } catch (e) {
    userResults.value = []
    flash(e?.response?.data?.message || 'Search failed.', true)
  } finally {
    searchingUsers.value = false
  }
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
    except_admin_role: form.scope !== 'users' && form.except_admin_role,
    except_user_ids: form.scope === 'users' ? [] : exceptPeople.value.map((person) => person.id),
    target_user_ids: form.scope === 'users' ? targetPeople.value.map((person) => person.id) : [],
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
      flash('Select at least one branch, or choose another scope.', true)
      return
    }
    if (form.scope === 'users' && !targetPeople.value.length) {
      flash('Search and select at least one person.', true)
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
  if (form.scope === 'users' && !targetPeople.value.length && enabled.value) {
    flash('Select at least one person while maintenance is ON.', true)
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
  padding: 0;
  font-size: 15px !important;
  line-height: 1.25 !important;
  font-weight: 700 !important;
  letter-spacing: -0.01em;
  color: #1e293b !important;
}

.mm__sub {
  margin: 3px 0 0;
  color: #64748b;
  font-size: 11px !important;
  line-height: 1.35;
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
  font-size: 11px !important;
  font-weight: 700 !important;
  color: #1e293b !important;
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
  font-size: 11px !important;
  font-weight: 650 !important;
  color: #1e293b !important;
}

.mm__radio small {
  display: block;
  color: #94a3b8 !important;
  font-size: 10px !important;
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

.mm__people {
  border: 1px solid #eef2f7;
  border-radius: 10px;
  padding: 10px;
}

.mm__label--space {
  margin-top: 10px;
}

.mm__check {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  margin: 8px 0;
  padding: 7px 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  background: #fafbfc;
  cursor: pointer;
}

.mm__check.is-active {
  border-color: color-mix(in srgb, var(--mm-accent) 35%, #e2e8f0);
  background: color-mix(in srgb, var(--mm-accent) 8%, #fff);
}

.mm__check strong {
  display: block;
  font-size: 11px !important;
  font-weight: 650 !important;
  color: #1e293b !important;
}

.mm__check small {
  display: block;
  margin-top: 1px;
  color: #94a3b8 !important;
  font-size: 10px !important;
  line-height: 1.35;
}

.mm__search {
  position: relative;
}

.mm__search input {
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

.mm__results {
  list-style: none;
  margin: 6px 0 0;
  padding: 4px;
  max-height: 180px;
  overflow: auto;
  border: 1px solid #e8e6ee;
  border-radius: 8px;
  background: #fff;
}

.mm__results button {
  width: 100%;
  display: grid;
  gap: 1px;
  text-align: left;
  border: 0;
  background: transparent;
  border-radius: 6px;
  padding: 6px 8px;
  cursor: pointer;
  color: #0f172a;
  font-size: 11px;
  font-weight: 650;
}

.mm__results button small {
  color: #94a3b8;
  font-size: 10px;
  font-weight: 500;
}

.mm__results button:hover {
  background: color-mix(in srgb, var(--mm-accent) 8%, #fff);
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
