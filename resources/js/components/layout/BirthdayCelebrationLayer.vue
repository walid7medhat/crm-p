<template>
  <div
    v-if="visible"
    class="birthday-celebration"
    aria-hidden="true"
  >
    <div class="birthday-celebration__stage">
      <span
        v-for="item in balloons"
        :key="'b-' + item.id"
        class="birthday-celebration__balloon"
        :style="item.style"
      />
      <span
        v-for="item in confetti"
        :key="'c-' + item.id"
        class="birthday-celebration__confetti"
        :style="item.style"
      />
      <span
        v-for="item in sparkles"
        :key="'s-' + item.id"
        class="birthday-celebration__sparkle"
        :style="item.style"
      />
    </div>

    <button
      type="button"
      class="birthday-celebration__stop"
      @click="stopCelebration"
    >
      Stop Celebration
    </button>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
// TEMPORARY DEV/TEST — remove with useBirthdayCelebrationDevTest.js
import {
  clearBirthdayCelebrationDevTest,
  useBirthdayCelebrationDevTest,
} from '@/composables/useBirthdayCelebrationDevTest'
import { setBirthdayBanner } from '@/composables/useBirthdayCelebrationBanner'

const props = defineProps({
  enabled: { type: Boolean, default: true },
})

const STORAGE_PREFIX = 'birthday_celebration_stopped_'
const hasBirthdayToday = ref(false)
const birthdayFirstName = ref(null)
const stopped = ref(false)
let pollTimer = null

// TEMPORARY DEV/TEST — Super Admin force-preview (does not touch birthday API)
const { forceShow } = useBirthdayCelebrationDevTest()

