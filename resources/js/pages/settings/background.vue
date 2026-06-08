<template>
  <div class="dashboard-main-body">
    <div class="breadcrumb-area mb-24">
      <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
          <h1 class="page-title mb-1">Background Studio</h1>
          <div class="d-flex align-items-center gap-2">
            <a href="#" class="breadcrumb-link">Settings</a>
            <i class="fas fa-chevron-right text-secondary" style="font-size: 10px"></i>
            <span class="breadcrumb-link active" style="color: #1f2937">Background ambiance</span>
          </div>
        </div>
        <div class="text-secondary-light">
          <i class="fas fa-palette me-1"></i> Personalize your workspace
        </div>
      </div>
    </div>

    <!-- Inline status message -->
    <transition name="fade">
      <div
        v-if="message"
        class="alert alert-premium mb-24 d-flex align-items-center gap-2"
        :class="messageIsError ? 'alert-danger' : 'alert-success'"
        role="alert"
      >
        <i class="fas" :class="messageIsError ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
        <span>{{ message }}</span>
      </div>
    </transition>

    <!-- Picker card -->
    <div class="premium-card mb-24">
      <div class="card-header-premium d-flex flex-wrap justify-content-between align-items-center">
        <div>
          <h6 class="text-lg fw-semibold mb-0" style="font-size: 1.25rem">Curate your canvas</h6>
          <span class="text-secondary-light">Pick an image that inspires you — saved only for your account.</span>
        </div>
        <div class="mt-2 mt-sm-0">
          <span class="badge bg-light text-dark rounded-pill px-3 py-2">
            <i class="far fa-image me-1"></i> {{ backgrounds.length }} backgrounds
          </span>
        </div>
      </div>

      <div class="card-body-premium">
        <div v-if="loading" class="row g-4">
          <div v-for="n in 6" :key="n" class="col-6 col-sm-4 col-md-3 col-xl-2">
            <div class="bg-thumb bg-thumb--skeleton w-100"></div>
          </div>
        </div>

        <div v-else class="row g-4">
          <!-- Reset to system default -->
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
              <span v-if="bg.is_default" class="bg-thumb-badge"><i class="fas fa-crown me-1"></i>Default</span>
              <span v-if="!bg.is_active" class="bg-thumb-badge bg-thumb-badge--right">
                <i class="fas fa-eye-slash me-1"></i>Hidden
              </span>
              <span v-if="selectedId === bg.id" class="bg-thumb-check"><i class="fas fa-check"></i></span>
              <span class="bg-thumb-name">{{ bg.name || 'Background #' + bg.id }}</span>
            </button>

            <!-- Superadmin per-item controls -->
            <div v-if="canManage" class="d-flex justify-content-center gap-1 mt-2">
              <button
                type="button"
                class="small-icon-btn"
                :title="bg.is_default ? 'Already default' : 'Set as default'"
                @click="makeDefault(bg)"
                :disabled="bg.is_default"
              >
                <i class="fas fa-star" :class="bg.is_default ? 'text-warning' : 'text-secondary'"></i>
              </button>
              <button type="button" class="small-icon-btn" :title="bg.is_active ? 'Hide from users' : 'Show to users'" @click="toggleActive(bg)">
                <i :class="bg.is_active ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
              </button>
              <button type="button" class="small-icon-btn text-danger" title="Delete" @click="remove(bg)">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer-premium d-flex justify-content-end align-items-center gap-3">
        <button type="button" class="btn-premium-outline" :disabled="!dirty || saving" @click="resetPending">
          Cancel
        </button>
        <button type="button" class="btn-premium-primary d-inline-flex align-items-center gap-2" :disabled="!dirty || saving" @click="save">
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-save"></i>
          {{ saving ? 'Saving…' : 'Save selection' }}
        </button>
      </div>
    </div>

    <!-- Upload Section (Superadmin only) -->
    <div v-if="canManage" class="premium-card">
      <div class="card-header-premium">
        <h6 class="fw-semibold mb-0" style="font-size: 1.2rem">
          <i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Enrich the library
        </h6>
        <span class="text-secondary-light">Upload HD wallpapers — they become instantly available to every user.</span>
      </div>

      <form @submit.prevent="upload">
        <div class="card-body-premium">
          <div class="bg-dropzone" :class="{ 'has-file': uploadFiles.length }" @click="$refs.fileInput.click()">
            <i class="fas" :class="uploadFiles.length ? 'fa-images' : 'fa-cloud-arrow-up'"></i>
            <span class="fw-semibold">
              {{ uploadFiles.length ? (uploadFiles.length === 1 ? uploadFiles[0].name : uploadFiles.length + ' images selected') : 'Click or drag images' }}
            </span>
            <span class="text-secondary-light">PNG, JPG, WEBP · up to 5MB each</span>
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              class="d-none"
              multiple
              @change="onFileChange"
            />
          </div>

          <!-- Preview chips -->
          <div v-if="uploadFiles.length" class="d-flex flex-wrap gap-2 mt-4">
            <div v-for="(f, i) in uploadPreviews" :key="i" class="bg-preview-chip">
              <img :src="f.url" :alt="f.name" />
              <button type="button" class="bg-preview-remove" @click.stop="removeSelected(i)">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>

          <div class="row g-3 mt-3 align-items-end">
            <div class="col-sm-6">
              <label class="form-label fw-semibold text-secondary">Background name (optional)</label>
              <input v-model="uploadName" type="text" placeholder="e.g. 'Midnight aurora'" class="form-control form-control-premium" />
            </div>
            <div class="col-sm-6">
              <div class="form-check">
                <input id="bgSetDefault" type="checkbox" class="form-check-input" v-model="uploadIsDefault" />
                <label for="bgSetDefault" class="form-check-label fw-medium">🌟 Set first image as global default</label>
              </div>
            </div>
          </div>
        </div>

        <div class="card-footer-premium d-flex justify-content-end">
          <button type="submit" class="btn-premium-primary d-inline-flex align-items-center gap-2 px-4" :disabled="!uploadFiles.length || uploading">
            <i v-if="uploading" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-upload"></i>
            {{ uploading ? 'Uploading...' : uploadButtonLabel }}
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
    const selectedId = ref(null) // pending pick
    const savedId = ref(null) // what's actually persisted
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

    const uploadPreviews = computed(() => uploadFiles.value.map((f) => ({ name: f.name, url: URL.createObjectURL(f) })))

    const uploadButtonLabel = computed(() => (uploadFiles.value.length > 1 ? `Upload ${uploadFiles.value.length} images` : 'Upload background'))

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
        notify('Background updated ✨')
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
        notify(res.data?.message || 'Backgrounds uploaded successfully')
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
        notify('Default background changed')
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
      if (!confirm('Delete this background? Users will see default.')) return
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
      loading,
      backgrounds,
      selectedId,
      canManage,
      dirty,
      saving,
      uploadFiles,
      uploadPreviews,
      uploadButtonLabel,
      uploadName,
      uploadIsDefault,
      uploading,
      fileInput,
      message,
      messageIsError,
      thumb,
      pick,
      resetPending,
      save,
      onFileChange,
      removeSelected,
      upload,
      makeDefault,
      toggleActive,
      remove,
    }
  },
}
</script>

