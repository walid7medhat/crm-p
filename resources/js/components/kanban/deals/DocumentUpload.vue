<template>
  <div class="document-upload-container">
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
      class="upload-zone border rounded p-4 text-center"
      @dragenter.prevent
      @dragover.prevent
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <iconify-icon icon="lucide:cloud-upload" class="upload-icon"></iconify-icon>
      <p class="upload-text mb-2">Drag and drop your files here</p>
      <p class="upload-hint text-muted small mb-2">JPEG, PNG and PDF formats, up to 50MB</p>
      <button type="button" class="btn btn-outline-secondary btn-sm" @click.stop="triggerFileInput">
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
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="file-group-title mb-0">{{ getTypeName(group.type) }}</h6>
          <span v-if="isTypeRequired(group.type)" class="text-danger small">
            Required
          </span>
        </div>
        <div v-for="(file, index) in group.files" :key="file.id" class="file-item d-flex align-items-center justify-content-between p-2 border rounded mb-2">
          <div class="d-flex align-items-center gap-2">
            <iconify-icon 
              :icon="getFileIcon(file.type)" 
              class="file-icon"
            />
            <span class="file-name">{{ file.name }}</span>
            <span class="file-size text-muted small">({{ formatFileSize(file.size) }})</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <iconify-icon 
              v-if="file.status === 'uploading'"
              icon="lucide:loader-2" 
              class="spinner"
            />
            <iconify-icon 
              v-else-if="file.status === 'success'"
              icon="lucide:check-circle" 
              class="text-success"
            />
            <iconify-icon 
              v-else-if="file.status === 'error'"
              icon="lucide:alert-circle" 
              class="text-danger"
            />
            <iconify-icon 
              icon="lucide:x" 
              class="remove-icon" 
              @click="removeFile(group.type, file.id)"
            />
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
.doc-tab.required {
  border-left: 3px solid #ef4444;
}

.doc-tab.has-files {
  background-color: #e8f5e9;
  border-color: #4caf50;
}

.file-count-badge {
  background: #0F172A;
  color: white;
  border-radius: 12px;
  padding: 2px 6px;
  font-size: 10px;
}

.file-group-title {
  font-size: 13px;
  font-weight: 600;
  color: #01062C;
}

.file-group {
  border-left: 3px solid #E2E8F0;
  padding-left: 12px;
}

.upload-zone {
  border: 2px dashed #E2E8F0;
  background: #F8FAFC;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-zone:hover {
  border-color: #0F172A;
  background: #F1F5F9;
}

.upload-icon {
  font-size: 32px;
  color: #64748B;
  margin-bottom: 8px;
}

.upload-text {
  font-size: 14px;
  color: #1E293B;
}

.upload-hint {
  font-size: 12px;
}

.file-item {
  background: #FFFFFF;
}

.file-icon {
  font-size: 20px;
  color: #64748B;
}

.file-name {
  font-size: 13px;
  color: #1E293B;
  font-weight: 500;
}

.file-size {
  font-size: 11px;
}

.remove-icon {
  cursor: pointer;
  color: #94A3B8;
  transition: color 0.2s;
}

.remove-icon:hover {
  color: #EF4444;
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>