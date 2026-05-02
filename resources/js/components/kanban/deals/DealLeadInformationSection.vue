<template>
  <div class="lead-info-card bg-white p-3 radius-12 shadow-sm mt-3">
    <div class="info-group">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="info-section-title mb-3">Lead information</div>
        <b-button
          variant="link"
          class="p-0 view-lead-btn"
          type="button"
          @click="$emit('view-more')"
        >
          View more
        </b-button>
      </div>

      <div v-if="loading" class="text-muted small py-2 d-flex align-items-center gap-2">
        <b-spinner small variant="secondary" label="Loading" />
        Loading lead…
      </div>

      <div v-else class="d-flex align-items-start gap-3">
        <div class="lead-avatar-wrap">
          <img
            v-if="displayLead?.avatar || displayLead?.photo"
            :src="displayLead?.avatar || displayLead?.photo"
            class="avatar-md rounded-circle"
            alt=""
          />
          <div v-else class="avatar-placeholder">
            <iconify-icon icon="lucide:user" class="avatar-icon" />
          </div>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="info-value text-truncate">{{ leadTitle }}</div>
          <div class="info-subline">
            <span class="sub-key">Phone:</span>
            <span class="sub-value">{{ phoneDisplay }}</span>
          </div>
          <div class="info-subline">
            <span class="sub-key">Email:</span>
            <span class="sub-value text-break">{{ emailDisplay }}</span>
          </div>
          <div v-if="stageLabel" class="info-subline">
            <span class="sub-key">Stage:</span>
            <span class="sub-value">{{ stageLabel }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { BButton, BSpinner } from 'bootstrap-vue-3'
import api from '@/plugins/axios'

const props = defineProps({
  leadId: { type: [Number, String], required: true },
  /** Optional embedded lead from GET /deals/:id (relation `lead`) */
  lead: { type: Object, default: null },
})

defineEmits(['view-more'])

const loading = ref(false)
const displayLead = ref(null)

const leadTitle = computed(() => {
  const l = displayLead.value
  if (!l) return '—'
  return (
    l.lead_name ||
    l.name ||
    [l.first_name, l.last_name].filter(Boolean).join(' ') ||
    `Lead #${l.id ?? props.leadId}`
  )
})

const phoneDisplay = computed(() => {
  const l = displayLead.value
  if (!l) return '—'
  return l.work_phone || l.phone || l.mobile || '—'
})

const emailDisplay = computed(() => {
  const l = displayLead.value
  if (!l) return '—'
  return l.email || '—'
})

const stageLabel = computed(() => {
  const l = displayLead.value
  if (!l) return ''
  return l.stage?.name || l.stage_name || ''
})

async function fetchLeadPreview() {
  if (!props.leadId) return
  loading.value = true
  try {
    const response = await api.get(`/leads/${props.leadId}`)
    displayLead.value = response.data?.data ?? response.data ?? null
  } catch {
    displayLead.value = displayLead.value || null
  } finally {
    loading.value = false
  }
}

watch(
  () => ({ id: props.leadId, embedded: props.lead }),
  async ({ id, embedded }) => {
    if (!id) return
    const rich =
      embedded &&
      typeof embedded === 'object' &&
      (embedded.lead_name ||
        embedded.name ||
        embedded.email ||
        embedded.work_phone ||
        embedded.phone ||
        embedded.first_name)
    if (rich) {
      displayLead.value = { ...embedded }
      return
    }
    await fetchLeadPreview()
  },
  { immediate: true, deep: true }
)
</script>

<style scoped>
.lead-info-card {
  position: relative;
  border: 1px solid rgba(15, 23, 42, 0.08);
  margin-bottom: 12px;
  overflow: hidden;
  background: #fff;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.05),
    0 10px 24px rgba(15, 23, 42, 0.06);
}

.lead-info-card .info-group {
  position: relative;
}

.info-section-title {
  font-size: 12px;
  font-weight: 700;
  color: #0f172a;
}

.info-value {
  font-size: 14px;
  color: #1e293b;
  font-weight: 600;
}

.info-subline {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}

.sub-key {
  font-weight: 500;
  margin-right: 4px;
}

.sub-value {
  color: #334155;
}

.lead-avatar-wrap {
  flex-shrink: 0;
}

.avatar-md {
  width: 48px;
  height: 48px;
  object-fit: cover;
}

.avatar-placeholder {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e5e7eb;
}

.avatar-icon {
  font-size: 24px;
  color: #9ca3af;
}

.view-lead-btn {
  text-decoration: none;
  color: #64748b;
  font-size: 12px;
  font-weight: 500;
}

.view-lead-btn:hover {
  color: #0f172a;
}
</style>
