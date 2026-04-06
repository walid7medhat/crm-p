<template>
  <div class="pf-shell">
    <!-- Map LEFT, sidebar RIGHT — no page-level list below map -->
    <div class="pf-split">
      <div class="pf-map-pane">
        <div ref="mapContainer" class="pf-map"></div>
      </div>

      <aside class="pf-sidebar">
        <div class="pf-sidebar__head">
          <span class="pf-sidebar__title">Properties</span>
        </div>

        <div class="pf-sidebar__controls">
          <label class="pf-sidebar__label" for="pf-prop-search">Search</label>
          <div class="pf-sidebar__search">
            <span class="pf-sidebar__search-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7" />
                <path d="M21 21l-4.3-4.3" />
              </svg>
            </span>
            <input
              id="pf-prop-search"
              v-model="propertySearchQuery"
              type="search"
              class="form-control form-control-sm pf-sidebar__search-input"
              placeholder="Title, area, type…"
              autocomplete="off"
              aria-label="Filter properties"
            />
          </div>

          <label class="pf-sidebar__label" for="pf-area-select">Area</label>
          <select
            id="pf-area-select"
            v-model="areaFilterSelect"
            class="form-select form-select-sm"
          >
            <option value="">All areas</option>
            <option v-for="a in allAreas" :key="a.id" :value="String(a.id)">
              {{ a.name }}
            </option>
          </select>

          <label class="pf-sidebar__label" for="pf-status-select">Status</label>
          <div class="pf-sidebar__row">
            <select id="pf-status-select" v-model="listingStatus" class="form-select form-select-sm pf-sidebar__grow">
              <option value="">All</option>
              <option value="sale">Sale</option>
              <option value="rent">Rent</option>
            </select>
            <button
              type="button"
              class="btn btn-primary btn-sm pf-sidebar__apply"
              :disabled="loading"
              @click="refreshData"
            >
              {{ loading ? '…' : 'Refresh' }}
            </button>
          </div>

          <p v-if="areasLoadError" class="pf-sidebar__warn small mb-0">{{ areasLoadError }}</p>
          <div v-if="fetchError" class="alert alert-danger py-2 px-2 mb-0 small" role="alert">{{ fetchError }}</div>
          <div
            v-else-if="!loading && !properties.length"
            class="alert alert-secondary py-2 px-2 mb-0 small"
            role="status"
          >
            No properties match filters.
          </div>
        </div>

        <div class="pf-sidebar__list-meta">
          <span>
            Page {{ sidebarPage }} / {{ totalSidebarPages }}
            · {{ rankedSidebarProperties.length }} total
          </span>
        </div>

        <div ref="cardListRef" class="pf-card-list">
          <button
            v-for="row in displayedSidebarRows"
            :key="row.p.id"
            type="button"
            class="pf-card"
            :class="{ 'pf-card--active': selectedListingId === row.p.id }"
            :data-listing-id="row.p.id"
            @click="onCardClick(row.p)"
          >
            <div class="pf-card__media">
              <img v-if="row.p.hero_image" class="pf-card__img" :src="row.p.hero_image" alt="" loading="lazy" />
              <div v-else class="pf-card__placeholder" />
            </div>
            <div class="pf-card__body">
              <div class="pf-card__price">{{ formatPriceFull(row.p.price) }}</div>
              <div class="pf-card__title">{{ row.headline }}</div>
              <div v-if="row.p.area_name" class="pf-card__area">
                <span class="pf-card__area-lbl">Area</span>
                <span class="pf-card__area-val">{{ row.p.area_name }}</span>
              </div>
              <div v-if="row.meta" class="pf-card__meta">{{ row.meta }}</div>
              <div v-if="row.statusLabel" class="pf-card__status">
                <span class="badge rounded-pill text-bg-primary">{{ row.statusLabel }}</span>
              </div>
            </div>
          </button>

          <p
            v-if="!loading && properties.length && !rankedSidebarProperties.length"
            class="pf-card-list__empty small mb-0"
          >
            No listings match your search.
          </p>
        </div>

        <div v-if="totalSidebarPages > 1" class="pf-sidebar__pager">
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            :disabled="sidebarPage <= 1 || loading"
            @click="sidebarPage--"
          >
            Prev
          </button>
          <span class="pf-sidebar__pager-info">{{ sidebarPage }} / {{ totalSidebarPages }}</span>
          <button
            type="button"
            class="btn btn-outline-secondary btn-sm"
            :disabled="sidebarPage >= totalSidebarPages || loading"
            @click="sidebarPage++"
          >
            Next
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'

