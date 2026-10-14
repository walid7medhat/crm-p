<template>
  <MkModal
    :model-value="modelValue"
    title="Daily report"
    subtitle="Team activity, finished work and overdue cards"
    icon="lucide:file-bar-chart"
    width="860px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="mk-rep__bar">
      <div class="mk-rep__date">
        <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Previous day" @click="shiftDay(-1)">
          <iconify-icon icon="lucide:chevron-left" />
        </button>
        <AdvancedDatePicker
          v-model="date"
          class="mk-date mk-rep__picker"
          date-only
          dob-layout
          :block-future-dates="true"
          placeholder="Report date"
        />
        <button type="button" class="mk-btn mk-btn--ghost mk-btn--sm mk-btn--icon" title="Next day" :disabled="date >= today" @click="shiftDay(1)">
          <iconify-icon icon="lucide:chevron-right" />
        </button>
        <button v-if="date !== today" type="button" class="mk-btn mk-btn--ghost mk-btn--sm" @click="date = today">Today</button>
      </div>
      <button type="button" class="mk-btn mk-btn--primary" :disabled="!report || downloading" @click="downloadPdf">
        <iconify-icon :icon="downloading ? 'lucide:loader-2' : 'lucide:download'" :class="{ 'mk-spin': downloading }" />
        {{ downloading ? 'Downloading…' : 'Download PDF' }}
      </button>
    </div>

    <div v-if="loading && !report" class="mk-empty"><iconify-icon icon="lucide:loader-2" class="mk-spin" /> Building report…</div>

    <div v-else-if="report" class="rep" :class="{ 'is-loading': loading }">
      <h3 class="rep__date">{{ report.date_label }}</h3>

      <div class="rep__totals">
        <div><b>{{ report.totals.created }}</b><span>Created</span></div>
        <div><b>{{ report.totals.finished }}</b><span>Finished</span></div>
        <div><b>{{ report.totals.posts_posted }}/{{ report.totals.posts_scheduled }}</b><span>Posted</span></div>
        <div><b>{{ report.team.length }}</b><span>Active members</span></div>
        <div :class="{ 'is-alert': report.totals.overdue }"><b>{{ report.totals.overdue }}</b><span>Overdue</span></div>
      </div>

      <section>
        <h4>Team activity <small>{{ report.team.length }} active</small></h4>
        <div v-if="report.team.length" class="rep__scroll">
          <table class="rep__table">
            <thead>
              <tr><th>Member</th><th>Created</th><th>Moved</th><th>Finished</th><th>Links</th><th>Comments</th><th>Posted</th><th>Total</th></tr>
            </thead>
            <tbody>
              <tr v-for="m in report.team" :key="m.name">
                <td class="rep__strong">{{ m.name }}</td>
                <td>{{ m.created }}</td>
                <td>{{ m.moved }}</td>
                <td class="rep__strong">{{ m.finished }}</td>
                <td>{{ m.links }}</td>
                <td>{{ m.comments }}</td>
                <td>{{ m.posted }}</td>
                <td class="rep__strong">{{ m.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="rep__none">No marketing activity on this day.</p>
      </section>

      <section>
        <h4>Finished <small>{{ report.finished.length }}</small></h4>
        <div v-if="report.finished.length" class="rep__scroll">
          <table class="rep__table">
            <thead><tr><th>Card</th><th>Board</th><th>People</th><th>Time</th><th>Link</th></tr></thead>
            <tbody>
              <tr v-for="(f, i) in report.finished" :key="i">
                <td class="rep__strong rep__wrap">{{ f.title }}</td>
                <td>{{ f.board }}</td>
                <td class="rep__wrap">{{ f.people }}</td>
                <td>{{ f.time }}</td>
                <td><span v-if="f.has_link">Yes</span><span v-else class="rep__late">Missing</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="rep__none">Nothing reached Done on this day.</p>
      </section>

      <section>
        <h4>Overdue <small>{{ report.totals.overdue }} open past due</small></h4>
        <div v-if="report.overdue.length" class="rep__scroll">
          <table class="rep__table">
            <thead><tr><th>Card</th><th>Board</th><th>Stage</th><th>Due</th><th>People</th></tr></thead>
            <tbody>
              <tr v-for="(o, i) in report.overdue" :key="i">
                <td class="rep__strong rep__wrap">{{ o.title }}</td>
                <td>{{ o.board }}</td>
                <td>{{ o.stage }}</td>
                <td class="rep__late">{{ o.due }}<br><small>{{ o.days_late }} day{{ o.days_late === 1 ? '' : 's' }} late</small></td>
                <td class="rep__wrap">{{ o.people }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="rep__none">Nothing overdue.</p>
      </section>
    </div>
  </MkModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '@/plugins/axios'
import MkModal from './shared/MkModal.vue'
import AdvancedDatePicker from '@/components/shared/AdvancedDatePicker.vue'
import { isoDate, apiError } from '@/config/marketingConfig.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const today = isoDate(new Date())
const date = ref(today)
const report = ref(null)
const loading = ref(false)
const downloading = ref(false)

let seq = 0
async function load() {
  const mine = ++seq
  loading.value = true
  try {
    const { data } = await api.get('/marketing/reports/daily', { params: { date: date.value } })
    if (mine === seq) report.value = data
  } catch (err) {
    if (mine === seq) Swal.fire({ icon: 'error', title: 'Could not build report', text: apiError(err) })
  } finally {
    if (mine === seq) loading.value = false
  }
}

function shiftDay(n) {
  const d = new Date(`${date.value}T00:00:00`)
  d.setDate(d.getDate() + n)
  const next = isoDate(d)
  if (next <= today) date.value = next
}

watch(() => props.modelValue, (open) => {
  if (open) load()
})
watch(date, () => {
  if (props.modelValue && date.value) load()
})

/** The PDF is rendered on the server (real text, A4), so it looks the same everywhere. */
async function downloadPdf() {
  downloading.value = true
  try {
    const res = await api.get('/marketing/reports/daily/pdf', { params: { date: date.value }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `marketing-daily-report-${date.value}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (err) {
    // Errors come back as a Blob when responseType is 'blob' — read the message out of it.
    let message = 'Please try again.'
    try {
      const text = await err?.response?.data?.text?.()
      message = JSON.parse(text)?.message || message
    } catch {
      /* keep default */
    }
    Swal.fire({ icon: 'error', title: 'Could not download PDF', text: message })
  } finally {
    downloading.value = false
  }
}
</script>

<style scoped>
.mk-rep__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 16px; }
.mk-rep__date { display: flex; align-items: center; gap: 6px; }
.mk-rep__picker { width: 220px; }

.rep { transition: opacity 0.2s; }
.rep.is-loading { opacity: 0.55; }
.rep__date { margin: 0 0 12px; font-size: 16px !important; font-weight: 700 !important; color: var(--text-primary-light); }

.rep__totals { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 18px; }
.rep__totals > div {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--neutral-50);
  border: 1px solid var(--border-color);
}
.rep__totals b { display: block; font-size: 20px !important; font-weight: 800 !important; line-height: 1.15; color: var(--text-primary-light); }
.rep__totals span { font-size: 11px !important; color: var(--text-secondary-light); text-transform: uppercase; letter-spacing: 0.04em; }
.rep__totals .is-alert { background: #fdf0f0; border-color: #f3c9c9; }
.rep__totals .is-alert b { color: #b42318; }

.rep section { margin-bottom: 18px; }
.rep h4 {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin: 0 0 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--border-color);
  font-size: 13.5px !important;
  font-weight: 700 !important;
  color: var(--color-primary);
}
.rep h4 small { font-size: 11.5px !important; font-weight: 500 !important; color: var(--text-secondary-light); }

.rep__scroll { overflow-x: auto; }
.rep__table { width: 100%; border-collapse: collapse; font-size: 13px !important; }
.rep__table th {
  padding: 7px 8px;
  text-align: left;
  font-size: 10.5px !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary-light);
  background: rgba(var(--color-primary-rgb), 0.06);
  white-space: nowrap;
}
.rep__table td { padding: 8px; border-bottom: 1px solid var(--border-color); color: var(--text-primary-light); vertical-align: top; white-space: nowrap; }
.rep__table td.rep__wrap { white-space: normal; min-width: 160px; }
.rep__table tbody tr:hover td { background: var(--neutral-50); }
.rep__strong { font-weight: 700 !important; }
.rep__late { color: #b42318; }
.rep__late small { color: var(--text-secondary-light); }
.rep__none { margin: 0; padding: 10px 12px; border-radius: 10px; background: var(--neutral-50); font-size: 13px !important; color: var(--text-secondary-light); }

@media (max-width: 640px) {
  .rep__totals { grid-template-columns: repeat(3, 1fr); }
}
</style>
