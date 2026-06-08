<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="Background" :breadcrumbs="[{ name: 'Settings - Background' }]" />

    <div class="bg-page">
      <!-- Hero / intro -->
      <header class="bg-hero">
        <div class="bg-hero-icon"><i class="fas fa-image"></i></div>
        <div>
          <h4 class="bg-hero-title">Workspace Background</h4>
          <p class="bg-hero-sub">Personalize how the app looks for you. Your choice is saved to your account only.</p>
        </div>
      </header>

      <!-- Picker -->
      <section class="bg-card">
        <div class="bg-card-head">
          <h5><i class="fas fa-swatchbook"></i> Choose your background</h5>
          <span class="bg-card-hint">Click a thumbnail to apply it instantly</span>
        </div>

        <div v-if="loading" class="bg-skeleton-grid">
          <div v-for="n in 6" :key="n" class="bg-skeleton"></div>
        </div>

        <div v-else class="bg-grid">
          <!-- Reset to the system default -->
          <button
            type="button"
            class="bg-thumb bg-thumb--default"
            :class="{ 'is-selected': selectedId === null }"
            @click="select(null)"
          >
            <div class="bg-thumb-default-inner">
              <i class="fas fa-rotate-left"></i>
              <span>Default</span>
            </div>
            <span v-if="selectedId === null" class="bg-thumb-check"><i class="fas fa-check"></i></span>
          </button>

          <div
            v-for="bg in backgrounds"
            :key="bg.id"
            class="bg-thumb-wrap"
          >
            <button
              type="button"
              class="bg-thumb"
              :class="{ 'is-selected': selectedId === bg.id, 'is-inactive': !bg.is_active }"
              :style="{ backgroundImage: `url('${thumb(bg.url)}')` }"
              @click="select(bg.id)"
            >
              <span v-if="bg.is_default" class="bg-thumb-badge"><i class="fas fa-star"></i> Default</span>
              <span v-if="!bg.is_active" class="bg-thumb-badge bg-thumb-badge--muted">Hidden</span>
              <span v-if="selectedId === bg.id" class="bg-thumb-check"><i class="fas fa-check"></i></span>
              <span class="bg-thumb-name">{{ bg.name || ('Background #' + bg.id) }}</span>
            </button>

            <!-- Superadmin per-item controls -->
            <div v-if="canManage" class="bg-admin-actions">
              <button type="button" title="Set as default" @click="makeDefault(bg)" :disabled="bg.is_default">
                <i class="fas fa-star"></i>
              </button>
              <button type="button" :title="bg.is_active ? 'Hide from users' : 'Show to users'" @click="toggleActive(bg)">
                <i :class="bg.is_active ? 'fas fa-eye' : 'fas fa-eye-slash'"></i>
              </button>
              <button type="button" title="Delete" class="danger" @click="remove(bg)">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Superadmin: upload new background -->
      <section v-if="canManage" class="bg-card">
        <div class="bg-card-head">
          <h5><i class="fas fa-cloud-arrow-up"></i> Add a background</h5>
          <span class="bg-card-hint">Available to everyone once uploaded</span>
        </div>

        <form class="bg-upload-form" @submit.prevent="upload">
          <label class="bg-dropzone" :class="{ 'has-file': uploadFiles.length }">
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              class="bg-dropzone-input"
              multiple
              @change="onFileChange"
            />
            <i class="fas" :class="uploadFiles.length ? 'fa-images' : 'fa-cloud-arrow-up'"></i>
            <span class="bg-dropzone-text">
              {{ uploadFiles.length
                ? (uploadFiles.length === 1 ? uploadFiles[0].name : uploadFiles.length + ' images selected')
                : 'Click to select one or more images' }}
            </span>
            <span class="bg-dropzone-hint">PNG, JPG or WEBP · up to 5MB each · select multiple at once</span>
          </label>

          <!-- Preview chips for the selected files -->
          <div v-if="uploadFiles.length" class="bg-preview-row">
            <div v-for="(f, i) in uploadPreviews" :key="i" class="bg-preview-chip">
              <img :src="f.url" :alt="f.name" />
              <button type="button" class="bg-preview-remove" title="Remove" @click="removeSelected(i)">
                <i class="fas fa-xmark"></i>
              </button>
            </div>
          </div>

          <div class="bg-upload-fields">
            <input v-model="uploadName" type="text" placeholder="Name applied to all (optional)" class="bg-upload-name" />
            <label class="bg-upload-default">
              <input type="checkbox" v-model="uploadIsDefault" />
              <span>Set first as default</span>
            </label>
            <button type="submit" class="bg-upload-btn" :disabled="!uploadFiles.length || uploading">
              <i v-if="uploading" class="fas fa-spinner fa-spin"></i>
              {{ uploading ? 'Uploading…' : uploadButtonLabel }}
            </button>
          </div>
        </form>
      </section>

      <transition name="bg-toast">
        <p v-if="message" class="bg-toast" :class="{ error: messageIsError }">
          <i class="fas" :class="messageIsError ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
          {{ message }}
        </p>
      </transition>
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
    const selectedId = ref(null)
    const canManage = ref(false)

    const uploadFiles = ref([])
    const uploadName = ref('')
    const uploadIsDefault = ref(false)
    const uploading = ref(false)
    const fileInput = ref(null)

    const message = ref('')
    const messageIsError = ref(false)

    // Object URLs for thumbnail previews of the files about to be uploaded.
    const uploadPreviews = computed(() =>
      uploadFiles.value.map((f) => ({ name: f.name, url: URL.createObjectURL(f) }))
    )

    const uploadButtonLabel = computed(() =>
      uploadFiles.value.length > 1
        ? `Upload ${uploadFiles.value.length} backgrounds`
        : 'Upload background'
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
        selectedId.value = data.selected_id ?? null
        canManage.value = !!data.can_manage
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed to load backgrounds', true)
      } finally {
        loading.value = false
      }
    }

    async function select(id) {
      const previous = selectedId.value
      selectedId.value = id
      try {
        const res = await backgroundsApi.select(id)
        syncFromUser(res.data?.data)
        notify('Background updated')
      } catch (e) {
        selectedId.value = previous
        notify(e?.response?.data?.message || 'Could not change background', true)
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
        if (selectedId.value === bg.id) selectedId.value = null
        notify('Background deleted')
        await load()
      } catch (e) {
        notify(e?.response?.data?.message || 'Failed to delete background', true)
      }
    }

    onMounted(load)

    return {
      loading, backgrounds, selectedId, canManage,
      uploadFiles, uploadPreviews, uploadButtonLabel,
      uploadName, uploadIsDefault, uploading, fileInput,
      message, messageIsError,
      thumb, select, onFileChange, removeSelected, upload, makeDefault, toggleActive, remove,
    }
  },
}
</script>

