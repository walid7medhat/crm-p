import { ref } from 'vue'

export const MAX_COMPARE = 3
const SESSION_KEY = 'property_compare_session'

const compareMode = ref(false)
const selected = ref([])
const resultOpen = ref(false)
const notice = ref('')
let noticeTimer = null

export function canUsePropertyCompare() {
  try {
    const user = JSON.parse(localStorage.getItem('user') || 'null')
    return Array.isArray(user?.roles) && user.roles.includes('super_admin')
  } catch {
    return false
  }
}

function flash(message) {
  notice.value = message
  clearTimeout(noticeTimer)
  noticeTimer = setTimeout(() => {
    notice.value = ''
  }, 2600)
}

export function isActiveListing(listing) {
  if (!listing) return false
  if (listing.is_active === false || listing.is_active === 0 || listing.is_active === '0') return false
  if (listing.is_archived === true || listing.is_archived === 1 || listing.is_archived === '1') return false
  if (listing.approved === false || listing.approved === 0 || listing.approved === '0') return false
  return !['converted', 'rented', 'draft'].includes(listing.status)
}

function listingImage(listing) {
  const main = listing?.main_image
  if (typeof main === 'string' && main) return main
  if (main?.image_url) return main.image_url
  const first = listing?.gallery_images?.[0]
  if (typeof first === 'string') return first
  return first?.image_url || first?.url || null
}

export function listingSnapshot(listing) {
  const area = listing?.area
  const type = listing?.property_type
  return {
    id: String(listing.id),
    title: listing.title || listing.project?.title || listing.project_name || listing.reference_number || 'Listing',
    price: listing.price ?? null,
    image: listingImage(listing),
    reference_number: listing.reference_number || null,
    area: typeof area === 'string' ? area : (area?.title || area?.name || null),
    listing_status: listing.listing_status || null,
    property_type: typeof type === 'string' ? type : (type?.name || null),
    number_of_bedrooms: listing.number_of_bedrooms,
    number_of_bathrooms: listing.number_of_bathrooms,
    size_sqft: listing.size_sqft,
    size_sqmt: listing.size_sqmt,
    furnishing: listing.furnishing || listing.furnished || null,
    status: listing.status,
    is_active: listing.is_active,
    approved: listing.approved,
    is_archived: listing.is_archived,
    project: listing.project?.title ? { title: listing.project.title } : null,
  }
}

function persist() {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify({
      mode: compareMode.value,
      items: selected.value,
    }))
  } catch {
    /* ignore */
  }
}

function restore() {
  if (!canUsePropertyCompare()) {
    selected.value = []
    compareMode.value = false
    try { sessionStorage.removeItem(SESSION_KEY) } catch { /* ignore */ }
    return
  }
  try {
    const parsed = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null')
    const items = Array.isArray(parsed?.items) ? parsed.items.filter((item) => item?.id) : []
    selected.value = items.slice(0, MAX_COMPARE).map((item) => ({ ...item, id: String(item.id) }))
    compareMode.value = parsed?.mode === true
  } catch {
    selected.value = []
    compareMode.value = false
  }
}

restore()

export function startPropertyCompare() {
  if (!canUsePropertyCompare()) return
  compareMode.value = true
  notice.value = ''
  persist()
}

export function usePropertyCompareSelection() {
  function isSelected(id) {
    return selected.value.some((item) => item.id === String(id))
  }

  function toggleListing(listing) {
    if (!canUsePropertyCompare() || !listing?.id) return false
    const id = String(listing.id)
    if (isSelected(id)) {
      selected.value = selected.value.filter((item) => item.id !== id)
      persist()
      return true
    }
    if (!isActiveListing(listing)) {
      flash('Only active units can be compared.')
      return false
    }
    if (selected.value.length >= MAX_COMPARE) {
      flash('You can select up to 3 listings.')
      return false
    }
    selected.value = [...selected.value, listingSnapshot(listing)]
    persist()
    return true
  }

  function removeListing(id) {
    selected.value = selected.value.filter((item) => item.id !== String(id))
    persist()
  }

  function openResults() {
    if (!canUsePropertyCompare() || selected.value.length < 2) return
    resultOpen.value = true
  }

  function closeResults() {
    resultOpen.value = false
  }

  function cancelCompare() {
    compareMode.value = false
    selected.value = []
    resultOpen.value = false
    notice.value = ''
    persist()
  }

  return {
    compareMode,
    selected,
    resultOpen,
    notice,
    maxCompare: MAX_COMPARE,
    isSelected,
    toggleListing,
    removeListing,
    openResults,
    closeResults,
    cancelCompare,
    startPropertyCompare,
  }
}
