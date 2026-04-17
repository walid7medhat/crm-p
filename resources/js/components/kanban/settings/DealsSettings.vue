<template>
  <div class="deal-stage-settings-container">
    <div class="page-header">
      <div>
        <h6 class="page-title">Deal Stage Settings</h6>
        <p class="page-subtitle">Manage deal stages by type. Create, rename, and recolor stages for Primary, Secondary, and Rental pipelines.</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-outline-secondary btn-sm" :disabled="loading || creatingNewStage" @click="fetchStages">
          <iconify-icon icon="lucide:rotate-ccw" class="me-1" />
          Refresh
        </button>
      </div>
    </div>

    <div class="settings-card stage-editor-card">
      <div class="card-head">
        <div>
          <div class="card-title">Deal Stage Setup</div>
          <div class="card-desc">Select a deal type tab, then manage all stages for that pipeline.</div>
        </div>
      </div>

      <div class="deal-type-tabs">
        <button
          v-for="tab in dealTypeTabs"
          :key="tab.id"
          type="button"
          class="deal-type-tab"
          :class="{ active: activeDealType === tab.id }"
          @click="activeDealType = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="new-stage-bar">
        <div class="new-stage-bar-label">
          <iconify-icon icon="lucide:plus-circle" class="new-stage-icon" />
          <span>New {{ activeDealTypeLabel }} stage</span>
        </div>
        <div class="new-stage-bar-fields">
          <input
            v-model="newStageName"
            type="text"
            class="form-control form-control-sm new-stage-input"
            placeholder="Stage name"
            :disabled="creatingNewStage"
            @keydown.enter.prevent="createNewStage"
          />
          <input
            v-model="newStageColor"
            type="color"
            class="form-control form-control-color new-stage-color"
            title="Stage color"
            :disabled="creatingNewStage"
          />
          <button
            type="button"
            class="btn btn-primary btn-sm new-stage-add"
            :disabled="creatingNewStage || !newStageName.trim()"
            @click="createNewStage"
          >
            <span v-if="creatingNewStage">Adding...</span>
            <span v-else>Add stage</span>
          </button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

      <div v-else-if="error" class="text-center py-4">
        <iconify-icon icon="lucide:alert-circle" class="text-danger mb-2" style="font-size: 32px;" />
        <p class="text-danger mb-2">{{ error }}</p>
        <button class="btn btn-outline-primary btn-sm" @click="fetchStages">Try again</button>
      </div>

      <div v-else class="stage-editor-list">
        <div v-if="activeStages.length === 0" class="stage-empty-state">
          No {{ activeDealTypeLabel.toLowerCase() }} stages found yet.
        </div>
        <div v-for="stage in activeStages" :key="stage.id" class="stage-editor-row">
          <div class="stage-preview-chip" :style="{ '--stage-color': stageDrafts[stage.id]?.color || stage.color || '#e2e8f0' }">
            <span class="stage-preview-order">{{ stage.order }}</span>
            <span class="stage-preview-name">{{ stageDrafts[stage.id]?.name || stage.name }}</span>
          </div>

          <div class="stage-editor-fields">
            <input
              v-model="stageDrafts[stage.id].name"
              type="text"
              class="form-control form-control-sm"
              placeholder="Stage name"
            />
            <input
              v-model="stageDrafts[stage.id].color"
              type="color"
              class="form-control form-control-color stage-color-input"
              title="Select stage color"
            />
            <button
              class="btn btn-outline-secondary btn-sm"
              :disabled="!isStageDirty(stage) || savingStageMap[stage.id]"
              @click="resetStageDraft(stage)"
            >
              Reset
            </button>
            <button
              class="btn btn-primary btn-sm"
              :disabled="!isStageDirty(stage) || savingStageMap[stage.id]"
              @click="saveStageMeta(stage)"
            >
              {{ savingStageMap[stage.id] ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const dealTypeTabs = [
  { id: 'primary', label: 'Primary' },
  { id: 'secondary', label: 'Secondary' },
  { id: 'rental', label: 'Rental' },
]

const loading = ref(true)
const error = ref(null)
const stages = ref([])
const activeDealType = ref('primary')
const stageDrafts = ref({})
const savingStageMap = ref({})

const newStageName = ref('')
const newStageColor = ref('#3b82f6')
const creatingNewStage = ref(false)

const activeDealTypeLabel = computed(() => {
  return dealTypeTabs.find((tab) => tab.id === activeDealType.value)?.label || 'Primary'
})

const activeStages = computed(() => {
  return [...stages.value].sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
})

function normalizeStagesFromResponse(payload) {
  if (Array.isArray(payload?.data?.data?.data)) return payload.data.data.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}

function hydrateDrafts(nextStages) {
  const nextDrafts = {}
  nextStages.forEach((stage) => {
    nextDrafts[stage.id] = {
      name: stage.name || '',
      color: stage.color || '#3b82f6',
    }
  })
  stageDrafts.value = nextDrafts
}

async function fetchStages() {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/stages', {
      params: {
        stage_type: 'deal',
        deal_type: activeDealType.value,
      },
    })
    const responseStages = normalizeStagesFromResponse(response)
    stages.value = responseStages
    hydrateDrafts(responseStages)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to load deal stages'
  } finally {
    loading.value = false
  }
}

