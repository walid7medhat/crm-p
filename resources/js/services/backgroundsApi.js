import api from '@/plugins/axios'

/**
 * Background images API.
 *
 * Any authenticated user can list backgrounds and pick one for themselves.
 * Superadmin-only endpoints (upload / update / setDefault / remove) will return
 * 403 for everyone else.
 */
const backgroundsApi = {
  // List backgrounds available to the current user + their current selection.
  list() {
    return api.get('/backgrounds')
  },

  // User picks a background (pass null to reset to the system default).
  select(backgroundId) {
    return api.post('/profile/background', { background_id: backgroundId ?? null })
  },

  // --- Superadmin only ---

  // Upload one or many images at once. `images` is an array of File objects.
  upload({ images, name = null, isDefault = false }) {
    const form = new FormData()
    const files = Array.isArray(images) ? images : [images]
    files.forEach((file) => form.append('images[]', file))
    if (name) form.append('name', name)
    form.append('is_default', isDefault ? 1 : 0)
    return api.post('/backgrounds', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  update(id, payload) {
    return api.put(`/backgrounds/${id}`, payload)
  },

  setDefault(id) {
    return api.post(`/backgrounds/${id}/default`)
  },

  remove(id) {
    return api.delete(`/backgrounds/${id}`)
  },
}

export default backgroundsApi