<style scoped>
/* Breadcrumb area */
.breadcrumb-area {
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 700;
  background: linear-gradient(135deg, #1e2a3e 0%, #0f172a 100%);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
  letter-spacing: -0.01em;
}

.breadcrumb-link {
  color: #5b6e8c;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
}

.text-secondary-light {
  color: #5b6e8c;
  font-size: 0.85rem;
}

/* Alert premium */
.alert-premium {
  border-radius: 60px;
  border: none;
  padding: 0.9rem 1.2rem;
  font-weight: 500;
  backdrop-filter: blur(4px);
}

/* Premium card */
.premium-card {
  background: rgba(255, 255, 255, 0.96);
  border-radius: 28px;
  box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease;
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.card-header-premium {
  background: transparent;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  padding: 1.2rem 1.8rem;
}

.card-body-premium {
  padding: 1.8rem;
}

.card-footer-premium {
  background: transparent;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  padding: 1.2rem 1.8rem;
}

/* Thumbnail styles */
.bg-thumb {
  position: relative;
  aspect-ratio: 16 / 10;
  border-radius: 20px;
  border: 2px solid rgba(255, 255, 255, 0.6);
  background-size: cover;
  background-position: center;
  background-color: #e9edf4;
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.25s cubic-bezier(0.2, 0.9, 0.4, 1.1), box-shadow 0.3s, border-color 0.2s;
  box-shadow: 0 6px 12px -6px rgba(0, 0, 0, 0.1);
}

.bg-thumb:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 28px -12px rgba(0, 0, 0, 0.2);
  border-color: rgba(255, 255, 255, 0.9);
}

.bg-thumb.is-selected {
  border-color: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3), 0 12px 20px -12px rgba(0, 0, 0, 0.2);
}

