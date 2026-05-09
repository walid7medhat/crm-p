<template>
  <div
    class="document-upload-container deal-figma-ui"
    ref="filesSection"
    :class="{ 'is-compact': compact }"
  >
    <div class="document-upload-head">
      <div class="document-upload-title">
        <iconify-icon icon="lucide:file-text" />
        <span>Document Upload</span>
      </div>
      <span class="document-upload-pill" v-if="missingRequiredDocs.length > 0">Required documents missing: {{ missingRequiredDocs.join(', ') }}</span>
    </div>

    <!-- Unified design for ALL categories (including property) -->
    <div class="document-box-grid">
      <div v-for="type in documentTypes" :key="type.id" class="document-type-group">
        <div class="document-type-header">
          <div class="document-box-label">
            <div>
              {{ getDisplayName(type) }}
              <span v-if="type.required" class="text-danger">*</span>
            </div>
            <button
              type="button"
              class="add-box-btn"
              @click="addNewBox(type.id)"
              :title="`Add another ${getDisplayName(type)}`"
            >
              <iconify-icon icon="lucide:plus" />
            </button>
          </div>
        </div>

        <div class="document-boxes-container">
          <div
            v-for="box in getBoxesForType(type.id)"
            :key="box.id"
            class="document-box-wrapper"
          >
            <div
              class="document-box"
              :class="{ required: type.required, uploaded: box.files.length > 0 }"
            >
              <button
                v-if="getBoxesForType(type.id).length > 1"
                type="button"
                class="remove-box-btn"
                @click.stop="removeBox(type.id, box.id)"
                title="Remove this box"
              >
                <iconify-icon icon="lucide:x" />
              </button>

              <div class="document-box-content" @click="triggerFileInput(type.id, box.id)">
                <template v-if="box.files.length > 0">
                  <img
                    v-if="isImageFile(box.files[0])"
                    :src="resolveViewTarget(box.files[0])"
                    alt="uploaded preview"
                    class="document-box-preview"
                  />
                  <iconify-icon
                    v-else
                    :icon="getFileIcon(box.files[0]?.type || box.files[0]?.mime_type)"
                    class="document-box-icon uploaded-icon"
                  />
                  <div class="document-box-uploaded-name">
                    {{ box.files[0]?.name || 'Uploaded' }}
                  </div>
                  <div class="document-box-actions">
                    <button type="button" class="document-box-action-btn" @click.stop="viewFile(box.files[0])">
                      View
                    </button>
                    <button
                      type="button"
                      class="document-box-action-btn danger"
                      @click.stop="removeFile(type.id, box.id, box.files[0]?.id)"
                    >
                      Delete
                    </button>
                  </div>
                </template>

                <template v-else>
                  <iconify-icon icon="lucide:upload" class="document-box-icon" />
                  <div class="document-box-upload-text">Upload {{ getDisplayName(type) }}</div>
                  <div class="document-box-upload-hint">Max 10MB · JPG, PNG, PDF</div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <input
      ref="fileInput"
      type="file"
      class="d-none"
      :multiple="false"
      @change="handleFileSelect"
      accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
    />
    
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
import { ref, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  category: { type: String, default: 'buyer' },
  documentTypes: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
  dealId: { type: [Number, String], default: null },
  propertyId: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:modelValue'])

const fileInput = ref(null)
const selectedBoxRef = ref({ typeId: null, boxId: null })
const isHydratingFromModel = ref(false)
const previewModal = ref({ open: false, url: '', kind: 'file' })
const filesSection = ref(null)

const alternativeGroups = ref([
  {
    groupId: 'identification',
    types: ['national_id', 'passport'], 
    name: 'Identification Documents'
  }
])
const alternativePairs = ref([
  { type1: 'national_id', type2: 'passport' }
])

const boxesByType = ref({})

function generateId() {
  return Date.now() + '-' + Math.random().toString(36).substr(2, 9)
}

/** True if this slot has a real file (uploaded or existing), not an empty drop zone. */
function boxHasContent(box) {
  if (!box?.files?.length) return false
  return box.files.some(
    (f) =>
      f &&
      (f.is_existing ||
        f.file ||
        f.url ||
        f.file_url ||
        f.path)
  )
}

/** After the last filled slot, always keep one empty slot so the user can add another file. */
function ensureTrailingEmptySlot(typeId) {
  if (!boxesByType.value[typeId]) {
    boxesByType.value[typeId] = []
  }
  const boxes = boxesByType.value[typeId]
  if (!boxes.length) {
    boxes.push({ id: generateId(), files: [] })
    return
  }
  const last = boxes[boxes.length - 1]
  if (boxHasContent(last)) {
    boxes.push({ id: generateId(), files: [] })
  }
}

function ensureTrailingEmptySlotsAllTypes(nextBoxes) {
  props.documentTypes.forEach((type) => {
    const tid = type.id
    let arr = nextBoxes[tid]
    if (!arr || arr.length === 0) {
      nextBoxes[tid] = [{ id: generateId(), files: [] }]
      return
    }
    const last = arr[arr.length - 1]
    if (boxHasContent(last)) {
      arr.push({ id: generateId(), files: [] })
    }
  })
}

function getDisplayName(type) {
  if (type.id === 'national_id') {
    return 'Emirates ID'
  }
  return type.name
}

function initializeBoxes() {
  const next = {}
  props.documentTypes.forEach(type => {
    next[type.id] = [
      {
        id: generateId(),
        files: []
      }
    ]
  })
  boxesByType.value = next
}

function getBoxesForType(typeId) {
  return boxesByType.value[typeId] || []
}

function addNewBox(typeId) {
  if (!boxesByType.value[typeId]) {
    boxesByType.value[typeId] = []
  }
  
  boxesByType.value[typeId].push({
    id: generateId(),
    files: []
  })
  
  nextTick(() => {
    const wrappers = document.querySelectorAll(`.document-type-group .document-box-wrapper`)
    const newBox = wrappers[wrappers.length - 1]
    // newBox?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

async function deleteExistingServerFile(typeId, file) {
  if (props.category === 'property' && props.dealId != null && props.propertyId != null) {
    const filePath = file.path || file.raw?.path
    if (!filePath) {
      throw new Error('Missing file path for property document')
    }
    const documentType = typeId === 'spa' ? 'spa_document' : 'payment_proof'
    await axios.delete('/api/deals/property-document', {
      data: {
        deal_id: props.dealId,
        property_id: props.propertyId,
        document_type: documentType,
        file_path: filePath,
      },
    })
    return
  }
  if (file.id == null || (typeof file.id === 'string' && !/^\d+$/.test(String(file.id)))) {
    throw new Error('Missing document id')
  }
  await axios.delete(`/api/deals/documents/${file.id}`)
}

function removeBox(typeId, boxId) {
  const boxes = boxesByType.value[typeId]
  if (boxes.length <= 1) {
    Swal.fire({
      title: 'Cannot remove',
      text: 'You need at least one box for each document type',
      icon: 'warning',
      confirmButtonText: 'OK'
    })
    return
  }
  
  const boxToRemove = boxes.find(b => b.id === boxId)
  const hasExistingFiles = boxToRemove?.files.some(f => f.is_existing)
  
  if (hasExistingFiles) {
    Swal.fire({
      title: 'Are you sure?',
      text: 'This box contains files that will be permanently deleted!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then(async (result) => {
      if (result.isConfirmed) {
        for (const file of boxToRemove.files) {
          if (file.is_existing) {
            try {
              await deleteExistingServerFile(typeId, file)
            } catch (err) {
              console.error(err)
            }
          }
        }
        boxesByType.value[typeId] = boxes.filter(b => b.id !== boxId)
        $showNotification('Box removed successfully', 'success')
      }
    })
  } else {
    boxesByType.value[typeId] = boxes.filter(b => b.id !== boxId)
    $showNotification('Box removed successfully', 'success')
  }
}

function triggerFileInput(typeId, boxId) {
  selectedBoxRef.value = { typeId, boxId }
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const selectedFiles = Array.from(event.target.files || [])
  const { typeId, boxId } = selectedBoxRef.value
  
  if (typeId && boxId) {
    // Add files to box
    addFilesToBox(selectedFiles, typeId, boxId)
    
    // Keep one empty slot after the last filled box (buyer, property, etc.)
    ensureTrailingEmptySlot(typeId)
  }
  
  selectedBoxRef.value = { typeId: null, boxId: null }
  event.target.value = ''
}

function addFilesToBox(newFiles, typeId, boxId) {
  const boxes = boxesByType.value[typeId]
  const targetBox = boxes?.find(b => b.id === boxId)
  
  if (!targetBox) return
  
  // For property category, allow multiple files per box
  // For other categories, one file per box
  if (props.category !== 'property') {
    targetBox.files = []
  }
  
  newFiles.forEach(file => {
    targetBox.files.push({
      id: generateId(),
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

async function removeFile(typeId, boxId, fileId) {
  const boxes = boxesByType.value[typeId]
  const box = boxes?.find(b => b.id === boxId)
  const file = box?.files.find(f => f.id === fileId)
  
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

  if (file.is_existing) {
    try {
      await deleteExistingServerFile(typeId, file)
    } catch (err) {
      console.error(err)
      $showNotification('Delete failed', 'error')
      return
    }
  }

  box.files = box.files.filter(f => f.id !== fileId)

  $showNotification('File deleted successfully', 'success')
  nextTick(() => {
    filesSection.value?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    })
  })
}

function hydrateFilesFromModelValue(model) {
  isHydratingFromModel.value = true
  
  const list = Array.isArray(model) ? model : []
  const next = {}
  
  props.documentTypes.forEach(type => {
    next[type.id] = []
  })
  
  const docsByType = {}
  list.forEach(doc => {
    const typeId = doc.document_type
    if (!docsByType[typeId]) {
      docsByType[typeId] = []
    }
    docsByType[typeId].push(doc)
  })
  
  Object.keys(docsByType).forEach(typeId => {
    next[typeId] = docsByType[typeId].map(doc => {
      const p = doc.path
      let resolvedUrl = doc.url || doc.file_url || null
      if (resolvedUrl) {
        resolvedUrl = normalizePublicStorageUrl(resolvedUrl) || resolvedUrl
      } else if (p && typeof p === 'string') {
        resolvedUrl = normalizePublicStorageUrl(p)
      }
      const hasServerFile = !!(resolvedUrl || p) && !doc.file
      return {
        id: doc.box_id || generateId(),
        files: [{
          id: doc.id || generateId(),
          file: doc.file || null,
          name: doc.name || doc.file_name || 'document',
          size: doc.size || doc.file_size || 0,
          type: doc.type || doc.mime_type || '',
          mime_type: doc.mime_type || doc.type || '',
          url: resolvedUrl,
          path: p || doc.path || null,
          raw: doc.raw ?? doc,
          document_type: typeId,
          category: doc.category || props.category,
          party_type: doc.party_type || props.category,
          status: doc.status || (hasServerFile ? 'existing' : 'pending'),
          is_existing: hasServerFile
        }]
      }
    })
    
    if (next[typeId].length === 0) {
      next[typeId] = [{ id: generateId(), files: [] }]
    }
  })
  
  props.documentTypes.forEach(type => {
    if (!next[type.id] || next[type.id].length === 0) {
      next[type.id] = [{ id: generateId(), files: [] }]
    }
  })

  ensureTrailingEmptySlotsAllTypes(next)
  
  boxesByType.value = next
  isHydratingFromModel.value = false
}

const missingRequiredDocs = computed(() => {
  const missing = []
  props.documentTypes.forEach(type => {
    if (isDocumentTypeRequired(type.id)) {
      const hasFile = hasFilesForType(type.id)
      if (!hasFile) {
        missing.push(getDisplayName(type))
      }
    }
  })
  
  alternativeGroups.value.forEach(group => {
    const typesInGroup = group.types
    const missingInGroup = typesInGroup.filter(typeId => {
      const hasFile = hasFilesForType(typeId)
      return !hasFile && isDocumentTypeRequired(typeId)
    })
    
    if (missingInGroup.length === typesInGroup.length && missingInGroup.length > 0) {
      missingInGroup.forEach(typeId => {
        const type = props.documentTypes.find(t => t.id === typeId)
        if (type && !missing.includes(getDisplayName(type))) {
          missing.push(getDisplayName(type))
        }
      })
    }
  })
  
  alternativePairs.value.forEach(pair => {
    const type1Required = isDocumentTypeRequired(pair.type1)
    const type2Required = isDocumentTypeRequired(pair.type2)
    const type1HasFile = hasFilesForType(pair.type1)
    const type2HasFile = hasFilesForType(pair.type2)
    
    if (type1Required && !type1HasFile && type2Required && !type2HasFile) {
      const type1 = props.documentTypes.find(t => t.id === pair.type1)
      const type2 = props.documentTypes.find(t => t.id === pair.type2)
      if (type1 && !missing.includes(getDisplayName(type1))) missing.push(getDisplayName(type1))
      if (type2 && !missing.includes(getDisplayName(type2))) missing.push(getDisplayName(type2))
    }
  })
  
  return missing
})

function hasFilesForType(typeId) {
  const boxes = boxesByType.value[typeId] || []
  return boxes.some((box) => boxHasContent(box))
}

function isImageFile(file) {
  const mimeType = (file?.mime_type || file?.type || '').toLowerCase()
  return mimeType.includes('image')
}

function resolveViewTarget(file) {
  if (!file) return null
  if (file.file instanceof Blob) return URL.createObjectURL(file.file)
  if (typeof file.file === 'string') {
    return normalizePublicStorageUrl(file.file) || file.file
  }
  const raw = file.path || file.file_url || file.url
  if (raw && typeof raw === 'string') {
    return normalizePublicStorageUrl(raw) || raw
  }
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
}

function closePreview() {
  previewModal.value = { open: false, url: '', kind: 'file' }
}

function getFileIcon(mimeType) {
  if (mimeType?.includes('pdf')) return 'lucide:file-text'
  if (mimeType?.includes('image')) return 'lucide:image'
  return 'lucide:file'
}

watch(
  boxesByType,
  (newVal) => {
    if (isHydratingFromModel.value) return

    const allFiles = []
    
    Object.entries(newVal || {}).forEach(([typeId, boxes]) => {
      boxes?.forEach((box, boxIndex) => {
        box.files?.forEach(file => {
          allFiles.push({
            ...file,
            category: props.category,
            document_type: typeId,
            document_category: props.category,
            box_id: box.id,
            box_index: boxIndex
          })
        })
      })
    })

    emit('update:modelValue', allFiles)
  },
  { deep: true, flush: 'post' }
)

watch(
  () => props.documentTypes,
  (types) => {
    if (!types?.length) return
    initializeBoxes()
    hydrateFilesFromModelValue(props.modelValue)
  },
  { immediate: true }
)

function isDocumentTypeRequired(typeId) {
  const docType = props.documentTypes.find(t => t.id === typeId)
  const originalRequired = docType?.required || false
  
  if (!originalRequired) return false
  if (hasFilesForType(typeId)) return false
  
  for (const pair of alternativePairs.value) {
    if (pair.type1 === typeId) {
      const hasAlternative = hasFilesForType(pair.type2)
      if (hasAlternative) return false
    }
    if (pair.type2 === typeId) {
      const hasAlternative = hasFilesForType(pair.type1)
      if (hasAlternative) return false
    }
  }
  
  for (const group of alternativeGroups.value) {
    if (group.types.includes(typeId)) {
      const otherTypes = group.types.filter(t => t !== typeId)
      const hasAnyOther = otherTypes.some(otherType => hasFilesForType(otherType))
      if (hasAnyOther) return false
    }
  }
  
  return true
}

defineExpose({
  missingRequiredDocs
})

const $showNotification = (message, type = 'success') => {
  console.log(message, type)
}
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
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.document-type-group {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 16px;
}

.document-type-header {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
}

.document-box-label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  margin-left: 5px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 8px;
}

.add-box-btn {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 2px 6px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.add-box-btn:hover {
  background: #e2e8f0;
  border-color: #94a3b8;
  color: #1e293b;
}

.add-box-btn iconify-icon {
  font-size: 12px;
}

.document-boxes-container {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.document-box-wrapper {
  position: relative;
}

.document-box {
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  background: #fff;
  min-height: 120px;
  padding: 10px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
  cursor: pointer;
  transition: all 0.2s;
}

.document-box:hover {
  border-color: #8ab5ff;
  background: #f8fbff;
}

.document-box.required {
  border-color: rgb(220, 53, 69);
}

.document-box.uploaded {
  border-style: solid;
  border-color: #8ab5ff;
  background: #f8fbff;
}

.remove-box-btn {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #ef4444;
  border: 2px solid #fff;
  color: white;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
  transition: all 0.2s;
  padding: 0;
}

.remove-box-btn:hover {
  background: #dc2626;
  transform: scale(1.05);
}

.remove-box-btn iconify-icon {
  font-size: 14px;
}

.document-box-content {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.document-box-icon {
  font-size: 20px;
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
  cursor: pointer;
}

.document-box-action-btn.danger {
  border-color: #fecaca;
  color: #b91c1c;
}

.document-box-action-btn:hover {
  background: #f1f5f9;
}

/* Compact mode styles */
.document-upload-container.is-compact .document-box-grid {
  gap: 16px;
}

.document-upload-container.is-compact .document-type-header {
  margin-bottom: 8px;
}

.document-upload-container.is-compact .document-box-label {
  font-size: 10px;
}

.document-upload-container.is-compact .add-box-btn {
  padding: 1px 4px;
  font-size: 10px;
}

.document-upload-container.is-compact .add-box-btn iconify-icon {
  font-size: 10px;
}

.document-upload-container.is-compact .document-boxes-container {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.document-upload-container.is-compact .document-box {
  min-height: 118px;
  padding: 8px;
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

/* Preview modal styles */
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

.d-none {
  display: none;
}

.text-danger {
  color: #ef4444;
}
</style>