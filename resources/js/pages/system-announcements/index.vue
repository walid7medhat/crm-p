<template>
  <div class="system-announcements-page">
    <Breadcrumb
      title="Announcements"
      :breadcrumbs="[
        { name: 'Dashboard', path: '/' },
        { name: 'Announcements' },
      ]"
    />

    <section class="sa-panel">
      <header class="sa-panel__head">
        <div>
          <h6 class="sa-title">{{ editingId ? 'Edit announcement' : 'New announcement' }}</h6>
          <p class="sa-subtitle">Shown to the selected audience on the schedule you set. It stays open until they close it.</p>
        </div>
        <button v-if="editingId" type="button" class="sa-btn sa-btn--ghost" @click="resetForm">Cancel edit</button>
      </header>

      <p v-if="notice" class="sa-notice" :class="{ 'is-error': noticeError }">{{ notice }}</p>

      <form class="sa-form" @submit.prevent="save">
        <label class="sa-field">
          <span>Name</span>
          <input v-model.trim="form.title" type="text" maxlength="160" required placeholder="Internal name" />
        </label>

        <div class="sa-uploads">
          <label class="sa-upload">
            <span>Desktop image</span>
            <small>Laptop and tablet landscape. 1440 × 960 WebP.</small>
            <input type="file" accept="image/png,image/jpeg,image/webp" @change="onFile('desktop', $event)" />
            <img v-if="desktopPreview" :src="desktopPreview" alt="Desktop preview" />
          </label>
          <label class="sa-upload">
            <span>Mobile image</span>
            <small>Phones and tablet portrait. 960 × 1360 WebP.</small>
            <input type="file" accept="image/png,image/jpeg,image/webp" @change="onFile('mobile', $event)" />
            <img v-if="mobilePreview" :src="mobilePreview" alt="Mobile preview" />
          </label>
        </div>

        <div class="sa-audience">
          <span class="sa-audience__label">Audience</span>
          <div class="sa-audience__options">
            <button
              v-for="option in audiences"
              :key="option.key"
              type="button"
              class="sa-audience__option"
              :class="{ 'is-selected': form.audiences.includes(option.key) }"
              @click="toggleAudience(option.key)"
            >
              <iconify-icon :icon="form.audiences.includes(option.key) ? 'lucide:check' : 'lucide:plus'" />
              {{ option.label }}
            </button>
          </div>
        </div>

        <label class="sa-field">
          <span>Show again every</span>
          <select v-model.number="form.frequency_hours" required>
            <option v-for="hours in frequencyHours" :key="hours" :value="hours">
              {{ hours === 1 ? 'Every 1 hour' : `Every ${hours} hours` }}
            </option>
          </select>
        </label>

        <label class="sa-check sa-check--toggle">
          <input v-model="form.is_active" type="checkbox" />
          <span>{{ form.is_active ? 'Active' : 'Inactive' }}</span>
        </label>

        <div class="sa-form__actions">
          <button type="submit" class="sa-btn sa-btn--primary" :disabled="saving">
            {{ saving ? 'Saving...' : editingId ? 'Save changes' : 'Save announcement' }}
          </button>
        </div>
      </form>
    </section>

    <section class="sa-panel">
      <header class="sa-panel__head">
        <h6 class="sa-title">Announcements</h6>
      </header>

      <p v-if="loading" class="sa-empty">Loading announcements...</p>
      <p v-else-if="!campaigns.length" class="sa-empty">No announcements yet.</p>

      <div v-else class="sa-list">
        <article v-for="item in campaigns" :key="item.id" class="sa-card">
          <div class="sa-card__images">
            <img :src="item.desktop_image_url" :alt="`${item.title} desktop`" />
            <img :src="item.mobile_image_url" :alt="`${item.title} mobile`" />
          </div>
          <div class="sa-card__body">
            <h3>{{ item.title }}</h3>
            <p>{{ item.audience_labels.join(', ') || 'No audience' }}</p>
            <p>{{ item.frequency_label }}</p>
            <p class="sa-status" :class="{ 'is-on': item.is_active }">
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </p>
          </div>
          <div class="sa-card__actions">
            <button type="button" class="sa-btn sa-btn--ghost" @click="toggleActive(item)" :disabled="busyId === item.id">
              {{ item.is_active ? 'Disable' : 'Enable' }}
            </button>
            <button type="button" class="sa-btn sa-btn--ghost" @click="edit(item)">Edit</button>
            <button type="button" class="sa-btn sa-btn--danger" @click="remove(item)" :disabled="busyId === item.id">Delete</button>
            <button type="button" class="sa-btn sa-btn--primary" @click="test(item)">Test</button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue'
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import { getApiErrorMessage } from '@/plugins/axios'
import { openSystemCampaignPreview } from '@/composables/useSystemCampaignPreview.js'
import {
  createSystemCampaign,
  deleteSystemCampaign,
  fetchSystemCampaigns,
  updateSystemCampaign,
  updateSystemCampaignActive,
} from '@/services/systemCampaignsApi.js'

