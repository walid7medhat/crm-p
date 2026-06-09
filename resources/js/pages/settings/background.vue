<template>
  <div class="dashboard-main-body">
    <div class="card h-100 p-0 radius-12 overflow-hidden bg-settings-card">
      <div class="card-body p-24 p-md-32">
        <!-- tiny page line -->
        <div class="bg-topline">
          <span class="text-primary-light text-xs">Settings</span>
          <span class="text-primary-light text-xs opacity-50">/</span>
          <span class="text-primary-light text-xs fw-medium">Background</span>
          <span v-if="!loading" class="bg-topline-count">{{ backgrounds.length }}</span>
        </div>

        <transition name="fade">
          <div v-if="message" class="bg-toast" :class="messageIsError ? 'bg-toast--err' : 'bg-toast--ok'">
            {{ message }}
          </div>
        </transition>

        <!-- admin upload strip -->
        <div v-if="canManage" class="bg-upload-strip">
          <form @submit.prevent="upload" class="bg-upload-form">
            <div
              class="bg-upload-zone"
              :class="{ 'is-drag': isDragging, 'has-files': uploadQueue.length }"
              @click="openFilePicker"
              @dragenter.prevent="onDragEnter"
              @dragover.prevent="onDragOver"
              @dragleave.prevent="onDragLeave"
              @drop.prevent="onDrop"
            >
              <iconify-icon icon="lucide:plus" class="bg-upload-zone-icon" />
              <span class="text-xs text-primary-light">
                {{ uploadQueue.length ? `${uploadQueue.length} selected` : 'Add images' }}
              </span>
              <input
                ref="fileInput"
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                class="d-none"
                multiple
                @change="onFileChange"
              />
            </div>

            <div v-if="uploadQueue.length" class="bg-upload-queue-mini">
              <div v-for="(item, index) in uploadQueue" :key="item.id" class="bg-queue-chip">
                <img :src="item.previewUrl" :alt="item.name" />
                <button type="button" class="bg-queue-rm" @click.stop="removeQueuedFile(index)">
                  <iconify-icon icon="lucide:x" />
                </button>
              </div>
              <button type="button" class="bg-queue-clear text-xs" @click.stop="clearUploadQueue">Clear</button>
            </div>

            <div class="bg-upload-opts">
              <input
                v-model="uploadName"
                type="text"
                class="form-control radius-8 text-xs bg-input"
                placeholder="Name prefix (optional)"
              />
              <label class="bg-check text-xs text-primary-light">
                <input v-model="uploadIsDefault" type="checkbox" class="form-check-input" />
                Default first
              </label>
              <button type="submit" class="btn btn-sm btn-primary radius-8 text-xs px-3" :disabled="!uploadQueue.length || uploading">
                <span v-if="uploading">{{ uploadProgress }}%</span>
                <span v-else>Upload</span>
              </button>
            </div>
          </form>
        </div>

        <!-- wallpaper grid -->
        <div class="bg-grid-wrap">
          <div v-if="loading" class="bg-grid">
            <div v-for="n in 8" :key="n" class="bg-tile bg-tile--skel" />
          </div>

          <div v-else-if="!backgrounds.length" class="bg-grid-empty text-xs text-primary-light">
            No wallpapers yet{{ canManage ? ' — add some above' : '' }}.
          </div>

          <div v-else class="bg-grid">
            <button
              type="button"
              class="bg-tile bg-tile--reset"
              :class="{ 'is-on': selectedId === null }"
              @click="pick(null)"
            >
              <iconify-icon icon="lucide:rotate-ccw" />
              <span class="text-xs">Default</span>
            </button>

            <div v-for="bg in backgrounds" :key="bg.id" class="bg-tile-wrap">
              <button
                type="button"
                class="bg-tile"
                :class="{
                  'is-on': selectedId === bg.id,
                  'is-off': !bg.is_active,
                }"
                :style="{ backgroundImage: `url('${thumb(bg.url)}')` }"
                :title="bg.name || `Background ${bg.id}`"
                @click="pick(bg.id)"
              >
                <span v-if="selectedId === bg.id" class="bg-tile-mark">
                  <iconify-icon icon="lucide:check" />
                </span>
                <span v-if="bg.is_default" class="bg-tile-tag">def</span>
                <span v-if="!bg.is_active" class="bg-tile-tag bg-tile-tag--hide">off</span>
              </button>

              <div v-if="canManage" class="bg-tile-actions">
                <button type="button" class="bg-act" :disabled="bg.is_default" title="Set default" @click="makeDefault(bg)">
                  <iconify-icon icon="lucide:star" />
                </button>
                <button type="button" class="bg-act" :title="bg.is_active ? 'Hide' : 'Show'" @click="toggleActive(bg)">
                  <iconify-icon :icon="bg.is_active ? 'lucide:eye' : 'lucide:eye-off'" />
                </button>
                <button type="button" class="bg-act bg-act--del" title="Delete" @click="remove(bg)">
                  <iconify-icon icon="lucide:trash-2" />
                </button>
              </div>
              <p v-if="bg.name" class="bg-tile-label text-xs text-primary-light mb-0">{{ bg.name }}</p>
            </div>
          </div>
        </div>

        <!-- save bar -->
        <div v-if="dirty" class="bg-savebar">
          <span class="text-xs text-primary-light">Unsaved change</span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary radius-8 text-xs" :disabled="saving" @click="resetPending">
              Cancel
            </button>
            <button type="button" class="btn btn-sm btn-primary radius-8 text-xs px-3" :disabled="saving" @click="save">
              {{ saving ? 'Saving…' : 'Apply' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import backgroundsApi from '@/services/backgroundsApi'
import { useBackground } from '@/composables/useBackground'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

const MAX_FILES = 50
/** Keep under typical PHP post_max_size (8M) so single-file uploads don't 413 */
const MAX_FILE_BYTES = 8 * 1024 * 1024
const UPLOAD_ONE_BY_ONE = true
const ACCEPTED_TYPES = new Set(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])

let uploadItemId = 0

function fileBaseName(filename) {
  return filename.replace(/\.[^.]+$/, '')
}

export default {
  name: 'BackgroundSettings',
  setup() {
    const { syncFromUser } = useBackground()

    const loading = ref(true)
    const backgrounds = ref([])
    const selectedId = ref(null)
    const savedId = ref(null)
    const saving = ref(false)
    const canManage = ref(false)

    const uploadQueue = ref([])
    const uploadName = ref('')
    const uploadIsDefault = ref(false)
    const uploading = ref(false)
    const uploadProgress = ref(0)
    const isDragging = ref(false)
    const fileInput = ref(null)
    let dragDepth = 0

    const message = ref('')
    const messageIsError = ref(false)

    const dirty = computed(() => selectedId.value !== savedId.value)

    function thumb(url) {
      return normalizePublicStorageUrl(url) || url
    }

    function notify(text, isError = false) {
      message.value = text
      messageIsError.value = isError
      if (!isError) {
        setTimeout(() => {
          if (message.value === text) message.value = ''
        }, 3000)
      }
    }

    function revokeQueuePreviews() {
      uploadQueue.value.forEach((item) => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl)
      })
    }

    function clearUploadQueue() {
      revokeQueuePreviews()
      uploadQueue.value = []
      if (fileInput.value) fileInput.value.value = ''
    }

    function addFiles(fileList) {
      const incoming = Array.from(fileList || [])
      if (!incoming.length) return

      const valid = []
      const errors = []

      incoming.forEach((file) => {
        if (!ACCEPTED_TYPES.has(file.type)) {
          errors.push(`${file.name}: bad format`)
          return
        }
        if (file.size > MAX_FILE_BYTES) {
          errors.push(`${file.name}: over 8MB`)
          return
        }
        valid.push(file)
      })

      const remainingSlots = MAX_FILES - uploadQueue.value.length
      if (remainingSlots <= 0) {
        notify(`Max ${MAX_FILES} files`, true)
        return
      }

      valid.slice(0, remainingSlots).forEach((file) => {
        uploadQueue.value.push({
          id: ++uploadItemId,
          file,
          name: fileBaseName(file.name),
          previewUrl: URL.createObjectURL(file),
        })
      })

      if (errors.length) notify(errors.slice(0, 2).join(', '), true)
    }

    async function load() {
      loading.value = true
      try {
        const res = await backgroundsApi.list()
        const data = res.data?.data || {}
        backgrounds.value = data.backgrounds || []
        savedId.value = data.selected_id ?? null
        selectedId.value = savedId.value
        canManage.value = !!data.can_manage
      } catch (e) {
        notify(e?.response?.data?.message || 'Load failed', true)
      } finally {
        loading.value = false
      }
    }

    function pick(id) {
      selectedId.value = id
    }

    function resetPending() {
      selectedId.value = savedId.value
    }

    async function save() {
      if (!dirty.value) return
      saving.value = true
      try {
        const res = await backgroundsApi.select(selectedId.value)
        syncFromUser(res.data?.data)
        savedId.value = selectedId.value
        notify('Applied')
      } catch (e) {
        notify(e?.response?.data?.message || 'Save failed', true)
      } finally {
        saving.value = false
      }
    }

    function openFilePicker() {
      fileInput.value?.click()
    }

    function onFileChange(e) {
      addFiles(e.target.files)
      if (fileInput.value) fileInput.value.value = ''
    }

    function onDragEnter() {
      dragDepth += 1
      isDragging.value = true
    }

    function onDragOver() {
      isDragging.value = true
    }

    function onDragLeave() {
      dragDepth = Math.max(0, dragDepth - 1)
      if (dragDepth === 0) isDragging.value = false
    }

    function onDrop(e) {
      dragDepth = 0
      isDragging.value = false
      addFiles(e.dataTransfer?.files)
    }

    function removeQueuedFile(index) {
      const [removed] = uploadQueue.value.splice(index, 1)
      if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl)
      if (!uploadQueue.value.length && fileInput.value) fileInput.value.value = ''
    }

    function uploadErrorMessage(error, fileName = '') {
      const status = error?.response?.status
      if (status === 413) {
        return fileName
          ? `${fileName} is too large for the server. Use images under 8MB or ask admin to raise upload limits.`
          : 'Upload too large for the server. Try fewer/smaller images (max 8MB each).'
      }
      return (
        error?.response?.data?.message
        || error?.response?.data?.errors?.images?.[0]
        || error?.response?.data?.errors?.['images.0']?.[0]
        || 'Upload failed'
      )
    }

    async function uploadOneItem(item, { index, total, isDefault }) {
      return backgroundsApi.upload({
        images: [item.file],
        names: [item.name],
        name: uploadName.value || null,
        isDefault,
        onUploadProgress: (event) => {
          if (!event.total) return
          const fileRatio = event.loaded / event.total
          uploadProgress.value = Math.min(100, Math.round(((index + fileRatio) / total) * 100))
        },
      })
    }

    async function upload() {
      if (!uploadQueue.value.length) return
      uploading.value = true
      uploadProgress.value = 0

      const queue = [...uploadQueue.value]
      const setDefaultOnFirst = uploadIsDefault.value
      let uploaded = 0
      let lastError = ''

      try {
        if (UPLOAD_ONE_BY_ONE) {
          for (let i = 0; i < queue.length; i += 1) {
            const item = queue[i]
            try {
              await uploadOneItem(item, {
                index: i,
                total: queue.length,
                isDefault: setDefaultOnFirst && i === 0,
              })
              uploaded += 1
            } catch (e) {
              lastError = uploadErrorMessage(e, item.file.name)
              notify(lastError, true)
            }
          }
        } else {
          await backgroundsApi.upload({
            images: queue.map((item) => item.file),
            names: queue.map((item) => item.name),
            name: uploadName.value || null,
            isDefault: setDefaultOnFirst,
            onUploadProgress: (event) => {
              if (!event.total) return
              uploadProgress.value = Math.min(100, Math.round((event.loaded / event.total) * 100))
            },
          })
          uploaded = queue.length
        }

        if (uploaded > 0) {
          clearUploadQueue()
          uploadName.value = ''
          uploadIsDefault.value = false
          const suffix = uploaded < queue.length ? ` (${queue.length - uploaded} skipped)` : ''
          notify(`Uploaded ${uploaded} wallpaper${uploaded === 1 ? '' : 's'}${suffix}`)
          await load()
        } else if (!lastError) {
          notify('Nothing uploaded', true)
        }
      } catch (e) {
        notify(uploadErrorMessage(e), true)
      } finally {
        uploading.value = false
        uploadProgress.value = 0
      }
    }

    async function makeDefault(bg) {
      try {
        await backgroundsApi.setDefault(bg.id)
        notify('Default set')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed', true)
      }
    }

    async function toggleActive(bg) {
      try {
        await backgroundsApi.update(bg.id, { is_active: !bg.is_active })
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed', true)
      }
    }

    async function remove(bg) {
      if (!confirm('Delete this wallpaper?')) return
      try {
        await backgroundsApi.remove(bg.id)
        if (selectedId.value === bg.id) selectedId.value = savedId.value === bg.id ? null : savedId.value
        if (savedId.value === bg.id) savedId.value = null
        notify('Deleted')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed', true)
      }
    }

    onMounted(load)
    onBeforeUnmount(revokeQueuePreviews)

    return {
      loading,
      backgrounds,
      selectedId,
      canManage,
      dirty,
      saving,
      uploadQueue,
      uploadName,
      uploadIsDefault,
      uploading,
      uploadProgress,
      isDragging,
      fileInput,
      message,
      messageIsError,
      thumb,
      pick,
      resetPending,
      save,
      openFilePicker,
      onFileChange,
      onDragEnter,
      onDragOver,
      onDragLeave,
      onDrop,
      removeQueuedFile,
      clearUploadQueue,
      upload,
      makeDefault,
      toggleActive,
      remove,
    }
  },
}
</script>

