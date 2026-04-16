<template>
  <div class="deal-docs-readonly">
    <div v-if="normalizedDocs.length === 0" class="empty-state">
      <iconify-icon icon="lucide:folder-open" class="empty-icon" />
      <p class="empty-text">No documents uploaded</p>
      <p class="empty-hint">Upload documents from edit mode</p>
    </div>
    
    <div v-else class="documents-grid">
      <div 
        v-for="doc in normalizedDocs" 
        :key="doc.key" 
        
      >
        <label class="info-label">{{ doc.categoryLabel }}</label>
        <div class="document-card">
            <div class="document-preview">
            
              <div 
              
                class="preview-placeholder"
                @click="previewDocument(doc)"
              >
                <iconify-icon :icon="fileIcon(doc)" class="placeholder-icon" />
              </div>
            </div>
            
            <div class="document-info">
              <div class="document-name">{{ doc.displayName }}</div>
              <div class="document-meta">
                <span v-if="doc.sizeLabel" class="document-size">{{ doc.sizeLabel }}</span>
              </div>
            </div>
            
   
          </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div v-if="previewDoc" class="modal-overlay" @click="closePreview">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>{{ previewDoc.name }}</h3>
          <button class="modal-close" @click="closePreview">
            <iconify-icon icon="lucide:x" />
          </button>
        </div>
        <div class="modal-body">
          <img 
            v-if="isImage(previewDoc)" 
            :src="previewDoc.url" 
            :alt="previewDoc.name"
            class="preview-full-image"
          />
          <iframe 
            v-else-if="isPdf(previewDoc)"
            :src="previewDoc.url"
            class="preview-pdf"
          ></iframe>
          <div v-else class="preview-placeholder-large">
            <iconify-icon :icon="fileIcon(previewDoc)" class="large-icon" />
            <p>Preview not available for this file type</p>
            <button @click="downloadDocument(previewDoc)" class="btn-download">
              Download File
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref ,onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  documents: { type: Array, default: () => [] },
  dealId: { type: [Number, String], default: null }
})

const emit = defineEmits(['delete-document'])

const activeMenu = ref(null)
const previewDoc = ref(null)

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
// Close menu when clicking outside
function handleClickOutside(event) {
  const el = event.target

  if (
    el.closest('.document-actions') ||
    el.closest('.dropdown-menu')
  ) {
    return
  }

  activeMenu.value = null
}


// Document normalization functions
function extractName(doc, index) {
  if (typeof doc === 'string') return doc.split('/').pop() || `Document ${index + 1}`
  return doc.file_name || doc.filename || doc.original_name || doc.title || doc.doc_name || `Document ${index + 1}`
}

function extractUrl(doc) {
  if (typeof doc === 'string') return doc
  return doc.url || doc.file_url || doc.path || doc.link || null
}

function extractCategory(doc) {
  if (typeof doc === 'string') return 'documents'
  return doc.document_type || doc.type_name || doc.label || doc.category || 'documents'
}

function formatCategoryLabel(category) {
  if (!category) return 'Document'
  return category
    .replace(/[_-]+/g, ' ')
    .split(' ')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}

function extractMime(doc) {
  if (typeof doc === 'string') return ''
  return doc.type || doc.mime_type || doc.mime || ''
}

function extractSize(doc) {
  if (typeof doc === 'string') return null
  return doc.size || doc.file_size
}

