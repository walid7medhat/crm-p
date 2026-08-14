<template>
  <div v-if="visible" class="rec-create-job-overlay" @click.self="$emit('close')">
    <div v-if="!confirming" class="rec-create-job-modal rec-schedule-modal">
      <div class="rec-create-job-modal__head">
        <h6>Schedule Interview</h6>
        <button type="button" class="rec-create-job-modal__close" @click="$emit('close')">
          <iconify-icon icon="lucide:x" />
        </button>
      </div>
      <div class="rec-create-job-modal__body">
        <section v-if="allowPickApplicant" class="rec-create-job-panel">
          <div class="rec-create-job-grid">
            <div class="rec-create-job-field">
              <label>Job Opening <em>*</em></label>
              <SearchableSelect
                v-model="form.job_id"
                :options="jobOptions"
                placeholder="Select Job Opening"
                :append-to-body="false"
                :clearable="false"
              />
            </div>
            <div class="rec-create-job-field">
              <label>Candidate <em>*</em></label>
              <SearchableSelect
                v-model="form.applicant_id"
                :options="applicantOptions"
                :placeholder="form.job_id ? 'Select Candidate' : 'Select a job first'"
                :append-to-body="false"
                :clearable="false"
                :disabled="!form.job_id"
              />
            </div>
          </div>
          <p v-if="form.job_id && !applicantOptions.length" class="rec-schedule-hint">
            No pending or shortlisted applicants for this job. Shortlist a candidate from View Applicants first.
          </p>
        </section>

        <section v-if="selectedApplicant" class="rec-create-job-panel rec-schedule-candidate">
          <div class="rec-schedule-candidate__top">
            <img :src="selectedApplicant.avatar" :alt="selectedApplicant.name" />
            <div>
              <strong>{{ selectedApplicant.name }}</strong>
              <p>{{ selectedApplicant.location }}</p>
            </div>
            <button v-if="!allowPickApplicant" type="button" class="rec-schedule-link" @click="$emit('view-details')">View Candidate Details ›</button>
          </div>
          <div class="rec-app-stats">
            <div><span>Applied at</span><strong>{{ appliedLabel }}</strong></div>
            <div><span>Availability Status</span><strong>{{ selectedApplicant.availabilityStatus }}</strong></div>
            <div><span>Hiring Status</span><strong>{{ selectedApplicant.hiringStatus }}</strong></div>
            <div><span>Interview Status</span><strong>{{ selectedApplicant.interviewStatus }}</strong></div>
          </div>
        </section>

        <section class="rec-create-job-panel">
          <div class="rec-create-job-grid">
            <div class="rec-create-job-field">
              <label>Interviewer <em>*</em></label>
              <SearchableSelect v-model="form.interviewer_id" :options="interviewerOptions" placeholder="Select Interviewer" :append-to-body="false" :clearable="false" />
            </div>
            <div class="rec-create-job-field">
              <label>Interview Date <em>*</em></label>
              <HrFancyDateField v-model="form.date" placeholder="dd/mm/yyyy" />
            </div>
            <div class="rec-create-job-field">
              <label>Branch <em>*</em></label>
              <SearchableSelect v-model="form.branch" :options="branchOptions" placeholder="Select Branch" :append-to-body="false" :clearable="false" />
            </div>
            <div class="rec-create-job-field">
              <label>Interview Type <em>*</em></label>
              <SearchableSelect v-model="form.type" :options="typeOptions" placeholder="Select Type" :append-to-body="false" :clearable="false" />
            </div>
            <div class="rec-create-job-field rec-create-job-field--full">
              <label>Link (If Online)</label>
              <div class="rec-link-field">
                <iconify-icon icon="lucide:link" />
                <input v-model="form.meeting_link" type="text" placeholder="Add link" />
              </div>
            </div>
            <div class="rec-create-job-field rec-create-job-field--full">
              <label>Comment</label>
              <textarea v-model="form.feedback" rows="3" placeholder="Add Comment" />
            </div>
          </div>
        </section>

        <section class="rec-create-job-panel">
          <div class="rec-slots-head">
            <h6>Choose Time Slots</h6>
            <span v-if="form.date">Interview Date : {{ dateLabel }}</span>
          </div>
          <div class="rec-slots">
            <div v-for="(slot, idx) in form.slots" :key="idx" class="rec-slot-card">
              <label>From<input v-model="slot.from" type="time" /></label>
              <label>To<input v-model="slot.to" type="time" /></label>
            </div>
            <button type="button" class="rec-slot-add" @click="addSlot">
              <iconify-icon icon="lucide:clock" />
              Add More Time Slots
            </button>
          </div>
        </section>
      </div>
      <div class="rec-create-job-modal__footer">
        <button type="button" class="rec-create-job-clear" @click="resetForm">Clear</button>
        <button type="button" class="rec-create-job-confirm" @click="goConfirm">Confirm</button>
      </div>
    </div>

    <div v-else class="rec-confirm-card">
      <button type="button" class="rec-create-job-modal__close rec-confirm-close" @click="confirming = false">
        <iconify-icon icon="lucide:x" />
      </button>
      <div class="rec-confirm-icon"><iconify-icon icon="lucide:calendar-clock" /></div>
      <h6>Confirm Interview Schedule</h6>
      <p>Please confirm the interview details below.</p>
      <dl class="rec-confirm-list">
        <div><dt>Interview Date</dt><dd>{{ dateLabel }}</dd></div>
        <div><dt>Candidate Name</dt><dd>{{ selectedApplicant?.name }}</dd></div>
        <div v-for="(slot, idx) in filledSlots" :key="idx">
          <dt>Interview Time (Slot {{ idx + 1 }})</dt>
          <dd>{{ formatSlot(slot) }}</dd>
        </div>
        <div><dt>Interviewer</dt><dd>{{ interviewerLabel }}</dd></div>
        <div><dt>Interview Type</dt><dd>{{ typeLabel }}</dd></div>
        <div><dt>Location</dt><dd>{{ form.branch || '—' }}</dd></div>
      </dl>
      <button type="button" class="rec-create-job-confirm rec-confirm-full" :disabled="saving" @click="submit">
        {{ saving ? 'Scheduling…' : 'Confirm' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import Swal from 'sweetalert2'
import SearchableSelect from '@/components/ui/SearchableSelect.vue'
import HrFancyDateField from '@/components/hr/shared/HrFancyDateField.vue'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'

const props = defineProps({
  visible: Boolean,
  applicant: { type: Object, default: null },
  jobs: { type: Array, default: () => [] },
  applicants: { type: Array, default: () => [] },
  interviewers: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  allowPickApplicant: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'confirm', 'view-details'])

const confirming = ref(false)
const typeOptions = [
  { value: 'in_person', label: 'Offline' },
  { value: 'online', label: 'Online' },
  { value: 'phone', label: 'Phone' },
]
const form = reactive(emptyForm())

watch(() => props.visible, (value) => {
  if (value) {
    confirming.value = false
    Object.assign(form, emptyForm())
    if (props.applicant?.id) form.applicant_id = props.applicant.id
    if (props.applicant?.jobId) form.job_id = props.applicant.jobId
    form.branch = props.applicant?.location && props.applicant.location !== '—' ? props.applicant.location : ''
  }
})

watch(() => form.job_id, () => {
  if (!props.allowPickApplicant) return
  const stillValid = eligibleApplicants.value.some((item) => String(item.id) === String(form.applicant_id))
  if (stillValid) return
  form.applicant_id = eligibleApplicants.value.length === 1 ? eligibleApplicants.value[0].id : ''
})

watch(() => form.applicant_id, () => {
  const person = selectedApplicant.value
  if (!person) return
  if (!form.job_id && person.jobId) form.job_id = person.jobId
  if (person.location && person.location !== '—') form.branch = person.location
})

const selectedApplicant = computed(() => {
  if (props.applicant) return props.applicant
  return props.applicants.find((item) => String(item.id) === String(form.applicant_id)) || null
})
const eligibleApplicants = computed(() => {
  const list = props.applicants.filter((item) => ['pending', 'shortlisted'].includes(item.status))
  if (!form.job_id) return list
  return list.filter((item) => String(item.jobId) === String(form.job_id))
})
const jobOptions = computed(() =>
  props.jobs.map((item) => ({ value: item.id, label: item.title })),
)
const applicantOptions = computed(() =>
  eligibleApplicants.value.map((item) => ({
    value: item.id,
    label: item.appliedPosition && item.appliedPosition !== '—' ? `${item.name} — ${item.appliedPosition}` : item.name,
  })),
)
const interviewerOptions = computed(() =>
  props.interviewers.map((item) => ({ value: item.id, label: item.name || item.email || `#${item.id}` })),
)
const branchOptions = computed(() => {
  const fromProps = props.branches.map((item) => ({ value: item.name, label: item.name }))
  if (fromProps.length) return fromProps
  return form.branch ? [{ value: form.branch, label: form.branch }] : []
})
const appliedLabel = computed(() => formatLongDate(selectedApplicant.value?.applicationDate))
const dateLabel = computed(() => formatAttendanceDate(form.date))
const interviewerLabel = computed(() => interviewerOptions.value.find((item) => String(item.value) === String(form.interviewer_id))?.label || '—')
const typeLabel = computed(() => typeOptions.find((item) => item.value === form.type)?.label || '—')
const filledSlots = computed(() => form.slots.filter((slot) => slot.from && slot.to))

function emptyForm() {
  return {
    job_id: '',
    applicant_id: '',
    interviewer_id: '',
    date: '',
    branch: '',
    type: 'in_person',
    meeting_link: '',
    feedback: '',
    slots: [{ from: '', to: '' }],
  }
}
function resetForm() {
  Object.assign(form, emptyForm())
}
function addSlot() {
  form.slots.push({ from: '', to: '' })
}
function formatLongDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })
}
function formatSlot(slot) {
  return `${toDisplayTime(slot.from)} - ${toDisplayTime(slot.to)}`
}
function toDisplayTime(value) {
  if (!value) return '—'
  const [h, m] = String(value).split(':')
  const date = new Date()
  date.setHours(Number(h || 0), Number(m || 0), 0, 0)
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}
function goConfirm() {
  if (!selectedApplicant.value) {
    Swal.fire({ icon: 'warning', title: 'Please select a job and candidate' })
    return
  }
  if (!form.interviewer_id || !form.date || !form.type) {
    Swal.fire({ icon: 'warning', title: 'Please fill required fields' })
    return
  }
  if (!filledSlots.value.length) {
    Swal.fire({ icon: 'warning', title: 'Please add a time slot' })
    return
  }
  confirming.value = true
}
function submit() {
  const slot = filledSlots.value[0]
  emit('confirm', {
    applicant_id: selectedApplicant.value?.id,
    interviewer_id: form.interviewer_id,
    scheduled_at: `${form.date} ${slot.from}:00`,
    end_time: `${form.date} ${slot.to}:00`,
    type: form.type,
    location: form.branch || null,
    meeting_link: form.meeting_link || null,
    feedback: form.feedback || null,
  })
}
</script>