const router = useRouter()
const route = useRoute()
const mapContainer = ref(null)
const cardListRef = ref(null)

const areaIdFromQuery = computed(() => {
  const q = route.query.area_id
  if (q === undefined || q === null || q === '') {
    return null
  }
  const n = Number(Array.isArray(q) ? q[0] : q)
  return Number.isFinite(n) && n > 0 ? n : null
})

const listingStatus = ref('')
const loading = ref(false)
const fetchError = ref('')
const properties = ref([])
const selectedListingId = ref(null)

/** Sidebar: max 20 per page + relevance ranking (instant filter) */
const SIDEBAR_PAGE_SIZE = 20
const propertySearchQuery = ref('')
const sidebarPage = ref(1)

watch(propertySearchQuery, () => {
  sidebarPage.value = 1
})

const normalizeSearch = (s) =>
  String(s || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ')
    .trim()

const propertySearchHaystack = (p) =>
  [
    p.listing_title,
    p.title,
    p.area_name,
    p.type,
    p.listing_status,
    p.project_name,
    p.project?.name,
    p.number_of_bedrooms != null ? `${p.number_of_bedrooms} bed` : '',
    p.number_of_bathrooms != null ? `${p.number_of_bathrooms} bath` : '',
  ]
    .filter(Boolean)
    .join(' ')

/**
 * Relevance score for sidebar ranking (higher = better match).
 * Fuzzy: full substring, prefix, word hits, subsequence match.
 */
const relevanceScore = (p, rawQuery) => {
  const q = normalizeSearch(rawQuery)
  if (!q) {
    return 1
  }
  const hay = normalizeSearch(propertySearchHaystack(p))
  if (!hay) {
    return 0
  }
  if (hay === q) {
    return 100000
  }
  if (hay.startsWith(q)) {
    return 50000 + q.length * 100
  }
  const idx = hay.indexOf(q)
  if (idx !== -1) {
    return 25000 + q.length * 80 + (200 - Math.min(idx, 200))
  }
  const words = q.split(' ').filter(Boolean)
  let wScore = 0
  for (const w of words) {
    if (w.length < 2) {
      continue
    }
    if (hay.includes(w)) {
      wScore += 8000 + w.length * 40
    }
  }
  let qi = 0
  for (let hi = 0; hi < hay.length && qi < q.length; hi++) {
    if (hay[hi] === q[qi]) {
      qi++
    }
  }
  if (qi === q.length) {
    wScore += 5000
  }
  return wScore
}

const rankedSidebarProperties = computed(() => {
  const list = [...properties.value]
  const q = propertySearchQuery.value.trim()
  if (!q) {
    return list.sort((a, b) => Number(a.id) - Number(b.id))
  }
  return list
    .map((p) => ({ p, s: relevanceScore(p, q) }))
    .filter((x) => x.s > 0)
    .sort((a, b) => b.s - a.s || Number(a.p.id) - Number(b.p.id))
    .map((x) => x.p)
})

const totalSidebarPages = computed(() =>
  Math.max(1, Math.ceil(rankedSidebarProperties.value.length / SIDEBAR_PAGE_SIZE)),
)

const displayedSidebarProperties = computed(() => {
  const start = (sidebarPage.value - 1) * SIDEBAR_PAGE_SIZE
  return rankedSidebarProperties.value.slice(start, start + SIDEBAR_PAGE_SIZE)
})

watch([rankedSidebarProperties, totalSidebarPages], () => {
  const max = totalSidebarPages.value
  if (sidebarPage.value > max) {
    sidebarPage.value = max
  }
})

/** Jump to the page that contains this listing (map → sidebar). */
const ensureSidebarContainsId = (id) => {
  if (id == null) {
    return
  }
  const list = rankedSidebarProperties.value
  const idx = list.findIndex((p) => p.id === id)
  if (idx === -1) {
    return
  }
  sidebarPage.value = Math.floor(idx / SIDEBAR_PAGE_SIZE) + 1
}

const formatPriceFull = (price) => {
  if (price == null || price === '') {
    return 'Price on request'
  }
  const n = Number(price)
  if (!Number.isFinite(n)) {
    return 'Price on request'
  }
  return `AED ${n.toLocaleString()}`
}

/** Card/popup headline: real unit title, or type — never project name. */
const propertyDisplayHeadline = (p) => {
  const t = p.listing_title != null ? String(p.listing_title).trim() : ''
  if (t) {
    return t
  }
  if (p.type) {
    return String(p.type)
  }
  return `Listing #${p.id}`
}

const formatListingStatusLabel = (s) => {
  if (!s) {
    return ''
  }
  const v = String(s).toLowerCase()
  if (v === 'sale') {
    return 'Sale'
  }
  if (v === 'rent') {
    return 'Rent'
  }
  return String(s)
}

/** Beds, baths, size for cards and popup (compact). */
const propertyMetaParts = (p) => {
  const parts = []
  const beds = p.number_of_bedrooms
  if (beds != null && Number(beds) > 0) {
    parts.push(`${Number(beds)} bed${Number(beds) === 1 ? '' : 's'}`)
  }
  const baths = p.number_of_bathrooms
  if (baths != null && Number(baths) > 0) {
    parts.push(`${Number(baths)} bath${Number(baths) === 1 ? '' : 's'}`)
  }
  const sqft = p.size_sqft
  const sqmt = p.size_sqmt
  if (sqft != null && Number(sqft) > 0) {
    parts.push(`${Number(sqft).toLocaleString()} sqft`)
  } else if (sqmt != null && Number(sqmt) > 0) {
    parts.push(`${Number(sqmt).toLocaleString()} sqm`)
  }
  return parts
}

const propertyMetaLine = (p) => propertyMetaParts(p).join(' · ')

/** Precompute card lines once per row (sidebar + relevance ordering). */
const displayedSidebarRows = computed(() =>
  displayedSidebarProperties.value.map((p) => ({
    p,
    headline: propertyDisplayHeadline(p),
    meta: propertyMetaLine(p),
    statusLabel: formatListingStatusLabel(p.listing_status),
  })),
)

/** Short label for map pins */
const formatPriceShort = (price) => {
  if (price == null || price === '') {
    return '· · ·'
  }
  const n = Number(price)
  if (!Number.isFinite(n)) {
    return '· · ·'
  }
  if (n >= 1_000_000) {
    const m = n / 1_000_000
    const t = m >= 10 ? m.toFixed(0) : m.toFixed(1).replace(/\.0$/, '')
    return `${t}M`
  }
  if (n >= 1000) {
    return `${Math.round(n / 1000)}K`
  }
  return String(n)
}

const hoveredMarkerId = ref(null)

const allAreas = ref([])
const areasLoadError = ref('')

const areaFilterSelect = computed({
  get() {
    return areaIdFromQuery.value != null ? String(areaIdFromQuery.value) : ''
  },
  set(v) {
    const next = { ...route.query }
    if (v === '' || v == null) {
      delete next.area_id
    } else {
      next.area_id = String(v)
    }
    router.replace({ path: route.path, query: next })
  },
})

/** Areas live under /api/listings/areas (not /api/areas). */
const loadAreasForSearch = async () => {
  areasLoadError.value = ''
  try {
    const [citiesRes, areasRes] = await Promise.all([
      api.get('/listings/areas', { params: { type: 'city' } }),
      api.get('/listings/areas', { params: { type: 'area' } }),
    ])
    const cities = Array.isArray(citiesRes.data?.data) ? citiesRes.data.data : []
    const areaRows = Array.isArray(areasRes.data?.data) ? areasRes.data.data : []
    const byId = new Map()
    ;[...cities, ...areaRows].forEach((a) => {
      if (a && a.id != null) {
        byId.set(a.id, a)
      }
    })
    allAreas.value = Array.from(byId.values()).sort((x, y) =>
      String(x.name || '').localeCompare(String(y.name || ''), undefined, { sensitivity: 'base' }),
    )
  } catch (e) {
    console.warn('Could not load areas for map search', e)
    areasLoadError.value =
      'Could not load the area list. Check that you can access Areas in the CRM, then refresh.'
  }
}

let map = null
let clusterLayer = null
const markerById = new Map()

const markerIconState = (id) => {
  if (selectedListingId.value === id) {
    return 'selected'
  }
  if (hoveredMarkerId.value === id) {
    return 'hover'
  }
  return 'default'
}

const makePricePinIcon = (property, state) => {
  const label = `AED ${formatPriceShort(property.price)}`
  const safeLabel = escapeHtml(label)
  const w = Math.min(118, Math.max(56, 36 + safeLabel.length * 5.5))
  const h = 34
  return L.divIcon({
    className: 'pf-price-pin-root',
    html: `<div class="pf-price-pin pf-price-pin--${state}" data-pid="${property.id}"><span class="pf-price-pin__lbl">${safeLabel}</span></div>`,
    iconSize: [Math.round(w), h],
    iconAnchor: [Math.round(w / 2), h],
    popupAnchor: [0, -h + 4],
  })
}

const updateMarkerIcon = (id) => {
  const m = markerById.get(id)
  const p = properties.value.find((x) => x.id === id)
  if (!m || !p) {
    return
  }
  const lat = p.latitude
  const lng = p.longitude
  if (lat == null || lng == null) {
    return
  }
  m.setIcon(makePricePinIcon(p, markerIconState(id)))
}

/** Escape text for use inside HTML (map popups). */
const escapeHtml = (s) => {
  if (s == null || s === '') {
    return ''
  }
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/** Popup: image, price, headline (no project), area, meta, CTA */
const buildPropertyPopupHtml = (property) => {
  const headline = escapeHtml(propertyDisplayHeadline(property))
  const area = escapeHtml(property.area_name || property.area?.name || '')
  const meta = escapeHtml(propertyMetaLine(property))
  const status = escapeHtml(formatListingStatusLabel(property.listing_status))
  const price =
    property.price != null
      ? `AED ${Number(property.price).toLocaleString()}`
      : 'Price on request'
  const imgUrl = property.hero_image ? escapeHtml(property.hero_image) : ''
  const id = Number(property.id)

  const thumb = imgUrl
    ? `<div class="pf-map-popup-card__thumb"><img class="pf-map-popup-card__thumb-img" src="${imgUrl}" alt="" loading="lazy" /></div>`
    : `<div class="pf-map-popup-card__thumb pf-map-popup-card__thumb--empty" aria-hidden="true"></div>`

  const areaRow = area
    ? `<div class="pf-map-popup-card__area"><span class="pf-map-popup-card__lbl">Area</span> ${area}</div>`
    : ''
  const metaRow = meta
    ? `<div class="pf-map-popup-card__meta">${meta}</div>`
    : ''
  const statusRow = status
    ? `<div class="pf-map-popup-card__status"><span class="badge text-bg-primary">${status}</span></div>`
    : ''

  return `
    <div class="pf-map-popup-card pf-map-popup-card--compact" data-listing-id="${id}">
      <div class="pf-map-popup-card__row">
        ${thumb}
        <div class="pf-map-popup-card__main">
          <div class="pf-map-popup-card__price">${escapeHtml(price)}</div>
          <div class="pf-map-popup-card__title">${headline}</div>
          ${areaRow}
          ${metaRow}
          ${statusRow}
        </div>
      </div>
      <button type="button" class="btn btn-primary btn-sm pf-map-popup-card__btn popup-view-btn w-100" data-id="${id}">View Details</button>
    </div>`
}

const fetchMapData = async () => {
  loading.value = true
  fetchError.value = ''
  try {
    const params = { per_page: 3000 }
    if (listingStatus.value) {
      params.listing_status = listingStatus.value
    }
    if (areaIdFromQuery.value) {
      params.area_id = areaIdFromQuery.value
    }

    const { data } = await api.get('/properties/map', { params })
    if (data && data.status === false) {
      fetchError.value = data.message || 'Map API returned an error'
      properties.value = []
      return []
    }
    const list = Array.isArray(data?.data) ? data.data : []
    properties.value = list
    return list
  } catch (error) {
    console.error('Failed to load property map data', error)
    fetchError.value =
      error.response?.data?.message ||
      error.message ||
      'Could not load map data. Check your connection.'
    properties.value = []
    return []
  } finally {
    loading.value = false
  }
}

const scrollCardIntoView = (id) => {
  ensureSidebarContainsId(id)
  nextTick(() => {
    const root = cardListRef.value
    const el = root?.querySelector(`[data-listing-id="${id}"]`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  })
}

/** After refresh / filter: show list from the top so properties in view match the map. */
const scrollSidebarListToTop = () => {
  nextTick(() => {
    const el = cardListRef.value
    if (el) {
      el.scrollTo({ top: 0, behavior: 'smooth' })
    }
  })
}

/**
 * Fit map to loaded markers so open/refresh always frames where properties are.
 * Single pin → flyTo; many → flyToBounds; none → area center or UAE default.
 */
const fitMapToMarkers = async () => {
  if (!map || !clusterLayer) {
    return
  }
  const layers = clusterLayer.getLayers()
  if (layers.length === 0) {
    if (areaIdFromQuery.value) {
      await centerMapOnArea(areaIdFromQuery.value)
    } else {
      map.flyTo([24.4539, 54.3773], 10, { duration: 0.5 })
    }
    return
  }
  if (layers.length === 1) {
    map.flyTo(layers[0].getLatLng(), 15, { duration: 0.55 })
    return
  }
  const b = clusterLayer.getBounds()
  if (b?.isValid?.()) {
    map.flyToBounds(b, { padding: [52, 52], maxZoom: 15, duration: 0.65 })
  }
}

const renderMarkers = (items) => {
  if (!map) {
    return
  }

  markerById.clear()

  if (clusterLayer) {
    clusterLayer.clearLayers()
    map.removeLayer(clusterLayer)
  }

  clusterLayer = L.markerClusterGroup({
    maxClusterRadius: 56,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
  })

  items.forEach((property) => {
    const lat = property.latitude
    const lng = property.longitude
    if (lat == null || lng == null) {
      return
    }

    const marker = L.marker([Number(lat), Number(lng)], {
      icon: makePricePinIcon(property, markerIconState(property.id)),
    })

    marker.bindPopup(buildPropertyPopupHtml(property), {
      maxWidth: 288,
      minWidth: 236,
      className: 'pf-map-popup-shell pf-map-popup-shell--compact',
      autoPanPadding: [20, 20],
    })

    marker.on('mouseover', () => {
      const prev = hoveredMarkerId.value
      hoveredMarkerId.value = property.id
      if (prev && prev !== property.id) {
        updateMarkerIcon(prev)
      }
      updateMarkerIcon(property.id)
    })

    marker.on('mouseout', () => {
      if (hoveredMarkerId.value === property.id) {
        hoveredMarkerId.value = null
      }
      updateMarkerIcon(property.id)
    })

    marker.on('click', () => {
      selectedListingId.value = property.id
      scrollCardIntoView(property.id)
    })

    marker.on('popupopen', (e) => {
      const root = e.popup?.getElement?.()
      const btn = root?.querySelector?.(`.popup-view-btn[data-id="${property.id}"]`)
      if (btn) {
        btn.onclick = (ev) => {
          ev.preventDefault()
          router.push(`/property-details/${property.id}`)
        }
      }
    })

    markerById.set(property.id, marker)
    clusterLayer.addLayer(marker)
  })

  map.addLayer(clusterLayer)
  void fitMapToMarkers()
}

const focusListingOnMap = (p) => {
  const m = markerById.get(p.id)
  if (!m || !map) {
    return
  }
  map.panTo(m.getLatLng())
  if (clusterLayer && typeof clusterLayer.zoomToShowLayer === 'function') {
    clusterLayer.zoomToShowLayer(m, () => {
      m.openPopup()
    })
  } else {
    m.openPopup()
  }
}

const onCardClick = (p) => {
  selectedListingId.value = p.id
  focusListingOnMap(p)
}

const refreshData = async () => {
  const list = await fetchMapData()
  sidebarPage.value = 1
  selectedListingId.value = null
  renderMarkers(list)
  scrollSidebarListToTop()
  await nextTick()
  map?.invalidateSize()
  requestAnimationFrame(() => map?.invalidateSize())
}

const centerMapOnArea = async (areaId) => {
  if (!map || !areaId) {
    return
  }
  try {
    const { data } = await api.get(`/listings/areas/${areaId}`)
    const a = data?.data ?? data
    if (a?.latitude != null && a?.longitude != null) {
      map.setView([Number(a.latitude), Number(a.longitude)], 13)
    }
  } catch (e) {
    console.warn('Could not load area for map center', e)
  }
}

const initMap = async () => {
  if (!mapContainer.value) {
    return
  }

  map = L.map(mapContainer.value, {
    center: [24.4539, 54.3773],
    zoom: 10,
    zoomControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '',
  }).addTo(map)

  await refreshData()
  nextTick(() => {
    map?.invalidateSize()
    requestAnimationFrame(() => map?.invalidateSize())
  })
}

onMounted(() => {
  loadAreasForSearch()
  initMap()
  window.addEventListener('resize', onWindowResize)
})

const onWindowResize = () => {
  map?.invalidateSize()
}

onBeforeUnmount(() => {
  window.removeEventListener('resize', onWindowResize)
  markerById.clear()
  if (map) {
    map.off()
    map.remove()
  }
  map = null
  clusterLayer = null
})

watch(listingStatus, () => {
  refreshData()
})

watch(areaIdFromQuery, async () => {
  if (!map) {
    return
  }
  await refreshData()
})

watch(selectedListingId, (next, prev) => {
  if (prev != null) {
    updateMarkerIcon(prev)
  }
  if (next != null) {
    updateMarkerIcon(next)
  }
})
</script>

<style scoped>
/* Full-height split: map left, fixed sidebar right; scroll only in list */
.pf-shell {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  max-height: 100%;
  overflow: hidden;
  background: transparent;

}

.pf-split {
  display: flex;
  flex-direction: row;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.pf-map-pane {
  flex: 1;
  min-width: 0;
  min-height: 0;
  position: relative;
  overflow: hidden;
}

.pf-map {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
}

.pf-sidebar {
  flex: 0 0 350px;
  width: 350px;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  background: var(--bs-body-bg, #fff);
  border-left: 1px solid var(--bs-border-color, #dee2e6);
}

.pf-sidebar__head {
  flex-shrink: 0;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}

.pf-sidebar__title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--bs-emphasis-color, #212529);
  margin: 0;
}

.pf-sidebar__controls {
  flex-shrink: 0;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pf-sidebar__label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--bs-primary, #0d6efd);
  margin: 0;
}

.pf-sidebar__search {
  position: relative;
}

.pf-sidebar__search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.65);
  pointer-events: none;
  display: flex;
  align-items: center;
}

.pf-sidebar__search-input {
  padding-left: 2.25rem;
}

.pf-sidebar__row {
  display: flex;
  align-items: stretch;
  gap: 8px;
}

.pf-sidebar__grow {
  flex: 1;
  min-width: 0;
}

.pf-sidebar__apply {
  flex-shrink: 0;
}

.pf-sidebar__warn {
  color: var(--bs-warning-text-emphasis, #997404);
}

.pf-sidebar__list-meta {
  flex-shrink: 0;
  padding: 6px 16px 8px;
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--bs-tertiary-color, #6c757d);
  border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.08));
}

.pf-card-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  -webkit-overflow-scrolling: touch;
}

.pf-card-list__empty {
  color: var(--bs-secondary-color, #6c757d);
  padding: 8px 4px;
}

.pf-card {
  display: flex;
  flex-direction: row;
  align-items: stretch;
  gap: 0;
  width: 100%;
  text-align: left;
  padding: 0;
  margin: 0;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--bs-border-radius-lg, 0.5rem);
  overflow: hidden;
  background: var(--bs-body-bg, #fff);
  box-shadow: 0 1px 3px rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), 0.06);
  cursor: pointer;
  transition:
    box-shadow 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease;
}

