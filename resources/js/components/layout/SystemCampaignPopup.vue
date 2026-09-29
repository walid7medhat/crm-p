<template>
  <Teleport to="body">
    <Transition name="system-campaign">
      <div
        v-if="rendered && display"
        class="system-campaign-overlay"
        @touchmove.prevent
      >
        <div
          class="system-campaign-popup"
          role="dialog"
          aria-modal="true"
          :aria-label="display.title || 'Announcement'"
        >
          <button
            ref="closeButton"
            type="button"
            class="system-campaign-popup__close"
            aria-label="Close announcement"
            @click="closePopup"
          >
            <iconify-icon icon="lucide:x" />
          </button>
          <picture class="system-campaign-popup__picture">
            <source media="(max-width: 768px)" :srcset="display.mobile_image_url" />
            <img
              class="system-campaign-popup__image"
              :src="display.desktop_image_url"
              :alt="display.title || 'Announcement'"
            />
          </picture>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import api, { resolveAuthToken } from '@/plugins/axios'
import { closeSystemCampaignPreview, useSystemCampaignPreview } from '@/composables/useSystemCampaignPreview.js'

const { previewCampaign } = useSystemCampaignPreview()

const queue = ref([])
const current = ref(null)
const closeButton = ref(null)
const acknowledging = new Map()
const testedIds = new Set()
let previousOverflow = ''
let scrollLocked = false

const display = computed(() => previewCampaign.value || current.value)
const rendered = computed(() => !!display.value)

function lockScroll() {
  if (scrollLocked) return
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  document.body.classList.add('system-campaign-open')
  scrollLocked = true
}

function unlockScroll() {
  if (!scrollLocked) return
  document.body.style.overflow = previousOverflow
  document.body.classList.remove('system-campaign-open')
  scrollLocked = false
}

async function loadDue() {
  if (!resolveAuthToken()) return
  if (previewCampaign.value) return
  if (!current.value && queue.value.length) {
    current.value = queue.value.shift() || null
    return
  }
  if (current.value) return
  try {
    const response = await api.get('/system-campaigns/due')
    const items = response?.data?.data
    const list = (Array.isArray(items) ? items : []).filter((item) => !testedIds.has(item.id))
    if (!list.length) return
    current.value = list[0]
    queue.value = list.slice(1)
  } catch (error) {
    console.warn('Unable to load announcements', error)
  }
}

function acknowledge(campaign) {
  if (!campaign?.id || campaign.preview || acknowledging.has(campaign.id)) return
  const request = api.post(`/system-campaigns/${campaign.id}/shown`).catch((error) => {
    acknowledging.delete(campaign.id)
    console.warn('Unable to record announcement', error)
  })
  acknowledging.set(campaign.id, request)
}

async function closePopup() {
  if (previewCampaign.value) {
    const testedId = previewCampaign.value.id
    if (testedId) testedIds.add(testedId)
    closeSystemCampaignPreview()
    queue.value = queue.value.filter((item) => item.id !== testedId)
    if (current.value?.id === testedId) {
      current.value = queue.value.shift() || null
    }
    return
  }

  const campaign = current.value
  if (!campaign) return

  const pending = acknowledging.get(campaign.id)
  if (pending) {
    try {
      await pending
    } catch {
      /* acknowledge() already logged the failure */
    }
  }

  try {
    await api.post(`/system-campaigns/${campaign.id}/dismiss`)
  } catch (error) {
    console.warn('Unable to dismiss announcement', error)
  }

  current.value = queue.value.shift() || null
}

function onVisible() {
  if (document.visibilityState === 'visible') {
    loadDue()
  }
}

watch(rendered, async (visible) => {
  if (visible) {
    lockScroll()
    await nextTick()
    closeButton.value?.focus()
    return
  }
  unlockScroll()
})

watch(current, (campaign) => {
  if (campaign && !previewCampaign.value) {
    acknowledge(campaign)
  }
})

watch(previewCampaign, (preview, previous) => {
  if (!previous || preview) return
  if (current.value) {
    acknowledge(current.value)
    return
  }
  loadDue()
})

onMounted(() => {
  loadDue()
  document.addEventListener('visibilitychange', onVisible)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisible)
  unlockScroll()
})
</script>

<style scoped>
.system-campaign-overlay {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding:
    calc(16px + env(safe-area-inset-top, 0px))
    16px
    calc(16px + env(safe-area-inset-bottom, 0px));
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(3px);
}

.system-campaign-popup {
  position: relative;
  width: min(720px, calc(100vw - 32px), calc((100dvh - 48px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) * 1.5));
  aspect-ratio: 3 / 2;
  border-radius: 20px;
  overflow: hidden;
  background: #0b0736;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
  animation: system-campaign-in 0.38s cubic-bezier(0.16, 1, 0.3, 1);
}

.system-campaign-popup__picture,
.system-campaign-popup__image {
  display: block;
  width: 100%;
  height: 100%;
}

.system-campaign-popup__image {
  object-fit: cover;
  object-position: center;
}

.system-campaign-popup__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
  width: 44px;
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.94);
  color: #0b0736;
  font-size: 20px;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.25);
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.system-campaign-popup__close:hover {
  background: #fff;
}

.system-campaign-enter-active,
.system-campaign-leave-active {
  transition: opacity 0.24s ease;
}

.system-campaign-enter-from,
.system-campaign-leave-to {
  opacity: 0;
}

@keyframes system-campaign-in {
  from {
    opacity: 0;
    transform: translateY(16px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@media (max-width: 768px) {
  .system-campaign-popup {
    width: min(480px, calc(100vw - 32px), calc((100dvh - 48px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) * 12 / 17));
    aspect-ratio: 12 / 17;
    border-radius: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .system-campaign-popup {
    animation: none;
  }

  .system-campaign-enter-active,
  .system-campaign-leave-active {
    transition: none;
  }
}
</style>

<style>
body.system-campaign-open {
  overflow: hidden !important;
}

body.system-campaign-open .mobile-core-dock {
  display: none !important;
}
</style>
