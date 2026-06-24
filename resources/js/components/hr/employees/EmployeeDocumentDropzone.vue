<template>
  <div class="emp-doc-upload">
    <div
      class="emp-doc-dropzone"
      :class="{ 'is-dragover': isDragOver, 'has-file': !!modelValue }"
      role="button"
      tabindex="0"
      @click="openPicker"
      @keydown.enter.prevent="openPicker"
      @keydown.space.prevent="openPicker"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent="onDragOver"
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <span class="emp-doc-dropzone__icon" aria-hidden="true">
        <iconify-icon :icon="isDragOver ? 'lucide:download' : 'lucide:upload-cloud'" />
      </span>
      <div class="emp-doc-dropzone__copy">
        <strong>{{ isDragOver ? 'Drop file here' : 'Drag and drop your file' }}</strong>
        <small>JPEG, PNG, PDF · up to {{ maxSizeMb }}MB</small>
      </div>
      <button type="button" class="emp-doc-dropzone__btn" @click.stop="openPicker">
        Select File
      </button>
      <input
        ref="inputRef"
        type="file"
        class="emp-doc-dropzone__input"
        :accept="accept"
        @change="onInputChange"
      />
    </div>

    <div v-if="modelValue" class="emp-doc-preview">
      <div class="emp-doc-preview__thumb">
        <img v-if="previewUrl" :src="previewUrl" :alt="modelValue.name" />
        <iconify-icon v-else icon="lucide:file-text" />
      </div>
      <div class="emp-doc-preview__meta">
        <p>{{ modelValue.name }}</p>
        <small>{{ formatSize(modelValue.size) }}</small>
      </div>
      <button type="button" class="emp-doc-preview__remove" aria-label="Remove file" @click.stop="clearFile">
        <iconify-icon icon="lucide:x" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: null },
  accept: { type: String, default: 'image/jpeg,image/png,image/webp,image/gif,.pdf,application/pdf' },
  maxSizeMb: { type: Number, default: 50 },
})

const emit = defineEmits(['update:modelValue', 'error'])

const inputRef = ref(null)
const isDragOver = ref(false)
const previewUrl = ref('')
let dragDepth = 0
let objectUrl = ''

const allowedMimeTypes = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'application/pdf',
])

function formatSize(bytes) {
  if (!bytes) return '0 KB'
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function revokePreview() {
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = ''
  }
  previewUrl.value = ''
}

function updatePreview(file) {
  revokePreview()
  if (file && file.type.startsWith('image/')) {
    objectUrl = URL.createObjectURL(file)
    previewUrl.value = objectUrl
  }
}

function isValidFile(file) {
  if (!file) return false
  const maxBytes = props.maxSizeMb * 1024 * 1024
  if (file.size > maxBytes) {
    emit('error', `File is too large. Maximum size is ${props.maxSizeMb}MB.`)
    return false
  }
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')
  const isImage = file.type.startsWith('image/')
  if (!isImage && !isPdf && !allowedMimeTypes.has(file.type)) {
    emit('error', 'Only JPEG, PNG, and PDF files are allowed.')
    return false
  }
  return true
}

function setFile(file) {
  if (!isValidFile(file)) return
  emit('update:modelValue', file)
  updatePreview(file)
}

function openPicker() {
  inputRef.value?.click()
}

function onInputChange(event) {
  const file = event?.target?.files?.[0]
  if (file) setFile(file)
  if (event?.target) event.target.value = ''
}

function onDragEnter() {
  dragDepth += 1
  isDragOver.value = true
}

function onDragOver() {
  isDragOver.value = true
}

function onDragLeave() {
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) isDragOver.value = false
}

function onDrop(event) {
  dragDepth = 0
  isDragOver.value = false
  const file = event?.dataTransfer?.files?.[0]
  if (file) setFile(file)
}

function clearFile() {
  emit('update:modelValue', null)
  revokePreview()
  if (inputRef.value) inputRef.value.value = ''
}

watch(
  () => props.modelValue,
  (file) => {
    if (!file) {
      revokePreview()
      return
    }
    updatePreview(file)
  },
  { immediate: true }
)

onBeforeUnmount(revokePreview)
</script>

<style>
@import '../../../../css/hr-employees.css';
</style>
