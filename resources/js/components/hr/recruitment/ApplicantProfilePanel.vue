<template>
  <aside class="rec-profile" :class="{ 'rec-profile--sheet': sheet }">
    <div v-if="loading" class="rec-profile__loading">
      <div v-for="n in 4" :key="n" class="emp-skeleton" />
    </div>

    <template v-else-if="applicant">
      <header class="rec-profile__head">
        <button v-if="sheet" type="button" class="rec-profile__close" @click="$emit('close')">
          <iconify-icon icon="lucide:x" />
        </button>
        <img :src="applicant.avatar" :alt="applicant.name" />
        <div>
          <h2>{{ applicant.name }}</h2>
          <p>{{ applicant.appliedPosition }}</p>
          <span class="rec-profile__status">{{ applicant.statusLabel }}</span>
        </div>
      </header>

      <div class="rec-profile__actions">
        <button type="button" @click="$emit('schedule', applicant)">
          <iconify-icon icon="lucide:calendar-clock" /> Schedule Interview
        </button>
        <button type="button" @click="$emit('move', applicant)">
          <iconify-icon icon="lucide:arrow-right-left" /> Move Candidate
        </button>
        <button type="button" class="danger" @click="$emit('reject', applicant)">
          <iconify-icon icon="lucide:user-x" /> Reject
        </button>
        <button type="button" class="success" @click="$emit('hire', applicant)">
          <iconify-icon icon="lucide:user-check" /> Hire
        </button>
        <button v-if="applicant.resumeUrl" type="button" @click="$emit('download', applicant)">
          <iconify-icon icon="lucide:download" /> Resume
        </button>
      </div>

      <section class="rec-profile__section">
        <h3>Contact</h3>
        <div class="rec-profile__grid">
          <p><span>Email</span><strong>{{ applicant.email }}</strong></p>
          <p><span>Phone</span><strong>{{ applicant.phone }}</strong></p>
          <p><span>Nationality</span><strong>{{ applicant.nationality }}</strong></p>
          <p><span>Applied</span><strong>{{ formatDate(applicant.applicationDate) }}</strong></p>
        </div>
      </section>

      <section v-if="applicant.resumeUrl" class="rec-profile__section">
        <h3>Resume Preview</h3>
        <div class="rec-profile__resume">
          <iframe v-if="isPdf(applicant.resumeUrl)" :src="applicant.resumeUrl" title="Resume preview" />
          <a v-else :href="applicant.resumeUrl" target="_blank" rel="noopener">Open resume file</a>
        </div>
      </section>

      <section class="rec-profile__section">
        <h3>Work Experience</h3>
        <div v-if="experienceItems.length" class="rec-profile__list">
          <div v-for="(item, idx) in experienceItems" :key="`exp-${idx}`" class="rec-profile__list-item">
            <strong>{{ item.title || item.role || 'Experience' }}</strong>
            <p>{{ item.company || item.organization || '' }}</p>
            <small>{{ item.duration || item.period || '' }}</small>
          </div>
        </div>
        <p v-else class="rec-profile__empty-text">
          {{ applicant.raw?.total_experience_years ? `${applicant.raw.total_experience_years} years total experience` : 'No work history provided.' }}
        </p>
      </section>

      <section class="rec-profile__section">
        <h3>Education</h3>
        <div v-if="educationItems.length" class="rec-profile__list">
          <div v-for="(item, idx) in educationItems" :key="`edu-${idx}`" class="rec-profile__list-item">
            <strong>{{ item.degree || item.qualification || 'Education' }}</strong>
            <p>{{ item.institution || item.school || '' }}</p>
            <small>{{ item.year || item.period || '' }}</small>
          </div>
        </div>
        <p v-else class="rec-profile__empty-text">No education details provided.</p>
      </section>

      <section class="rec-profile__section">
        <h3>Skills</h3>
        <div v-if="skills.length" class="rec-profile__skills">
          <span v-for="(skill, idx) in skills" :key="`skill-${idx}`">{{ skill }}</span>
        </div>
        <p v-else class="rec-profile__empty-text">No skills listed.</p>
      </section>

      <section class="rec-profile__section">
        <h3>Notes</h3>
        <p class="rec-profile__notes">{{ applicant.notes || 'No notes yet.' }}</p>
      </section>

      <section class="rec-profile__section">
        <h3>Interview History</h3>
        <div v-if="applicant.interviews?.length" class="rec-profile__list">
          <div v-for="interview in applicant.interviews" :key="interview.id" class="rec-profile__list-item">
            <strong>{{ formatInterviewType(interview.type) }}</strong>
            <p>{{ formatDateTime(interview.scheduled_at) }}</p>
            <small>{{ interview.status }} · {{ interview.interviewer?.name || 'Interviewer TBD' }}</small>
          </div>
        </div>
        <p v-else class="rec-profile__empty-text">No interviews scheduled yet.</p>
      </section>
    </template>
  </aside>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  applicant: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  sheet: { type: Boolean, default: false },
})

defineEmits(['close', 'schedule', 'move', 'reject', 'hire', 'download'])

const answers = computed(() => props.applicant?.answers || props.applicant?.raw?.answers || {})

const experienceItems = computed(() => {
  const raw = answers.value.experience || answers.value.work_experience || answers.value.experiences
  return Array.isArray(raw) ? raw : []
})

const educationItems = computed(() => {
  const raw = answers.value.education || answers.value.educations
  return Array.isArray(raw) ? raw : []
})

const skills = computed(() => {
  const raw = answers.value.skills
  if (Array.isArray(raw)) return raw
  if (typeof raw === 'string') return raw.split(',').map((s) => s.trim()).filter(Boolean)
  return []
})

function isPdf(url) {
  return String(url || '').toLowerCase().includes('.pdf')
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatInterviewType(type) {
  const map = { online: 'Online Interview', in_person: 'In-person Interview', phone: 'Phone Interview' }
  return map[type] || type || 'Interview'
}
</script>
