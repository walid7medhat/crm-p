<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="Background" :breadcrumbs="[{ name: 'Settings - Background' }]" />

    <!-- Inline status message -->
    <div
      v-if="message"
      class="alert mb-24 d-flex align-items-center gap-2"
      :class="messageIsError ? 'alert-danger' : 'alert-success'"
      role="alert"
    >
      <i class="fas" :class="messageIsError ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
      {{ message }}
    </div>

    <!-- Picker card -->
    <div class="card radius-12 mb-24">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Choose your background</h6>
        <span class="text-secondary-light text-sm">Pick an image, then click Save. It only changes how the app looks for you.</span>
      </div>

      <div class="card-body p-24">
        <div v-if="loading" class="row g-3">
          <div v-for="n in 6" :key="n" class="col-6 col-sm-4 col-md-3 col-xl-2">
            <div class="bg-thumb bg-thumb--skeleton"></div>
          </div>
        </div>

        <div v-else class="row g-3">
          <!-- Reset to the system default -->
          <div class="col-6 col-sm-4 col-md-3 col-xl-2">
            <button
              type="button"
              class="bg-thumb bg-thumb--default w-100"
              :class="{ 'is-selected': selectedId === null }"
              @click="pick(null)"
            >
              <span class="bg-thumb-default-inner">
                <i class="fas fa-rotate-left"></i>
                <span>Default</span>
              </span>
              <span v-if="selectedId === null" class="bg-thumb-check"><i class="fas fa-check"></i></span>
            </button>
          </div>

          <div v-for="bg in backgrounds" :key="bg.id" class="col-6 col-sm-4 col-md-3 col-xl-2">
            <button
              type="button"
              class="bg-thumb w-100"
              :class="{ 'is-selected': selectedId === bg.id, 'is-inactive': !bg.is_active }"
              :style="{ backgroundImage: `url('${thumb(bg.url)}')` }"
              @click="pick(bg.id)"
            >
              <span v-if="bg.is_default" class="badge bg-primary bg-thumb-badge"><i class="fas fa-star me-1"></i>Default</span>
              <span v-if="!bg.is_active" class="badge bg-secondary bg-thumb-badge bg-thumb-badge--right">Hidden</span>
              <span v-if="selectedId === bg.id" class="bg-thumb-check"><i class="fas fa-check"></i></span>
              <span class="bg-thumb-name">{{ bg.name || ('Background #' + bg.id) }}</span>
            </button>

            <!-- Superadmin per-item controls -->
            <div v-if="canManage" class="d-flex justify-content-center gap-1 mt-2">
              <button type="button" class="btn btn-sm btn-outline-primary px-2" title="Set as default" @click="makeDefault(bg)" :disabled="bg.is_default">
                <i class="fas fa-star"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary px-2" :title="bg.is_active ? 'Hide from users' : 'Show to users'" @click="toggleActive(bg)">
                <i :class="bg.is_active ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger px-2" title="Delete" @click="remove(bg)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer bg-base d-flex justify-content-end align-items-center gap-2 py-16 px-24">
        <button type="button" class="btn btn-outline-secondary" :disabled="!dirty || saving" @click="resetPending">
          Cancel
        </button>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" :disabled="!dirty || saving" @click="save">
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          {{ saving ? 'Saving…' : 'Save background' }}
        </button>
      </div>
    </div>

    <!-- Superadmin: upload new backgrounds -->
    <div v-if="canManage" class="card radius-12">
      <div class="card-header border-bottom bg-base py-16 px-24">
        <h6 class="text-lg fw-semibold mb-0">Add backgrounds</h6>
        <span class="text-secondary-light text-sm">Upload one or more images to make them available for everyone.</span>
      </div>

      <form @submit.prevent="upload">
        <div class="card-body p-24">
          <label class="bg-dropzone" :class="{ 'has-file': uploadFiles.length }">
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              class="d-none"
              multiple
              @change="onFileChange"
            />
            <i class="fas mb-2" :class="uploadFiles.length ? 'fa-images' : 'fa-cloud-arrow-up'"></i>
            <span class="fw-semibold">
              {{ uploadFiles.length
                ? (uploadFiles.length === 1 ? uploadFiles[0].name : uploadFiles.length + ' images selected')
                : 'Click to select one or more images' }}
            </span>
            <span class="text-secondary-light text-sm">PNG, JPG or WEBP · up to 5MB each</span>
          </label>

          <!-- Preview chips -->
          <div v-if="uploadFiles.length" class="d-flex flex-wrap gap-2 mt-3">
            <div v-for="(f, i) in uploadPreviews" :key="i" class="bg-preview-chip">
              <img :src="f.url" :alt="f.name" />
              <button type="button" class="bg-preview-remove" title="Remove" @click="removeSelected(i)">
                <i class="fas fa-xmark"></i>
              </button>
            </div>
          </div>

          <div class="row g-3 mt-2">
            <div class="col-sm-6">
              <input v-model="uploadName" type="text" placeholder="Name applied to all (optional)" class="form-control radius-8" />
            </div>
            <div class="col-sm-6 d-flex align-items-center">
              <div class="form-check mb-0">
                <input id="bgSetDefault" type="checkbox" class="form-check-input" v-model="uploadIsDefault" />
                <label for="bgSetDefault" class="form-check-label">Set first as default</label>
              </div>
            </div>
          </div>
        </div>

        <div class="card-footer bg-base d-flex justify-content-end py-16 px-24">
          <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2" :disabled="!uploadFiles.length || uploading">
            <i v-if="uploading" class="fas fa-spinner fa-spin"></i>
            {{ uploading ? 'Uploading…' : uploadButtonLabel }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import backgroundsApi from '@/services/backgroundsApi'
import { useBackground } from '@/composables/useBackground'
import { normalizePublicStorageUrl } from '@/composables/usePublicStorageUrl'

export default {
  name: 'BackgroundSettings',
  components: { Breadcrumb },
  setup() {
    const { syncFromUser } = useBackground()

    const loading = ref(true)
    const backgrounds = ref([])
    const selectedId = ref(null)   // pending pick (highlighted)
    const savedId = ref(null)      // what's actually persisted
    const saving = ref(false)
    const canManage = ref(false)

    const uploadFiles = ref([])
    const uploadName = ref('')
    const uploadIsDefault = ref(false)
    const uploading = ref(false)
    const fileInput = ref(null)

    const message = ref('')
    const messageIsError = ref(false)

    const dirty = computed(() => selectedId.value !== savedId.value)

    const uploadPreviews = computed(() =>
      uploadFiles.value.map((f) => ({ name: f.name, url: URL.createObjectURL(f) }))
    )

    const uploadButtonLabel = computed(() =>
      uploadFiles.value.length > 1 ? `Upload ${uploadFiles.value.length} backgrounds` : 'Upload background'
    )

    function thumb(url) {
      return normalizePublicStorageUrl(url) || url
    }

    function notify(text, isError = false) {
      message.value = text
      messageIsError.value = isError
      if (!isError) {
        setTimeout(() => { if (message.value === text) message.value = '' }, 3000)
      }
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
        notify(e?.response?.data?.message || 'Failed to load backgrounds', true)
      } finally {
        loading.value = false
      }
    }

    // Local pick only — nothing is persisted until Save.
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
        notify('Background saved')
      } catch (e) {
        notify(e?.response?.data?.message || 'Could not save background', true)
      } finally {
        saving.value = false
      }
    }

    function onFileChange(e) {
      uploadFiles.value = Array.from(e.target.files || [])
    }

    function removeSelected(index) {
      uploadFiles.value = uploadFiles.value.filter((_, i) => i !== index)
      if (!uploadFiles.value.length && fileInput.value) fileInput.value.value = ''
    }

    async function upload() {
      if (!uploadFiles.value.length) return
      uploading.value = true
      try {
        const res = await backgroundsApi.upload({
          images: uploadFiles.value,
          name: uploadName.value || null,
          isDefault: uploadIsDefault.value,
        })
        uploadFiles.value = []
        uploadName.value = ''
        uploadIsDefault.value = false
        if (fileInput.value) fileInput.value.value = ''
        notify(res.data?.message || 'Backgrounds uploaded')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Upload failed', true)
      } finally {
        uploading.value = false
      }
    }

    async function makeDefault(bg) {
      try {
        await backgroundsApi.setDefault(bg.id)
        notify('Default background updated')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed to set default', true)
      }
    }

    async function toggleActive(bg) {
      try {
        await backgroundsApi.update(bg.id, { is_active: !bg.is_active })
        notify(bg.is_active ? 'Background hidden' : 'Background shown')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed to update background', true)
      }
    }

    async function remove(bg) {
      if (!confirm('Delete this background? Users using it will fall back to the default.')) return
      try {
        await backgroundsApi.remove(bg.id)
        if (selectedId.value === bg.id) selectedId.value = savedId.value === bg.id ? null : savedId.value
        if (savedId.value === bg.id) savedId.value = null
        notify('Background deleted')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed to delete background', true)
      }
    }

    onMounted(load)

    return {
      loading, backgrounds, selectedId, canManage, dirty, saving,
      uploadFiles, uploadPreviews, uploadButtonLabel,
      uploadName, uploadIsDefault, uploading, fileInput,
      message, messageIsError,
      thumb, pick, resetPending, save, onFileChange, removeSelected, upload, makeDefault, toggleActive, remove,
    }
  },
}
</script>

