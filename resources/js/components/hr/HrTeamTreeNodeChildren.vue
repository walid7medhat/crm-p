<template>
  <div class="hr-oc-sub">
    <div v-if="hasMany" class="hr-oc-hwrap">
      <div class="hr-oc-hlane" aria-hidden="true" />
    </div>
    <div class="hr-oc-cols">
      <div v-for="child in children" :key="child.id" class="hr-oc-col">
        <div class="hr-oc-vin" aria-hidden="true" />
        <HrTeamTreeNodeCard :node="child" :depth="depth" @open-sales="emit('open-sales', $event)" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import HrTeamTreeNodeCard from './HrTeamTreeNodeCard.vue'

const props = defineProps({
  children: { type: Array, required: true },
  depth: { type: Number, required: true },
})
const emit = defineEmits(['open-sales'])

const hasMany = computed(() => props.children.length > 1)
</script>

<style scoped>
.hr-oc-sub {
  position: relative;
  width: max-content;
  max-width: 100%;
  margin-left: auto;
  margin-right: auto;
  font-family: var(--hr-tree-font, 'Inter', 'Montserrat', system-ui, sans-serif);
}

.hr-oc-hwrap {
  width: 100%;
  display: flex;
  justify-content: center;
  margin-bottom: 0;
}

.hr-oc-hlane {
  height: 2px;
  width: calc(100% - var(--hr-oc-card-w, 248px));
  min-width: 44px;
  background: var(--hr-tree-line, #c7c9da);
  border-radius: 99px;
}

.hr-oc-cols {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: flex-start;
  gap: var(--hr-oc-sibling-gap, 18px);
}

.hr-oc-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 0 0 min(var(--hr-oc-card-w, 248px), 100%);
  max-width: 100%;
}

.hr-oc-vin {
  width: 2px;
  height: var(--hr-oc-vin-h, 18px);
  background: var(--hr-tree-line, #c7c9da);
  border-radius: 99px;
}

@media (max-width: 900px) {
  .hr-oc-cols { gap: 12px; }
  .hr-oc-col { flex-basis: min(var(--hr-oc-card-w-mobile, 220px), 100%); }
}
</style>