const loading = ref(true)
const saving = ref(false)
const busyId = ref(null)
const notice = ref('')
const noticeError = ref(false)
const campaigns = ref([])
const audiences = ref([{ key: 'sales_active', label: 'Sales Active' }])
const frequencyHours = ref(Array.from({ length: 24 }, (_, index) => index + 1))
const editingId = ref(null)
const desktopFile = ref(null)
const mobileFile = ref(null)
const desktopPreview = ref('')
const mobilePreview = ref('')
const localPreviews = []

const form = reactive({
  title: '',
  audiences: ['sales_active'],
  frequency_hours: 1,
  is_active: true,
})

function notify(message, isError = false) {
  notice.value = message
  noticeError.value = isError
}

function errorText(error, fallback) {
  const errors = error?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat()[0]
    if (first) return first
  }
  return getApiErrorMessage(error, fallback)
}

function rememberPreview(url) {
  if (url && url.startsWith('blob:')) localPreviews.push(url)
  return url
}

function clearLocalPreviews() {
  localPreviews.splice(0).forEach((url) => URL.revokeObjectURL(url))
}

function resetForm() {
  editingId.value = null
  form.title = ''
  form.audiences = audiences.value.some((option) => option.key === 'sales_active')
    ? ['sales_active']
    : audiences.value.slice(0, 1).map((option) => option.key)
  form.frequency_hours = 1
  form.is_active = true
  desktopFile.value = null
  mobileFile.value = null
  clearLocalPreviews()
  desktopPreview.value = ''
  mobilePreview.value = ''
}

function toggleAudience(key) {
  if (form.audiences.includes(key)) {
    form.audiences = form.audiences.filter((item) => item !== key)
    return
  }
  form.audiences = [...form.audiences, key]
}

function onFile(slot, event) {
  const file = event.target.files?.[0] || null
  const preview = file ? rememberPreview(URL.createObjectURL(file)) : ''
  if (slot === 'desktop') {
    desktopFile.value = file
    if (preview) desktopPreview.value = preview
    return
  }
  mobileFile.value = file
  if (preview) mobilePreview.value = preview
}

function edit(item) {
  editingId.value = item.id
  form.title = item.title || ''
  form.audiences = Array.isArray(item.audiences) ? [...item.audiences] : []
  form.frequency_hours = item.frequency_hours || 1
  form.is_active = !!item.is_active
  desktopFile.value = null
  mobileFile.value = null
  clearLocalPreviews()
  desktopPreview.value = item.desktop_image_url || ''
  mobilePreview.value = item.mobile_image_url || ''
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function buildFormData() {
  const data = new FormData()
  data.append('title', form.title)
  form.audiences.forEach((audience) => data.append('audiences[]', audience))
  data.append('frequency_hours', String(form.frequency_hours))
  data.append('is_active', form.is_active ? '1' : '0')
  if (desktopFile.value) data.append('desktop_image', desktopFile.value)
  if (mobileFile.value) data.append('mobile_image', mobileFile.value)
  return data
}

async function load() {
  loading.value = true
  try {
    const response = await fetchSystemCampaigns()
    const payload = response?.data?.data || {}
    campaigns.value = Array.isArray(payload.campaigns) ? payload.campaigns : []
    if (Array.isArray(payload.audiences) && payload.audiences.length) {
      audiences.value = payload.audiences
    }
    if (Array.isArray(payload.frequency_hours) && payload.frequency_hours.length) {
      frequencyHours.value = payload.frequency_hours
    }
  } catch (error) {
    notify(errorText(error, 'Could not load announcements'), true)
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.audiences.length) {
    notify('Select at least one audience.', true)
    return
  }
  if (!editingId.value && (!desktopFile.value || !mobileFile.value)) {
    notify('Upload both a desktop image and a mobile image.', true)
    return
  }

  saving.value = true
  notice.value = ''
  try {
    const data = buildFormData()
    if (editingId.value) {
      await updateSystemCampaign(editingId.value, data)
      notify('Announcement updated.')
    } else {
      await createSystemCampaign(data)
      notify('Announcement saved.')
    }
    resetForm()
    await load()
  } catch (error) {
    notify(errorText(error, 'Could not save announcement'), true)
  } finally {
    saving.value = false
  }
}

async function toggleActive(item) {
  busyId.value = item.id
  notice.value = ''
  try {
    const response = await updateSystemCampaignActive(item.id, !item.is_active)
    const updated = response?.data?.data
    campaigns.value = campaigns.value.map((row) => (row.id === item.id && updated ? updated : row))
    if (editingId.value === item.id && updated) {
      form.is_active = !!updated.is_active
    }
  } catch (error) {
    notify(errorText(error, 'Could not update status'), true)
  } finally {
    busyId.value = null
  }
}

async function remove(item) {
  if (!window.confirm(`Delete "${item.title}"?`)) return
  busyId.value = item.id
  notice.value = ''
  try {
    await deleteSystemCampaign(item.id)
    if (editingId.value === item.id) resetForm()
    notify('Announcement deleted.')
    await load()
  } catch (error) {
    notify(errorText(error, 'Could not delete announcement'), true)
  } finally {
    busyId.value = null
  }
}

function test(item) {
  openSystemCampaignPreview(item)
}

onMounted(load)
onUnmounted(clearLocalPreviews)
</script>

<style scoped>
.system-announcements-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 8px 16px 32px;
  box-sizing: border-box;
}

