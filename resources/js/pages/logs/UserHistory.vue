<template>
  <div class="uh-page">
    <header class="uh-card uh-filters">
      <div class="uh-head">
        <div>
          <h6 class="uh-title mb-0">User History</h6>
          <p class="uh-sub mb-0">Pick a user to see what they changed and when.</p>
        </div>
        <div class="uh-head-actions">
          <button
            type="button"
            class="uh-export"
            :disabled="!filters.user_id || !total || exporting"
            @click="exportCsv"
          >
            <iconify-icon icon="lucide:download" />
            {{ exporting ? `Exporting… ${exportProgress}` : 'Export CSV' }}
          </button>
          <router-link to="/logs" class="uh-link">All system logs →</router-link>
        </div>
      </div>

      <div class="uh-row">
        <SearchableSelect
          v-model="filters.user_id"
          class="uh-user"
          :options="userOptions"
          option-label="label"
          option-value="value"
          placeholder="Choose a user…"
          :append-to-body="true"
        />
        <label class="uh-field">
          <span>From</span>
          <input v-model="filters.date_from" type="date" />
        </label>
        <label class="uh-field">
          <span>To</span>
          <input v-model="filters.date_to" type="date" />
        </label>
        <div class="uh-quick">
          <button type="button" @click="setRange(0)">Today</button>
          <button type="button" @click="setRange(6)">7 days</button>
          <button type="button" @click="setRange(29)">30 days</button>
          <button type="button" @click="setRange(null)">Any time</button>
        </div>
      </div>

      <div class="uh-row">
        <div class="uh-tabs" role="tablist">
          <button
            v-for="t in types"
            :key="t.value"
            type="button"
            role="tab"
            :aria-selected="filters.type === t.value"
            :class="{ active: filters.type === t.value }"
            @click="filters.type = t.value"
          >
            <iconify-icon :icon="t.icon" />
            {{ t.label }}
          </button>
        </div>
        <label class="uh-check">
          <input v-model="filters.include_views" type="checkbox" />
          <span>Also show when they only opened a record</span>
        </label>
      </div>
    </header>

    <section class="uh-card uh-body">
      <div v-if="!filters.user_id" class="uh-state">
        <iconify-icon icon="lucide:user-search" class="uh-state-icon" />
        Choose a user to see their history.
      </div>
      <div v-else-if="loading && !items.length" class="uh-state">Loading…</div>
      <div v-else-if="error" class="uh-state uh-error">{{ error }}</div>
      <div v-else-if="!items.length" class="uh-state">No changes found for these filters.</div>

      <template v-else>
        <div class="uh-summary">
          <strong>{{ userName }}</strong> made <strong>{{ total }}</strong>
          {{ total === 1 ? 'change' : 'changes' }}{{ rangeText }}
        </div>

        <div v-for="group in grouped" :key="group.day" class="uh-day">
          <div class="uh-day-label">{{ group.label }}</div>

          <article v-for="item in group.items" :key="item.key" class="uh-item">
            <div class="uh-time">{{ timeOf(item.created_at) }}</div>
            <div class="uh-main">
              <div class="uh-line">
                <span class="uh-badge" :class="`b-${item.type}`">{{ typeLabel(item) }}</span>
                <span class="uh-action">{{ item.action }}</span>
                <span class="uh-record">{{ item.record_name }}</span>
              </div>

              <ul v-if="item.changes.length" class="uh-changes">
                <li v-for="(c, i) in visibleChanges(item)" :key="i">
                  <span class="uh-label">{{ c.label }}</span>
                  <template v-if="c.detail || c.old === null">
                    <span class="uh-new">{{ shown(c.new) }}</span>
                  </template>
                  <template v-else>
                    <span class="uh-old">{{ shown(c.old) }}</span>
                    <span class="uh-arrow">→</span>
                    <span class="uh-new">{{ shown(c.new) }}</span>
                  </template>
                </li>
              </ul>
              <button
                v-if="item.changes.length > maxChanges"
                type="button"
                class="uh-more"
                @click="toggle(item.key)"
              >
                {{ expanded.has(item.key) ? 'Show less' : `Show ${item.changes.length - maxChanges} more` }}
              </button>
            </div>
          </article>
        </div>

        <footer class="uh-pager" v-if="lastPage > 1">
          <button type="button" :disabled="page <= 1 || loading" @click="page -= 1">Previous</button>
          <span>Page {{ page }} of {{ lastPage }}</span>
          <button type="button" :disabled="page >= lastPage || loading" @click="page += 1">Next</button>
        </footer>
      </template>
    </section>
  </div>