<style scoped>
/* Solid, readable surface over the dark app background */
.bg-page {
  max-width: 1040px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  color: #1f2937;
}

/* Hero */
.bg-hero {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
  color: #fff;
  border-radius: 18px;
  padding: 1.4rem 1.6rem;
  box-shadow: 0 12px 30px rgba(79, 70, 229, 0.28);
}

.bg-hero-icon {
  flex: 0 0 auto;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
}

.bg-hero-title {
  margin: 0;
  font-weight: 700;
  font-size: 1.25rem;
}

.bg-hero-sub {
  margin: 0.2rem 0 0;
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.85);
}

/* Cards — solid white so all text is crisp */
.bg-card {
  background: #ffffff;
  border: 1px solid #eef0f4;
  border-radius: 18px;
  padding: 1.4rem 1.6rem 1.6rem;
  box-shadow: 0 10px 34px rgba(17, 24, 39, 0.1);
}

.bg-card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.1rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid #f1f2f6;
}

.bg-card-head h5 {
  margin: 0;
  font-weight: 700;
  font-size: 1.02rem;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.bg-card-head h5 i {
  color: #6366f1;
}

.bg-card-hint {
  font-size: 0.8rem;
  color: #9ca3af;
}

/* Grid */
.bg-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 1.1rem;
}

.bg-thumb-wrap {
  position: relative;
}

.bg-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 10;
  border-radius: 14px;
  border: 2px solid #e5e7eb;
  background-size: cover;
  background-position: center;
  background-color: #eef0f4;
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  display: block;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.bg-thumb:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 24px rgba(17, 24, 39, 0.18);
  border-color: #c7cbff;
}

