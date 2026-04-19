<template>
  <span class="si-as" :class="{ 'si-as--tick': tick }">{{ display }}</span>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  value: { type: [Number, String, null], default: null },
})

const display = ref(format(props.value))
const tick = ref(false)

function format(v) {
  if (v == null || v === '' || Number.isNaN(Number(v))) return '—'
  return String(Math.round(Number(v)))
}

watch(
  () => props.value,
  (nv, ov) => {
    const next = format(nv)
    if (next !== display.value) {
      display.value = next
      tick.value = true
      window.setTimeout(() => {
        tick.value = false
      }, 520)
    }
  }
)
</script>

<style scoped>
.si-as {
  font-variant-numeric: tabular-nums;
  transition:
    color 0.35s ease,
    transform 0.35s ease;
}

.si-as--tick {
  color: #4f46e5;
  transform: scale(1.04);
}
</style>
