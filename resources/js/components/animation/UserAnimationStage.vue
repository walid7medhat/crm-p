<template>
  <video
    v-if="showVideo"
    ref="videoEl"
    class="ua-stage"
    :src="playbackSrc"
    autoplay
    loop
    muted
    playsinline
    preload="auto"
    disablepictureinpicture
    aria-hidden="true"
    @error="onVideoError"
  />
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { prefersReducedMotion, transparentVideoSrc } from '@/motion/userAnimations.js'

const props = defineProps({
  motion: { type: String, default: '' },
  kind: { type: String, default: 'video' },
  src: { type: String, default: '' },
  active: { type: Boolean, default: false },
})

const emit = defineEmits(['failed'])

const videoEl = ref(null)
const failed = ref(false)
const reduced = ref(prefersReducedMotion())
let reduceQuery = null

const playbackSrc = computed(() => transparentVideoSrc(props.src))
const showVideo = computed(() => props.active && !reduced.value && !failed.value && !!playbackSrc.value)

function play() {
  if (!showVideo.value) {
    const video = videoEl.value
    if (video) {
      video.pause()
    }
    return
  }
  nextTick(() => {
    videoEl.value?.play?.().catch(() => onVideoError())
  })
}

function onVideoError() {
  if (failed.value) return
  failed.value = true
  emit('failed')
}

function onMotionPreference() {
  reduced.value = prefersReducedMotion()
  play()
}

watch(() => [props.active, props.src], () => {
  failed.value = false
  play()
})

onMounted(() => {
  if (typeof window.matchMedia === 'function') {
    reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
    reduceQuery.addEventListener?.('change', onMotionPreference)
  }
  play()
})

onUnmounted(() => {
  reduceQuery?.removeEventListener?.('change', onMotionPreference)
  const video = videoEl.value
  if (!video) return
  video.pause()
  video.removeAttribute('src')
  video.load()
})
</script>

<style scoped>
.ua-stage {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  background: transparent;
  pointer-events: none;
}
</style>
