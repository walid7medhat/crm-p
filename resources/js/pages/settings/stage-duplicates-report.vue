<template>
  <div class="dashboard-main-body sdr-page">
    <div class="sdr-shell">
      <div class="sdr-top">
        <div>
          <div class="sdr-title">Stage lead duplicates</div>
          <p class="sdr-hint">Phone or email match · Contacted / Qualified / Assigned · Super Admin only</p>
        </div>
        <div class="sdr-actions">
          <button type="button" class="sdr-btn" @click="fetchSummary" :disabled="loading">
            <iconify-icon icon="lucide:refresh-cw" width="14" height="14" />
            Refresh counts
          </button>
          <button
            v-if="report && !detailsLoaded"
            type="button"
            class="sdr-btn sdr-btn--secondary"
            @click="fetchDetails"
            :disabled="loadingDetails"
          >
            <iconify-icon icon="lucide:list" width="14" height="14" />
            {{ loadingDetails ? 'Loading details…' : 'Load full details' }}
          </button>
        </div>
      </div>

      <div v-if="error" class="sdr-error">
        <span>{{ error }}</span>
        <button type="button" class="sdr-link" @click="fetchSummary">Retry</button>
      </div>

      <div v-if="loading" class="sdr-loading">Loading counts…</div>

      <template v-else-if="!error && report">
        <!-- Key numbers -->
        <div class="sdr-metrics">
          <div class="sdr-metric">
            <span class="sdr-metric__n">{{ summary.unique_duplicate_groups }}</span>
            <span class="sdr-metric__l">Unique groups</span>
          </div>
          <div class="sdr-metric">
            <span class="sdr-metric__n">{{ summary.groups_exactly_two_stages }}</span>
            <span class="sdr-metric__l">In 2 stages</span>
          </div>
          <div class="sdr-metric sdr-metric--warn">
            <span class="sdr-metric__n">{{ summary.groups_all_three_stages }}</span>
            <span class="sdr-metric__l">In all 3 stages</span>
          </div>
          <div class="sdr-metric" v-if="summary.ambiguous_bridges">
            <span class="sdr-metric__n">{{ summary.ambiguous_bridges }}</span>
            <span class="sdr-metric__l">Ambiguous</span>
          </div>
          <div
            v-for="(meta, name) in report.stages"
            :key="name"
            class="sdr-metric sdr-metric--stage"
          >
            <span class="sdr-metric__n">{{ meta.active_leads }}</span>
            <span class="sdr-metric__l">{{ name }}</span>
          </div>
        </div>

        <!-- Pairwise summary -->
        <div class="sdr-label">Pair comparisons</div>
        <div class="sdr-table-wrap">
          <table class="sdr-table">
            <thead>
              <tr>
                <th>Comparison</th>
                <th>Pairs</th>
                <th>Leads</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Both</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in summary.comparisons" :key="row.label">
                <td>{{ row.label }}</td>
                <td>{{ row.duplicate_pairs }}</td>
                <td>{{ row.unique_leads_involved }}</td>
                <td>{{ row.matches_by_phone }}</td>
                <td>{{ row.matches_by_email }}</td>
                <td>{{ row.matches_by_both }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!detailsLoaded" class="sdr-hint sdr-hint--block">
          Counts only — open “Load full details” if you need lead rows (slower).
        </div>

        <template v-else>
          <div v-if="detailsError" class="sdr-error">
            <span>{{ detailsError }}</span>
            <button type="button" class="sdr-link" @click="fetchDetails">Retry</button>
          </div>

          <!-- Matching pairs (only comparisons that have pairs) -->
          <template v-for="cmp in comparisonsWithPairs" :key="cmp.label">
            <div class="sdr-label">
              {{ cmp.label }}
              <span class="sdr-label__meta">{{ cmp.duplicate_pairs }} pairs</span>
            </div>
            <div class="sdr-table-wrap">
              <table class="sdr-table">
                <thead>
                  <tr>
                    <th>Match</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Stage</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Stage</th>
                    <th>Phone</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(pair, idx) in cmp.pairs" :key="idx">
                    <td><span class="sdr-tag" :class="'sdr-tag--' + pair.match">{{ pair.match }}</span></td>
                    <td class="sdr-mono">#{{ pair.lead_a.id }}</td>
                    <td class="sdr-clip" :title="pair.lead_a.name">{{ pair.lead_a.name }}</td>
                    <td>{{ pair.lead_a.stage }}</td>
                    <td class="sdr-mono">{{ pair.lead_a.phone || '—' }}</td>
                    <td class="sdr-clip" :title="pair.lead_a.email || ''">{{ pair.lead_a.email || '—' }}</td>
                    <td class="sdr-vs">↔</td>
                    <td class="sdr-mono">#{{ pair.lead_b.id }}</td>
                    <td class="sdr-clip" :title="pair.lead_b.name">{{ pair.lead_b.name }}</td>
                    <td>{{ pair.lead_b.stage }}</td>
                    <td class="sdr-mono">{{ pair.lead_b.phone || '—' }}</td>
                    <td class="sdr-clip" :title="pair.lead_b.email || ''">{{ pair.lead_b.email || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          <div v-if="!comparisonsWithPairs.length" class="sdr-empty">No cross-stage duplicate pairs.</div>

          <!-- Groups: all 3 -->
          <div class="sdr-label">
            In all 3 stages
            <span class="sdr-label__meta">{{ groups.all_three_stages.length }}</span>
          </div>
          <div v-if="!groups.all_three_stages.length" class="sdr-empty">None</div>
          <div v-for="(g, i) in groups.all_three_stages" :key="'t3-' + i" class="sdr-group">
            <div class="sdr-group__meta">
              <span class="sdr-tag" :class="'sdr-tag--' + g.match_type">{{ g.match_type }}</span>
              <span>{{ g.stages.join(' · ') }}</span>
              <span class="sdr-dim">{{ g.match_key }}</span>
            </div>
            <div class="sdr-table-wrap">
              <table class="sdr-table sdr-table--compact">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Stage</th>
                    <th>Phone</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="l in g.leads" :key="l.id">
                    <td class="sdr-mono">#{{ l.id }}</td>
                    <td>{{ l.name }}</td>
                    <td>{{ l.stage }}</td>
                    <td class="sdr-mono">{{ l.phone || '—' }}</td>
                    <td>{{ l.email || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Groups: exactly 2 -->
          <div class="sdr-label">
            In exactly 2 stages
            <span class="sdr-label__meta">{{ groups.exactly_two_stages.length }}</span>
          </div>
          <div v-if="!groups.exactly_two_stages.length" class="sdr-empty">None</div>
          <div v-for="(g, i) in groups.exactly_two_stages" :key="'t2-' + i" class="sdr-group">
            <div class="sdr-group__meta">
              <span class="sdr-tag" :class="'sdr-tag--' + g.match_type">{{ g.match_type }}</span>
              <span>{{ g.stages.join(' · ') }}</span>
              <span class="sdr-dim">{{ g.match_key }}</span>
            </div>
            <div class="sdr-table-wrap">
              <table class="sdr-table sdr-table--compact">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Stage</th>
                    <th>Phone</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="l in g.leads" :key="l.id">
                    <td class="sdr-mono">#{{ l.id }}</td>
                    <td>{{ l.name }}</td>
                    <td>{{ l.stage }}</td>
                    <td class="sdr-mono">{{ l.phone || '—' }}</td>
                    <td>{{ l.email || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Ambiguous -->
          <template v-if="groups.ambiguous_bridges.length">
            <div class="sdr-label">
              Ambiguous (review)
              <span class="sdr-label__meta">{{ groups.ambiguous_bridges.length }}</span>
            </div>
            <p class="sdr-hint">Different phones linked by a shared email — not counted as one client.</p>
            <div v-for="(g, i) in groups.ambiguous_bridges" :key="'amb-' + i" class="sdr-group sdr-group--warn">
              <div class="sdr-group__meta">
                <span>{{ g.stages.join(' · ') }}</span>
                <span class="sdr-dim">{{ g.distinct_phones }} phones · {{ g.distinct_emails }} emails</span>
              </div>
              <div class="sdr-table-wrap">
                <table class="sdr-table sdr-table--compact">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Stage</th>
                      <th>Phone</th>
                      <th>Email</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="l in g.leads" :key="l.id">
                      <td class="sdr-mono">#{{ l.id }}</td>
                      <td>{{ l.name }}</td>
                      <td>{{ l.stage }}</td>
                      <td class="sdr-mono">{{ l.phone || '—' }}</td>
                      <td>{{ l.email || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

const loading = ref(false)
const loadingDetails = ref(false)
const error = ref('')
const detailsError = ref('')
const report = ref(null)
const detailsLoaded = ref(false)

const summary = computed(() => report.value?.summary || {
  comparisons: [],
  unique_duplicate_groups: 0,
  groups_exactly_two_stages: 0,
  groups_all_three_stages: 0,
  ambiguous_bridges: 0,
})

const groups = computed(() => report.value?.groups || {
  exactly_two_stages: [],
  all_three_stages: [],
  ambiguous_bridges: [],
})

const comparisonsWithPairs = computed(() =>
  (report.value?.comparisons || []).filter((c) => (c.pairs || []).length > 0)
)

const fetchSummary = async () => {
  loading.value = true
  error.value = ''
  detailsLoaded.value = false
  detailsError.value = ''
  try {
    const response = await api.get('/reports/stage-duplicates', {
      params: { summary: 1 },
      timeout: 120000,
    })
    report.value = response?.data?.data || null
    if (!report.value) error.value = 'Empty response'
  } catch (err) {
    error.value = err?.response?.status === 403
      ? 'Forbidden — Super Admin only'
      : (err?.code === 'ECONNABORTED'
        ? 'Timed out loading counts — try again'
        : (err?.response?.data?.message || 'Failed to load report'))
    report.value = null
  } finally {
    loading.value = false
  }
}

const fetchDetails = async () => {
  loadingDetails.value = true
  detailsError.value = ''
  try {
    const response = await api.get('/reports/stage-duplicates', {
      params: { summary: 0 },
      timeout: 300000,
    })
    const data = response?.data?.data || null
    if (!data) {
      detailsError.value = 'Empty response'
      return
    }
    report.value = data
    detailsLoaded.value = true
  } catch (err) {
    detailsError.value = err?.code === 'ECONNABORTED'
      ? 'Timed out loading details — counts above are still valid'
      : (err?.response?.data?.message || 'Failed to load details')
  } finally {
    loadingDetails.value = false
  }
}

onMounted(fetchSummary)
</script>

<style scoped>
.sdr-page.sdr-page {
  background: #fff !important;
  min-height: 100vh;
  padding: 10px 12px;
  font-size: 12px;
  color: #2a3142;
}

.sdr-shell {
  border: 1px solid #e3e7ef;
  border-radius: 10px;
  padding: 12px;
  background: #fff;
}

.sdr-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.sdr-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  flex-shrink: 0;
}

.sdr-title {
  font-size: 13px;
  font-weight: 650;
  color: #151a2b;
  line-height: 1.25;
  margin-bottom: 2px;
}

.sdr-hint {
  margin: 0;
  font-size: 11px;
  color: #8b93a7;
  line-height: 1.35;
}
.sdr-hint--block {
  margin-top: 12px;
  padding: 8px 10px;
  border: 1px dashed #e3e7ef;
  border-radius: 8px;
  background: #fafbfd;
}

.sdr-btn {
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
  flex-shrink: 0;
}
.sdr-btn--secondary {
  background: #f5f7fb;
  border-color: #d8dee9;
}
.sdr-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.sdr-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fdecec;
  color: #b3261e;
  border: 1px solid #f5c2c0;
  border-radius: 8px;
  padding: 8px 10px;
  margin-bottom: 10px;
  font-size: 12px;
}
.sdr-link {
  border: none;
  background: transparent;
  color: #b3261e;
  text-decoration: underline;
  cursor: pointer;
  margin-left: auto;
  font-size: 11px;
}

.sdr-loading,
.sdr-empty {
  padding: 12px 4px;
  color: #8b93a7;
  font-size: 12px;
}

.sdr-metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 14px;
}

.sdr-metric {
  border: 1px solid #e8ecf3;
  border-radius: 8px;
  padding: 6px 10px;
  min-width: 72px;
  background: #fafbfd;
}
.sdr-metric--warn { border-color: #f0cfcb; background: #fff8f7; }
.sdr-metric--stage { background: #fff; }
.sdr-metric__n {
  display: block;
  font-size: 14px;
  font-weight: 700;
  color: #151a2b;
  line-height: 1.2;
}
.sdr-metric--warn .sdr-metric__n { color: #b3261e; }
.sdr-metric__l {
  display: block;
  font-size: 10px;
  color: #8b93a7;
  margin-top: 1px;
}

.sdr-label {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 11px;
  font-weight: 700;
  color: #5b6478;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin: 14px 0 6px;
}
.sdr-label__meta {
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
  color: #8b93a7;
  font-size: 11px;
}

.sdr-table-wrap { overflow-x: auto; }

.sdr-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 640px;
}
.sdr-table--compact { min-width: 480px; }

.sdr-table th {
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  color: #8b93a7;
  text-transform: uppercase;
  padding: 5px 6px;
  border-bottom: 1px solid #e8ecf3;
  white-space: nowrap;
}

.sdr-table td {
  padding: 5px 6px;
  border-bottom: 1px solid #f1f3f7;
  font-size: 11px;
  color: #2a3142;
  vertical-align: middle;
  white-space: nowrap;
}

.sdr-mono { font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; }
.sdr-clip {
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sdr-vs { color: #c0c6d4; text-align: center; padding-left: 2px; padding-right: 2px; }
.sdr-dim { color: #8b93a7; font-size: 10px; }

.sdr-tag {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.sdr-tag--phone { background: #e8f1ff; color: #1d4ed8; }
.sdr-tag--email { background: #f3e8ff; color: #7c3aed; }
.sdr-tag--both { background: #e6f7ee; color: #0f9d58; }

.sdr-group {
  border: 1px solid #e8ecf3;
  border-radius: 8px;
  padding: 8px;
  margin-bottom: 8px;
}
.sdr-group--warn { border-color: #f0cfcb; background: #fffaf9; }
.sdr-group__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 11px;
  color: #5b6478;
}

</style>