.pf-card:hover {
  border-color: var(--bs-primary-border-subtle, rgba(13, 110, 253, 0.35));
  box-shadow: 0 6px 16px rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), 0.1);
  transform: translateY(-1px);
}

.pf-card--active {
  border-color: var(--bs-primary, #0d6efd);
  box-shadow:
    0 0 0 2px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.25),
    0 6px 16px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.12);
}

.pf-card__media {
  flex: 0 0 104px;
  width: 104px;
  min-height: 96px;
  overflow: hidden;
  background: var(--bs-secondary-bg, #e9ecef);
}

.pf-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.pf-card__placeholder {
  width: 100%;
  height: 100%;
  min-height: 96px;
  background: var(--bs-secondary-bg, #e9ecef);
  border-right: 1px solid var(--bs-border-color, #dee2e6);
}

.pf-card__body {
  flex: 1;
  min-width: 0;
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  justify-content: center;
}

.pf-card__price {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--bs-primary, #0d6efd);
  line-height: 1.2;
}

.pf-card__title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--bs-emphasis-color, #212529);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pf-card__area {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 6px;
  font-size: 0.75rem;
  line-height: 1.35;
}

.pf-card__area-lbl {
  flex-shrink: 0;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--bs-primary, #0d6efd);
}

.pf-card__area-val {
  color: var(--bs-body-color, #212529);
  font-weight: 500;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pf-card__meta {
  font-size: 0.72rem;
  color: var(--bs-secondary-color, #6c757d);
  line-height: 1.35;
}

.pf-card__status {
  margin-top: 1px;
}

.pf-card__status :deep(.badge) {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 0.28em 0.55em;
}

.pf-sidebar__pager {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 10px 16px 14px;
  border-top: 1px solid var(--bs-border-color, #dee2e6);
  background: var(--bs-body-bg, #fff);
}

.pf-sidebar__pager-info {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--bs-tertiary-color, #6c757d);
  min-width: 4.5rem;
  text-align: center;
}

@media (max-width: 991px) {
  .pf-split {
    flex-direction: column;
  }

  .pf-map-pane {
    flex: 1 1 45%;
    min-height: 240px;
  }

  .pf-sidebar {
    flex: 1 1 55%;
    width: 100%;
    max-width: none;
    border-left: none;
    border-top: 1px solid var(--bs-border-color, #dee2e6);
  }
}

.pf-shell :deep(.alert-danger) {
  background: var(--bs-danger-bg-subtle, #f8d7da);
  border-color: var(--bs-danger-border-subtle, #f1aeb5);
  color: var(--bs-danger-text-emphasis, #58151c);
}

.pf-shell :deep(.alert-secondary) {
  background: var(--bs-secondary-bg-subtle, #e2e3e5);
  border-color: var(--bs-secondary-border-subtle, #c4c8cb);
  color: var(--bs-secondary-text-emphasis, #41464b);
}
</style>

<style>
/* Unscoped: Leaflet popups + price markers (HTML from divIcon) */
.leaflet-container .pf-map-popup-shell .leaflet-popup-content-wrapper {
  padding: 0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow:
    0 4px 24px rgb(15 23 42 / 0.12),
    0 0 0 1px rgb(226 232 240 / 0.95);
}

.leaflet-container .pf-map-popup-shell--compact .leaflet-popup-content-wrapper {
  border-radius: 12px;
}

.leaflet-container .pf-map-popup-shell .leaflet-popup-content {
  margin: 0;
  min-width: 0;
  line-height: 1.45;
}

.leaflet-container .pf-map-popup-shell .leaflet-popup-tip {
  box-shadow: none;
  border: 1px solid rgb(226 232 240 / 0.95);
}

.pf-map-popup-card--compact {
  width: 264px;
  max-width: min(264px, 90vw);
  font-family:
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    sans-serif;
  background: #fff;
  color: #0f172a;
  text-align: left;
  padding: 10px 10px 10px;
}

.pf-map-popup-card__row {
  display: flex;
  gap: 10px;
  align-items: stretch;
  margin-bottom: 10px;
}

.pf-map-popup-card__thumb {
  width: 64px;
  height: 64px;
  flex-shrink: 0;
  border-radius: 10px;
  overflow: hidden;
  background: linear-gradient(145deg, #e2e8f0 0%, #f1f5f9 100%);
}

.pf-map-popup-card__thumb--empty {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.18);
  position: relative;
}

.pf-map-popup-card__thumb--empty::after {
  content: '';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='1.2' opacity='0.45'%3E%3Cpath d='M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'/%3E%3C/svg%3E")
    center / 28px 28px no-repeat;
}

.pf-map-popup-card__thumb-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pf-map-popup-card__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
}

.pf-map-popup-card--compact .pf-map-popup-card__price {
  font-size: 0.88rem;
  font-weight: 800;
  color: var(--bs-primary, #0d6efd);
  letter-spacing: -0.02em;
  margin: 0;
  line-height: 1.2;
}

.pf-map-popup-card--compact .pf-map-popup-card__title {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--bs-body-color, #212529);
  line-height: 1.35;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin: 0;
}

.pf-map-popup-card__area {
  font-size: 0.7rem;
  color: var(--bs-body-color, #212529);
  margin: 0;
  line-height: 1.35;
}

.pf-map-popup-card__lbl {
  display: inline-block;
  margin-right: 4px;
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--bs-primary, #0d6efd);
  vertical-align: baseline;
}

.pf-map-popup-card__meta {
  font-size: 0.66rem;
  color: var(--bs-secondary-color, #6c757d);
  margin: 0;
  line-height: 1.35;
}

.pf-map-popup-card__status {
  margin-top: 2px;
}

.pf-map-popup-card__status .badge {
  font-size: 0.62rem;
  font-weight: 600;
}

.pf-map-popup-card--compact .pf-map-popup-card__btn {
  margin-top: 4px;
}

/* Price pill markers */
.pf-price-pin-root {
  background: transparent !important;
  border: none !important;
}

.pf-price-pin {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding: 0 10px;
  border-radius: 999px;
  font-family:
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    sans-serif;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  white-space: nowrap;
  color: #fff;
  background: var(--bs-primary, #0d6efd);
  border: 2px solid rgb(255 255 255 / 0.95);
  box-shadow:
    0 2px 10px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.35),
    0 0 0 1px rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), 0.08);
  transform-origin: 50% 100%;
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    filter 0.18s ease;
}

.pf-price-pin__lbl {
  pointer-events: none;
}

.pf-price-pin--default:hover {
  transform: scale(1.06);
}

.pf-price-pin--hover {
  transform: scale(1.1);
  box-shadow:
    0 6px 20px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.45),
    0 0 0 2px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.35);
  filter: brightness(1.05);
}

.pf-price-pin--selected {
  transform: scale(1.1);
  background: var(--bs-primary, #0d6efd);
  border-color: #fff;
  filter: brightness(0.92) saturate(1.05);
  box-shadow:
    0 4px 18px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.55),
    0 0 0 3px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.5);
}
</style>
