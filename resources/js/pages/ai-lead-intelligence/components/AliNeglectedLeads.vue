<template>
  <AliSection section-key="neglected" emoji="⏱" title="Neglected Leads" subtitle="Grouped by urgency using CRM activity signals" :state="sectionState" :error-message="errorMessage" empty-message="No neglected leads in your visible pipeline." @retry="$emit('retry')">
    <div class="ali-neglect-groups">
      <div v-for="group in groupDefs" :key="group.key" class="ali-neglect-group" :class="`ali-neglect-group--${group.key}`">
        <header class="ali-neglect-group__head">
          <span class="ali-neglect-group__dot" aria-hidden="true" />
          <strong>{{ group.label }}</strong>
          <span class="ali-neglect-group__count">
            <template v-if="groupData(group.key)?.count != null">{{ groupData(group.key).count }}</template>
            <template v-else>—</template>
          </span>
        </header>
        <AliSectionState v-if="groupData(group.key)?.status === 'not_available'" variant="unavailable" title="Not analyzed yet" message="Run Refresh Analysis to calculate neglect urgency buckets." />
        <AliSectionState v-else-if="groupData(group.key)?.status === 'empty' || !(groupData(group.key)?.items || []).length" variant="empty" title="None" message="No leads in this group." />
        <div v-else class="ali-list ali-list--compact">
          <article v-for="lead in groupData(group.key).items" :key="lead.id" class="ali-risk-row">
            <div class="ali-risk-row__main">
              <h3>{{ lead.lead_name || 'Unnamed lead' }}</h3>
              <p class="ali-risk-row__reason">{{ lead.risk_reason || lead.why_text || 'Neglect reason pending' }}</p>
              <div class="ali-lead-card__tags">
                <span v-if="lead.stage_name" class="ali-chip">{{ lead.stage_name }}</span>
                <span v-if="lead.last_contact_label" class="ali-chip">{{ lead.last_contact_label }}</span>
                <span v-if="lead.responsible_person?.name" class="ali-chip">{{ lead.responsible_person.name }}</span>
              </div>
            </div>
            <AliLeadActions :lead-id="lead.id" @open-lead="$emit('open-lead', $event)" @view-matches="$emit('view-matches', $event)" />
          </article>
        </div>
      </div>
    </div>
  </AliSection>
</template>

<script setup>
import { computed } from 'vue'
import AliSection from './AliSection.vue'
import AliSectionState from './AliSectionState.vue'
import AliLeadActions from './AliLeadActions.vue'
const props = defineProps({
  status: { type: String, default: 'not_available' },
  groups: {
    type: Object,
    default: () => ({
      critical: { status: 'not_available', count: null, items: [] },
      needs_attention: { status: 'not_available', count: null, items: [] },
      monitor: { status: 'not_available', count: null, items: [] },
    }),
  },
  errorMessage: { type: String, default: '' },
  pageState: { type: String, default: '' },
})
defineEmits(['retry', 'open-lead', 'view-matches'])
const groupDefs = [
  { key: 'critical', label: 'Critical' },
  { key: 'needs_attention', label: 'Needs Attention' },
  { key: 'monitor', label: 'Monitor' },
]
function groupData(key) {
  return props.groups?.[key] || { status: 'not_available', count: null, items: [] }
}
const sectionState = computed(() => {
  if (props.pageState === 'loading') return 'loading'
  if (props.pageState === 'refreshing') return 'refreshing'
  if (props.pageState === 'forbidden') return 'forbidden'
  if (props.pageState === 'error') return 'error'
  if (props.status === 'not_available' || props.status === 'ready' || props.status === 'empty') return 'ready'
  return 'unavailable'
})
</script>