</template>

<script>
import api from '@/plugins/axios'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'

const pad = (n) => String(n).padStart(2, '0')
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

export default {
  components: { SearchableSelect },
  data() {
    return {
      users: [],
      items: [],
      total: 0,
      page: 1,
      lastPage: 1,
      loading: false,
      exporting: false,
      exportProgress: '',
      error: '',
      fetchToken: 0,
      maxChanges: 6,
      expanded: new Set(),
      types: [
        { value: 'all', label: 'Everything', icon: 'lucide:layers' },
        { value: 'lead', label: 'Leads', icon: 'lucide:user-round' },
        { value: 'deal', label: 'Deals', icon: 'lucide:handshake' },
        { value: 'listing', label: 'Listings', icon: 'lucide:building-2' },
        { value: 'other', label: 'Other', icon: 'lucide:ellipsis' },
      ],
      filters: {
        user_id: this.$route.query.user_id ? String(this.$route.query.user_id) : '',
        type: this.$route.query.type || 'all',
        date_from: this.$route.query.date_from || ymd(new Date(Date.now() - 6 * 864e5)),
        date_to: this.$route.query.date_to || ymd(new Date()),
        include_views: false,
      },
    }
  },
  computed: {
    userOptions() {
      return this.users.map((u) => ({ value: String(u.id), label: u.name || `User ${u.id}` }))
    },
    userName() {
      return this.users.find((u) => String(u.id) === String(this.filters.user_id))?.name || 'This user'
    },
    rangeText() {
      const { date_from: f, date_to: t } = this.filters
      if (f && t) return f === t ? ` on ${f}` : ` between ${f} and ${t}`
      if (f) return ` since ${f}`
      if (t) return ` until ${t}`
      return ''
    },
    grouped() {
      const today = ymd(new Date())
      const yesterday = ymd(new Date(Date.now() - 864e5))
      const groups = []
      for (const item of this.items) {
        const day = item.created_at ? ymd(new Date(item.created_at)) : 'unknown'
        let g = groups[groups.length - 1]
        if (!g || g.day !== day) {
          const label = day === today ? 'Today' : day === yesterday ? 'Yesterday'
            : new Date(item.created_at).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
          g = { day, label, items: [] }
          groups.push(g)
        }
        g.items.push(item)
      }
      return groups
    },
  },
  watch: {
    filters: {
      deep: true,
      handler() {
        this.syncQuery()
        if (this.page !== 1) this.page = 1
        else this.fetch()
      },
    },
    page() {
      this.fetch()
    },
  },
  mounted() {
    this.fetchUsers()
    this.fetch()
  },
  methods: {
    async fetchUsers() {
      try {
        const res = await api.get('/user-history/filters')
        this.users = res.data.users || []
      } catch {
        this.users = []
      }
    },
    async fetch() {
      if (!this.filters.user_id) {
        this.items = []
        this.total = 0
        return
      }
      const token = ++this.fetchToken
      this.loading = true
      this.error = ''
      try {
        const res = await api.get('/user-history', { params: this.params(this.page, 25) })
        if (token !== this.fetchToken) return
        this.items = res.data.data || []
        this.total = res.data.total || 0
        this.lastPage = res.data.last_page || 1
        this.expanded = new Set()
      } catch (e) {
        if (token !== this.fetchToken) return
        this.items = []
        this.total = 0
        this.error = e?.response?.status === 403 ? 'Only super admins can view user history.' : 'Could not load history. Please try again.'
      } finally {
        if (token === this.fetchToken) this.loading = false
      }
    },
    params(page, perPage) {
      const params = { user_id: this.filters.user_id, type: this.filters.type, page, per_page: perPage }
      if (this.filters.date_from) params.date_from = this.filters.date_from
      if (this.filters.date_to) params.date_to = this.filters.date_to
      if (this.filters.include_views) params.include_views = 1
      return params
    },
    // Exports every entry matching the filters, not just the page on screen.
    async exportCsv() {
      if (!this.filters.user_id || this.exporting) return
      this.exporting = true
      try {
        const all = []
        let page = 1
        let last = 1
        do {
          this.exportProgress = last > 1 ? `${page}/${last}` : ''
          const res = await api.get('/user-history', { params: this.params(page, 200) })
          all.push(...(res.data.data || []))
          last = res.data.last_page || 1
          page += 1
        } while (page <= last)

        const header = ['Date', 'Time', 'User', 'Type', 'Action', 'Record', 'Changes']
        const rows = all.map((item) => {
          const d = item.created_at ? new Date(item.created_at) : null
          const changes = item.changes
            .map((c) => (c.detail || c.old === null ? `${c.label}: ${this.shown(c.new)}` : `${c.label}: ${this.shown(c.old)} → ${this.shown(c.new)}`))
            .join('\n')
          return [d ? ymd(d) : '', d ? this.timeOf(item.created_at) : '', this.userName, this.typeLabel(item), item.action, item.record_name, changes]
        })
        const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
        const csv = [header, ...rows].map((r) => r.map(cell).join(',')).join('\r\n')

        // BOM so Excel reads UTF-8 (Arabic names) correctly.
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        const safeName = this.userName.replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-|-$/g, '') || 'user'
        a.href = url
        a.download = `history-${safeName}-${this.filters.date_from || 'start'}-to-${this.filters.date_to || ymd(new Date())}.csv`
        a.click()
        URL.revokeObjectURL(url)
      } catch {
        this.error = 'Export failed. Please try again.'
      } finally {
        this.exporting = false
        this.exportProgress = ''
      }
    },
    syncQuery() {
      const q = {}
      if (this.filters.user_id) q.user_id = this.filters.user_id
      if (this.filters.type !== 'all') q.type = this.filters.type
      if (this.filters.date_from) q.date_from = this.filters.date_from
      if (this.filters.date_to) q.date_to = this.filters.date_to
      this.$router.replace({ query: q }).catch(() => {})
    },
    setRange(days) {
      if (days === null) {
        this.filters.date_from = ''
        this.filters.date_to = ''
        return
      }
      this.filters.date_from = ymd(new Date(Date.now() - days * 864e5))
      this.filters.date_to = ymd(new Date())
    },
    typeLabel(item) {
      if (item.type === 'other') return item.model || 'Other'
      return { lead: 'Lead', deal: 'Deal', listing: 'Listing' }[item.type] || item.type
    },
    visibleChanges(item) {
      return this.expanded.has(item.key) ? item.changes : item.changes.slice(0, this.maxChanges)
    },
    toggle(key) {
      const next = new Set(this.expanded)
      next.has(key) ? next.delete(key) : next.add(key)
      this.expanded = next
    },
    shown(v) {
      if (v === null || v === undefined || v === '') return '(empty)'
      const s = String(v)
      return s.length > 160 ? `${s.slice(0, 160)}…` : s
    },
    timeOf(date) {
      return date ? new Date(date).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : ''
    },
  },
}
</script>

