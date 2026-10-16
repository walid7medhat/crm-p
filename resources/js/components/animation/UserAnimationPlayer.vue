<template>
  <Transition name="ua-slide">
    <div v-if="visible" class="ua-dock" aria-hidden="true">
      <UserAnimationStage
        :motion="motion"
        :kind="kind"
        :src="src"
        :active="visible"
        @failed="hideAfterFailure"
      />
    </div>
  </Transition>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import api from '@/plugins/axios'
import { prefersReducedMotion } from '@/motion/userAnimations.js'
import UserAnimationStage from './UserAnimationStage.vue'

const enabled = ref(false)
const animationId = ref(null)
const motion = ref('')
const kind = ref('canvas')
const src = ref('')
const failed = ref(false)
const reduced = ref(prefersReducedMotion())
let appliedVersion = -1
let channel = null
let reduceQuery = null

const visible = computed(() => enabled.value && !!motion.value && !failed.value && !reduced.value)

function currentUserId() {
  try {
    const raw = localStorage.getItem('user')
    if (!raw) return null
    const id = Number(JSON.parse(raw)?.id)
    return Number.isFinite(id) && id > 0 ? id : null
  } catch {
    return null
  }
}

function applyState(payload) {
  const version = Number(payload?.version ?? 0)
  if (version < appliedVersion) return
  if (version === appliedVersion && enabled.value === !!payload?.enabled) return
  appliedVersion = version
  failed.value = false
  const nextEnabled = !!payload?.enabled && !!payload?.animation_id
  enabled.value = nextEnabled
  animationId.value = nextEnabled ? payload.animation_id : null
  motion.value = nextEnabled ? (payload.motion || payload.animation_id) : ''
  kind.value = nextEnabled ? (payload.kind || 'canvas') : 'canvas'
  src.value = nextEnabled ? (payload.src || '') : ''
}

async function loadState() {
  if (!currentUserId()) return
  try {
    const response = await api.get('/user-animations/me')
    applyState(response.data?.data || {})
  } catch {
    enabled.value = false
  }
}

function onEvent(payload) {
  const data = payload?.data && typeof payload.data === 'object' ? { ...payload, ...payload.data } : payload
  applyState(data)
}

function subscribe() {
  const id = currentUserId()
  if (!id || !window.Echo || channel) return
  channel = window.Echo.private(`user.${id}`)
  channel.listen('.user.animation', onEvent)
}

function unsubscribe() {
  if (channel) {
    channel.stopListening('.user.animation')
    channel = null
  }
}

function hideAfterFailure() {
  failed.value = true
  enabled.value = false
}

function onMotionPreference() {
  reduced.value = prefersReducedMotion()
}

onMounted(() => {
  if (typeof window.matchMedia === 'function') {
    reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    reduceQuery.addEventListener?.('change', onMotionPreference)
  }
  loadState()
  subscribe()
  window.addEventListener('echo-ready', subscribe)
})

onUnmounted(() => {
  reduceQuery?.removeEventListener?.('change', onMotionPreference)
  window.removeEventListener('echo-ready', subscribe)
  unsubscribe()
})
</script>

<style scoped>
.ua-dock {
  position: fixed;
  top: 18vh;
  right: 12px;
  z-index: 35;
  width: 220px;
  aspect-ratio: 1 / 1;
  pointer-events: none;
  background: transparent;
}

.ua-slide-enter-active,
.ua-slide-leave-active {
  transition: transform 0.35s ease, opacity 0.35s ease;
}

.ua-slide-enter-from,
.ua-slide-leave-to {
  transform: translateX(110%);
  opacity: 0;
}

@media (max-width: 768px) {
  .ua-dock {
    width: 148px;
    top: 22vh;
    right: 8px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ua-slide-enter-active,
  .ua-slide-leave-active {
    transition: none;
  }
}
</style>
