<template>
  <div v-if="hasContent" class="si-why" :class="{ 'si-why--compact': compact }">
    <button type="button" class="si-why__toggle" :aria-expanded="open" @click="open = !open">
      <iconify-icon icon="lucide:sparkles" class="si-why__ic" aria-hidden="true" />
      <span class="si-why__t">{{ title }}</span>
      <span v-if="deltaLine" class="si-why__d">{{ deltaLine }}</span>
      <iconify-icon :icon="open ? 'lucide:chevron-up' : 'lucide:chevron-down'" class="si-why__chev" aria-hidden="true" />
    </button>
    <div v-show="open" class="si-why__body">
      <ul class="si-why__ul">
        <li v-for="(b, i) in bullets" :key="i" class="si-why__li">{{ b }}</li>
      </ul>
      <p v-if="factors?.length" class="si-why__f">
        <span class="si-why__fk">Contributing factors</span>
        {{ factors.join(' · ') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  title: { type: String, default: 'Why this changed' },
  bullets: { type: Array, default: () => [] },
  /** Short delta label e.g. "+4 pts" */
  deltaLine: { type: String, default: '' },
  factors: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
  /** Start expanded when there is a delta */
  defaultOpen: { type: Boolean, default: false },
})

const open = ref(props.defaultOpen)

const hasContent = computed(() => (props.bullets && props.bullets.length > 0) || !!props.deltaLine)
</script>

<style scoped>
.si-why {
  margin-top: 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fafafa;
  overflow: hidden;
}

.si-why--compact {
  margin-top: 6px;
}

.si-why__toggle {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font-size: 11px;
  color: #374151;
  transition: background var(--si-ease, 0.16s ease);
}

.si-why__toggle:hover {
  background: #f3f4f6;
}

.si-why__toggle:focus-visible {
  outline: none;
  box-shadow: inset 0 0 0 2px #d4d4d4;
}

.si-why__ic {
  font-size: 14px;
  color: #6366f1;
  flex-shrink: 0;
}

.si-why__t {
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 9px;
  color: #6b7280;
  flex-shrink: 0;
}

.si-why__d {
  margin-left: auto;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #111827;
  font-size: 11px;
}

.si-why__chev {
  font-size: 14px;
  color: #9ca3af;
  flex-shrink: 0;
}

.si-why__body {
  padding: 0 10px 10px;
  border-top: 1px solid #f3f4f6;
}

.si-why__ul {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 11px;
  line-height: 1.45;
  color: #4b5563;
}

.si-why__li {
  margin-bottom: 4px;
}

.si-why__f {
  margin: 8px 0 0;
  font-size: 10px;
  color: #9ca3af;
  line-height: 1.35;
}

.si-why__fk {
  display: block;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 2px;
  color: #9ca3af;
}
</style>