async function createNewStage() {
  if (!newStageName.value.trim()) return
  creatingNewStage.value = true
  try {
    await api.post('/stages', {
      name: newStageName.value.trim(),
      color: newStageColor.value || '#3b82f6',
      stage_type: 'deal',
      deal_type: activeDealType.value,
    })
    newStageName.value = ''
    newStageColor.value = '#3b82f6'
    await fetchStages()
    showNotification('Deal stage created successfully', 'success')
  } catch (err) {
    showNotification(err?.response?.data?.message || 'Failed to create stage', 'error')
  } finally {
    creatingNewStage.value = false
  }
}

function isStageDirty(stage) {
  const draft = stageDrafts.value[stage.id]
  if (!draft) return false
  return (draft.name || '').trim() !== (stage.name || '').trim() || String(draft.color || '').toLowerCase() !== String(stage.color || '').toLowerCase()
}

function resetStageDraft(stage) {
  stageDrafts.value[stage.id] = {
    name: stage.name || '',
    color: stage.color || '#3b82f6',
  }
}

async function saveStageMeta(stage) {
  const draft = stageDrafts.value[stage.id]
  if (!draft || !draft.name?.trim()) {
    showNotification('Stage name is required', 'warning')
    return
  }

  savingStageMap.value = { ...savingStageMap.value, [stage.id]: true }
  try {
    await api.put(`/stages/${stage.id}`, {
      name: draft.name.trim(),
      color: draft.color || stage.color,
      stage_type: 'deal',
      deal_type: stage.deal_type || activeDealType.value,
    })
    stage.name = draft.name.trim()
    stage.color = draft.color || stage.color
    showNotification('Stage updated successfully', 'success')
  } catch (err) {
    showNotification(err?.response?.data?.message || 'Failed to update stage', 'error')
  } finally {
    savingStageMap.value = { ...savingStageMap.value, [stage.id]: false }
  }
}

function showNotification(message, type = 'info') {
  if (window.$showNotification) {
    window.$showNotification(message, type)
    return
  }
  Swal.fire({
    icon: type,
    title: message,
    timer: 1500,
    showConfirmButton: false,
  })
}

onMounted(() => {
  fetchStages()
})

watch(activeDealType, () => {
  fetchStages()
})
</script>

<style scoped>
.deal-stage-settings-container {
  padding: 16px 18px 20px;
  min-height: auto;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #ffffff;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  margin-top: 12px;
}

.page-title {
  margin: 0;
  font-weight: 700;
  font-size: 15px;
  color: #0f172a;
}

.page-subtitle {
  margin: 6px 0 0 0;
  color: #64748b;
  font-size: 13px;
}

.page-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.settings-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  box-shadow: 0 1px 6px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}

.card-head {
  padding: 16px;
  border-bottom: 1px solid #eef2f7;
}

.card-title {
  font-weight: 700;
  color: #0f172a;
  font-size: 14px;
}

.card-desc {
  margin-top: 4px;
  color: #64748b;
  font-size: 12.5px;
  max-width: 60ch;
}

.deal-type-tabs {
  display: flex;
  gap: 8px;
  padding: 14px 16px 8px;
  flex-wrap: wrap;
}

.deal-type-tab {
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
  border-radius: 999px;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 700;
  transition: all 0.16s ease;
}

.deal-type-tab.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #ffffff;
}

.new-stage-bar {
  margin: 0 16px 14px;
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px dashed #cbd5e1;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.new-stage-bar-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 800;
  color: #0f172a;
}

.new-stage-icon {
  font-size: 16px;
  color: #2563eb;
}

.new-stage-bar-fields {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.new-stage-input {
  flex: 1 1 200px;
  min-width: 160px;
  border-radius: 10px !important;
  border: 1px solid #e2e8f0 !important;
  font-size: 12px !important;
}

.new-stage-color {
  width: 44px;
  height: 34px;
  padding: 2px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
}

.new-stage-add {
  border-radius: 10px;
  font-weight: 700;
  padding-left: 14px;
  padding-right: 14px;
}

.stage-editor-list {
  padding: 12px 16px 16px;
  display: grid;
  gap: 10px;
}

.stage-empty-state {
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 14px;
  text-align: center;
  color: #64748b;
  font-size: 12.5px;
}

.stage-editor-row {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px;
  background: #fff;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.stage-preview-chip {
  position: relative;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 6px 10px 6px 12px;
  min-width: 170px;
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  overflow: hidden;
}

.stage-preview-chip::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--stage-color);
}

.stage-preview-order {
  font-weight: 700;
  font-size: 12px;
  color: #0f172a;
}

.stage-preview-name {
  font-size: 12px;
  color: #334155;
  font-weight: 500;
  max-width: 130px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.stage-editor-fields {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 250px;
}

.stage-color-input {
  width: 44px;
  min-width: 44px;
  padding: 2px;
  border-radius: 8px;
}
</style>