<style scoped>
.bg-settings-card {
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.p-24 {
  padding: 1.25rem;
}

.p-md-32 {
  padding: 1.5rem;
}

@media (min-width: 768px) {
  .p-md-32 {
    padding: 1.75rem;
  }
}

/* tiny breadcrumb line — no big titles */
.bg-topline {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 1rem;
  font-size: 11px;
  line-height: 1;
}

.bg-topline-count {
  margin-left: auto;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 10px;
  font-weight: 600;
}

.bg-toast {
  margin-bottom: 0.75rem;
  padding: 0.35rem 0.6rem;
  border-radius: 6px;
  font-size: 11px;
}

.bg-toast--ok {
  background: #ecfdf5;
  color: #047857;
}

.bg-toast--err {
  background: #fef2f2;
  color: #b91c1c;
}

/* admin upload — one compact row */
.bg-upload-strip {
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.bg-upload-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.bg-upload-zone {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  font-size: 11px;
  color: #64748b;
}

.bg-upload-zone:hover,
.bg-upload-zone.is-drag {
  border-color: #2a1548;
  background: #faf5ff;
}

.bg-upload-zone.has-files {
  border-color: #10b981;
  border-style: solid;
}

.bg-upload-zone-icon {
  font-size: 14px;
  color: #64748b;
}

.bg-upload-queue-mini {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
}

.bg-queue-chip {
  position: relative;
  width: 36px;
  height: 24px;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.bg-queue-chip img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bg-queue-rm {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  font-size: 10px;
  opacity: 0;
  transition: opacity 0.15s;
  cursor: pointer;
}

.bg-queue-chip:hover .bg-queue-rm {
  opacity: 1;
}

.bg-queue-clear {
  border: none;
  background: none;
  color: #ef4444;
  padding: 0;
  cursor: pointer;
}

.bg-upload-opts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}

.bg-input {
  width: 140px;
  height: 30px;
  padding: 0 0.5rem;
  font-size: 11px !important;
}

.bg-check {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  margin: 0;
  white-space: nowrap;
}

.bg-check .form-check-input {
  width: 0.85rem;
  height: 0.85rem;
  margin: 0;
}

/* wallpaper grid */
.bg-grid-wrap {
  min-height: 120px;
}

.bg-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(108px, 1fr));
  gap: 0.65rem;
}

