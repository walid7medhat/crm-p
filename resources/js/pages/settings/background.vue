<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="Background" :breadcrumbs="[{ name: 'Settings - Background' }]" />

    <div class="bg-settings-card">
      <div class="bg-settings-header">
        <h5>Choose your background</h5>
        <p>Pick a background image for your workspace. It only changes how the app looks for you.</p>
      </div>

      <div v-if="loading" class="bg-settings-empty">Loading backgrounds…</div>

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
            <span v-if="bg.is_default" class="bg-thumb-badge">Default</span>
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
    </div>

    <!-- Superadmin: upload new background -->
    <div v-if="canManage" class="bg-settings-card">
      <div class="bg-settings-header">
        <h5>Add a background</h5>
        <p>Upload an image to make it available for everyone to choose.</p>
      </div>

      <form class="bg-upload-form" @submit.prevent="upload">
        <input
          ref="fileInput"
          type="file"
          accept="image/png,image/jpeg,image/jpg,image/webp"
          @change="onFileChange"
        />
        <input v-model="uploadName" type="text" placeholder="Name (optional)" class="bg-upload-name" />
        <label class="bg-upload-default">
          <input type="checkbox" v-model="uploadIsDefault" />
          Set as default
        </label>
        <button type="submit" class="bg-upload-btn" :disabled="!uploadFile || uploading">
          {{ uploading ? 'Uploading…' : 'Upload' }}
        </button>
      </form>
    </div>

    <p v-if="message" class="bg-settings-message" :class="{ error: messageIsError }">{{ message }}</p>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
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

    const uploadFile = ref(null)
    const uploadName = ref('')
    const uploadIsDefault = ref(false)
    const uploading = ref(false)
    const fileInput = ref(null)

    const message = ref('')
    const messageIsError = ref(false)

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
      uploadFile.value = e.target.files?.[0] || null
    }

    async function upload() {
      if (!uploadFile.value) return
      uploading.value = true
      try {
        await backgroundsApi.upload({
          image: uploadFile.value,
          name: uploadName.value || null,
          isDefault: uploadIsDefault.value,
        })
        uploadFile.value = null
        uploadName.value = ''
        uploadIsDefault.value = false
        if (fileInput.value) fileInput.value.value = ''
        notify('Background uploaded')
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
      uploadFile, uploadName, uploadIsDefault, uploading, fileInput,
      message, messageIsError,
      thumb, select, onFileChange, upload, makeDefault, toggleActive, remove,
    }
  },
}
</script>

<style scoped>
.bg-settings-card {
  background: rgba(255, 255, 255, 0.9);
  border-radius: 14px;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
}

.bg-settings-header h5 {
  margin: 0 0 0.25rem;
  font-weight: 600;
}

.bg-settings-header p {
  margin: 0 0 1rem;
  color: #6b7280;
  font-size: 0.875rem;
}

.bg-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 1rem;
}

.bg-thumb-wrap {
  position: relative;
}

.bg-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 10;
  border-radius: 12px;
  border: 2px solid transparent;
  background-size: cover;
  background-position: center;
  background-color: #e5e7eb;
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  display: block;
}

.bg-thumb.is-selected {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
}

.bg-thumb.is-inactive {
  opacity: 0.55;
}

.bg-thumb--default {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  color: #4b5563;
}

.bg-thumb-default-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.85rem;
}

.bg-thumb-default-inner i {
  font-size: 1.25rem;
}

.bg-thumb-name {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 0.35rem 0.5rem;
  font-size: 0.75rem;
  color: #fff;
  text-align: left;
  background: linear-gradient(transparent, rgba(0, 0, 0, 0.6));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bg-thumb-badge {
  position: absolute;
  top: 0.4rem;
  left: 0.4rem;
  background: #4f46e5;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 999px;
}

.bg-thumb-badge--muted {
  background: #6b7280;
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
  background: #4f46e5;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
}

.bg-admin-actions {
  display: flex;
  gap: 0.4rem;
  margin-top: 0.5rem;
  justify-content: center;
}

.bg-admin-actions button {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 8px;
  width: 30px;
  height: 30px;
  cursor: pointer;
  color: #4b5563;
}

.bg-admin-actions button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.bg-admin-actions button.danger {
  color: #dc2626;
  border-color: #fca5a5;
}

.bg-upload-form {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.bg-upload-name {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 0.45rem 0.65rem;
  font-size: 0.875rem;
}

.bg-upload-default {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.bg-upload-btn {
  background: #4f46e5;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.5rem 1.1rem;
  cursor: pointer;
  font-weight: 600;
}

.bg-upload-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.bg-settings-empty {
  color: #6b7280;
  padding: 1rem 0;
}

.bg-settings-message {
  color: #059669;
  font-weight: 500;
}

.bg-settings-message.error {
  color: #dc2626;
}
</style>
