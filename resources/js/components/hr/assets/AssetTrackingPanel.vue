<template>
  <section class="ast-tracking">
    <div class="ast-tracking__block">
      <h6><iconify-icon icon="lucide:history" /> Assignment history</h6>
      <div v-if="!assignments.length" class="ast-tracking__empty">No assignment history yet.</div>
      <div v-else class="ast-tracking__list">
        <article v-for="item in assignments" :key="item.id" class="ast-tracking__item">
          <div class="ast-tracking__dot" />
          <div>
            <strong>{{ item.user?.name || `User #${item.user_id}` }}</strong>
            <p>{{ formatDate(item.handover_date) }} → {{ item.return_date ? formatDate(item.return_date) : 'Present' }}</p>
            <small>{{ item.status }}</small>
          </div>
        </article>
      </div>
    </div>

    <div class="ast-tracking__block">
      <h6><iconify-icon icon="lucide:git-branch" /> Asset lifecycle</h6>
      <div v-if="!timeline.length" class="ast-tracking__empty">No lifecycle events recorded.</div>
      <div v-else class="ast-tracking__timeline">
        <article v-for="item in timeline" :key="item.id" class="ast-tracking__timeline-item">
          <span class="ast-tracking__timeline-badge">{{ item.title }}</span>
          <p>{{ item.detail }}</p>
          <small>{{ formatDateTime(item.date) }}</small>
        </article>
      </div>
    </div>

    <div class="ast-tracking__block">
      <h6><iconify-icon icon="lucide:wrench" /> Maintenance schedule</h6>
      <div v-if="!maintenanceRecords.length" class="ast-tracking__empty">No maintenance records.</div>
      <div v-else class="ast-tracking__list">
        <article v-for="(item, idx) in maintenanceRecords" :key="`m-${idx}`" class="ast-tracking__item">
          <div class="ast-tracking__dot ast-tracking__dot--warn" />
          <div>
            <strong>Maintenance</strong>
            <p>{{ item.details }}</p>
            <small>{{ formatDateTime(item.created_at) }}</small>
          </div>
        </article>
      </div>
    </div>

    <div class="ast-tracking__block">
      <h6><iconify-icon icon="lucide:bell-ring" /> Warranty alerts</h6>
      <div v-if="!warrantyAlerts.length" class="ast-tracking__empty">No warranty alerts.</div>
      <div v-else class="ast-tracking__alerts">
        <article
          v-for="alert in warrantyAlerts"
          :key="alert.id"
          class="ast-tracking__alert"
          :class="`ast-tracking__alert--${alert.status.key}`"
        >
          <strong>{{ alert.name }}</strong>
          <p>{{ alert.assetId }} · {{ alert.status.label }}</p>
          <small>{{ formatDate(alert.warrantyDate) }}</small>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { buildActivityTimeline, getMaintenanceRecords } from '@/services/assetsApi'

const props = defineProps({
  asset: { type: Object, default: null },
  assets: { type: Array, default: () => [] },
  warrantyAlerts: { type: Array, default: () => [] },
})

const assignments = computed(() => props.asset?.assignments || props.asset?.raw?.assignments || [])
const timeline = computed(() => (props.asset ? buildActivityTimeline(props.asset.raw || props.asset) : []))
const maintenanceRecords = computed(() => (props.asset ? getMaintenanceRecords(props.asset.raw || props.asset) : []))

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
