<template>
  <div class="document-upload-container">
    <!-- Document Type Tabs -->
    <div class="doc-tabs d-flex gap-2 mb-3 flex-wrap">
      <button 
        v-for="type in documentTypes" 
        :key="type.id"
        class="doc-tab" 
        :class="{ active: selectedType === type.id }" 
        @click="selectedType = type.id"
      >
        {{ type.name }}
      </button>
    </div>

    <!-- Upload Area -->
    <div 
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

    <!-- Uploaded Files List - Grouped by type -->
    <div v-if="groupedFiles.length > 0" class="uploaded-files-list mt-3">
      <!-- Loop through each type group -->
      <div v-for="group in groupedFiles" :key="group.type" class="file-group mb-3">
        <h6 class="file-group-title mb-2">{{ getTypeName(group.type) }}</h6>
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
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  category: { type: String, default: 'buyer' }, // buyer, seller, tenant, landlord, property
  documentTypes: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const selectedType = ref(props.documentTypes[0]?.id || '')
const filesByType = ref({})

// Initialize filesByType when props change
function initializeFilesByType() {
  filesByType.value = {}
  props.documentTypes.forEach(type => {
    filesByType.value[type.id] = []
  })
}

// Initialize on mount and when documentTypes change
onMounted(() => {
  initializeFilesByType()
})

watch(() => props.documentTypes, () => {
  initializeFilesByType()
}, { deep: true })

// Computed property to group files for display
const groupedFiles = computed(() => {
  return Object.entries(filesByType.value)
    .filter(([type, files]) => files.length > 0)
    .map(([type, files]) => ({
      type,
      files
    }))
})

// Watch for changes and emit to parent
watch(filesByType, (newFilesByType) => {
  // Flatten all files from all types with category info
  const allFiles = []
  Object.entries(newFilesByType).forEach(([type, files]) => {
    files.forEach(file => {
      allFiles.push({
        ...file,
        category: props.category,  // ← هنا بنستخدم category مباشرة
        document_type: type
      })
    })
  })
  emit('update:modelValue', allFiles)
}, { deep: true })

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
    
    filesByType.value[selectedType.value].push({
      id: Date.now() + Math.random(),
      file: file,
      name: file.name,
      size: file.size,
      type: file.type,
      status: 'pending'
      // ملاحظة: مش بنضيف category هنا، لأنها هتتضاف في الـ emit
    })
  })
}

function removeFile(typeId, fileId) {
  if (filesByType.value[typeId]) {
    filesByType.value[typeId] = filesByType.value[typeId].filter(f => f.id !== fileId)
  }
}

// Reset function
function clearAllFiles() {
  initializeFilesByType()
}

// Expose reset function
defineExpose({
  clearAllFiles
})

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
</script>

<style scoped>
/* أضف الـ styles بتاعة الـ file-group */
.file-group-title {
  font-size: 13px;
  font-weight: 600;
  color: #01062C;
  margin-bottom: 8px;
}

.file-group {
  border-left: 3px solid #E2E8F0;
  padding-left: 12px;
}

/* باقي الـ styles زي ما هي */
.doc-tab {
  padding: 6px 14px;
  border-radius: 100px;
  border: 1px solid #E2E8F0;
  background: #fff;
  font-size: 12px;
  color: #64748B;
  cursor: pointer;
  transition: all 0.2s;
}

.doc-tab:hover {
  background: #F1F5F9;
}

.doc-tab.active {
  background: #0F172A;
  color: #fff;
  border-color: #0F172A;
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
  color: #94A3B8;
}

.file-item {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
}

.file-icon {
  font-size: 20px;
  color: #64748B;
}

.remove-icon {
  font-size: 18px;
  color: #EF4444;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.remove-icon:hover {
  opacity: 1;
}

.spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>