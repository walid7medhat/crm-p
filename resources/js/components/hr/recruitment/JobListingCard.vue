<template>
  <article class="rec-job-card" @click="$emit('select', job)">
    <div class="rec-job-card__head">
      <div class="rec-job-card__title-wrap">
        <p class="rec-job-card__title">{{ job.title }}</p>
        <span class="rec-job-card__badge" :class="`rec-job-card__badge--${job.status}`">{{ job.statusLabel }}</span>
      </div>
      <button type="button" class="rec-job-card__menu" @click.stop="$emit('menu', job)">
        <iconify-icon icon="lucide:more-vertical" />
      </button>
    </div>

    <div class="rec-job-card__meta">
      <span><iconify-icon icon="lucide:building-2" /> {{ job.department }}</span>
      <span><iconify-icon icon="lucide:map-pin" /> {{ job.location }}</span>
      <span><iconify-icon icon="lucide:briefcase" /> {{ job.employmentType }}</span>
    </div>

    <div class="rec-job-card__foot">
      <div class="rec-job-card__stat">
        <strong>{{ job.applicantsCount }}</strong>
        <small>Applicants</small>
      </div>
      <div class="rec-job-card__stat">
        <strong>{{ formatDate(job.postedDate) }}</strong>
        <small>Posted</small>
      </div>
      <button type="button" class="rec-job-card__cta" @click.stop="$emit('pipeline', job)">
        Pipeline
        <iconify-icon icon="lucide:arrow-right" />
      </button>
    </div>
  </article>
</template>

<script setup>
defineProps({
  job: { type: Object, required: true },
})

defineEmits(['select', 'pipeline', 'menu'])

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
