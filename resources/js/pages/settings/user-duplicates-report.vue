<template>
  <div class="dashboard-main-body udr-page">
    <Breadcrumb title="Non-OIA & Duplicate Users" :breadcrumbs="[{ name: 'Non-OIA & Duplicate Users' }]" />

    <div class="udr-shell">
      <div class="udr-toolbar">
        <div class="udr-stats" v-if="!loading && !error">
          <span class="udr-stat"><strong>{{ totalUsers }}</strong> total users</span>
          <span class="udr-stat"><strong>{{ rows.length }}</strong> non-oiaproperties.com</span>
          <span class="udr-stat udr-stat--warn"><strong>{{ duplicateCount }}</strong> duplicate names</span>
        </div>

        <div class="udr-filters">
          <input
            type="text"
            v-model="search"
            class="udr-input"
            placeholder="Search name or email..."
          />
          <label class="udr-toggle">
            <input type="checkbox" v-model="duplicatesOnly" />
            Duplicates only
          </label>
          <button type="button" class="udr-btn udr-btn--ghost" @click="fetchReport" :disabled="loading">
            <iconify-icon icon="lucide:refresh-cw" width="16" height="16" />
            Refresh
          </button>
          <button type="button" class="udr-btn udr-btn--primary" @click="exportCsv" :disabled="loading || !rows.length">
            <iconify-icon icon="lucide:download" width="16" height="16" />
            Export CSV
          </button>
        </div>
      </div>

      <div v-if="error" class="udr-error">
        <iconify-icon icon="lucide:alert-circle" width="16" height="16" />
        <span>{{ error }}</span>
        <button type="button" class="udr-link" @click="fetchReport">Retry</button>
      </div>

      <div v-if="loading" class="udr-loading">Loading report...</div>

      <template v-else-if="!error">
        <div v-if="!filteredRows.length" class="udr-empty">No matching users found.</div>

        <div v-else class="udr-table-wrap">
          <table class="udr-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Listings</th>
                <th>Leads (Responsible)</th>
                <th>Leads (Added)</th>
                <th>Duplicate Of</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in filteredRows" :key="row.id" :class="{ 'udr-row--dup': row.is_duplicate }">
                <td>{{ row.name || '—' }}</td>
                <td>{{ row.email }}</td>
                <td>
                  <span class="udr-badge" :class="row.status === 'active' ? 'udr-badge--active' : 'udr-badge--inactive'">
                    {{ row.status }}
                  </span>
                </td>
                <td>{{ row.listings_count }}</td>
                <td>{{ row.leads_responsible_count }}</td>
                <td>{{ row.leads_added_count }}</td>
                <td>
                  <span v-if="!row.is_duplicate" class="udr-muted">—</span>
                  <div v-else class="udr-dup-list">
                    <div v-for="m in row.duplicate_matches" :key="m.id" class="udr-dup-item">
                      <span class="udr-dup-email">{{ m.email }}</span>
                      <span class="udr-dup-meta">
                        {{ m.status }} · {{ m.listings_count }} listings · {{ m.leads_responsible_count }} leads
                      </span>
                    </div>
                  </div>
                </td>
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
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import api from '@/plugins/axios'

const loading = ref(false)
const error = ref('')
const rows = ref([])
const totalUsers = ref(0)
const search = ref('')
const duplicatesOnly = ref(false)

const duplicateCount = computed(() => rows.value.filter((r) => r.is_duplicate).length)

const filteredRows = computed(() => {
  let list = rows.value
  if (duplicatesOnly.value) {
    list = list.filter((r) => r.is_duplicate)
  }
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter((r) =>
      (r.name || '').toLowerCase().includes(q) || (r.email || '').toLowerCase().includes(q)
    )
  }
  return list
})

const fetchReport = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/users/reports/non-oia-duplicates')
    const data = response?.data?.data || {}
    rows.value = data.rows || []
    totalUsers.value = data.total_users || 0
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to load report'
  } finally {
    loading.value = false
  }
}

