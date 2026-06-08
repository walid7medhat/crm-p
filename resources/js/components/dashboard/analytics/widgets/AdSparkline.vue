<template>
  <svg
    class="ad-sparkline"
    :viewBox="`0 0 ${width} ${height}`"
    preserveAspectRatio="none"
    role="img"
    :aria-label="ariaLabel"
  >
    <defs>
      <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="color" stop-opacity="0.35" />
        <stop offset="100%" :stop-color="color" stop-opacity="0" />
      </linearGradient>
    </defs>
    <path v-if="areaPath" :d="areaPath" :fill="`url(#${gradId})`" />
    <path v-if="linePath" :d="linePath" fill="none" :stroke="color" stroke-width="1.5" stroke-linecap="round" />
  </svg>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  points: { type: Array, default: () => [] },
  color: { type: String, default: '#7c5cbf' },
  width: { type: Number, default: 120 },
  height: { type: Number, default: 32 },
  ariaLabel: { type: String, default: 'Trend sparkline' },
})

const gradId = `spark-${Math.random().toString(36).slice(2, 9)}`

const values = computed(() =>
  (props.points || []).map((p) => (typeof p === 'object' ? Number(p.value ?? p) : Number(p))).filter((n) => !Number.isNaN(n)),
)

const linePath = computed(() => buildPath(false))
const areaPath = computed(() => buildPath(true))

function buildPath(closed) {
  const vals = values.value
  if (!vals.length) return ''
  const max = Math.max(...vals, 1)
  const min = Math.min(...vals, 0)
  const span = max - min || 1
  const step = props.width / Math.max(vals.length - 1, 1)

  const coords = vals.map((v, i) => {
    const x = i * step
    const y = props.height - ((v - min) / span) * (props.height - 4) - 2
    return [x, y]
  })

  let d = coords.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ')
  if (closed) {
    d += ` L${props.width},${props.height} L0,${props.height} Z`
  }
  return d
}
</script>
