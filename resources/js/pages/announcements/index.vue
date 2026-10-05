<template>
  <div class="announcement-board">
    <header class="announcement-board__hero">
      <div class="announcement-board__hero-copy">
        <p class="announcement-board__eyebrow">
          <iconify-icon icon="lucide:megaphone" />
          Announcements
        </p>
        <p class="announcement-board__title">Latest updates</p>
      </div>
      <div class="announcement-board__hero-side">
        <p v-if="!loading && !error" class="announcement-board__count">{{ countLabel }}</p>
        <label v-if="announcements.length" class="announcement-board__search">
          <iconify-icon icon="lucide:search" />
          <input v-model.trim="query" type="search" placeholder="Search announcements" />
        </label>
      </div>
    </header>

    <p v-if="error" class="announcement-board__state announcement-board__state--error">
      {{ error }}
      <button type="button" @click="load">Try again</button>
    </p>

    <div v-else-if="loading" class="announcement-board__grid" aria-hidden="true">
      <div v-for="n in 6" :key="n" class="announcement-card announcement-card--skeleton">
        <span class="announcement-card__media" />
        <span class="announcement-card__meta" />
      </div>
    </div>

    <div v-else-if="!announcements.length" class="announcement-board__empty">
      <span class="announcement-board__empty-icon">
        <iconify-icon icon="lucide:megaphone" />
      </span>
      <h2>No announcements yet</h2>
      <p>New campaigns will show up here as image cards.</p>
    </div>

    <div v-else-if="!visible.length" class="announcement-board__empty">
      <span class="announcement-board__empty-icon">
        <iconify-icon icon="lucide:search" />
      </span>
      <h2>No matching announcements</h2>
      <p>Try a different name.</p>
    </div>

    <div v-else class="announcement-board__grid">
      <button
        v-for="(item, index) in visible"
        :key="item.id"
        type="button"
        class="announcement-card"
        @click="openViewer(index)"
      >
        <span class="announcement-card__media">
          <img
            :src="item.desktop_image_url"
            :alt="item.title"
            loading="lazy"
          />
          <span class="announcement-card__view">
            <iconify-icon icon="lucide:expand" />
            View
          </span>
        </span>
        <span class="announcement-card__body">
          <span class="announcement-card__title">{{ item.title }}</span>
          <span v-if="item.dateLabel" class="announcement-card__date">{{ item.dateLabel }}</span>
        </span>
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="active"
        class="announcement-viewer"
        role="dialog"
        aria-modal="true"
        :aria-label="active.title"
        @click.self="closeViewer"
      >
        <button type="button" class="announcement-viewer__close" aria-label="Close" @click="closeViewer">
          <iconify-icon icon="lucide:x" />
        </button>
        <button
          v-if="visible.length > 1"
          type="button"
          class="announcement-viewer__nav announcement-viewer__nav--prev"
          aria-label="Previous announcement"
          @click="step(-1)"
        >
          <iconify-icon icon="lucide:chevron-left" />
        </button>
        <figure class="announcement-viewer__frame">
          <img :src="viewerSrc" :alt="active.title" />
          <figcaption>
            <strong>{{ active.title }}</strong>
            <span v-if="active.dateLabel">{{ active.dateLabel }}</span>
          </figcaption>
        </figure>
        <button
          v-if="visible.length > 1"
          type="button"
          class="announcement-viewer__nav announcement-viewer__nav--next"
          aria-label="Next announcement"
          @click="step(1)"
        >
          <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { fetchAnnouncementGallery } from '@/services/systemCampaignsApi.js'
import { getApiErrorMessage } from '@/plugins/axios'

const loading = ref(true)
const error = ref('')
const query = ref('')
const announcements = ref([])
const openIndex = ref(null)
const isNarrow = ref(false)

const visible = computed(() => {
  const term = query.value.toLowerCase()
  if (!term) return announcements.value
  return announcements.value.filter((item) => item.title.toLowerCase().includes(term))
})

const active = computed(() => {
  if (openIndex.value == null) return null
  return visible.value[openIndex.value] || null
})

const viewerSrc = computed(() => {
  const item = active.value
  if (!item) return ''
  if (isNarrow.value && item.mobile_image_url) return item.mobile_image_url
  return item.desktop_image_url || item.mobile_image_url || ''
})

const countLabel = computed(() => {
  const total = announcements.value.length
  if (total === 1) return '1 announcement'
  return `${total} announcements`
})

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function syncViewport() {
  isNarrow.value = window.matchMedia('(max-width: 768px)').matches
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetchAnnouncementGallery()
    const rows = Array.isArray(response?.data?.data) ? response.data.data : []
    announcements.value = rows.map((item) => ({
      ...item,
      title: item.title || 'Announcement',
      dateLabel: formatDate(item.created_at),
    }))
  } catch (err) {
    announcements.value = []
    error.value = getApiErrorMessage(err, 'Could not load announcements')
  } finally {
    loading.value = false
  }
}

function openViewer(index) {
  openIndex.value = index
}

function closeViewer() {
  openIndex.value = null
}

function step(delta) {
  const count = visible.value.length
  if (!count || openIndex.value == null) return
  openIndex.value = (openIndex.value + delta + count) % count
}

function onKeydown(event) {
  if (openIndex.value == null) return
  if (event.key === 'Escape') closeViewer()
  if (event.key === 'ArrowRight') step(1)
  if (event.key === 'ArrowLeft') step(-1)
}

watch(active, (item) => {
  document.body.style.overflow = item ? 'hidden' : ''
})

