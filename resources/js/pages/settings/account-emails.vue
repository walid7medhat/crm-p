<template>
  <div class="dashboard-main-body ae-page">
    <div class="ae-shell">
      <div class="ae-top">
        <div>
          <div class="ae-title">Account emails</div>
          <p class="ae-hint">
            Sends each user the “new ALT CRM is ready” email: the login URL and the steps to set a password with Forgot Password.
            Super Admin only.
          </p>
        </div>
        <div class="ae-actions">
          <span class="ae-mailer" :class="{ 'ae-mailer--warn': mailer === 'log' }" :title="mailer === 'log' ? 'Mail goes to the log file, not real inboxes' : ''">
            <iconify-icon icon="lucide:mail" width="13" height="13" />
            Mailer: {{ mailer || '—' }}
          </span>
        </div>
      </div>

      <div class="ae-test">
        <label class="ae-field ae-field--grow">
          <span>Send test to email</span>
          <input
            v-model.trim="testEmail"
            type="email"
            class="ae-input"
            placeholder="name@example.com"
            @keyup.enter="sendTest"
          />
        </label>
        <button type="button" class="ae-btn ae-btn--primary ae-test__btn" :disabled="!!busy || !testEmailValid" @click="sendTest">
          <iconify-icon icon="lucide:send" width="14" height="14" />
          {{ busy === 'me' ? 'Sending…' : 'Send test' }}
        </button>
        <p class="ae-test__note">
          Sends the same email to this address so you can check how it looks.
        </p>
      </div>

      <div v-if="notice" class="ae-notice" :class="notice.error ? 'ae-notice--err' : 'ae-notice--ok'">
        <span>{{ notice.text }}</span>
        <button type="button" class="ae-link" @click="notice = null">Dismiss</button>
      </div>

      <div class="ae-filters">
        <label class="ae-field">
          <span>Branch</span>
          <select v-model="branchId" class="ae-input" :disabled="loadingBranches" @change="loadUsers">
            <option :value="null" disabled>{{ loadingBranches ? 'Loading…' : 'Select branch' }}</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">
              {{ b.level === 'office' ? '— ' : '' }}{{ b.name }}
            </option>
          </select>
        </label>
        <label class="ae-field ae-field--grow">
          <span>Search</span>
          <input v-model.trim="search" class="ae-input" placeholder="Name or email" :disabled="!users.length" />
        </label>
      </div>

      <div v-if="!branchId" class="ae-empty">Pick a branch to see the active users who will get the email.</div>
      <div v-else-if="loadingUsers" class="ae-empty">Loading users…</div>
      <div v-else-if="!users.length" class="ae-empty">No active users with a valid email in this branch.</div>

      <template v-else>
        <div class="ae-bar">
          <span class="ae-count">
            {{ users.length }} active user{{ users.length === 1 ? '' : 's' }}
            <template v-if="selectedIds.length"> · {{ selectedIds.length }} selected</template>
          </span>
          <div class="ae-bar__actions">
          <button
            type="button"
            class="ae-btn"
            :disabled="!visibleUsers.length"
            :title="selectedIds.length ? 'Export the selected users' : 'Export the users in the list'"
            @click="exportCsv"
          >
            <iconify-icon icon="lucide:download" width="14" height="14" />
            Export{{ selectedIds.length ? ` (${selectedIds.length})` : '' }}
          </button>
          <button type="button" class="ae-btn" :disabled="!visibleUsers.length" @click="toggleAllVisible(!allVisibleSelected)">
            <iconify-icon :icon="allVisibleSelected ? 'lucide:square' : 'lucide:check-square'" width="14" height="14" />
            {{ allVisibleSelected ? 'Unselect all' : 'Select all' }}
          </button>
          <button
            type="button"
            class="ae-btn ae-btn--primary"
            :disabled="!selectedIds.length || !!busy"
            @click="sendSelected"
          >
            <iconify-icon icon="lucide:send" width="14" height="14" />
            {{ busy === 'bulk' ? 'Sending…' : `Send to selected (${selectedIds.length})` }}
          </button>
          </div>
        </div>

        <div class="ae-table-wrap">
          <table class="ae-table">
            <thead>
              <tr>
                <th class="ae-check">
                  <button
                    type="button"
                    class="ae-cb"
                    :class="{ 'is-on': allVisibleSelected, 'is-some': someVisibleSelected && !allVisibleSelected }"
                    :aria-pressed="allVisibleSelected"
                    aria-label="Select all"
                    @click="toggleAllVisible(!allVisibleSelected)"
                  >
                    <iconify-icon v-if="allVisibleSelected" icon="lucide:check" width="12" height="12" />
                    <iconify-icon v-else-if="someVisibleSelected" icon="lucide:minus" width="12" height="12" />
                  </button>
                </th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Account</th>
                <th>Email status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="u in visibleUsers"
                :key="u.id"
                class="ae-row"
                :class="{ 'is-selected': selected[u.id] }"
                @click="toggleOne(u.id)"
              >
                <td class="ae-check">
                  <button
                    type="button"
                    class="ae-cb"
                    :class="{ 'is-on': selected[u.id] }"
                    :aria-pressed="!!selected[u.id]"
                    :aria-label="`Select ${u.name}`"
                    @click.stop="toggleOne(u.id)"
                  >
                    <iconify-icon v-if="selected[u.id]" icon="lucide:check" width="12" height="12" />
                  </button>
                </td>
                <td>
                  <div class="ae-user">
                    <img v-if="u.avatar" :src="u.avatar" alt="" class="ae-avatar" />
                    <span v-else class="ae-avatar ae-avatar--initial">{{ (u.name || '?').charAt(0).toUpperCase() }}</span>
                    <span class="ae-clip" :title="u.name">{{ u.name }}</span>
                  </div>
                </td>
                <td class="ae-clip" :title="u.email">{{ u.email }}</td>
                <td><span v-if="u.role" class="ae-role">{{ u.role }}</span></td>
                <td>
                  <span class="ae-tag" :class="u.account_status === 'active' ? 'ae-tag--ok' : 'ae-tag--muted'">
                    {{ u.account_status || '—' }}
                  </span>
                </td>
                <td>
                  <span v-if="status[u.id] === 'sending'" class="ae-tag ae-tag--wait">Sending…</span>
                  <template v-else-if="u.last_email">
                    <span
                      class="ae-tag"
                      :class="u.last_email.status === 'sent' ? 'ae-tag--ok' : 'ae-tag--err'"
                      :title="u.last_email.error || ''"
                    >{{ u.last_email.status === 'sent' ? 'Sent' : 'Failed' }}</span>
                    <span class="ae-when">{{ fmtWhen(u.last_email.sent_at) }}</span>
                  </template>
                  <span v-else class="ae-tag ae-tag--muted">Not sent</span>
                </td>
                <td class="ae-row-actions">
                  <button type="button" class="ae-btn ae-btn--sm" :disabled="!!busy" @click.stop="sendOne(u)">
                    <iconify-icon icon="lucide:send" width="12" height="12" />
                    Send test
                  </button>
                </td>
              </tr>
              <tr v-if="!visibleUsers.length">
                <td colspan="7" class="ae-empty">No users match “{{ search }}”.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const branches = ref([])
