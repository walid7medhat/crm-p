<template>
  <Teleport to="body">
    <Transition name="edge-slide" mode="out-in">
      <div v-if="activePayload" :key="activePayload.message?.number" class="edge-dock">
        <DailyEdgeCard
          :date-label="activePayload.date_label"
          :message="activePayload.message"
          dismissible
          @close="closeActive"
        />
      </div>
    </Transition>
    <button
      v-if="showPill"
      type="button"
      class="edge-pill"
      @click="reopen"
    >
      <span class="edge-pill__mark" aria-hidden="true"></span>
      Daily Message
    </button>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { resolveAuthToken } from '@/plugins/axios'
import DailyEdgeCard from './DailyEdgeCard.vue'
import { dismissToday, fetchToday } from '@/services/dailyMotivationApi'
import { closeDailyEdgeTest, openDailyEdgeTest, useDailyEdgeTest } from '@/composables/useDailyEdgePreview.js'

const { testPayload, testOpen } = useDailyEdgeTest()
const production = ref(null)
const productionOpen = ref(false)
const lastClosed = ref(null)
let dismissing = false

const showingTest = computed(() => testOpen.value && !!testPayload.value?.message)
const showingProduction = computed(() => !showingTest.value && productionOpen.value && !!production.value?.message)
const activePayload = computed(() => {
  if (showingTest.value) return testPayload.value
  if (showingProduction.value) return production.value
  return null
})

const showPill = computed(() => {
  if (activePayload.value) return false
  if (testPayload.value?.message && lastClosed.value === 'test') return true
  return !!production.value?.message && !productionOpen.value
})

async function onDelivered() {
  closeDailyEdgeTest()
  await load()
  if (production.value?.message) productionOpen.value = true
}

function onTestEvent(event) {
  const data = event?.detail || {}
  if (!data.body_en && !data.body_ar) return
  openDailyEdgeTest({
    date_label: production.value?.date_label || '',
    message: {
      number: data.number,
      position: data.number,
      cycle_length: 120,
      body_en: data.body_en,
      body_ar: data.body_ar,
      subtitle_en: data.subtitle_en,
      subtitle_ar: data.subtitle_ar,
      test: true,
    },
  })
}

function onKeydown(event) {
  if (event.key !== 'Escape' || !activePayload.value) return
  const tag = document.activeElement?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return
  closeActive()
}

async function load() {
  if (!resolveAuthToken()) return
  try {
    const response = await fetchToday()
    const payload = response.data?.data
    production.value = payload
    productionOpen.value = !!payload?.auto_open
    if (payload?.message && !payload.auto_open) lastClosed.value = 'production'
  } catch {
    production.value = null
    productionOpen.value = false
  }
}

async function closeActive() {
  if (showingTest.value) {
    closeDailyEdgeTest()
    lastClosed.value = 'test'
    return
  }
  if (!showingProduction.value || dismissing) return
  productionOpen.value = false
  lastClosed.value = 'production'
  if (production.value) production.value = { ...production.value, auto_open: false, dismissed: true }
  dismissing = true
  try {
    const response = await dismissToday()
    if (response.data?.data) production.value = response.data.data
  } catch {
    // The card is already closed. A failed dismiss must not trap the user.
  } finally {
    dismissing = false
  }
}

function reopen() {
  if (lastClosed.value === 'test' && testPayload.value?.message) {
    testOpen.value = true
    return
  }
  if (production.value?.message) {
    productionOpen.value = true
    return
  }
  if (testPayload.value?.message) testOpen.value = true
}

onMounted(() => {
  load()
  window.addEventListener('daily-edge:test', onTestEvent)
  window.addEventListener('daily-edge:open', onDelivered)
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('daily-edge:test', onTestEvent)
  window.removeEventListener('daily-edge:open', onDelivered)
  window.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.edge-dock {
  position: fixed;
  z-index: 1100;
  top: 0;
  right: 16px;
  bottom: 0;
  display: flex;
  align-items: center;
  width: min(340px, calc(100vw - 32px));
  pointer-events: none;
}

.edge-dock :deep(.edge-card) {
  pointer-events: auto;
}

.edge-pill {
  position: fixed;
  z-index: 1100;
  right: 16px;
  top: calc(50% + 150px);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border: 0;
  border-radius: 999px;
  color: #ffffff;
  background: #733e87;
  box-shadow: 0 10px 24px rgba(115, 62, 135, 0.28);
  font-family: Inter, system-ui, sans-serif;
  font-size: 13px;
  font-weight: 650;
  letter-spacing: 0.04em;
  cursor: pointer;
}

.edge-pill__mark {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #ffffff;
}

.edge-slide-enter-active {
  transition: opacity 0.3s ease, transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}

.edge-slide-leave-active {
  transition: opacity 0.2s ease, transform 0.28s ease;
}

.edge-slide-enter-from,
.edge-slide-leave-to {
  opacity: 0;
  transform: translateX(110%);
}

@media (max-width: 1024px) {
  .edge-dock {
    align-items: flex-end;
    right: 12px;
    padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
  }

  .edge-pill {
    top: auto;
    right: 12px;
    bottom: calc(96px + env(safe-area-inset-bottom, 0px));
  }
}

@media (prefers-reduced-motion: reduce) {
  .edge-slide-enter-active,
  .edge-slide-leave-active {
    transition: none;
  }
}
</style>
