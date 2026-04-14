<template>
  <div class="deal-docs-readonly deal-figma-ui">
    <div v-if="normalizedDocs.length === 0" class="upload-zone upload-zone--readonly border rounded text-center">
      <iconify-icon icon="lucide:cloud-upload" class="upload-icon" aria-hidden="true" />
      <p class="upload-text mb-1">No documents uploaded</p>
      <p class="upload-hint text-muted small mb-0">JPEG, PNG and PDF formats, up to 50MB</p>
    </div>
    <div v-else class="uploaded-files-list">
      <div v-for="group in groupedDocs" :key="group.type" class="doc-group mb-3">
        <div class="doc-group-title">{{ group.label }}</div>
        <div
          v-for="doc in group.files"
          :key="doc.key"
          class="file-item d-flex align-items-center justify-content-between p-2 border rounded mb-2"
        >
          <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
            <iconify-icon :icon="fileIcon(doc)" class="file-icon flex-shrink-0" aria-hidden="true" />
            <div class="min-w-0">
              <div class="file-name text-truncate">{{ doc.name }}</div>
              <div v-if="doc.sizeLabel" class="file-size text-muted small">{{ doc.sizeLabel }}</div>
            </div>
          </div>
          <button class="doc-menu-btn" type="button" aria-label="Document actions">
            <iconify-icon icon="lucide:more-vertical" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  documents: { type: Array, default: () => [] }
})

function formatSize(bytes) {
  if (bytes == null || bytes === '' || Number.isNaN(Number(bytes))) return ''
  const n = Number(bytes)
  if (n <= 0) return ''
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(n) / Math.log(k))
  return `${parseFloat((n / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}

const normalizedDocs = computed(() => {
  const list = Array.isArray(props.documents) ? props.documents : []
  return list.map((d, i) => {
    if (typeof d === 'string') {
      return { key: `s-${i}`, name: d, mime: '', sizeLabel: '' }
    }
    const name = d.name || d.filename || d.original_name || `Document ${i + 1}`
    const mime = d.type || d.mime_type || ''
    const category = d.document_type || d.type_name || d.label || 'Documents'
    return {
      key: String(d.id ?? d.url ?? `d-${i}-${name}`),
      name,
      mime,
      sizeLabel: formatSize(d.size),
      category
    }
  })
})

function formatCategoryLabel(raw) {
  if (!raw) return 'Documents'
  return String(raw)
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, c => c.toUpperCase())
}

const groupedDocs = computed(() => {
  const map = new Map()
  normalizedDocs.value.forEach(doc => {
    const key = doc.category || 'documents'
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(doc)
  })
  return Array.from(map.entries()).map(([type, files]) => ({
    type,
    label: formatCategoryLabel(type),
    files
  }))
})

function fileIcon(doc) {
  const m = (doc.mime || '').toLowerCase()
  const n = (doc.name || '').toLowerCase()
  if (m.includes('pdf') || n.endsWith('.pdf')) return 'lucide:file-text'
  if (m.includes('image') || /\.(jpe?g|png|gif|webp)$/i.test(n)) return 'lucide:image'
  if (m.includes('word') || n.endsWith('.doc') || n.endsWith('.docx')) return 'lucide:file-text'
  return 'lucide:file'
}
</script>

<style scoped>
.upload-zone {
  border: 2px dashed #e2e8f0 !important;
  background: #f8fafc;
  padding: 1.25rem 1rem;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.upload-zone--readonly {
  cursor: default;
  pointer-events: none;
}

.upload-icon {
  font-size: 32px;
  color: #64748b;
  margin-bottom: 8px;
}

.upload-text {
  font-size: 14px;
  color: #1e293b;
  font-weight: 500;
  margin: 0;
}

.upload-hint {
  font-size: 12px;
  color: #64748b !important;
}

.uploaded-files-list {
  margin: 0;
}

.doc-group-title {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
  margin-bottom: 8px;
}

.file-item {
  background: #fff;
  border-color: #eef2f7 !important;
  border-radius: 8px !important;
  font-family: var(--deal-font, 'Inter', ui-sans-serif, sans-serif);
}

.file-icon {
  font-size: 20px;
  color: #64748b;
}

.file-name {
  font-size: 12px;
  color: #1e293b;
  font-weight: 500;
}

.file-size {
  font-size: 11px;
}

.doc-menu-btn {
  border: none;
  background: transparent;
  color: #9ca3af;
  width: 24px;
  height: 24px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.doc-menu-btn:hover {
  background: #f1f5f9;
  color: #64748b;
}
</style>
