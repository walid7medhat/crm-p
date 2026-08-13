<template>
  <article class="rec-job-card" @click="$emit('select', job)">
    <div class="rec-job-card__head">
      <div class="rec-job-card__title-wrap">
        <p class="rec-job-card__title">{{ job.title }}</p>
        <span class="rec-job-card__badge" :class="`rec-job-card__badge--${job.status}`">{{ job.statusLabel }}</span>
      </div>
      <div class="rec-job-card__menu-wrap" @click.stop>
        <button type="button" class="rec-job-card__menu" @click.stop="showMenu = !showMenu">
          <iconify-icon icon="lucide:more-vertical" />
        </button>
        <div v-if="showMenu" class="rec-job-card__dropdown" @click.stop>
          <button type="button" @click.stop="onEdit">
            <iconify-icon icon="lucide:pencil" /> Edit
          </button>
          <button type="button" class="danger" @click.stop="onDelete">
            <iconify-icon icon="lucide:trash-2" /> Delete
          </button>
        </div>
      </div>
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
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  job: { type: Object, required: true },
})

const emit = defineEmits(['select', 'pipeline', 'edit', 'delete'])

const showMenu = ref(false)

function onEdit() {
  showMenu.value = false
  emit('edit', props.job)
}

function onDelete() {
  showMenu.value = false
  emit('delete', props.job)
}

function closeOnOutsideClick() {
  showMenu.value = false
}

onMounted(() => {
  document.addEventListener('click', closeOnOutsideClick)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closeOnOutsideClick)
})

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>