<template>
  <div class="document-upload-container deal-figma-ui"   ref="filesSection" >
    <!-- Document Type Tabs with Required Indicator -->
    <div class="doc-tabs d-flex gap-2 mb-3 flex-wrap">
      <button 
        v-for="type in documentTypes" 
        :key="type.id"
        class="doc-tab" 
        :class="{ 
          active: selectedType === type.id,
          required: type.required,
          'has-files': hasFilesForType(type.id)
        }" 
        @click="selectedType = type.id"
      >
        {{ type.name }}
        <span v-if="type.required" class="text-danger ms-1">*</span>
        <span v-if="hasFilesForType(type.id)" class="file-count-badge ms-1">
          {{ getFileCountForType(type.id) }}
        </span>
      </button>
    </div>

    <!-- Upload Area (show if max files not reached for required types) -->
    <div
   
      v-if="canUploadMoreForType(selectedType)"
      class="upload-zone border rounded"
      @dragenter.prevent
      @dragover.prevent
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <div class="upload-left">
        <iconify-icon icon="lucide:file-text" class="upload-icon"></iconify-icon>
        <div class="upload-copy">
          <p class="upload-text mb-1">Drag and drop your files</p>
          <p class="upload-hint text-muted small mb-0">JPEG, PNG and PDF formats, up to 50MB</p>
        </div>
      </div>
      <button type="button" class="btn-upload-file" @click.stop="triggerFileInput">
        Select File
      </button>
      <input 
        ref="fileInput"
        type="file" 
        class="d-none" 
        multiple
        @change="handleFileSelect"
        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
      />
      <input
        ref="replaceFileInput"
        type="file"
        class="d-none"
        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
        @change="handleReplaceFileSelect"
      />
    </div>

    <!-- Uploaded Files List -->
    <div v-if="selectedTypeFiles.length > 0" class="uploaded-files-list mt-3">
      <div class="file-group mb-3">
        <div class="file-grid">
          <div v-for="file in selectedTypeFiles" :key="file.id" class="file-item border rounded mb-2">
            <button type="button" class="file-actions-btn" @click.stop="toggleFileMenu(selectedType, file.id)">
              <iconify-icon icon="lucide:more-vertical" />
            </button>
            <div
              v-if="isFileMenuOpen(selectedType, file.id)"
              class="file-actions-menu"
              @click.stop
            >
              <button type="button" class="file-action-item" @click="viewFile(file)">
                <iconify-icon icon="lucide:eye" />
                <span>View document</span>
              </button>
              <button type="button" class="file-action-item delete" @click="removeFile(selectedType, file.id)">
                <iconify-icon icon="lucide:trash-2" />
                <span>Delete</span>
              </button>
            </div>
            <div class="file-item-content">
              <iconify-icon 
                :icon="getFileIcon(file.type)" 
                class="file-icon"
              />
              <span class="file-name">{{ file.name }}</span>
              <span class="file-size text-muted small">{{ formatFileSize(file.size) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Required Documents Warning -->
    <div v-if="missingRequiredDocs.length > 0" class="alert alert-warning mt-3 small p-2">
      <iconify-icon icon="lucide:alert-triangle" class="me-1"></iconify-icon>
      Required documents missing: {{ missingRequiredDocs.join(', ') }}
    </div>

    <div v-if="previewModal.open" class="doc-preview-backdrop" @click.self="closePreview">
      <div class="doc-preview-modal">
        <button type="button" class="doc-preview-close" @click="closePreview">
          <iconify-icon icon="lucide:x" />
        </button>
        <div class="doc-preview-body">
          <img
            v-if="previewModal.kind === 'image'"
            :src="previewModal.url"
            alt="Document preview"
            class="doc-preview-image"
          />
          <iframe
            v-else-if="previewModal.url"
            :src="previewModal.url"
            class="doc-preview-iframe"
            title="Document preview"
          />
          <div v-else class="doc-preview-empty">Preview is not available for this file.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount ,nextTick} from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  category: { type: String, default: 'buyer' },
  documentTypes: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue'])
import Swal from 'sweetalert2'
const fileInput = ref(null)
const replaceFileInput = ref(null)

const selectedType = ref(null) // ✅ FIX 1 (was causing crash)

const filesByType = ref({})
const openFileMenuKey = ref(null)
const pendingReplace = ref(null)
const isHydratingFromModel = ref(false)
const previewModal = ref({ open: false, url: '', kind: 'file' })
const filesSection = ref(null)
// =========================
// INIT
// =========================
function initializeFilesByType() {
  const next = {}
  props.documentTypes.forEach(type => {
    next[type.id] = []
  })
  filesByType.value = next
}

// =========================
// SAFE SELECTED TYPE INIT
// =========================
watch(
  () => props.documentTypes,
  (types) => {
    if (!types?.length) return

    initializeFilesByType()

    if (!selectedType.value) {
      selectedType.value = types[0].id
    }

    hydrateFilesFromModelValue(props.modelValue)
  },
  { immediate: true }
)

// =========================
// HYDRATE
// =========================
function hydrateFilesFromModelValue(model) {
  isHydratingFromModel.value = true

  const list = Array.isArray(model) ? model : []
  const next = {}

  props.documentTypes.forEach(type => {
    next[type.id] = []
  })

  const fallbackType = props.documentTypes?.[0]?.id

  list.forEach((doc, idx) => {
    const typeId =
      (doc.document_type && next[doc.document_type])
        ? doc.document_type
        : fallbackType

    if (!typeId || !next[typeId]) return

    next[typeId].push({
      id: doc.id || `${typeId}-${idx}`,
      file: doc.file || null,
      name: doc.name || doc.file_name || `document-${idx + 1}`,
      size: doc.size || doc.file_size || 0,
      type: doc.type || doc.mime_type || '',
      mime_type: doc.mime_type || doc.type || '',
      url: doc.url || doc.file_url || doc.path || null,
      document_type: typeId,
      category: doc.category || props.category,
      party_type: doc.party_type || props.category,
      status: doc.status || (doc.url ? 'existing' : 'pending'),
      is_existing: !!doc.url && !doc.file
    })
  })

  filesByType.value = next
  isHydratingFromModel.value = false
}

// =========================
// COMPUTED
// =========================
const selectedTypeFiles = computed(() => {
  return filesByType.value?.[selectedType.value] || []
})

const missingRequiredDocs = computed(() => {
  const missing = []
  props.documentTypes.forEach(type => {
    if (
      type.required &&
      (!filesByType.value[type.id] || filesByType.value[type.id].length === 0)
    ) {
      missing.push(type.name)
    }
  })
  return missing
})

// =========================
// SAFE HELPERS
// =========================
function hasFilesForType(typeId) {
  return filesByType.value?.[typeId]?.length > 0
}

function getFileCountForType(typeId) {
  return filesByType.value?.[typeId]?.length || 0
}

function canUploadMoreForType(typeId) {
  if (!typeId) return false
  return true
}

// =========================
// FILE ACTIONS
// =========================
function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files || [])
  addFiles(selectedFiles)
  event.target.value = ''
}

