<template>
  <div class="logs-page">
    <section class="logs-shell">
      <header class="control-bar card-surface">
        <div class="bar-head">
          <div>
            <h6 class="title mb-0">System Logs</h6>
            <p class="subtitle mb-0">Operational events, audit actions, and system diagnostics in real time.</p>
          </div>
          <div class="bar-actions">
            <label class="auto-refresh-chip">
              <input v-model="autoRefresh" type="checkbox" />
              <span>Auto refresh</span>
            </label>
            <button type="button" class="btn btn-soft" @click="refreshNow">
              <iconify-icon icon="lucide:refresh-cw" />
              Refresh
            </button>
            <button type="button" class="btn btn-soft" @click="exportJson">Export JSON</button>
            <button type="button" class="btn btn-soft" @click="exportCsv">Export CSV</button>
          </div>
        </div>

        <div class="filters-grid">
          <div class="search-wrap">
            <iconify-icon icon="lucide:search" class="search-icon" />
            <input
              v-model.trim="searchQuery"
              type="text"
              class="search-input"
              placeholder="Search logs, users, actions, payload fields, or messages..."
            />
          </div>

          <SearchableSelect
            v-model="filters.level"
            class="advanced-select"
            :options="levelSelectOptions"
            option-label="label"
            option-value="value"
            placeholder="All Levels"
            :append-to-body="true"
          />

          <SearchableSelect
            v-model="filters.model"
            class="advanced-select"
            :options="modelSelectOptions"
            option-label="label"
            option-value="value"
            placeholder="All Modules"
            :append-to-body="true"
          />

          <SearchableSelect
            v-model="filters.user_id"
            class="advanced-select"
            :options="userSelectOptions"
            option-label="label"
            option-value="value"
            placeholder="All Users"
            :append-to-body="true"
          />

          <select v-model="roleDraft" class="control-select" @change="addRoleFilter(roleDraft)">
            <option value="">Add Role Filter</option>
            <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
          </select>

          <input v-model="filters.date_from" type="date" class="control-input" />
          <input v-model="filters.date_to" type="date" class="control-input" />

          <button type="button" class="btn btn-ghost" @click="resetFilters">Reset</button>
        </div>

        <div class="active-chips" v-if="activeFilterChips.length">
          <button
            v-for="chip in activeFilterChips"
            :key="chip.key"
            class="filter-chip"
            type="button"
            @click="removeChip(chip)"
          >
            <span>{{ chip.label }}</span>
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
      </header>

      <div class="logs-layout">
        <section class="logs-main card-surface">
          <div class="table-toolbar">
            <div class="table-stats">
              <span>{{ filteredLogs.length }} matching logs</span>
              <span>•</span>
              <span>{{ logs.length }} total</span>
            </div>
            <div class="table-legend">
              <span class="legend-dot info"></span>Info
              <span class="legend-dot success"></span>Success
              <span class="legend-dot warning"></span>Warning
              <span class="legend-dot error"></span>Error
            </div>
          </div>

          <div v-if="loading" class="state-view">Loading logs...</div>
          <div v-else-if="paginatedLogs.length === 0" class="state-view">No logs match the current filters.</div>

          <div v-else class="table-wrap">
            <table class="logs-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Level</th>
                  <th>Action / Event</th>
                  <th>User</th>
                  <th>Source</th>
                  <th>Changes</th>
                  <th class="text-right">Details</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="log in paginatedLogs" :key="log.id">
                  <tr
                    class="log-row"
                    :class="`row-${inferLevel(log)}`"
                    @click="toggleExpanded(log.id)"
                  >
                    <td>
                      <div class="timestamp-cell">
                        <span>{{ formatDate(log.created_at) }}</span>
                        <small>{{ timeAgo(log.created_at) }}</small>
                      </div>
                    </td>
                    <td>
                      <span class="level-badge" :class="`level-${inferLevel(log)}`">
                        <iconify-icon :icon="levelIcon(inferLevel(log))" />
                        {{ inferLevel(log) }}
                      </span>
                    </td>
                    <td>
                      <div class="event-cell">
                        <span class="event-title">{{ getAction(log) }} - {{ getSubjectName(log) }}</span>
                        <small class="event-sub">{{ getLogMessage(log) }}</small>
                      </div>
                    </td>
                    <td>
                      <div class="user-cell">
                        <span>{{ log.causer?.name || 'System' }}</span>
                        <small>{{ userRole(log.causer_id) || 'No role' }}</small>
                      </div>
                    </td>
                    <td>{{ getModelName(log.subject_type) }}</td>
                    <td>{{ getChangedFields(log).length }}</td>
                    <td class="text-right">
                      <button class="row-action" type="button" @click.stop="toggleExpanded(log.id)">
                        {{ expandedRows.has(log.id) ? 'Hide' : 'Expand' }}
                      </button>
                    </td>
                  </tr>
                  <tr v-if="expandedRows.has(log.id)" class="expanded-row">
                    <td colspan="7">
                      <div class="expanded-content">
                        <div class="expanded-header">
                          <span class="expanded-title">Field Changes</span>
                          <span class="expanded-count">{{ getChangedFields(log).length }} fields</span>
                        </div>
                        <div v-if="getChangedFields(log).length === 0" class="muted">No field-level changes captured.</div>
                        <div v-else class="change-list">
                          <div v-for="field in getChangedFields(log)" :key="field" class="change-item">
                            <span class="change-field">{{ field }}</span>
                            <div class="change-values">
                              <div class="change-box change-box-old">
                                <span class="change-label">From</span>
                                <span class="change-text">{{ formatValue(getOldValue(log, field)) }}</span>
                              </div>
                              <span class="change-arrow">→</span>
                              <div class="change-box change-box-new">
                                <span class="change-label">To</span>
                                <span class="change-text">{{ formatValue(getNewValue(log, field)) }}</span>
                              </div>
                            </div>
                          </div>
                        </div>

                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <footer class="pagination-row" v-if="totalPages > 1">
            <button type="button" class="btn btn-soft" :disabled="page <= 1" @click="page -= 1">Previous</button>
            <button
              v-for="p in visiblePages"
              :key="`p-${p}`"
              type="button"
              class="page-btn"
              :class="{ active: p === page }"
              @click="page = p"
            >
              {{ p }}
            </button>
            <button type="button" class="btn btn-soft" :disabled="page >= totalPages" @click="page += 1">Next</button>
          </footer>
        </section>

      </div>

    </section>
  </div>
