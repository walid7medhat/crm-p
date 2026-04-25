<template>
  <div class="document-upload-container deal-figma-ui" :class="{ 'is-compact': compact }" ref="filesSection">
    <div class="document-upload-head">
      <div class="document-upload-title">
        <iconify-icon icon="lucide:file-text" />
        <span>Document Upload</span>
      </div>
      <span class="document-upload-pill" v-if="missingRequiredDocs.length > 0">Required documents missing: {{ missingRequiredDocs.join(', ') }}</span>
    </div>

    <div class="document-box-grid">
      <button
        v-for="type in documentTypes"
        :key="type.id"
        type="button"
       
        :class="{ required: type.required, uploaded: hasFilesForType(type.id) }"
        @click="triggerFileInput(type.id)"
      >
      <div class="document-box-label">
          {{ type.name }}
          <span v-if="type.required" class="text-danger">*</span>
        </div>
      <div  class="document-box">
        

        <template v-if="getPrimaryFileForType(type.id)">
          <img
            v-if="isImageFile(getPrimaryFileForType(type.id))"
            :src="resolveViewTarget(getPrimaryFileForType(type.id))"
            alt="uploaded preview"
            class="document-box-preview"
          />
          <iconify-icon
            v-else
            :icon="getFileIcon(getPrimaryFileForType(type.id)?.type || getPrimaryFileForType(type.id)?.mime_type)"
            class="document-box-icon uploaded-icon"
          />
          <div class="document-box-uploaded-name">
            {{ getPrimaryFileForType(type.id)?.name || 'Uploaded' }}
          </div>
          <div class="document-box-actions">
            <button type="button" class="document-box-action-btn" @click.stop="viewFile(getPrimaryFileForType(type.id))">View</button>
            <button type="button" class="document-box-action-btn danger" @click.stop="removeFile(type.id, getPrimaryFileForType(type.id)?.id)">Delete</button>
          </div>
        </template>

        <template v-else>
          <iconify-icon icon="lucide:upload" class="document-box-icon" />
          <div class="document-box-upload-text">Upload {{ type.name }}</div>
          <div class="document-box-upload-hint">Max 10MB · JPG, PNG, PDF</div>
        </template>
        </div>
      </button>
    </div>

    <input
      ref="fileInput"
      type="file"
      class="d-none"
      @change="handleFileSelect"
      accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
    />
    
    <!-- Required Documents Warning -->
    <!-- <div v-if="missingRequiredDocs.length > 0" class="alert alert-warning mt-3 small p-2">
      <iconify-icon icon="lucide:alert-triangle" class="me-1"></iconify-icon>
      Required documents missing: {{ missingRequiredDocs.join(', ') }}
    </div> -->

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
  documentTypes: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
import Swal from 'sweetalert2'
const fileInput = ref(null)

const selectedType = ref(null) // ✅ FIX 1 (was causing crash)
const pendingUploadType = ref(null)

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

    const hasSelectedType = types.some((type) => type.id === selectedType.value)
    if (!selectedType.value || !hasSelectedType) {
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

  list.forEach((doc, idx) => {
    const typeId = (doc.document_type && next[doc.document_type])
      ? doc.document_type
      : null

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

function getPrimaryFileForType(typeId) {
  const files = filesByType.value?.[typeId] || []
  return files.length ? files[0] : null
}

function isImageFile(file) {
  const mimeType = (file?.mime_type || file?.type || '').toLowerCase()
  return mimeType.includes('image')
}

function canUploadMoreForType(typeId) {
  if (!typeId) return false
  return true
}

// =========================
// FILE ACTIONS
// =========================
function triggerFileInput(typeId = null) {
  if (typeId) {
    pendingUploadType.value = typeId
    selectedType.value = typeId
  }
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files || [])
  const targetType = pendingUploadType.value || selectedType.value
  addFiles(selectedFiles, targetType)
  pendingUploadType.value = null
  event.target.value = ''
}

function handleDrop(event) {
  const droppedFiles = Array.from(event.dataTransfer.files || [])
  addFiles(droppedFiles)
}

function addFiles(newFiles, targetType = null) {
  const typeId = targetType || selectedType.value
  if (!typeId) return

  if (!filesByType.value[typeId]) {
    filesByType.value[typeId] = []
  }

  // Keep one primary file per document box.
  filesByType.value[typeId] = []
  newFiles.forEach(file => {
    filesByType.value[typeId].push({
      id: Date.now() + Math.random(),
      file,
      name: file.name,
      size: file.size,
      type: file.type,
      mime_type: file.type,
      document_type: typeId,
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
.document-upload-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.document-upload-title {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 400;
  color: #1f2937;
}

.document-upload-pill {
  background: #f6c453;
  color: #3d2d00;
  border-radius: 999px;
  font-size: 11px;
  padding: 2px 10px;
  font-weight: 600;
}

.document-box-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.document-box {
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  background: #fff;
  min-height: 152px;
  padding: 10px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
}

.document-box.required {
  border-color: #f59e0b;
}

.document-box.uploaded {
  border-style: solid;
  border-color: #8ab5ff;
  background: #f8fbff;
}

.document-box-label {
  /* position: absolute; */
  /* top: 8px;
  left: 8px;
  right: 8px; */
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 5px;
  margin-left: 5px;
}

.document-box-icon {
  font-size: 28px;
  color: #6b7280;
}

.document-box-preview {
  width: 100%;
  max-height: 72px;
  object-fit: cover;
  border-radius: 8px;
}

.document-box-uploaded-name {
  font-size: 12px;
  color: #334155;
  word-break: break-word;
  max-width: 100%;
}

.document-box-upload-text {
  font-size: 13px;
  color: #475569;
  font-weight: 500;
}

.document-box-upload-hint {
  font-size: 11px;
  color: #94a3b8;
}

.document-box-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.document-box-action-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 8px;
  font-size: 11px;
  padding: 2px 8px;
}

.document-box-action-btn.danger {
  border-color: #fecaca;
  color: #b91c1c;
}

.document-upload-container.is-compact .document-upload-head {
  margin-bottom: 6px;
}

.document-upload-container.is-compact .document-upload-title {
  font-size: 13px;
}

.document-upload-container.is-compact .document-upload-pill {
  font-size: 10px;
  padding: 1px 6px;
}

.document-upload-container.is-compact .document-box-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.document-upload-container.is-compact .document-box {
  min-height: 118px;
  padding: 8px;
}

.document-upload-container.is-compact .document-box-label {
  font-size: 10px;
  top: 6px;
  left: 7px;
  right: 7px;
}

.document-upload-container.is-compact .document-box-icon {
  font-size: 20px;
}

.document-upload-container.is-compact .document-box-preview {
  max-height: 52px;
}

.document-upload-container.is-compact .document-box-upload-text {
  font-size: 10px;
}

.document-upload-container.is-compact .document-box-upload-hint {
  font-size: 9px;
}

.document-upload-container.is-compact .document-box-uploaded-name {
  font-size: 9px;
}

.document-upload-container.is-compact .document-box-actions {
  gap: 6px;
}

.document-upload-container.is-compact .document-box-action-btn {
  font-size: 9px;
  padding: 1px 6px;
}

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