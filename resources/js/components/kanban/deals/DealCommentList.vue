<template>
  <div class="bg-white p-3 radius-12 shadow-sm mt-3">
    <div class="modal-header-custom d-flex justify-content-between align-items-center pb-9 mb-3 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <span class="modal-title">Deal Comments</span>
      </div>
    </div>
    <div v-if="loading" class="text-muted small">Loading comments...</div>
    <div v-else-if="comments.length === 0" class="text-muted small">No comments yet.</div>
    <div v-else class="d-flex flex-column gap-2">
      <div v-for="comment in comments" :key="comment.id" class="border rounded p-2">
        <div class="d-flex justify-content-between align-items-center">
          <strong>{{ comment.user_name || 'User' }}</strong>
          <span class="small text-muted">{{ formatDate(comment.created_at) }}</span>
        </div>
        <div class="small mt-1">{{ comment.comment }}</div>
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

const comments = ref([])
const loading = ref(false)

const formatDate = (value) => {
  if (!value) return '-'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString()
}

const fetchComments = async () => {
  if (!props.dealId) {
    comments.value = []
    return
  }
  loading.value = true
  try {
    const response = await api.get(`/deals/${props.dealId}/comments`)
    const data = response?.data?.data || []
    comments.value = Array.isArray(data) ? data : []
  } catch {
    comments.value = []
  } finally {
    loading.value = false
  }
}

const addComment = (comment) => {
  if (!comment) return
  comments.value.unshift(comment)
}

defineExpose({ addComment, fetchComments })

watch(() => props.dealId, fetchComments, { immediate: true })
onMounted(fetchComments)
</script>