function formatSize(bytes) {
  if (!bytes || bytes === '' || isNaN(Number(bytes))) return ''
  const n = Number(bytes)
  if (n <= 0) return ''
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(n) / Math.log(k))
  return `${parseFloat((n / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

const normalizedDocs = computed(() => {
  const list = Array.isArray(props.documents) ? props.documents : []
  
  return list.map((doc, i) => {
    const name = extractName(doc, i)
    const url = extractUrl(doc)
    const category = extractCategory(doc)
    const mime = extractMime(doc)
    const size = extractSize(doc)
    
    // Create a clean display name without extension for better UI
    const displayName = name.length > 30 ? name.substring(0, 27) + '...' : name
    
    return {
      key: doc.id || doc.doc_id || `doc-${i}`,
      name: name,
      displayName: name,
      url: url,
      category: category,
      categoryLabel: formatCategoryLabel(category),
      mime: mime,
      size: size,
      sizeLabel: formatSize(size),
      raw: doc
    }
  })
})

function isImage(doc) {
  if (!doc.mime) {
    const ext = doc.name?.toLowerCase().split('.').pop()
    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)
  }
  return doc.mime.startsWith('image/')
}

function isPdf(doc) {
  if (!doc.mime) {
    return doc.name?.toLowerCase().endsWith('.pdf')
  }
  return doc.mime === 'application/pdf'
}

function fileIcon(doc) {
  if (isImage(doc)) return 'lucide:image'
  if (isPdf(doc)) return 'lucide:file-text'
  const m = (doc.mime || '').toLowerCase()
  const n = (doc.name || '').toLowerCase()
  if (m.includes('word') || n.endsWith('.doc') || n.endsWith('.docx')) return 'lucide:file-text'
  if (m.includes('sheet') || n.endsWith('.xls') || n.endsWith('.xlsx')) return 'lucide:table'
  return 'lucide:file'
}

function toggleMenu(key) {
  activeMenu.value = activeMenu.value === key ? null : key
}

function previewDocument(doc) {
  if (doc.url) {
    previewDoc.value = doc
    activeMenu.value = null
  }
}

function closePreview() {
  previewDoc.value = null
}

function downloadDocument(doc) {
  if (doc.url) {
    const link = document.createElement('a')
    link.href = doc.url
    link.download = doc.name
    link.target = '_blank'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }
  activeMenu.value = null
}

function deleteDocument(doc) {
  if (confirm(`Are you sure you want to delete "${doc.name}"?`)) {
    emit('delete-document', doc)
  }
  activeMenu.value = null
}
</script>

<style scoped>
.deal-docs-readonly {
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  background: #F8FAFC;
  border-radius: 12px;
  border: 1px dashed #E2E8F0;
}

.empty-icon {
  font-size: 48px;
  color: #94A3B8;
  margin-bottom: 16px;
}

.empty-text {
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  margin: 0 0 4px 0;
}

.empty-hint {
  font-size: 12px;
  color: #94A3B8;
  margin: 0;
}

/* Documents Grid */
.documents-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.document-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #FFFFFF;
  border: 1px solid #F1F5F9;
  border-radius: 12px;
  transition: all 0.2s ease;
  position: relative;
}

.document-card:hover {
  border-color: #E2E8F0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

/* Document Preview */
.document-preview {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
  background: #F8FAFC;
  cursor: pointer;
}

.preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #F1F5F9;
}

.placeholder-icon {
  font-size: 24px;
  color: #64748B;
}

/* Document Info */
.document-info {
  flex: 1;
  min-width: 0;
}

.document-name {
  font-size: 12px;
  font-weight: 500;
  color: #0F172A;
  margin-bottom: 4px;
  line-height: 1.4;
}

.document-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
}

.document-type {
  color: #3B82F6;
  background: #EFF6FF;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.document-size {
  color: #94A3B8;
}

/* Document Actions */
.document-actions {
  position: relative;
  flex-shrink: 0;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #94A3B8;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #F1F5F9;
  color: #475569;
}

/* Dropdown Menu */
.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  min-width: 160px;
  z-index: 1000;
  overflow: hidden;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  font-size: 13px;
  color: #334155;
  cursor: pointer;
  transition: background 0.2s;
  text-align: left;
}

.dropdown-item:hover {
  background: #F8FAFC;
}

.dropdown-item.delete-item {
  color: #EF4444;
}

.dropdown-item.delete-item:hover {
  background: #FEF2F2;
}

.dropdown-item iconify-icon {
  font-size: 16px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  animation: fadeIn 0.2s ease;
}

.modal-content {
  background: #FFFFFF;
  border-radius: 16px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid #F1F5F9;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 600;
  color: #0F172A;
  margin: 0;
}

.modal-close {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #94A3B8;
}

.modal-close:hover {
  background: #F1F5F9;
  color: #475569;
}

.modal-body {
  flex: 1;
  overflow: auto;
  padding: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.preview-full-image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
  border-radius: 8px;
}

.preview-pdf {
  width: 100%;
  height: 70vh;
  border: none;
  border-radius: 8px;
}

.preview-placeholder-large {
  text-align: center;
  padding: 48px;
}

.large-icon {
  font-size: 64px;
  color: #94A3B8;
  margin-bottom: 16px;
}

.btn-download {
  margin-top: 16px;
  padding: 8px 24px;
  background: #3B82F6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.btn-download:hover {
  background: #2563EB;
}

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>