const loadingBranches = ref(false)
const mailer = ref('')
const expiresInDays = ref(7)
const myEmail = ref('')

const branchId = ref(null)
const users = ref([])
const loadingUsers = ref(false)
const search = ref('')

const selected = ref({})
const status = ref({})
const errors = ref({})
const busy = ref(null) // 'me' | 'bulk' | user id
const notice = ref(null)
const testEmail = ref('')
const testEmailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmail.value))

const visibleUsers = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return users.value
  return users.value.filter((u) => (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q))
})

const selectedIds = computed(() => users.value.filter((u) => selected.value[u.id]).map((u) => u.id))
const allVisibleSelected = computed(() => visibleUsers.value.length > 0 && visibleUsers.value.every((u) => selected.value[u.id]))
const someVisibleSelected = computed(() => visibleUsers.value.some((u) => selected.value[u.id]))

function toggleAllVisible(on) {
  const next = { ...selected.value }
  visibleUsers.value.forEach((u) => { next[u.id] = on })
  selected.value = next
}

// CSV of the selected users (or every user in the list), named "<branch>-users-<date>.csv".
function exportCsv() {
  const rows = selectedIds.value.length
    ? users.value.filter((u) => selected.value[u.id])
    : visibleUsers.value
  if (!rows.length) return

  const cell = (v) => {
    const s = String(v ?? '')
    return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
  }
  const header = ['Name', 'Email', 'Role', 'Account', 'Email status', 'Last sent']
  const lines = rows.map((u) => [
    u.name,
    u.email,
    u.role || '',
    u.account_status || '',
    u.last_email ? (u.last_email.status === 'sent' ? 'Sent' : 'Failed') : 'Not sent',
    u.last_email?.sent_at || '',
  ].map(cell).join(','))

  // BOM so Excel opens Arabic / accented names correctly.
  const blob = new Blob(['﻿' + [header.join(','), ...lines].join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const branch = branches.value.find((b) => b.id === branchId.value)?.name || 'branch'
  const safe = branch.replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-|-$/g, '') || 'branch'
  const date = new Date().toISOString().slice(0, 10)

  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${safe}-users-${date}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(a.href), 1000)
}

function toggleOne(id) {
  selected.value = { ...selected.value, [id]: !selected.value[id] }
}

function errorText(e, fallback) {
  if (e?.response?.status === 429) return 'Too many sends in a minute — wait a moment and try again.'
  return e?.response?.data?.message || e?.message || fallback
}

