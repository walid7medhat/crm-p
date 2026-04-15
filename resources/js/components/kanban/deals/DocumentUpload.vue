<template>
  <div class="document-upload-container deal-figma-ui">
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
    </div>

    <!-- Uploaded Files List -->
    <div v-if="groupedFiles.length > 0" class="uploaded-files-list mt-3">
      <div v-for="group in groupedFiles" :key="group.type" class="file-group mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2 d-none">
          <h6 class="file-group-title mb-0">{{ getTypeName(group.type) }}</h6>
          <span v-if="isTypeRequired(group.type)" class="text-danger small">
            Required
          </span>
        </div>
        <div class="file-grid">
          <div v-for="file in group.files" :key="file.id" class="file-item border rounded mb-2">
            <iconify-icon 
              icon="lucide:x" 
              class="remove-icon" 
              @click="removeFile(group.type, file.id)"
            />
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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  category: { type: String, default: 'buyer' },
  documentTypes: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const selectedType = ref(props.documentTypes[0]?.id || '')
const filesByType = ref({})

// Initialize filesByType
function initializeFilesByType() {
  filesByType.value = {}
  props.documentTypes.forEach(type => {
    filesByType.value[type.id] = []
  })
}

onMounted(() => {
  initializeFilesByType()
})

watch(() => props.documentTypes, () => {
  initializeFilesByType()
}, { deep: true })

// Computed properties
const groupedFiles = computed(() => {
  return Object.entries(filesByType.value)
    .filter(([type, files]) => files.length > 0)
    .map(([type, files]) => ({
      type,
      files
    }))
})

const missingRequiredDocs = computed(() => {
  const missing = []
  props.documentTypes.forEach(type => {
    if (type.required && (!filesByType.value[type.id] || filesByType.value[type.id].length === 0)) {
      missing.push(type.name)
    }
  })
  return missing
})

// Helper functions
function hasFilesForType(typeId) {
  return filesByType.value[typeId]?.length > 0
}

function getFileCountForType(typeId) {
  return filesByType.value[typeId]?.length || 0
}

function isTypeRequired(typeId) {
  const type = props.documentTypes.find(t => t.id === typeId)
  return type?.required || false
}

function canUploadMoreForType(typeId) {
  const type = props.documentTypes.find(t => t.id === typeId)
  // For required types, allow multiple files
  return true
}

function getTypeName(typeId) {
  const type = props.documentTypes.find(t => t.id === typeId)
  return type ? type.name : typeId
}

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files)
  addFiles(selectedFiles)
  event.target.value = ''
}

function handleDrop(event) {
  const droppedFiles = Array.from(event.dataTransfer.files)
  addFiles(droppedFiles)
}

function addFiles(newFiles) {
  newFiles.forEach(file => {
    if (!filesByType.value[selectedType.value]) {
      filesByType.value[selectedType.value] = []
    }
    
    // ✅ إضافة كل البيانات المهمة للملف
    filesByType.value[selectedType.value].push({
      id: Date.now() + Math.random(),
      file: file,
      name: file.name,
      size: file.size,
      type: file.type,
       mime_type: file.type, 
      document_type: selectedType.value,     // national_id, passport, etc.
      category: props.category,               // buyer, seller, etc.
      party_type: props.category,              // buyer, seller, etc.
      status: 'pending'
    })
  })
}

function removeFile(typeId, fileId) {
  if (filesByType.value[typeId]) {
    filesByType.value[typeId] = filesByType.value[typeId].filter(f => f.id !== fileId)
  }
}

function clearAllFiles() {
  initializeFilesByType()
}

// Watch for changes and emit
watch(filesByType, (newFilesByType) => {
  const allFiles = []
  Object.entries(newFilesByType).forEach(([type, files]) => {
    files.forEach(file => {
      allFiles.push({
        ...file,
        category: props.category,
        document_type: type,
        document_category: props.category
      })
    })
  })
  emit('update:modelValue', allFiles)
}, { deep: true })

// File helpers
function getFileIcon(mimeType) {
  if (mimeType?.includes('pdf')) return 'lucide:file-text'
  if (mimeType?.includes('image')) return 'lucide:image'
  if (mimeType?.includes('word')) return 'lucide:file-text'
  return 'lucide:file'
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

defineExpose({
  clearAllFiles,
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

.doc-tab.required:not(.active) {
  /* border-left: 3px solid #ef4444; */
}

.doc-tab.has-files:not(.active) {
  background: #fff;
  border-color: #e2e8f0;
  color: #64748b;
}

.file-count-badge {
  /* background: #FAA300; */
  color: white;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  padding: 0;
  font-size: 12px;
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
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.file-item {
  position: relative;
  width: 140px;
  min-height: 100px;
  background: #FFFFFF;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  padding: 12px 10px;
}

.file-item-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
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
}

.file-size {
  font-size: 11px;
  color: #9ca3af !important;
}

.remove-icon {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ef233c;
  color: #fff;
  cursor: pointer;
  padding: 3px;
  transition: color 0.2s;
}

.remove-icon:hover {
  color: #fff;
  background: #dc1f37;
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.uploaded-files-list{
    display:flex !important;
}
</style>