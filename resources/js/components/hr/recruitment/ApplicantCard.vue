<template>
  <article
    class="rec-applicant-card"
    :class="{ 'is-active': active, 'is-swiped': swipeOpen }"
    @click="$emit('select', applicant)"
    @touchstart.passive="onTouchStart"
    @touchmove.passive="onTouchMove"
    @touchend="onTouchEnd"
  >
    <div class="rec-applicant-card__main">
      <img :src="applicant.avatar" :alt="applicant.name" class="rec-applicant-card__avatar" loading="lazy" />
      <div class="rec-applicant-card__body">
        <div class="rec-applicant-card__head">
          <p class="rec-applicant-card__name">{{ applicant.name }}</p>
          <span class="rec-applicant-card__status">{{ applicant.statusLabel }}</span>
        </div>
        <p class="rec-applicant-card__role">{{ applicant.appliedPosition }}</p>
        <div class="rec-applicant-card__meta">
          <span>{{ applicant.experienceLevel }}</span>
          <span>{{ formatDate(applicant.applicationDate) }}</span>
        </div>
      </div>
    </div>

    <div class="rec-applicant-card__swipe" v-if="!isFinalStatus" @click.stop>
      <button type="button" class="rec-swipe-btn rec-swipe-btn--move" @click="$emit('move', applicant)">
        <iconify-icon icon="lucide:arrow-right-left" />
      </button>
      <button type="button" class="rec-swipe-btn rec-swipe-btn--reject" @click="$emit('reject', applicant)">
        <iconify-icon icon="lucide:x" />
      </button>
      <button type="button" class="rec-swipe-btn rec-swipe-btn--hire" @click="$emit('hire', applicant)">
        <iconify-icon icon="lucide:check" />
      </button>
    </div>
  </article>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  applicant: { type: Object, required: true },
  active: { type: Boolean, default: false },
})

defineEmits(['select', 'move', 'reject', 'hire'])

const FINAL_STATUSES = ['hired', 'rejected', 'withdrawn']

const isFinalStatus = computed(() => {
  const status = String(props.applicant?.status || props.applicant?.raw?.status || '').toLowerCase()
  return FINAL_STATUSES.includes(status)
})

const swipeOpen = ref(false)
let startX = 0

function onTouchStart(e) {
  startX = e.touches[0].clientX
}

function onTouchMove(e) {
  const delta = startX - e.touches[0].clientX
  swipeOpen.value = delta > 40
}

function onTouchEnd() {
  if (!swipeOpen.value) return
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}
</script>