.bg-thumb.is-inactive {
  opacity: 0.65;
  filter: grayscale(0.2) brightness(0.95);
}

.bg-thumb--default {
  background: linear-gradient(145deg, #ffffff, #f3f6fc);
  border: 2px dashed #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 1px 2px #fff, 0 4px 8px rgba(0, 0, 0, 0.02);
}

.bg-thumb-default-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: #1e293b;
}

.bg-thumb-default-inner i {
  font-size: 1.6rem;
  background: linear-gradient(145deg, #3b82f6, #2563eb);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

.bg-thumb-name {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 0.6rem 0.75rem 0.5rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: white;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
  backdrop-filter: blur(2px);
  letter-spacing: 0.3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bg-thumb-badge {
  position: absolute;
  top: 0.65rem;
  left: 0.65rem;
  font-size: 0.6rem;
  padding: 0.2rem 0.6rem;
  border-radius: 40px;
  font-weight: 600;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  color: white;
  border: none;
}

.bg-thumb-badge--right {
  left: auto;
  right: 0.65rem;
  background: #475569cc;
}

.bg-thumb-check {
  position: absolute;
  top: 0.65rem;
  right: 0.65rem;
  width: 26px;
  height: 26px;
  background: #3b82f6;
  border-radius: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.8rem;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Skeleton loading */
.bg-thumb--skeleton {
  background: linear-gradient(110deg, #eceff5 8%, #f5f7fc 18%, #eceff5 33%);
  background-size: 200% 100%;
  animation: shimmer 1.6s infinite linear;
  border-radius: 20px;
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

/* Dropzone */
.bg-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border: 2px dashed #cbd5e6;
  border-radius: 28px;
  padding: 2.2rem 1.5rem;
  background: #fafcff;
  transition: all 0.2s;
  cursor: pointer;
}

.bg-dropzone:hover {
  border-color: #3b82f6;
  background: #f3f9ff;
}

.bg-dropzone i {
  font-size: 2.3rem;
  background: linear-gradient(145deg, #3b82f6, #2563eb);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}

.has-file {
  border-color: #10b981;
}

/* Preview chips */
.bg-preview-chip {
  position: relative;
  width: 84px;
  height: 56px;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 6px 12px -8px rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.6);
}

.bg-preview-chip img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bg-preview-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  color: white;
  border: none;
  cursor: pointer;
  transition: 0.1s;
}

.bg-preview-remove:hover {
  background: #ef4444;
  transform: scale(1.05);
}

/* Buttons */
.btn-premium-primary {
  background: linear-gradient(105deg, #1e3a8a, #2563eb);
  border: none;
  padding: 0.55rem 1.5rem;
  border-radius: 40px;
  font-weight: 600;
  color: white;
  transition: all 0.2s;
  box-shadow: 0 4px 8px rgba(37, 99, 235, 0.2);
}

.btn-premium-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  background: linear-gradient(105deg, #1e40af, #3b82f6);
  box-shadow: 0 12px 18px -8px rgba(37, 99, 235, 0.4);
}

.btn-premium-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-premium-outline {
  border-radius: 40px;
  border: 1px solid #cbd5e1;
  background: white;
  padding: 0.5rem 1.3rem;
  font-weight: 500;
  transition: 0.2s;
}

.btn-premium-outline:hover:not(:disabled) {
  border-color: #3b82f6;
  background: #f8fafc;
}

.btn-premium-outline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.small-icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid #e2e8f0;
  transition: all 0.15s;
}

.small-icon-btn:hover:not(:disabled) {
  background: white;
  border-color: #94a3b8;
  transform: scale(1.03);
}

.small-icon-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Form controls */
.form-control-premium {
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  padding: 0.6rem 1rem;
  background: #ffffff;
  transition: 0.2s;
}

.form-control-premium:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
  outline: none;
}

/* Utility classes */
.mb-24 {
  margin-bottom: 1.5rem;
}

.mt-24 {
  margin-top: 1.5rem;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>