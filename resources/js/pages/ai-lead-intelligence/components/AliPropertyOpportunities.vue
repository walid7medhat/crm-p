<template>
  <AliSection section-key="property" emoji="🏠" title="Property Opportunities" subtitle="Lead demand connected to existing listing matches" :state="sectionState" :error-message="errorMessage" empty-message="No property match opportunities in your visible pipeline." @retry="$emit('retry')">
    <div class="ali-prop-grid">
      <article v-for="lead in items" :key="lead.id" class="ali-prop-card">
        <h3>{{ lead.lead_name || 'Unnamed lead' }}</h3>
        <p class="ali-prop-card__demand">{{ [lead.property_type, lead.area].filter(Boolean).join(' · ') || 'Requirements pending' }}</p>
        <p class="ali-prop-card__budget">{{ lead.budget_display || 'Budget not set' }}</p>
        <p class="ali-prop-card__matches">
          <template v-if="lead.matching_listings_count != null"><strong>{{ lead.matching_listings_count }}</strong> matching properties</template>
          <template v-else>Match count pending</template>
        </p>
        <AliLeadActions :lead-id="lead.id" @open-lead="$emit('open-lead', $event)" @view-matches="$emit('view-matches', $event)" />
      </article>
      <article v-for="(gap, idx) in gaps" :key="`gap-${idx}`" class="ali-prop-card ali-prop-card--gap">
        <span class="ali-chip ali-chip--risk">Inventory gap</span>
        <h3>{{ gap.label || 'High demand / limited inventory' }}</h3>
        <p class="ali-prop-card__demand">{{ [gap.property_type, gap.area].filter(Boolean).join(' · ') || '—' }}</p>
        <p class="ali-prop-card__budget">{{ gap.budget_display || '—' }}</p>
        <p class="ali-prop-card__matches">Demand {{ gap.demand_count ?? '—' }} · Inventory {{ gap.inventory_count ?? '—' }}</p>
      </article>
    </div>
  </AliSection>
</template>

<script setup>
import { computed } from 'vue'
import AliSection from './AliSection.vue'
import AliLeadActions from './AliLeadActions.vue'
const props = defineProps({
  status: { type: String, default: 'not_available' },
  items: { type: Array, default: () => [] },
  gaps: { type: Array, default: () => [] },
  errorMessage: { type: String, default: '' },
  pageState: { type: String, default: '' },
})
defineEmits(['retry', 'open-lead', 'view-matches'])
const sectionState = computed(() => {
  if (props.pageState === 'loading') return 'loading'
  if (props.pageState === 'refreshing') return 'refreshing'
  if (props.pageState === 'forbidden') return 'forbidden'
  if (props.pageState === 'error') return 'error'
  if (props.status === 'not_available') return 'unavailable'
  const hasData = props.items.length > 0 || props.gaps.length > 0
  if (props.status === 'empty' || (props.status === 'ready' && !hasData)) return 'empty'
  if (props.status === 'ready') return 'ready'
  return 'unavailable'
})
</script>