</template>

<script>
import api from '@/plugins/axios'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'

export default {
  components: { SearchableSelect },
  data() {
    return {
      logs: [],
      users: [],
      loading: false,
      searchQuery: '',
      autoRefresh: false,
      refreshTimer: null,
      expandedRows: new Set(),
      roleDraft: '',
      page: 1,
      perPage: 16,
      filters: {
        model: '',
        level: '',
        date_from: '',
        date_to: '',
        user_id: '',
        roles: [],
      },
    }
  },
  computed: {
    availableLevels() {
      return ['info', 'success', 'warning', 'error']
    },
  
    levelSelectOptions() {
      return [
        { value: '', label: 'All Levels' },
        ...this.availableLevels.map((level) => ({
          value: level,
          label: `${level.charAt(0).toUpperCase()}${level.slice(1)}`,
        })),
      ]
    },
    modelOptions() {
      const set = new Set(this.logs.map((log) => this.getModelName(log.subject_type)).filter(Boolean))
      return Array.from(set).sort((a, b) => a.localeCompare(b))
    },
    modelSelectOptions() {
      return [
        { value: '', label: 'All Modules' },
        ...this.modelOptions.map((model) => ({ value: model, label: model })),
      ]
    },
    roleOptions() {
      const set = new Set(this.users.map((u) => (u.role || '').trim()).filter(Boolean))
      return Array.from(set).sort((a, b) => a.localeCompare(b))
    },
    userSelectOptions() {
      return [
        { value: '', label: 'All Users' },
        ...this.users.map((u) => ({ value: String(u.id), label: u.name || `User ${u.id}` })),
      ]
    },
    activeFilterChips() {
      const chips = []
      if (this.filters.level) chips.push({ key: `level-${this.filters.level}`, type: 'level', value: this.filters.level, label: `Level: ${this.filters.level}` })
      if (this.filters.model) chips.push({ key: `model-${this.filters.model}`, type: 'model', value: this.filters.model, label: `Module: ${this.filters.model}` })
      if (this.filters.user_id) {
        const user = this.users.find((u) => String(u.id) === String(this.filters.user_id))
        chips.push({ key: `user-${this.filters.user_id}`, type: 'user_id', value: this.filters.user_id, label: `User: ${user?.name || this.filters.user_id}` })
      }
      if (this.filters.date_from) chips.push({ key: `from-${this.filters.date_from}`, type: 'date_from', value: this.filters.date_from, label: `From: ${this.filters.date_from}` })
      if (this.filters.date_to) chips.push({ key: `to-${this.filters.date_to}`, type: 'date_to', value: this.filters.date_to, label: `To: ${this.filters.date_to}` })
      this.filters.roles.forEach((role) => chips.push({ key: `role-${role}`, type: 'role', value: role, label: `Role: ${role}` }))
      return chips
    },
    filteredLogs() {
      const query = this.searchQuery.toLowerCase()
      return this.logs.filter((log) => {
        if (this.filters.model && !this.getModelName(log.subject_type).toLowerCase().includes(this.filters.model)) {
          return false
        }
        if (this.filters.level && this.inferLevel(log) !== this.filters.level) {
          return false
        }
        if (this.filters.user_id && String(log.causer_id || '') !== String(this.filters.user_id)) {
          return false
        }
        if (this.filters.roles.length) {
          const currentRole = (this.userRole(log.causer_id) || '').toLowerCase()
          if (!this.filters.roles.map((r) => r.toLowerCase()).includes(currentRole)) return false
        }
        if (this.filters.date_from && log.created_at && new Date(log.created_at) < new Date(this.filters.date_from)) {
          return false
        }
        if (this.filters.date_to && log.created_at && new Date(log.created_at) > new Date(`${this.filters.date_to}T23:59:59`)) {
          return false
        }
        if (!query) return true
        const blob = JSON.stringify(log).toLowerCase()
        return blob.includes(query)
      })
    },
    totalPages() {
      return Math.max(1, Math.ceil(this.filteredLogs.length / this.perPage))
    },
    paginatedLogs() {
      const start = (this.page - 1) * this.perPage
      return this.filteredLogs.slice(start, start + this.perPage)
    },
    visiblePages() {
      const start = Math.max(1, this.page - 2)
      const end = Math.min(this.totalPages, start + 4)
      const pages = []
      for (let i = start; i <= end; i += 1) pages.push(i)
      return pages
    },
  },
  watch: {
    autoRefresh(val) {
      if (val) {
        this.refreshTimer = window.setInterval(() => this.fetchLogs(), 10000)
      } else if (this.refreshTimer) {
        window.clearInterval(this.refreshTimer)
        this.refreshTimer = null
      }
    },
    filteredLogs() {
      if (this.page > this.totalPages) this.page = 1
    },
    filters: {
      deep: true,
      handler() {
        this.page = 1
      },
    },
    searchQuery() {
      this.page = 1
    },
  },
  mounted() {
    this.fetchUsers()
    this.fetchLogs()
  },
  beforeUnmount() {
    if (this.refreshTimer) window.clearInterval(this.refreshTimer)
  },
  methods: {
    async fetchLogs() {
      this.loading = true
      try {
        const res = await api.get('/logs')
        this.logs = Array.isArray(res.data) ? res.data : (res.data.data || [])
      } catch (e) {
        this.logs = []
      } finally {
        this.loading = false
      }
    },
    async fetchUsers() {
      try {
        const res = await api.get('/users')
        this.users = res.data.data || res.data || []
      } catch {
        this.users = []
      }
    },
    refreshNow() {
      this.fetchLogs()
    },
    resetFilters() {
      this.searchQuery = ''
      this.filters = {
        model: '',
        level: '',
        date_from: '',
        date_to: '',
        user_id: '',
        roles: [],
      }
      this.roleDraft = ''
    },
    addRoleFilter(role) {
      if (!role) return
      const roles = new Set(this.filters.roles)
      roles.add(role)
      this.filters.roles = Array.from(roles)
      this.roleDraft = ''
    },
    removeChip(chip) {
      if (chip.type === 'role') {
        this.filters.roles = this.filters.roles.filter((r) => r !== chip.value)
        return
      }
      this.filters[chip.type] = chip.type === 'roles' ? [] : ''
    },
    getAttributes(log) {
      return log?.properties?.attributes || {}
    },
    getOld(log) {
      return log?.properties?.old || {}
    },
    getNewValue(log, field) {
      return this.getAttributes(log)?.[field]
    },
      getSubjectName(log) {
      if (log.subject?.name) return log.subject.name

      const attrs = this.getAttributes(log)

      return (
        attrs.name ||
        attrs.title ||
        attrs.full_name ||
        attrs.email ||
        `#${log.subject_id || '-'}`
      )
    },
    getOldValue(log, field) {
      return this.getOld(log)?.[field]
    },
    getChangedFields(log) {
      const attrs = this.getAttributes(log)
      return Object.keys(attrs).filter((field) => JSON.stringify(this.getOldValue(log, field)) !== JSON.stringify(attrs[field]))
    },
    inferLevel(log) {
      const text = `${log?.description || ''} ${JSON.stringify(log?.properties || {})}`.toLowerCase()
      if (text.includes('error') || text.includes('fail') || text.includes('exception')) return 'error'
      if (text.includes('warning') || text.includes('warn')) return 'warning'
      if (text.includes('create') || text.includes('updated') || text.includes('saved') || text.includes('success')) return 'success'
      return 'info'
    },
    getAction(log) {
      return log?.description || log?.event || 'updated'
    },
    getLogMessage(log) {
      return log?.properties?.message || log?.description || log?.event || 'No message available.'
    },
    userRole(userId) {
      const user = this.users.find((u) => String(u.id) === String(userId))
      return user?.role || user?.user_type || ''
    },
    getMeta(log, key) {
      const meta = log?.properties?.meta || log?.properties?.context || {}
      return meta?.[key] || '-'
    },
    levelIcon(level) {
      if (level === 'success') return 'lucide:check-circle-2'
      if (level === 'warning') return 'lucide:triangle-alert'
      if (level === 'error') return 'lucide:circle-x'
      return 'lucide:info'
    },
    formatValue(val) {
      if (val === null || val === undefined) return '-'
      if (typeof val === 'object') return JSON.stringify(val)
      return String(val)
    },
    getModelName(type) {
      if (!type) return '-'
      return type.split('\\').pop()
    },
    formatDate(date) {
      return date ? new Date(date).toLocaleString('en-GB') : '-'
    },
    timeAgo(date) {
      if (!date) return '-'
      const diff = Date.now() - new Date(date).getTime()
      const mins = Math.floor(diff / 60000)
      if (mins < 1) return 'Just now'
      if (mins < 60) return `${mins}m ago`
      const hrs = Math.floor(mins / 60)
      if (hrs < 24) return `${hrs}h ago`
      return `${Math.floor(hrs / 24)}d ago`
    },
    toggleExpanded(id) {
      const next = new Set(this.expandedRows)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      this.expandedRows = next
    },
    prettyLog(log) {
      return JSON.stringify(log, null, 2)
    },
    async copyLog(log) {
      try {
        await navigator.clipboard.writeText(this.prettyLog(log))
      } catch {}
    },
    exportJson() {
      const blob = new Blob([JSON.stringify(this.filteredLogs, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `logs-${Date.now()}.json`
      a.click()
      URL.revokeObjectURL(url)
    },
    exportCsv() {
      const rows = this.filteredLogs.map((log) => ({
        id: log.id,
        timestamp: log.created_at,
        level: this.inferLevel(log),
        action: this.getAction(log),
        user: log.causer?.name || 'System',
        source: this.getModelName(log.subject_type),
      }))
      const header = Object.keys(rows[0] || { id: '', timestamp: '', level: '', action: '', user: '', source: '' })
      const csv = [header.join(','), ...rows.map((r) => header.map((h) => `"${String(r[h] ?? '').replace(/"/g, '""')}"`).join(','))].join('\n')
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `logs-${Date.now()}.csv`
      a.click()
      URL.revokeObjectURL(url)
    },
  },
}
</script>

<style scoped>
.logs-page {
  width: 100%;
  min-height: 100%;
  padding: 12px 16px;
  background: transparent;
}

.logs-shell {
  width: 100%;
  border-radius: 16px;
  background: transparent;
  display: grid;
  gap: 10px;
}

.control-bar {
  padding: 12px;
  border: 1px solid #d8dee8;
  border-radius: 14px;
  background: #ffffff;
  box-shadow: none;
}

.bar-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.subtitle {
  font-size: 11px;
  color: #334155;
}

.bar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.auto-refresh-chip {
  height: 32px;
  border: 1px solid #d5dbe6;
  border-radius: 8px;
  background: #ffffff;
  padding: 0 10px;
  font-size: 11px;
  color: #0f172a;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.filters-grid {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) repeat(6, minmax(120px, auto)) 90px;
  gap: 8px;
}

.search-wrap,
.control-select,
.control-input {
  height: 34px;
  border: 1px solid #d5dbe6;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 12px;
}

.search-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px;
}

.search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  color: #0f172a;
  font-size: 12px;
}

.search-icon {
  color: #475569;
  font-size: 13px;
}

.control-select,
.control-input {
  padding: 0 10px;
  line-height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.control-select {
  appearance: none;
  text-align-last: center;
}

.btn {
  height: 32px;
  border: 1px solid #d5dbe6;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 11px;
  font-weight: 600;
  padding: 0 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  text-align: center;
  transition: .18s ease;
}

.btn:hover {
  border-color: #9ca3af;
  transform: translateY(-1px);
}

.btn-soft {
  background: #ffffff;
}

.btn-ghost {
  background: #ffffff;
  color: #334155;
}

.active-chips {
  margin-top: 10px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-chip {
  border: 1px solid #d5dbe6;
  background: #ffffff;
  color: #1e293b;
  height: 28px;
  border-radius: 999px;
  padding: 0 10px;
  font-size: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.logs-layout {
  display: block;
}

.card-surface {
  border: 1px solid #d8dee8;
  border-radius: 12px;
  background: #ffffff;
}

.logs-main {
  padding: 0;
  overflow: hidden;
}

.table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  background: #ffffff;
}

.table-stats,
.table-legend {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  color: #1e293b;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-left: 8px;
}

.legend-dot.info { background: #5d7cb2; }
.legend-dot.success { background: #4ba76a; }
.legend-dot.warning { background: #d5a14a; }
.legend-dot.error { background: #cf5b62; }

.table-wrap {
  overflow: auto;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}

.logs-table th,
.logs-table td {
  border-bottom: 1px solid #e5e7eb;
  padding: 10px;
  color: #0f172a;
  text-align: left;
  vertical-align: middle;
}

.logs-table th {
  font-size: 10px;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: .02em;
  background: #f9fafb;
  position: sticky;
  top: 0;
  z-index: 1;
}

.log-row {
  cursor: pointer;
  transition: background .16s ease, box-shadow .16s ease;
}

.log-row:hover {
  background: #f9fafb;
}

.row-error {
  box-shadow: inset 3px 0 0 #cf5b62;
}

.level-badge {
  font-size: 11px;
  line-height: 1;
  padding: 5px 10px;
  border-radius: 999px;
  text-transform: capitalize;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  white-space: nowrap;
  min-width: 74px;
  border: 1px solid transparent;
}

.level-info { background: #e0edff; color: #1e3a8a; border-color: #bfdbfe; }
.level-success { background: #dcfce7; color: #166534; border-color: #86efac; }
.level-warning { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.level-error { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

.row-action {
  border: 1px solid #d5dbe6;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 10px;
  padding: 3px 8px;
}

.expanded-row td {
  background: #f8fafc;
  padding: 6px 10px;
}

.expanded-content {
  display: grid;
  gap: 6px;
  padding: 6px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #ffffff;
}

.expanded-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 4px;
  border-bottom: 1px solid #eef2f7;
}

.expanded-title {
  font-size: 10px;
  font-weight: 700;
  color: #0f172a;
}

.expanded-count {
  font-size: 9px;
  color: #475569;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  padding: 1px 6px;
}

.change-list {
  display: grid;
  gap: 4px;
}

.change-item {
  display: grid;
  grid-template-columns: minmax(100px, 130px) minmax(0, 1fr);
  align-items: start;
  gap: 6px;
  font-size: 10px;
  padding: 4px 6px;
  border: 1px solid #eef2f7;
  border-radius: 6px;
  background: #fcfdff;
}

.change-field {
  min-width: 0;
  color: #0f172a;
  font-weight: 600;
  font-size: 10px;
  overflow-wrap: anywhere;
}

.change-values {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 4px;
}

.change-box {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 3px 6px;
  display: grid;
  gap: 2px;
  min-width: 0;
}

.change-box-old {
  background: #fff1f2;
  border-color: #fecdd3;
}

.change-box-new {
  background: #ecfdf5;
  border-color: #bbf7d0;
}

.change-label {
  font-size: 9px;
  color: #64748b;
}

.change-text {
  color: #0f172a;
  font-size: 10px;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.change-arrow {
  color: #94a3b8;
  font-weight: 600;
  font-size: 10px;
}

.copy-btn {
  width: fit-content;
  border: 1px solid #d5dbe6;
  border-radius: 6px;
  background: #ffffff;
  color: #0f172a;
  font-size: 10px;
  padding: 4px 8px;
}

.state-view {
  padding: 40px;
  text-align: center;
  color: #334155;
  font-size: 12px;
}

.pagination-row {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
  padding: 10px;
  border-top: 1px solid #e5e7eb;
}

.page-btn {
  width: 30px;
  height: 30px;
  border: 1px solid #d5dbe6;
  border-radius: 8px;
  background: #ffffff;
  color: #0f172a;
  font-size: 11px;
}

.page-btn.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #fff;
}

.message-block,
.json-block {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #ffffff;
  overflow: hidden;
}

.json-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.json-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 11px;
  color: #1d4ed8;
}

.message-block pre,
.json-block pre {
  margin: 0;
  padding: 10px;
  font-size: 11px;
  line-height: 1.45;
  color: #0f172a;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  max-height: 360px;
  overflow: auto;
}

.muted {
  color: #334155;
  font-size: 11px;
}

.expanded-content .muted {
  font-size: 10px;
  padding: 2px 0;
}

.timestamp-cell,
.event-cell,
.user-cell {
  display: grid;
  gap: 2px;
}

.timestamp-cell small,
.event-sub,
.user-cell small {
  font-size: 10px;
  color: #475569;
}

.event-title {
  color: #0f172a;
}

.advanced-select {
  min-width: 180px;
}

:deep(.advanced-select .crm-searchable-select .vs__dropdown-toggle) {
  min-height: 34px;
  height: 34px;
  border: 1px solid #d5dbe6;
  border-radius: 8px;
  background: #ffffff;
  padding: 0 10px;
}

:deep(.advanced-select .crm-searchable-select .vs__selected-options) {
  align-items: center;
}

:deep(.advanced-select .crm-searchable-select .vs__search),
:deep(.advanced-select .crm-searchable-select .vs__selected),
:deep(.advanced-select .crm-searchable-select .vs__dropdown-option) {
  font-size: 12px;
  color: #0f172a;
}

:deep(.advanced-select .crm-searchable-select .vs__search::placeholder) {
  color: #475569;
}

:deep(.advanced-select .crm-searchable-select .vs__dropdown-menu) {
  border: 1px solid #d5dbe6;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

:deep(.advanced-select .crm-searchable-select .vs__dropdown-option--highlight) {
  background: #eff6ff;
  color: #1d4ed8;
}

.text-right {
  text-align: right !important;
}

@media (max-width: 1200px) {
}

@media (max-width: 992px) {
  .bar-head {
    align-items: flex-start;
    flex-direction: column;
  }
  .filters-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 640px) {
  .logs-page {
    padding: 8px;
  }
  .filters-grid {
    grid-template-columns: 1fr;
  }
  .table-toolbar {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .change-item {
    grid-template-columns: 1fr;
  }
  .change-values {
    grid-template-columns: 1fr;
  }
  .change-arrow {
    display: none;
  }
}
</style>