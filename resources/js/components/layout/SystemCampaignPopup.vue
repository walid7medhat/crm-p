<template>
  <Teleport to="body">
    <Transition name="system-campaign">
      <div
        v-if="rendered && display"
        class="system-campaign-overlay"
        @touchmove.prevent
      >
        <div class="system-campaign-stage">
          <span class="system-campaign-glow" aria-hidden="true" />
          <div
            class="system-campaign-popup"
            role="dialog"
            aria-modal="true"
            :aria-label="display.title || 'Announcement'"
          >
            <picture class="system-campaign-popup__picture">
              <source media="(max-width: 768px)" :srcset="display.mobile_image_url" />
              <img
                class="system-campaign-popup__image"
                :src="display.desktop_image_url"
                :alt="display.title || 'Announcement'"
              />
            </picture>
            <span class="system-campaign-popup__shine" aria-hidden="true" />
            <button
              ref="closeButton"
              type="button"
              class="system-campaign-popup__close"
              aria-label="Close announcement"
              @click="closePopup"
            >
              <iconify-icon icon="lucide:x" />
            </button>
          </div>
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
  background:
    radial-gradient(ellipse at center, rgba(115, 62, 135, 0.28), transparent 58%),
    rgba(8, 6, 24, 0.72);
  backdrop-filter: blur(8px);
}

.system-campaign-stage {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: min(720px, calc(100vw - 32px), calc((100dvh - 48px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) * 1.5));
}

.system-campaign-glow {
  position: absolute;
  inset: -12% -8%;
  border-radius: 40px;
  background: radial-gradient(ellipse at center, rgba(115, 62, 135, 0.55), rgba(11, 7, 54, 0.15) 55%, transparent 72%);
  filter: blur(18px);
  pointer-events: none;
  animation: system-campaign-glow 2.8s ease-in-out infinite alternate;
}

.system-campaign-popup {
  position: relative;
  z-index: 1;
  width: 100%;
  aspect-ratio: 3 / 2;
  border-radius: 22px;
  overflow: hidden;
  background: #0b0736;
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.22),
    0 28px 70px rgba(8, 6, 24, 0.45),
    0 0 48px rgba(115, 62, 135, 0.35);
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
  transform-origin: center;
  animation: system-campaign-zoom 9s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.system-campaign-popup__shine {
  position: absolute;
  inset: 0;
  z-index: 2;
  pointer-events: none;
  background: linear-gradient(
    115deg,
    transparent 32%,
    rgba(255, 255, 255, 0.08) 42%,
    rgba(255, 255, 255, 0.55) 50%,
    rgba(255, 255, 255, 0.08) 58%,
    transparent 68%
  );
  transform: translateX(-130%);
  animation: system-campaign-shine 0.95s 0.42s ease forwards;
}

.system-campaign-popup__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 3;
  width: 44px;
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.55);
  border-radius: 999px;
  background: rgba(11, 7, 54, 0.45);
  color: #fff;
  font-size: 20px;
  backdrop-filter: blur(8px);
  box-shadow: 0 8px 20px rgba(8, 6, 24, 0.28);
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  animation: system-campaign-close-in 0.45s 0.28s cubic-bezier(0.16, 1, 0.3, 1) both;
  transition: transform 0.18s ease, background 0.18s ease;
}

.system-campaign-popup__close:hover {
  background: rgba(11, 7, 54, 0.72);
  transform: scale(1.06);
}

.system-campaign-popup__close:active {
  transform: scale(0.94);
}

.system-campaign-enter-active {
  transition: opacity 0.35s ease;
}

.system-campaign-enter-active .system-campaign-popup {
  animation: system-campaign-in 0.62s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.system-campaign-leave-active {
  transition: opacity 0.28s ease;
}

.system-campaign-leave-active .system-campaign-popup {
  animation: system-campaign-out 0.28s ease both;
}

.system-campaign-enter-from,
.system-campaign-leave-to {
  opacity: 0;
}

@keyframes system-campaign-in {
  0% {
    opacity: 0;
    transform: translateY(36px) scale(0.82);
  }
  70% {
    opacity: 1;
    transform: translateY(-4px) scale(1.02);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes system-campaign-out {
  to {
    opacity: 0;
    transform: translateY(12px) scale(0.94);
  }
}

@keyframes system-campaign-zoom {
  from {
    transform: scale(1.1);
  }
  to {
    transform: scale(1);
  }
}

@keyframes system-campaign-shine {
  to {
    transform: translateX(130%);
  }
}

@keyframes system-campaign-glow {
  from {
    opacity: 0.65;
    transform: scale(0.96);
  }
  to {
    opacity: 1;
    transform: scale(1.04);
  }
}

@keyframes system-campaign-close-in {
  from {
    opacity: 0;
    transform: scale(0.6);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@media (max-width: 768px) {
  .system-campaign-stage {
    width: min(480px, calc(100vw - 32px), calc((100dvh - 48px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) * 12 / 17));
  }

  .system-campaign-popup {
    aspect-ratio: 12 / 17;
    border-radius: 18px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .system-campaign-glow,
  .system-campaign-popup__image,
  .system-campaign-popup__shine,
  .system-campaign-popup__close,
  .system-campaign-enter-active .system-campaign-popup,
  .system-campaign-leave-active .system-campaign-popup {
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