.sa-panel {
  background: #fff;
  border: 1px solid #ece7f3;
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(11, 7, 54, 0.04);
  padding: 20px;
}

.sa-panel__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.sa-title {
  margin: 0;
  color: #0b0736;
  font-size: 16px;
  font-weight: 700;
}

.sa-subtitle,
.sa-empty,
.sa-card__body p {
  margin: 4px 0 0;
  color: #6b7280;
  font-size: 13px;
  line-height: 1.5;
}

.sa-notice {
  margin: 0 0 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f3ecf7;
  color: #0b0736;
  font-size: 13px;
}

.sa-notice.is-error {
  background: #fef2f2;
  color: #991b1b;
}

.sa-form,
.sa-uploads,
.sa-list {
  display: grid;
  gap: 14px;
}

.sa-uploads {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.sa-field,
.sa-upload {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.sa-field span,
.sa-upload span,
.sa-field legend {
  color: #0b0736;
  font-size: 13px;
  font-weight: 600;
}

.sa-upload small {
  color: #6b7280;
  font-size: 12px;
}

.sa-field input:not([type="checkbox"]),
.sa-field select,
.sa-upload input[type="file"] {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  color: #1a1528;
  font-size: 16px;
  padding: 10px 12px;
}

.sa-field fieldset,
.sa-field {
  border: 0;
  padding: 0;
  margin: 0;
}

.sa-audience__label {
  color: #0b0736;
  font-size: 13px;
  font-weight: 600;
}

.sa-audience__options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.sa-audience__option {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #fff;
  color: #1a1528;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  touch-action: manipulation;
}

.sa-audience__option.is-selected {
  border-color: #733e87;
  background: #f3ecf7;
  color: #0b0736;
}

.sa-check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #1a1528;
  font-size: 14px;
}

.sa-check input {
  width: 18px;
  height: 18px;
  accent-color: #733e87;
}

.sa-upload img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 12px;
  background: #f7f5fb;
}

.sa-form__actions {
  display: flex;
  justify-content: flex-start;
}

.sa-list {
  grid-template-columns: 1fr;
}

.sa-card {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 12px;
  border: 1px solid #f0ebf5;
  border-radius: 14px;
}

.sa-card__images {
  display: grid;
  grid-template-columns: 1.4fr 0.7fr;
  gap: 8px;
}

.sa-card__images img {
  width: 100%;
  height: 92px;
  object-fit: cover;
  border-radius: 10px;
  background: #f7f5fb;
}

.sa-card__body h3 {
  margin: 0;
  color: #0b0736;
  font-size: 15px;
  font-weight: 700;
}

.sa-status {
  display: inline-flex;
  margin-top: 6px;
  color: #6b7280;
  font-size: 12px;
  font-weight: 700;
}

.sa-status.is-on {
  color: #733e87;
}

.sa-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.sa-btn {
  min-height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1px solid transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  touch-action: manipulation;
}

.sa-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.sa-btn--primary {
  background: #6b21a8;
  color: #fff;
}

.sa-btn--primary:hover:not(:disabled) {
  background: #733e87;
}

.sa-btn--ghost {
  background: #fff;
  color: #0b0736;
  border-color: #e5e7eb;
}

.sa-btn--danger {
  background: #fff;
  color: #991b1b;
  border-color: #fecaca;
}

@media (max-width: 800px) {
  .sa-uploads,
  .sa-card {
    grid-template-columns: 1fr;
  }

  .sa-card__actions {
    justify-content: flex-start;
  }
}
</style>