const todayKey = () => {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${STORAGE_PREFIX}${y}-${m}-${day}`
}

function readStopped() {
  try {
    return localStorage.getItem(todayKey()) === '1'
  } catch {
    return false
  }
}

function writeStopped() {
  try {
    localStorage.setItem(todayKey(), '1')
  } catch {
    /* private mode / quota */
  }
}

const visible = computed(
  () =>
    props.enabled &&
    (hasBirthdayToday.value || forceShow.value) &&
    !stopped.value,
)

/** Corporate greeting — first name when available; safe fallback for Super Admin test. */
const greetingTitle = computed(() => {
  const raw = (birthdayFirstName.value || '').trim()
  if (!raw) return 'HAPPY BIRTHDAY!'
  const first = raw.split(/\s+/)[0] || raw
  return `HAPPY BIRTHDAY, ${first.toUpperCase()}!`
})

function stopCelebration() {
  stopped.value = true
  writeStopped()
  // TEMPORARY DEV/TEST — clear force-preview when Stop is used
  clearBirthdayCelebrationDevTest()
}

async function checkBirthdays() {
  if (!props.enabled) return
  try {
    const response = await api.get('/auth/birthdays/today')
    const data = response?.data?.data ?? response?.data ?? {}
    hasBirthdayToday.value = !!data.has_birthday
    const name = typeof data.first_name === 'string' ? data.first_name.trim() : ''
    birthdayFirstName.value = name || null
  } catch (error) {
    console.warn('Unable to check today\'s birthdays', error)
  }
}

function startPolling() {
  stopPolling()
  if (!props.enabled) return
  stopped.value = readStopped()
  checkBirthdays()
  // Re-check periodically so the layer appears after the 08:00 celebrate job.
  pollTimer = setInterval(checkBirthdays, 5 * 60 * 1000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

const balloonColors = ['#ff6b8a', '#ffd166', '#6bcBff', '#b388ff', '#7ddea2', '#ff9f43', '#e8a0bf']
const confettiColors = ['#ff6b8a', '#ffd166', '#6bcBff', '#ffffff', '#b388ff', '#ff9f43', '#c9a227']

const balloons = Array.from({ length: 12 }, (_, i) => {
  const left = 2 + i * 8.2 + (i % 3) * 1.5
  const delay = (i * 0.55).toFixed(2)
  const duration = (9 + (i % 4) * 2.2).toFixed(2)
  const color = balloonColors[i % balloonColors.length]
  const size = 26 + (i % 4) * 6
  return {
    id: i,
    style: {
      left: `${Math.min(left, 94)}%`,
      '--bc-delay': `${delay}s`,
      '--bc-duration': `${duration}s`,
      '--bc-color': color,
      '--bc-size': `${size}px`,
      '--bc-drift': `${i % 2 === 0 ? 16 : -18}px`,
      '--bc-opacity': `${0.5 + (i % 3) * 0.08}`,
    },
  }
})

const confetti = Array.from({ length: 24 }, (_, i) => {
  const left = (i * 4.2 + 2) % 100
  const delay = (i * 0.28).toFixed(2)
  const duration = (5 + (i % 5) * 0.7).toFixed(2)
  const color = confettiColors[i % confettiColors.length]
  const w = 5 + (i % 4)
  const h = 9 + (i % 5)
  const rotate = (i * 37) % 360
  return {
    id: i,
    style: {
      left: `${left}%`,
      '--bc-delay': `${delay}s`,
      '--bc-duration': `${duration}s`,
      '--bc-color': color,
      '--bc-w': `${w}px`,
      '--bc-h': `${h}px`,
      '--bc-rotate': `${rotate}deg`,
    },
  }
})

const sparkles = Array.from({ length: 14 }, (_, i) => {
  const left = 5 + ((i * 7.2) % 90)
  const top = 8 + ((i * 11) % 75)
  const delay = (i * 0.4).toFixed(2)
  return {
    id: i,
    style: {
      left: `${left}%`,
      top: `${top}%`,
      '--bc-delay': `${delay}s`,
    },
  }
})

onMounted(() => {
  startPolling()
})

onUnmounted(() => {
  stopPolling()
  setBirthdayBanner({ visible: false })
})

watch(
  () => props.enabled,
  (on) => {
    if (on) startPolling()
    else {
      stopPolling()
      hasBirthdayToday.value = false
      birthdayFirstName.value = null
      setBirthdayBanner({ visible: false })
    }
  },
)

// TEMPORARY DEV/TEST — when Super Admin triggers preview, re-read Stop key
watch(forceShow, (on) => {
  if (on) stopped.value = readStopped()
})

// Keep the compact header greeting in sync with overlay visibility
watch(
  [visible, greetingTitle],
  ([isVisible, title]) => {
    setBirthdayBanner({ visible: isVisible, title })
  },
  { immediate: true },
)
</script>

<style scoped>
.birthday-celebration {
  position: fixed;
  inset: 0;
  z-index: 40;
  pointer-events: none;
  overflow: hidden;
}

.birthday-celebration__stage {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.birthday-celebration__balloon {
  position: absolute;
  bottom: -48px;
  width: var(--bc-size, 28px);
  height: calc(var(--bc-size, 28px) * 1.25);
  border-radius: 50% 50% 50% 50% / 55% 55% 45% 45%;
  background: radial-gradient(circle at 30% 28%, rgba(255, 255, 255, 0.55), transparent 42%),
    var(--bc-color, #ff6b8a);
  opacity: var(--bc-opacity, 0.55);
  animation: bc-float-up var(--bc-duration, 12s) var(--bc-delay, 0s) ease-in-out infinite;
  will-change: transform, opacity;
}

.birthday-celebration__balloon::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 100%;
  width: 1px;
  height: 22px;
  background: rgba(255, 255, 255, 0.35);
  transform: translateX(-50%);
}

.birthday-celebration__confetti {
  position: absolute;
  top: -12px;
  width: var(--bc-w, 6px);
  height: var(--bc-h, 10px);
  border-radius: 1px;
  background: var(--bc-color, #ffd166);
  opacity: 0.72;
  transform: rotate(var(--bc-rotate, 0deg));
  animation: bc-fall var(--bc-duration, 6s) var(--bc-delay, 0s) linear infinite;
  will-change: transform, opacity;
}

.birthday-celebration__sparkle {
  position: absolute;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 0 8px 2px rgba(255, 255, 255, 0.45);
  opacity: 0;
  animation: bc-twinkle 2.8s var(--bc-delay, 0s) ease-in-out infinite;
}

.birthday-celebration__stop {
  position: fixed;
  left: 50%;
  bottom: 20px;
  transform: translateX(-50%);
  z-index: 3;
  pointer-events: auto;
  margin: 0;
  padding: 6px 12px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 999px;
  background: rgba(11, 7, 54, 0.55);
  color: #fff;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.2;
  letter-spacing: 0.01em;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}

.birthday-celebration__stop:hover {
  background: rgba(11, 7, 54, 0.72);
}

.birthday-celebration__stop:focus-visible {
  outline: 2px solid rgba(255, 255, 255, 0.7);
  outline-offset: 2px;
}

@keyframes bc-float-up {
  0% {
    transform: translate3d(0, 0, 0);
    opacity: 0;
  }
  8% {
    opacity: var(--bc-opacity, 0.55);
  }
  50% {
    transform: translate3d(var(--bc-drift, 10px), -55vh, 0);
  }
  100% {
    transform: translate3d(calc(var(--bc-drift, 10px) * -0.4), -110vh, 0);
    opacity: 0;
  }
}

@keyframes bc-fall {
  0% {
    transform: translate3d(0, 0, 0) rotate(var(--bc-rotate, 0deg));
    opacity: 0;
  }
  10% {
    opacity: 0.75;
  }
  100% {
    transform: translate3d(18px, 110vh, 0) rotate(calc(var(--bc-rotate, 0deg) + 180deg));
    opacity: 0;
  }
}

@keyframes bc-twinkle {
  0%,
  100% {
    opacity: 0;
    transform: scale(0.6);
  }
  50% {
    opacity: 0.9;
    transform: scale(1.15);
  }
}

@media (max-width: 768px) {
  .birthday-celebration__balloon {
    opacity: 0.42;
  }

  .birthday-celebration__confetti {
    opacity: 0.55;
  }

  .birthday-celebration__stop {
    bottom: calc(88px + env(safe-area-inset-bottom, 0px));
    padding: 5px 10px;
    font-size: 11px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .birthday-celebration__balloon,
  .birthday-celebration__confetti,
  .birthday-celebration__sparkle {
    animation: none;
  }

  .birthday-celebration__balloon {
    bottom: 18%;
    opacity: 0.35;
  }

  .birthday-celebration__confetti {
    top: 20%;
    opacity: 0.35;
  }

  .birthday-celebration__sparkle {
    opacity: 0.35;
  }
}
</style>