function csvEscape(value) {
  const s = String(value ?? '')
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}

const exportCsv = () => {
  const header = ['Name', 'Email', 'Status', 'Listings', 'Leads (Responsible)', 'Leads (Added)', 'Is Duplicate', 'Duplicate Matches']
  const lines = [header.map(csvEscape).join(',')]

  filteredRows.value.forEach((r) => {
    const matches = (r.duplicate_matches || [])
      .map((m) => `${m.email} (${m.status}, ${m.listings_count} listings, ${m.leads_responsible_count} leads)`)
      .join(' | ')
    lines.push([
      r.name,
      r.email,
      r.status,
      r.listings_count,
      r.leads_responsible_count,
      r.leads_added_count,
      r.is_duplicate ? 'Yes' : 'No',
      matches,
    ].map(csvEscape).join(','))
  })

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'non-oia-duplicate-users.csv'
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

onMounted(fetchReport)
</script>

<style scoped>
.udr-page.udr-page {
  background: #ffffff !important;
  background-image: none !important;
  min-height: 100vh;
  padding: 12px;
}
.udr-shell { border: 1px solid #d9deea; background: #ffffff !important; border-radius: 14px; padding: 16px; }

.udr-toolbar { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
.udr-stats { display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px; color: #495467; }
.udr-stat strong { color: #10152f; font-size: 15px; }
.udr-stat--warn strong { color: #b3261e; }

.udr-filters { display: flex; flex-direction: column; align-items: stretch; gap: 8px; }
.udr-input { height: 38px; width: 100%; border: 1px solid #ebeef3; border-radius: 8px; padding: 0 10px; color: #10152f; background: #fff; box-sizing: border-box; }
.udr-toggle { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #10152f; white-space: nowrap; }

.udr-btn { display: flex; align-items: center; justify-content: center; gap: 6px; height: 38px; width: 100%; border-radius: 8px; padding: 0 14px; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; box-sizing: border-box; }
.udr-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.udr-btn--ghost { background: #fff; border-color: #ebeef3; color: #10152f; }
.udr-btn--primary { background: #020b38; color: #fff; }

.udr-error { display: flex; align-items: center; gap: 8px; background: #fdecec; color: #b3261e; border: 1px solid #f5c2c0; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; font-size: 13px; }
.udr-link { border: none; background: transparent; color: #b3261e; text-decoration: underline; cursor: pointer; margin-left: auto; }
.udr-loading, .udr-empty { padding: 24px; text-align: center; color: #8390a7; }

.udr-table-wrap { overflow-x: auto; }
.udr-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.udr-table th { text-align: left; font-size: 12px; color: #8390a7; text-transform: uppercase; padding: 8px 10px; border-bottom: 1px solid #ebeef3; white-space: nowrap; }
.udr-table td { padding: 10px; border-bottom: 1px solid #f2f4f8; font-size: 13px; color: #10152f; vertical-align: top; }
.udr-row--dup { background: #fff8ea; }

.udr-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
.udr-badge--active { background: #e6f7ee; color: #0f9d58; }
.udr-badge--inactive { background: #f1f2f6; color: #6b7280; }

.udr-muted { color: #b7bdd1; }
.udr-dup-list { display: flex; flex-direction: column; gap: 4px; }
.udr-dup-item { display: flex; flex-direction: column; }
.udr-dup-email { font-weight: 600; }
.udr-dup-meta { font-size: 11px; color: #8390a7; }

@media (min-width: 768px) {
  .udr-page.udr-page { padding: 20px; }
  .udr-toolbar { flex-direction: row; align-items: center; justify-content: space-between; }
  .udr-filters { flex-direction: row; align-items: center; width: auto; }
  .udr-input { width: 220px; }
  .udr-btn { width: auto; }
}
</style>
