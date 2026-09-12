<template>
  <section class="ali-section" :aria-labelledby="headingId">
    <header class="ali-section__head">
      <div class="ali-section__title-wrap">
        <span class="ali-section__emoji" aria-hidden="true">{{ emoji }}</span>
        <div>
          <h2 :id="headingId" class="ali-section__title">{{ title }}</h2>
          <p v-if="subtitle" class="ali-section__sub">{{ subtitle }}</p>
        </div>
      </div>
      <slot name="actions" />
    </header>
    <div class="ali-section__body">
      <AliSectionState v-if="state === 'loading'" variant="loading" title="Loading…" message="Fetching intelligence for this section." />
      <AliSectionState v-else-if="state === 'refreshing'" variant="refreshing" title="Refreshing analysis…" message="Updated results will appear when ready." />
      <AliSectionState v-else-if="state === 'error'" variant="error" title="Could not load this section" :message="errorMessage || 'Something went wrong.'" show-retry @retry="$emit('retry')" />
      <AliSectionState v-else-if="state === 'forbidden'" variant="forbidden" title="No permission" message="You do not have access to this lead intelligence." />
      <AliSectionState v-else-if="state === 'unavailable'" variant="unavailable" title="Analysis not available yet" :message="unavailableMessage" />
      <AliSectionState v-else-if="state === 'empty'" variant="empty" title="Nothing to show" :message="emptyMessage || 'No leads match this signal right now.'" />
      <slot v-else />
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import AliSectionState from './AliSectionState.vue'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  emoji: { type: String, default: '✨' },
  sectionKey: { type: String, required: true },
  state: {
    type: String,
    default: 'unavailable',
    validator: (v) => ['loading', 'empty', 'error', 'forbidden', 'unavailable', 'refreshing', 'ready'].includes(v),
  },
  errorMessage: { type: String, default: '' },
  emptyMessage: { type: String, default: '' },
  unavailableMessage: {
    type: String,
    default: 'Run Refresh Analysis to aggregate CRM lead intelligence for this section.',
  },
})
defineEmits(['retry'])
const headingId = computed(() => `ali-section-${props.sectionKey}`)
</script>
