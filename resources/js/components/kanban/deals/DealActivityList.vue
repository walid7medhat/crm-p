<template>
  <div class="bg-white p-3 radius-12 shadow-sm mt-3">
    <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <span class="modal-title">Deal Activity</span>
      </div>
    </div>
    <div v-if="loading" class="text-muted small">Loading activities...</div>
    <div v-else-if="activities.length === 0" class="text-muted small">No activities yet.</div>
    <div v-else class="d-flex flex-column gap-2">
      <div v-for="activity in activities" :key="activity.id" class="border rounded p-2">
        <div class="d-flex justify-content-between align-items-center">
          <strong>{{ activity.title }}</strong>
          <span class="small text-muted">{{ formatDate(activity.reminder_date) }}</span>
        </div>
        <div class="small text-muted mt-1">Status: {{ activity.status || (activity.is_completed ? 'Completed' : 'Pending') }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'

const props = defineProps({
  dealId: {
    type: [Number, String],
    default: null,
  },
})

const activities = ref([])
const loading = ref(false)

const formatDate = (value) => {
  if (!value) return '-'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const fetchActivities = async () => {
  if (!props.dealId) {
    activities.value = []
    return
  }
  loading.value = true
  try {
    const response = await api.get(`/deals/${props.dealId}/activities`)
    const data = response?.data?.data || []
    activities.value = Array.isArray(data) ? data : []
  } catch {
    activities.value = []
  } finally {
    loading.value = false
  }
}

const addActivity = (activity) => {
  if (!activity) return
  activities.value.unshift(activity)
}

defineExpose({ addActivity, fetchActivities })

watch(() => props.dealId, fetchActivities, { immediate: true })
onMounted(fetchActivities)
</script>