function handleDrop(event) {
  const droppedFiles = Array.from(event.dataTransfer.files || [])
  addFiles(droppedFiles)
}

function addFiles(newFiles) {
  if (!selectedType.value) return

  if (!filesByType.value[selectedType.value]) {
    filesByType.value[selectedType.value] = []
  }

  newFiles.forEach(file => {
    filesByType.value[selectedType.value].push({
      id: Date.now() + Math.random(),
      file,
      name: file.name,
      size: file.size,
      type: file.type,
      mime_type: file.type,
      document_type: selectedType.value,
      category: props.category,
      party_type: props.category,
      status: 'pending'
    })
  })
}

async function removeFile(typeId, fileId) {
  const file = filesByType.value?.[typeId]?.find(f => f.id === fileId)
  if (!file) return

  const result = await Swal.fire({
    title: 'Are you sure?',
    text: 'This file will be permanently deleted!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it',
    cancelButtonText: 'Cancel'
  })

  if (!result.isConfirmed) return

  // 🟡 لو فايل من السيرفر
  if (file.is_existing) {
    try {
      await axios.delete(`/api/deals/documents/${file.id}`)
    } catch (err) {
      console.error(err)
      $showNotification('Delete failed', 'error')
      return
    }
  }

  // 🟢 امسحي من UI
  filesByType.value[typeId] =
    filesByType.value[typeId].filter(f => f.id !== fileId)

  $showNotification('File deleted successfully', 'success')
   nextTick(() => {
    filesSection.value?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    })
  })
}

// =========================
// MENU
// =========================
function fileMenuKey(typeId, fileId) {
  return `${typeId}::${fileId}`
}

function toggleFileMenu(typeId, fileId) {
  const key = fileMenuKey(typeId, fileId)
  openFileMenuKey.value =
    openFileMenuKey.value === key ? null : key
}

function isFileMenuOpen(typeId, fileId) {
  return openFileMenuKey.value === fileMenuKey(typeId, fileId)
}

function closeFileMenuOnOutside(event) {
  if (
    event.target.closest('.file-actions-btn') ||
    event.target.closest('.file-actions-menu')
  ) return

  openFileMenuKey.value = null
}

// =========================
// VIEW FILE
// =========================
function resolveViewTarget(file) {
  if (!file) return null
  if (file.url) return file.url
  if (typeof file.file === 'string') return file.file
  if (file.file instanceof Blob) return URL.createObjectURL(file.file)
  return null
}

function viewFile(file) {
  const target = resolveViewTarget(file)
  if (!target) return

  const mime = (file?.mime_type || file?.type || '').toLowerCase()
  const isImage = mime.includes('image')

  previewModal.value = {
    open: true,
    url: target,
    kind: isImage ? 'image' : 'file'
  }

  openFileMenuKey.value = null
}

function closePreview() {
  previewModal.value = { open: false, url: '', kind: 'file' }
}