<style scoped>
/* Only the thumbnail visuals are custom; layout uses the app's Bootstrap cards. */
.bg-thumb {
  position: relative;
  aspect-ratio: 16 / 10;
  border-radius: 10px;
  border: 2px solid var(--bs-border-color, #e5e7eb);
  background-size: cover;
  background-position: center;
  background-color: #eef0f4;
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  display: block;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.bg-thumb:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(17, 24, 39, 0.16);
}

.bg-thumb.is-selected {
  border-color: var(--bs-primary, #487fff);
  box-shadow: 0 0 0 3px rgba(72, 127, 255, 0.25);
}

.bg-thumb.is-inactive {
  opacity: 0.55;
  filter: grayscale(0.35);
}

.bg-thumb--default {
  display: flex;
  align-items: center;
  justify-content: center;
  background: repeating-linear-gradient(45deg, #f8f9fc, #f8f9fc 12px, #f1f3f9 12px, #f1f3f9 24px);
  color: #6b7280;
  border-style: dashed;
}

.bg-thumb--skeleton {
  cursor: default;
  background: linear-gradient(90deg, #eef0f4 25%, #f6f7fa 50%, #eef0f4 75%);
  background-size: 200% 100%;
  animation: bg-shimmer 1.3s ease infinite;
}

@keyframes bg-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.bg-thumb-default-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.bg-thumb-default-inner i {
  font-size: 1.3rem;
}

.bg-thumb-name {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 0.45rem 0.55rem 0.35rem;
  font-size: 0.72rem;
  font-weight: 500;
  color: #fff;
  text-align: left;
  background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bg-thumb-badge {
  position: absolute;
  top: 0.4rem;
  left: 0.4rem;
  font-size: 0.6rem;
}

.bg-thumb-badge--right {
  left: auto;
  right: 0.4rem;
}

.bg-thumb-check {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--bs-primary, #487fff);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.68rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}

/* Dropzone */
.bg-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  border: 2px dashed var(--bs-border-color, #d1d5db);
  border-radius: 12px;
  padding: 1.6rem;
  cursor: pointer;
  text-align: center;
  width: 100%;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.bg-dropzone:hover {
  border-color: var(--bs-primary, #487fff);
}

.bg-dropzone.has-file {
  border-color: #10b981;
}

.bg-dropzone > i {
  font-size: 1.6rem;
  color: var(--bs-primary, #487fff);
}

.bg-dropzone.has-file > i {
  color: #10b981;
}

/* Preview chips */
.bg-preview-chip {
  position: relative;
  width: 88px;
  height: 56px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--bs-border-color, #e5e7eb);
}

.bg-preview-chip img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.bg-preview-remove {
  position: absolute;
  top: 3px;
  right: 3px;
  width: 18px;
  height: 18px;
  border: none;
  border-radius: 50%;
  background: rgba(17, 24, 39, 0.75);
  color: #fff;
  font-size: 0.6rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.bg-preview-remove:hover {
  background: #dc2626;
}
</style>