async function loadBranches() {
  loadingBranches.value = true
  try {
    const res = await api.get('/admin/account-emails/branches')
    const data = res.data?.data || {}
    branches.value = data.branches || []
    mailer.value = data.mailer || ''
    expiresInDays.value = data.expires_in_days || 7
    myEmail.value = data.me?.email || ''
    if (!testEmail.value) testEmail.value = myEmail.value
    // Default to Dubai when present.
    const dubai = branches.value.find((b) => b.level === 'city' && /dubai/i.test(b.name))
    if (dubai) {
      branchId.value = dubai.id
      loadUsers()
    }
  } catch (e) {
    notice.value = { error: true, text: errorText(e, 'Could not load branches') }
  } finally {
    loadingBranches.value = false
  }
}

async function loadUsers() {
  if (!branchId.value) return
  loadingUsers.value = true
  users.value = []
  selected.value = {}
  status.value = {}
  errors.value = {}
  search.value = ''
  try {
    const res = await api.get(`/admin/account-emails/branches/${branchId.value}/users`)
    users.value = res.data?.data || []
  } catch (e) {
    notice.value = { error: true, text: errorText(e, 'Could not load users') }
  } finally {
    loadingUsers.value = false
  }
}

function fmtWhen(value) {
  if (!value) return ''
  const d = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

// Write the result onto the user rows so "Email status" updates right away (and matches a reload).
function markResult(ids, data) {
  const sent = new Set(data?.sent || [])
  const failed = {}
  ;(data?.failed || []).forEach((f) => { failed[f.id] = f.error })
  const at = data?.at || new Date().toISOString()
  ids.forEach((id) => {
    delete status.value[id]
    const user = users.value.find((u) => u.id === id)
    if (!user) return
    if (sent.has(id)) user.last_email = { status: 'sent', sent_at: at, error: null }
    else user.last_email = { status: 'failed', sent_at: at, error: failed[id] || 'Skipped: not an active user of this branch' }
  })
}

async function send(ids) {
  ids.forEach((id) => { status.value[id] = 'sending' })
  try {
    const res = await api.post('/admin/account-emails/send', { branch_id: branchId.value, user_ids: ids })
    markResult(ids, res.data?.data)
    const data = res.data?.data || {}
    const failedCount = ids.length - (data.sent || []).length // failed or skipped
    notice.value = {
      error: failedCount > 0,
      text: `${(data.sent || []).length} sent${failedCount ? `, ${failedCount} failed` : ''}.`,
    }
  } catch (e) {
    markResult(ids, { sent: [], failed: ids.map((id) => ({ id, error: errorText(e, 'Failed') })) })
    notice.value = { error: true, text: errorText(e, 'Sending failed') }
  }
}

async function sendOne(user) {
  if (!window.confirm(`Send the account email to ${user.name} <${user.email}>?`)) return
  busy.value = user.id
  try {
    await send([user.id])
  } finally {
    busy.value = null
  }
}

async function sendSelected() {
  const ids = selectedIds.value
  if (!ids.length) return
  if (!window.confirm(`Send the account email to ${ids.length} user${ids.length === 1 ? '' : 's'}?`)) return
  busy.value = 'bulk'
  try {
    // Small batches so a long list doesn't hit request timeouts.
    for (let i = 0; i < ids.length; i += 20) {
      await send(ids.slice(i, i + 20))
    }
    const sentCount = ids.filter((id) => users.value.find((u) => u.id === id)?.last_email?.status === 'sent').length
    const failedCount = ids.length - sentCount
    notice.value = { error: failedCount > 0, text: `${sentCount} sent${failedCount ? `, ${failedCount} failed` : ''}.` }
  } finally {
    busy.value = null
  }
}

async function sendTest() {
  if (!testEmailValid.value || busy.value) return
  busy.value = 'me'
  try {
    const res = await api.post('/admin/account-emails/send', { to_me: true, test_email: testEmail.value })
    const ok = (res.data?.data?.sent || []).length > 0
    notice.value = ok
      ? { error: false, text: `Test email sent to ${res.data?.data?.to || testEmail.value}.` }
      : { error: true, text: res.data?.data?.failed?.[0]?.error || 'Test email failed.' }
  } catch (e) {
    notice.value = { error: true, text: errorText(e, 'Test email failed') }
  } finally {
    busy.value = null
  }
}

onMounted(loadBranches)
</script>

<style scoped>
.ae-page.ae-page {
  background: #fff !important;
  min-height: 100vh;
  padding: 10px 12px;
  font-size: 12px;
  color: #2a3142;
}
.ae-shell {
  border: 1px solid #e3e7ef;
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}
.ae-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.ae-title { font-size: 13px; font-weight: 650; color: #151a2b; margin-bottom: 2px; }
.ae-hint { margin: 0; font-size: 11px; color: #8b93a7; line-height: 1.35; max-width: 560px; }
.ae-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }

.ae-mailer {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 24px;
  padding: 0 8px;
  border-radius: 999px;
  background: #eef6f2;
  color: #0f7a4b;
  font-size: 11px;
  font-weight: 600;
}
.ae-mailer--warn { background: #fff6e5; color: #a15c00; }

.ae-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  height: 28px;
  padding: 0 10px;
  border-radius: 6px;
  border: 1px solid #e3e7ef;
  background: #fff;
  color: #2a3142;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.ae-btn:hover:not(:disabled) { border-color: var(--color-primary, #2563eb); color: var(--color-primary, #2563eb); }
.ae-btn--primary { background: var(--color-primary, #2563eb); border-color: var(--color-primary, #2563eb); color: #fff; }
.ae-btn--primary:hover:not(:disabled) { color: #fff; filter: brightness(1.05); }
.ae-btn--sm { height: 24px; padding: 0 8px; font-size: 10px; }
.ae-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.ae-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  border-radius: 8px;
  padding: 8px 10px;
  margin-bottom: 10px;
  font-size: 12px;
  border: 1px solid;
}
.ae-notice--ok { background: #e9f8f0; color: #0f7a4b; border-color: #bfe8d2; }
.ae-notice--err { background: #fdecec; color: #b3261e; border-color: #f5c2c0; }
.ae-link { border: none; background: transparent; color: inherit; text-decoration: underline; cursor: pointer; margin-left: auto; font-size: 11px; }

.ae-test {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px;
  padding: 10px;
  margin-bottom: 12px;
  border: 1px dashed #e3e7ef;
  border-radius: 8px;
  background: #fafbfd;
}
.ae-test__btn { height: 32px; }
.ae-test__note { flex-basis: 100%; margin: 0; font-size: 11px; color: #8b93a7; }

.ae-filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
.ae-field { display: flex; flex-direction: column; gap: 3px; min-width: 220px; }
.ae-field--grow { flex: 1; }
.ae-field > span { font-size: 10px; font-weight: 600; color: #8b93a7; text-transform: uppercase; }
.ae-input {
  height: 32px;
  border: 1px solid #e3e7ef;
  border-radius: 6px;
  padding: 0 8px;
  font-size: 12px;
  color: #2a3142;
  background: #fff;
  outline: none;
}
.ae-input:focus { border-color: var(--color-primary, #2563eb); }

.ae-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.ae-count { font-size: 11px; color: #5b6478; font-weight: 600; }

.ae-empty { padding: 12px 4px; color: #8b93a7; font-size: 12px; }

.ae-table-wrap { overflow-x: auto; }
.ae-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.ae-table th {
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  color: #8b93a7;
  text-transform: uppercase;
  padding: 6px;
  border-bottom: 1px solid #e8ecf3;
  white-space: nowrap;
}
.ae-table td {
  padding: 6px;
  border-bottom: 1px solid #f1f3f7;
  font-size: 12px;
  vertical-align: middle;
  white-space: nowrap;
}
.ae-table tr.is-selected td { background: #f7f9ff; }
.ae-check { width: 32px; }
.ae-row { cursor: pointer; }
.ae-row:hover td { background: #fafbfd; }
.ae-bar__actions { display: flex; flex-wrap: wrap; gap: 6px; }

/* Own checkbox: the theme hides native checkboxes. */
.ae-cb {
  width: 18px;
  height: 18px;
  padding: 0;
  border: 1.5px solid #c3cad8;
  border-radius: 4px;
  background: #fff;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s;
}
.ae-cb:hover { border-color: var(--color-primary, #2563eb); }
.ae-cb.is-on,
.ae-cb.is-some {
  background: var(--color-primary, #2563eb);
  border-color: var(--color-primary, #2563eb);
}
.ae-row-actions { text-align: right; }

.ae-user { display: flex; align-items: center; gap: 8px; }
.ae-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.ae-avatar--initial {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #eef1f6;
  color: #5b6478;
  font-size: 11px;
  font-weight: 700;
}
.ae-clip { max-width: 220px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
.ae-role { padding: 1px 6px; border-radius: 4px; background: #f1f3f7; color: #5b6478; font-size: 10px; font-weight: 600; }
.ae-dim { color: #c0c6d4; }

.ae-tag { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.ae-tag--ok { background: #e6f7ee; color: #0f9d58; }
.ae-tag--err { background: #fdecec; color: #b3261e; cursor: help; }
.ae-tag--wait { background: #fff6e5; color: #a15c00; }
.ae-tag--muted { background: #f1f3f7; color: #8b93a7; text-transform: capitalize; }
.ae-when { margin-left: 6px; font-size: 10px; color: #8b93a7; }
</style>
