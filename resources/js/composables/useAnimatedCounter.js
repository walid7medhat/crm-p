import { ref, watch, onUnmounted } from 'vue'

export function useAnimatedCounter(source, duration = 600) {
  const display = ref(0)
  let frame = null

  function animate(to) {
    if (frame) cancelAnimationFrame(frame)
    const from = display.value
    const target = Number(to) || 0
    if (from === target) return
    const start = performance.now()

    const tick = (now) => {
      const t = Math.min(1, (now - start) / duration)
      const eased = 1 - Math.pow(1 - t, 3)
      display.value = Math.round(from + (target - from) * eased)
      if (t < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
  }

  watch(
    () => Number(source?.value ?? source ?? 0),
    (v) => animate(v),
    { immediate: true },
  )

  onUnmounted(() => {
    if (frame) cancelAnimationFrame(frame)
  })

  return display
}