.bg-grid-empty {
  padding: 2rem 0;
  text-align: center;
}

.bg-tile-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.bg-tile {
  position: relative;
  aspect-ratio: 16 / 10;
  width: 100%;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 8px;
  background-size: cover;
  background-position: center;
  background-color: #e2e8f0;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.15s, opacity 0.15s;
}

.bg-tile:hover {
  border-color: #cbd5e1;
}

.bg-tile.is-on {
  border-color: #2a1548;
  box-shadow: 0 0 0 1px #2a1548;
}

.bg-tile.is-off {
  opacity: 0.45;
}

.bg-tile--reset {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.15rem;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  color: #64748b;
  font-size: 11px;
}

.bg-tile--reset iconify-icon {
  font-size: 14px;
}

.bg-tile--reset.is-on {
  border-color: #2a1548;
  border-style: solid;
  color: #2a1548;
}

.bg-tile-mark {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #2a1548;
  color: #fff;
  font-size: 10px;
}

.bg-tile-tag {
  position: absolute;
  top: 4px;
  left: 4px;
  padding: 0 4px;
  border-radius: 3px;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.bg-tile-tag--hide {
  left: auto;
  right: 4px;
  background: rgba(71, 85, 105, 0.85);
}

.bg-tile-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 10px !important;
  line-height: 1.2;
  opacity: 0.75;
}

.bg-tile-actions {
  display: flex;
  justify-content: center;
  gap: 0.2rem;
}

.bg-act {
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: #fff;
  color: #64748b;
  font-size: 11px;
  padding: 0;
  cursor: pointer;
  transition: background 0.12s;
}

.bg-act:hover:not(:disabled) {
  background: #f8fafc;
  color: #334155;
}

.bg-act:disabled {
  opacity: 0.35;
  cursor: default;
}

.bg-act--del:hover:not(:disabled) {
  color: #ef4444;
  border-color: #fecaca;
}

.bg-tile--skel {
  background: linear-gradient(110deg, #eceff5 8%, #f5f7fc 18%, #eceff5 33%);
  background-size: 200% 100%;
  animation: bg-shimmer 1.4s infinite linear;
  border: none;
  cursor: default;
}

@keyframes bg-shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* sticky save */
.bg-savebar {
  position: sticky;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 1rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
