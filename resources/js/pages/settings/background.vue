<template>
  <div class="dashboard-main-body background-settings-page">
    <Breadcrumb
      title="Background"
      :breadcrumbs="[{ name: 'Settings' }, { name: 'Background' }]"
    />

    <div class="card radius-12 overflow-hidden bg-settings-card">
      <div class="card-body bg-settings-body">
        <!-- tiny page line -->
        <div class="bg-topline">
          <span class="text-primary-light text-xs">Settings</span>
          <span class="text-primary-light text-xs opacity-50">/</span>
          <span class="text-primary-light text-xs fw-medium">Background</span>
          <span v-if="!loading" class="bg-topline-count">{{ backgrounds.length }}</span>
        </div>

        <transition name="bg-fade">
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
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
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
  components: { Breadcrumb },
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
