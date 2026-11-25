import api from '@/plugins/axios'

let idsPromise = null
let savedIds = new Set()

export function loadSavedListingIds() {
  if (!idsPromise) {
    idsPromise = api.get('/listings/saved/ids')
      .then((response) => {
        const list = response?.data?.data || []
        savedIds = new Set(list.map((id) => Number(id)))
        return savedIds
      })
      .catch((error) => {
        idsPromise = null
        throw error
      })
  }
  return idsPromise
}

export function rememberSavedListing(id, saved) {
  const numericId = Number(id)
  if (saved) savedIds.add(numericId)
  else savedIds.delete(numericId)
  return savedIds
}

export function isListingSaved(id) {
  return savedIds.has(Number(id))
}