watch(visible, (items) => {
  if (openIndex.value != null && !items[openIndex.value]) closeViewer()
})

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', syncViewport)
  window.addEventListener('keydown', onKeydown)
  load()
})

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport)
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<style scoped>
.announcement-board {
  width: min(1180px, 100%);
  margin: 0 auto;
  padding: 1.25rem 1.25rem 2.5rem;
  box-sizing: border-box;
}

.announcement-board__hero {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
  padding: 22px 24px;
  border-radius: 22px;
  background:
    radial-gradient(circle at top right, rgba(115, 62, 135, 0.18), transparent 42%),
    linear-gradient(180deg, #ffffff 0%, #faf8fc 100%);
  border: 1px solid #ece7f3;
  box-shadow: 0 12px 32px rgba(11, 7, 54, 0.05);
}

.announcement-board__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 8px;
  color: #733e87;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.announcement-board__title {
  margin: 0;
  color: #0b0736;
  font-size: 15px;
  line-height: 1.3;
  font-weight: 700;
}

.announcement-board__hero-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
  min-width: min(280px, 100%);
}

.announcement-board__count {
  margin: 0;
  color: #733e87;
  font-size: 13px;
  font-weight: 700;
}

.announcement-board__search {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 12px;
  border-radius: 12px;
  border: 1px solid #e6e0ee;
  background: #fff;
  color: #8b8498;
}

.announcement-board__search input {
  width: 100%;
  border: 0;
  outline: none;
  background: transparent;
  color: #1a1528;
  font-size: 16px;
}

.announcement-board__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 18px;
}

.announcement-card {
  display: flex;
  flex-direction: column;
  padding: 0;
  overflow: hidden;
  text-align: left;
  border: 1px solid #ece7f3;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 10px 28px rgba(11, 7, 54, 0.05);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.announcement-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 36px rgba(115, 62, 135, 0.14);
}

.announcement-card__media {
  position: relative;
  display: block;
  aspect-ratio: 3 / 2;
  background: #f4f0f8;
}

.announcement-card__media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.announcement-card__view {
  position: absolute;
  right: 12px;
  bottom: 12px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 10px;
  border-radius: 999px;
  background: rgba(11, 7, 54, 0.78);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.announcement-card:hover .announcement-card__view,
.announcement-card:focus-visible .announcement-card__view {
  opacity: 1;
  transform: none;
}

.announcement-card__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 14px 16px;
}

.announcement-card__title {
  color: #0b0736;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.35;
}

.announcement-card__date {
  color: #8b8498;
  font-size: 12px;
}

.announcement-card--skeleton {
  pointer-events: none;
}

.announcement-card--skeleton .announcement-card__media,
.announcement-card--skeleton .announcement-card__meta {
  display: block;
  background: linear-gradient(90deg, #f3eef6 0%, #faf8fc 50%, #f3eef6 100%);
  background-size: 200% 100%;
  animation: announcement-shimmer 1.2s ease-in-out infinite;
}

.announcement-card--skeleton .announcement-card__meta {
  height: 52px;
}

.announcement-board__empty,
.announcement-board__state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 280px;
  padding: 32px 20px;
  text-align: center;
  border-radius: 22px;
  border: 1px dashed #e0d8ea;
  background: #fff;
  color: #6b6578;
}

.announcement-board__empty h2,
.announcement-board__state {
  margin: 0;
  color: #0b0736;
  font-size: 18px;
}

.announcement-board__empty p {
  margin: 0;
  font-size: 14px;
}

.announcement-board__empty-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  margin-bottom: 4px;
  border-radius: 16px;
  background: #f6f0fa;
  color: #733e87;
  font-size: 24px;
}

.announcement-board__state--error button {
  margin-top: 8px;
  border: 0;
  border-radius: 999px;
  padding: 8px 14px;
  background: #733e87;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.announcement-viewer {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding:
    calc(20px + env(safe-area-inset-top, 0px))
    20px
    calc(20px + env(safe-area-inset-bottom, 0px));
  background:
    radial-gradient(ellipse at center, rgba(115, 62, 135, 0.28), transparent 58%),
    rgba(8, 6, 24, 0.78);
  backdrop-filter: blur(8px);
}

.announcement-viewer__frame {
  margin: 0;
  width: min(960px, calc(100vw - 140px));
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.announcement-viewer__frame img {
  width: 100%;
  max-height: calc(100vh - 140px);
  object-fit: contain;
  border-radius: 16px;
  background: #120e22;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.announcement-viewer__frame figcaption {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  color: #fff;
}

.announcement-viewer__frame strong {
  font-size: 16px;
}

.announcement-viewer__frame span {
  color: rgba(255, 255, 255, 0.72);
  font-size: 13px;
}

.announcement-viewer__close,
.announcement-viewer__nav {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  color: #fff;
  font-size: 20px;
  cursor: pointer;
}

.announcement-viewer__close {
  position: absolute;
  top: calc(16px + env(safe-area-inset-top, 0px));
  right: 16px;
}

.announcement-viewer__nav:hover,
.announcement-viewer__close:hover {
  background: rgba(255, 255, 255, 0.24);
}

@keyframes announcement-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

@media (max-width: 720px) {
  .announcement-board {
    padding: 12px 12px 88px;
  }

  .announcement-board__hero {
    flex-direction: column;
    align-items: stretch;
    padding: 18px;
  }

  .announcement-board__hero-side {
    align-items: stretch;
  }

  .announcement-card__view {
    opacity: 1;
    transform: none;
  }

  .announcement-viewer {
    flex-direction: column;
    gap: 10px;
  }

  .announcement-viewer__frame {
    width: min(100%, calc(100vw - 32px));
  }

  .announcement-viewer__nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
  }

  .announcement-viewer__nav--prev { left: 8px; }
  .announcement-viewer__nav--next { right: 8px; }
}
</style>