.bg-thumb.is-selected {
  border-color: #4f46e5;
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
}

.bg-thumb.is-inactive {
  opacity: 0.5;
  filter: grayscale(0.4);
}

.bg-thumb--default {
  display: flex;
  align-items: center;
  justify-content: center;
  background: repeating-linear-gradient(45deg, #f8f9fc, #f8f9fc 12px, #f1f3f9 12px, #f1f3f9 24px);
  color: #6b7280;
  border-style: dashed;
}

.bg-thumb-default-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.bg-thumb-default-inner i {
  font-size: 1.4rem;
  color: #818cf8;
}

.bg-thumb-name {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 0.5rem 0.6rem 0.4rem;
  font-size: 0.75rem;
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
  top: 0.5rem;
  left: 0.5rem;
  background: #4f46e5;
  color: #fff;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.bg-thumb-badge--muted {
  background: #6b7280;
  left: auto;
  right: 0.5rem;
}

.bg-thumb-check {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #4f46e5;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}

.bg-admin-actions {
  display: flex;
  gap: 0.4rem;
  margin-top: 0.6rem;
  justify-content: center;
}

.bg-admin-actions button {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 9px;
  width: 34px;
  height: 32px;
  cursor: pointer;
  color: #4b5563;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.bg-admin-actions button:hover:not(:disabled) {
  background: #f5f6ff;
  border-color: #c7cbff;
  color: #4f46e5;
}

.bg-admin-actions button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.bg-admin-actions button.danger {
  color: #dc2626;
  border-color: #fca5a5;
}

.bg-admin-actions button.danger:hover {
  background: #fef2f2;
}

/* Upload */
.bg-upload-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.bg-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  border: 2px dashed #d1d5db;
  border-radius: 14px;
  padding: 1.6rem;
  cursor: pointer;
  text-align: center;
  background: #fafbff;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.bg-dropzone:hover {
  border-color: #818cf8;
  background: #f5f6ff;
}

.bg-dropzone.has-file {
  border-color: #10b981;
  background: #f0fdf9;
}

.bg-dropzone > i {
  font-size: 1.6rem;
  color: #818cf8;
}

.bg-dropzone.has-file > i {
  color: #10b981;
}

.bg-dropzone-input {
  display: none;
}

.bg-dropzone-text {
  font-weight: 600;
  color: #374151;
  font-size: 0.9rem;
  word-break: break-all;
}

.bg-dropzone-hint {
  font-size: 0.75rem;
  color: #9ca3af;
}

/* Selected-file previews */
.bg-preview-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.bg-preview-chip {
  position: relative;
  width: 86px;
  height: 56px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  box-shadow: 0 2px 8px rgba(17, 24, 39, 0.12);
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

.bg-upload-fields {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.bg-upload-name {
  flex: 1 1 200px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
  color: #1f2937;
  background: #fff;
}

.bg-upload-name:focus {
  outline: none;
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.2);
}

.bg-upload-default {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.875rem;
  color: #4b5563;
  cursor: pointer;
  user-select: none;
}

.bg-upload-btn {
  background: linear-gradient(135deg, #4f46e5, #6d28d9);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 0.6rem 1.3rem;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.875rem;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.bg-upload-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.38);
}

.bg-upload-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  box-shadow: none;
}

/* Skeleton loading */
.bg-skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 1.1rem;
}

.bg-skeleton {
  aspect-ratio: 16 / 10;
  border-radius: 14px;
  background: linear-gradient(90deg, #eef0f4 25%, #f6f7fa 50%, #eef0f4 75%);
  background-size: 200% 100%;
  animation: bg-shimmer 1.3s ease infinite;
}

@keyframes bg-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Toast */
.bg-toast {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  background: #065f46;
  color: #fff;
  padding: 0.7rem 1.2rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
  z-index: 1200;
}

.bg-toast.error {
  background: #b91c1c;
}

.bg-toast-enter-active,
.bg-toast-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.bg-toast-enter-from,
.bg-toast-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(12px);
}

@media (max-width: 640px) {
  .bg-hero {
    flex-direction: column;
    text-align: center;
  }
}
</style>