<style scoped>
.uh-page { padding: 12px 16px; display: grid; gap: 10px; }
.uh-card { background: #fff; border: 1px solid #d8dee8; border-radius: 12px; }
.uh-filters { padding: 12px; display: grid; gap: 10px; }
.uh-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.uh-title { font-size: 14px; font-weight: 700; color: #0f172a; }
.uh-sub { font-size: 12px; color: #475569; }
.uh-link { font-size: 12px; color: #2563eb; text-decoration: none; }
.uh-head-actions { display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.uh-export {
  height: 32px; border: 1px solid #d5dbe6; border-radius: 8px; background: #fff; color: #0f172a;
  font-size: 12px; font-weight: 600; padding: 0 12px; display: inline-flex; align-items: center; gap: 6px;
}
.uh-export:hover:not(:disabled) { border-color: #94a3b8; }
.uh-export:disabled { opacity: .5; cursor: not-allowed; }

.uh-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.uh-user { min-width: 240px; flex: 1 1 240px; max-width: 360px; }
.uh-field { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #334155; }
.uh-field input {
  height: 34px; border: 1px solid #d5dbe6; border-radius: 8px; padding: 0 8px; font-size: 12px; color: #0f172a; background: #fff;
}
.uh-quick { display: inline-flex; gap: 4px; flex-wrap: wrap; }
.uh-quick button, .uh-pager button, .uh-more {
  height: 30px; border: 1px solid #d5dbe6; border-radius: 8px; background: #fff; color: #0f172a; font-size: 11px; padding: 0 10px;
}
.uh-quick button:hover, .uh-pager button:hover:not(:disabled) { border-color: #94a3b8; }

.uh-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 10px; }
.uh-tabs button {
  height: 30px; border: 0; background: transparent; border-radius: 8px; padding: 0 12px; font-size: 12px; color: #334155;
  display: inline-flex; align-items: center; gap: 6px;
}
.uh-tabs button.active { background: #fff; color: #0f172a; font-weight: 600; box-shadow: 0 1px 2px rgba(15, 23, 42, .12); }
.uh-check { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; margin-left: auto; }

.uh-body { padding: 4px 0 8px; }
.uh-state { padding: 48px 16px; text-align: center; color: #475569; font-size: 13px; display: grid; justify-items: center; gap: 8px; }
.uh-state-icon { font-size: 28px; color: #94a3b8; }
.uh-error { color: #b91c1c; }
.uh-summary { padding: 12px 16px; font-size: 13px; color: #334155; border-bottom: 1px solid #eef2f7; }

.uh-day-label {
  position: sticky; top: 0; z-index: 1; background: #f8fafc; padding: 6px 16px; font-size: 11px; font-weight: 700;
  color: #475569; text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #eef2f7;
}
.uh-item { display: grid; grid-template-columns: 56px 1fr; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
.uh-time { font-size: 12px; color: #64748b; padding-top: 2px; font-variant-numeric: tabular-nums; }
.uh-main { min-width: 0; display: grid; gap: 6px; }
.uh-line { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; }
.uh-action { font-weight: 600; color: #0f172a; }
.uh-record { color: #334155; overflow-wrap: anywhere; }

.uh-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; border: 1px solid transparent; }
.b-lead { background: #e0edff; color: #1e3a8a; border-color: #bfdbfe; }
.b-deal { background: #dcfce7; color: #166534; border-color: #86efac; }
.b-listing { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.b-other { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }

.uh-changes { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; }
.uh-changes li {
  display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; font-size: 12px; padding: 4px 8px;
  background: #fafbfc; border: 1px solid #eef2f7; border-radius: 6px;
}
.uh-label { font-weight: 600; color: #0f172a; min-width: 120px; }
.uh-old { color: #9f1239; text-decoration: line-through; text-decoration-color: rgba(159, 18, 57, .4); overflow-wrap: anywhere; }
.uh-new { color: #166534; overflow-wrap: anywhere; }
.uh-arrow { color: #94a3b8; }
.uh-more { justify-self: start; height: 26px; }

.uh-pager { display: flex; justify-content: center; align-items: center; gap: 10px; padding: 12px; font-size: 12px; color: #334155; }
.uh-pager button:disabled { opacity: .5; }

@media (max-width: 640px) {
  .uh-page { padding: 8px; }
  .uh-user { max-width: none; }
  .uh-check { margin-left: 0; }
  .uh-item { grid-template-columns: 1fr; gap: 2px; }
  .uh-label { min-width: 0; width: 100%; }
}
</style>