// =========================
// WATCH EMIT (SAFE)
// =========================
watch(
  filesByType,
  (newVal) => {
    if (isHydratingFromModel.value) return

    const allFiles = []

    Object.entries(newVal || {}).forEach(([type, files]) => {
      files?.forEach(file => {
        allFiles.push({
          ...file,
          category: props.category,
          document_type: type,
          document_category: props.category
        })
      })
    })

    emit('update:modelValue', allFiles)
  },
  { deep: true, flush: 'post' }
)

// =========================
// OUTSIDE CLICK
// =========================
onMounted(() => {
  document.addEventListener('click', closeFileMenuOnOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeFileMenuOnOutside)
})

// =========================
// HELPERS UI
// =========================
function getFileIcon(mimeType) {
  if (mimeType?.includes('pdf')) return 'lucide:file-text'
  if (mimeType?.includes('image')) return 'lucide:image'
  return 'lucide:file'
}

function formatFileSize(bytes) {
  if (!bytes) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

defineExpose({
  missingRequiredDocs
})
</script>


<style scoped>
/* Match create / edit deal forms: pill tabs, navy active */
.doc-tab {
  height: 30px;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 100px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1.2;
  transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.doc-tab:hover {
  border-color: #cbd5e1;
  color: #334155;
}

.doc-tab.active {
  background: #01062C !important;
  color: #fff !important;
  border-color: #01062C !important;
}

.doc-tab.has-files:not(.active) {
  background: #fff;
  border-color: #e2e8f0;
  color: #64748b;
}

.file-count-badge {
  /* background: #FAA300; */
  color: #01062C;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  padding: 0;
  font-size: 12px;
}
.doc-tab.active .file-count-badge {
  /* background: #FAA300; */
  color: #fff;

}
.file-group-title {
  font-size: 13px;
  font-weight: 600;
  color: #01062C;
}

.file-group {
  /*border-left: 3px solid #E2E8F0;*/
  padding-left: 12px;
}

.upload-zone {
  border: 1px dashed #E2E8F0;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s;
  border-radius: 8px;
  min-height: 74px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  position: relative;
}

.upload-zone::before {
  content: '';
  position: absolute;
  inset: 10px;
  border: 1px dashed #eef2f7;
  border-radius: 8px;
  pointer-events: none;
}

.upload-zone:hover {
  border-color: #d5dce5;
  background: #fbfdff;
}

.upload-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.upload-copy {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.upload-icon {
  font-size: 22px;
  color: #a3adb8;
  margin-bottom: 0;
}

.upload-text {
  font-size: 12px;
  color: #1f2937;
  font-weight: 500;
}

.upload-hint {
  font-size: 11px;
  color: #9ca3af !important;
}

.btn-upload-file {
  height: 36px;
  min-width: 100px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #4b5563;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
}

.btn-upload-file:hover {
  background: #f8fafc;
}

.file-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.file-item {
  position: relative;
  width: 100%;
  min-height: 72px;
  background: #FFFFFF;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  padding: 10px 12px;
}

.file-item-content {
  display: flex;
  flex-direction: row;
  align-items: center;
  text-align: left;
  gap: 10px;
  padding-right: 28px;
}

.file-icon {
  font-size: 22px;
  color: #d1d5db;
}

.file-name {
  font-size: 12px;
  color: #111827;
  font-weight: 500;
  line-height: 1.3;
  word-break: break-word;
  overflow-wrap: anywhere;
  flex: 1;
}

.file-size {
  font-size: 11px;
  color: #9ca3af !important;
  white-space: nowrap;
}

.file-actions-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: #000000 !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.file-actions-btn:hover {
  background: #f1f5f9;
  color: #000000 !important;
}

.file-actions-menu {
  position: absolute;
  top: 30px;
  right: 6px;
  min-width: 140px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  z-index: 20;
  overflow: hidden;
}

.file-action-item {
  width: 100%;
  border: none;
  background: #fff;
  color: #000000;
  padding: 8px 10px;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  text-align: left;
}

.file-action-item:hover {
  background: #f8fafc;
}

.file-action-item.delete {
  color: #000000;
}

.file-action-item iconify-icon {
  color: #000000 !important;
}

.doc-preview-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.doc-preview-modal {
  position: relative;
  width: min(900px, 100%);
  height: min(80vh, 760px);
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 18px 48px rgba(2, 6, 23, 0.28);
  overflow: hidden;
}

.doc-preview-close {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 32px;
  height: 32px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  background: #fff;
  color: #0f172a;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 1;
}

.doc-preview-body {
  width: 100%;
  height: 100%;
  padding: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}

.doc-preview-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 8px;
  background: #fff;
}

.doc-preview-iframe {
  width: 100%;
  height: 100%;
  border: none;
  border-radius: 8px;
  background: #fff;
}

.doc-preview-empty {
  font-size: 14px;
  color: #334155;
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.uploaded-files-list {
  display: block !important;
  width: 100%;
}

.file-group {
  width: 100%;
}
</style>