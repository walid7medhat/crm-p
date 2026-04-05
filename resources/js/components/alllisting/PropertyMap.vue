<template>
  <div class="pf-shell">
    <!-- Top bar: search + filters (Property Finder–style) -->
    <header class="pf-toolbar">
      <div class="pf-toolbar__row">
        <div class="pf-search-block">
          <div class="pf-search-field">
            <span class="pf-search-icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7" />
                <path d="M21 21l-4.3-4.3" />
              </svg>
            </span>
            <input
              v-model="areaSearch"
              type="search"
              class="pf-search-input"
              placeholder="Location, area or community…"
              autocomplete="off"
              @focus="showAreaSuggestions = true"
              @blur="onAreaSearchBlur"
              @keydown.escape.prevent="showAreaSuggestions = false"
            />
            <ul
              v-show="showAreaSuggestions && filteredAreasForPicker.length > 0"
              class="pf-suggestions"
            >
              <li
                v-for="a in filteredAreasForPicker"
                :key="a.id"
                class="pf-suggestions__item"
                @mousedown.prevent="pickArea(a)"
              >
                <span class="pf-suggestions__name">{{ a.name }}</span>
                <span v-if="a.type" class="pf-suggestions__type">{{ a.type }}</span>
              </li>
            </ul>
          </div>
          <div v-if="areaIdFromQuery" class="pf-filter-pill">
            <span>{{ selectedAreaDisplayName }}</span>
            <button type="button" class="pf-filter-pill__x" aria-label="Clear area" @click="clearAreaFilter">×</button>
          </div>
        </div>

        <div class="pf-toolbar__filters">
          <select v-model="listingStatus" class="form-select pf-select">
            <option value="">Property status</option>
            <option value="sale">Sale</option>
            <option value="rent">Rent</option>
          </select>
          <button type="button" class="btn pf-btn-find" :disabled="loading" @click="refreshData">
            {{ loading ? '…' : 'Find' }}
          </button>
        </div>
      </div>
      <p v-if="areasLoadError" class="pf-areas-warn">{{ areasLoadError }}</p>
      <div v-if="fetchError" class="alert alert-danger py-2 mb-0 mt-2" role="alert">{{ fetchError }}</div>
      <div
        v-else-if="!loading && properties.length === 0"
        class="alert alert-light border py-2 mb-0 mt-2"
      >
        No properties match your filters.
      </div>
    </header>

    <!-- Split: listings left, map right -->
    <div class="pf-split">
      <aside class="pf-sidebar">
        <div class="pf-sidebar__head">
          <span class="pf-sidebar__title">Properties</span>
          <span class="pf-sidebar__count">{{ properties.length }}</span>
        </div>
        <div ref="cardListRef" class="pf-card-list">
          <button
            v-for="p in properties"
            :key="p.id"
            type="button"
            class="pf-card"
            :class="{ 'pf-card--active': selectedListingId === p.id }"
            :data-listing-id="p.id"
            @click="onCardClick(p)"
          >
            <div class="pf-card__media">
              <img v-if="p.hero_image" class="pf-card__img" :src="p.hero_image" alt="" />
              <div v-else class="pf-card__placeholder" />
            </div>
            <div class="pf-card__body">
              <div class="pf-card__title">{{ p.title || 'Property' }}</div>
              <div class="pf-card__loc" v-if="p.area_name">{{ p.area_name }}</div>
              <div class="pf-card__price" v-if="p.price != null">
                AED {{ Number(p.price).toLocaleString() }}
              </div>
              <div class="pf-card__type" v-if="p.type">{{ p.type }}</div>
            </div>
          </button>
          <p v-if="!loading && !properties.length" class="pf-card-list__empty text-muted small px-2">
            Nothing to show yet.
          </p>
        </div>
      </aside>

      <div class="pf-map-pane">
        <div ref="mapContainer" class="pf-map"></div>
      </div>
    </div>

    <!-- Horizontal carousel (scroll down on the page) -->
    <section v-if="properties.length" class="pf-carousel-section" aria-label="Property carousel">
      <div class="pf-carousel-head">
        <span class="pf-carousel-title">More listings</span>
        <span class="pf-carousel-hint">Scroll sideways — or use the mouse wheel here</span>
      </div>
      <div ref="carouselRef" class="pf-carousel">
        <button
          v-for="p in properties"
          :key="'carousel-' + p.id"
          type="button"
          class="pf-carousel-card"
          :class="{ 'pf-carousel-card--active': selectedListingId === p.id }"
          :data-listing-id="p.id"
          @click="onCarouselCardClick(p)"
        >
          <div class="pf-carousel-card__media">
            <img v-if="p.hero_image" class="pf-carousel-card__img" :src="p.hero_image" alt="" />
            <div v-else class="pf-carousel-card__placeholder" />
          </div>
          <div class="pf-carousel-card__body">
            <div class="pf-carousel-card__title">{{ p.title || 'Property' }}</div>
            <div class="pf-carousel-card__loc" v-if="p.area_name">{{ p.area_name }}</div>
            <div class="pf-carousel-card__price" v-if="p.price != null">
              AED {{ Number(p.price).toLocaleString() }}
            </div>
          </div>
        </button>
      </div>
    </section>
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
const carouselRef = ref(null)

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

const allAreas = ref([])
const areaSearch = ref('')
const showAreaSuggestions = ref(false)
const areasLoadError = ref('')

const filteredAreasForPicker = computed(() => {
  const q = areaSearch.value.trim().toLowerCase()
  if (!q || !allAreas.value.length) {
    return []
  }
  return allAreas.value
    .filter((a) => (a.name || '').toLowerCase().includes(q))
    .slice(0, 80)
})

const selectedAreaDisplayName = computed(() => {
  const id = areaIdFromQuery.value
  if (!id) {
    return ''
  }
  const found = allAreas.value.find((a) => Number(a.id) === id)
  return found?.name ? String(found.name) : `#${id}`
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

const onAreaSearchBlur = () => {
  setTimeout(() => {
    showAreaSuggestions.value = false
  }, 200)
}

const pickArea = (area) => {
  if (!area?.id) {
    return
  }
  router.replace({ path: route.path, query: { ...route.query, area_id: String(area.id) } })
  areaSearch.value = ''
  showAreaSuggestions.value = false
}

const clearAreaFilter = () => {
  const nextQuery = { ...route.query }
  delete nextQuery.area_id
  router.replace({ path: route.path, query: nextQuery })
  areaSearch.value = ''
}

let map = null
let clusterLayer = null
const markerById = new Map()

const pinIcon = L.divIcon({
  className: 'pf-leaflet-pin',
  html: '<div class="pf-leaflet-pin__shape"></div>',
  iconSize: [30, 38],
  iconAnchor: [15, 38],
  popupAnchor: [0, -34],
})

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

/** Rich popup card HTML (image + key facts) for Leaflet. */
const buildPropertyPopupHtml = (property) => {
  const title = escapeHtml(property.title || property.project_name || 'Property')
  const area = escapeHtml(property.area_name || property.area?.name || '')
  const type = escapeHtml(property.type || '')
  const status = escapeHtml(property.listing_status || '')
  const price =
    property.price != null
      ? `AED ${Number(property.price).toLocaleString()}`
      : 'Price on request'
  const imgUrl = property.hero_image ? escapeHtml(property.hero_image) : ''
  const id = Number(property.id)

  const metaParts = [type, status].filter(Boolean)
  const metaLine = metaParts.length ? `<div class="pf-map-popup-card__meta">${metaParts.join(' · ')}</div>` : ''

  const mediaBlock = imgUrl
    ? `<div class="pf-map-popup-card__media"><img class="pf-map-popup-card__img" src="${imgUrl}" alt="" loading="lazy" /></div>`
    : `<div class="pf-map-popup-card__media pf-map-popup-card__media--empty" aria-hidden="true"></div>`

  return `
    <div class="pf-map-popup-card" data-listing-id="${id}">
      ${mediaBlock}
      <div class="pf-map-popup-card__body">
        <div class="pf-map-popup-card__title">${title}</div>
        ${area ? `<div class="pf-map-popup-card__area">${area}</div>` : ''}
        ${metaLine}
        <div class="pf-map-popup-card__price">${escapeHtml(price)}</div>
        <button type="button" class="pf-map-popup-card__btn popup-view-btn" data-id="${id}">View details</button>
      </div>
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
  nextTick(() => {
    const root = cardListRef.value
    const el = root?.querySelector(`[data-listing-id="${id}"]`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
  })
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

    const marker = L.marker([Number(lat), Number(lng)], { icon: pinIcon })

    marker.bindPopup(buildPropertyPopupHtml(property), {
      maxWidth: 300,
      minWidth: 260,
      className: 'pf-map-popup-shell',
      autoPanPadding: [16, 16],
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

  if (clusterLayer.getLayers().length > 0) {
    const b = clusterLayer.getBounds()
    if (b.isValid()) {
      map.fitBounds(b, { padding: [40, 40], maxZoom: 15 })
    }
  }
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

const onCarouselCardClick = (p) => {
  selectedListingId.value = p.id
  focusListingOnMap(p)
  scrollCardIntoView(p.id)
}

let unbindCarouselWheel = null

const bindCarouselHorizontalWheel = () => {
  unbindCarouselWheel?.()
  unbindCarouselWheel = null
  nextTick(() => {
    const el = carouselRef.value
    if (!el) {
      return
    }
    const onWheel = (e) => {
      const dy = e.deltaY
      const dx = e.deltaX
      if (Math.abs(dx) > Math.abs(dy)) {
        return
      }
      e.preventDefault()
      el.scrollLeft += dy
    }
    el.addEventListener('wheel', onWheel, { passive: false })
    unbindCarouselWheel = () => el.removeEventListener('wheel', onWheel)
  })
}

const refreshData = async () => {
  const list = await fetchMapData()
  renderMarkers(list)
  nextTick(() => map?.invalidateSize())
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

  if (areaIdFromQuery.value) {
    await centerMapOnArea(areaIdFromQuery.value)
  }
  await refreshData()
  nextTick(() => {
    map?.invalidateSize()
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
  unbindCarouselWheel?.()
  unbindCarouselWheel = null
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

watch(areaIdFromQuery, async (id) => {
  if (!map) {
    return
  }
  if (id) {
    await centerMapOnArea(id)
  }
  await refreshData()
})

watch(
  () => properties.value.length,
  () => {
    bindCarouselHorizontalWheel()
  },
)
watch(
  () => properties.value,
  async () => {
    await nextTick()
    setTimeout(() => {
      map?.invalidateSize()
    }, 300)
  },
  { deep: true }
)
</script>

<style scoped>
.pf-shell {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 120px);
  background: transparent;

}

/* Kanban-style: transparent chrome; map sits on dashboard background (see property-map.vue) */
.pf-toolbar {
  flex-shrink: 0;
  background: transparent;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  padding: 16px 20px 0;
  box-shadow: none;
}

.pf-toolbar__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 12px 16px;
  padding-bottom: 8px;
}

.pf-search-block {
  flex: 1 1 280px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pf-search-field {
  position: relative;
  max-width: 560px;
}

.pf-search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(255, 255, 255, 0.45);
  pointer-events: none;
  display: flex;
}

.pf-search-input {
  width: 100%;
  padding: 12px 16px 12px 44px;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 10px;
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.95);
  background: rgba(0, 0, 0, 0.18);
  transition:
    border-color 0.15s,
    box-shadow 0.15s,
    background 0.15s;
}

.pf-search-input::placeholder {
  color: rgba(255, 255, 255, 0.45);
}

.pf-search-input:focus {
  outline: none;
  border-color: rgba(94, 234, 212, 0.65);
  background: rgba(0, 0, 0, 0.28);
  box-shadow: 0 0 0 2px rgba(45, 212, 191, 0.2);
}

.pf-suggestions {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 6px);
  margin: 0;
  padding: 0;
  list-style: none;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 12px 40px rgb(15 23 42 / 0.12);
  max-height: 260px;
  overflow-y: auto;
  z-index: 2000;
}

.pf-suggestions__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 11px 14px;
  cursor: pointer;
  font-size: 0.9rem;
  border-bottom: 1px solid #f1f5f9;
}

.pf-suggestions__item:last-child {
  border-bottom: none;
}

.pf-suggestions__item:hover {
  background: #f0fdfa;
}

.pf-suggestions__type {
  font-size: 0.72rem;
  color: #64748b;
  text-transform: capitalize;
}

.pf-filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  background: #0f766e;
  color: #fff;
  border-radius: 999px;
  font-size: 0.85rem;
  max-width: 100%;
}

.pf-filter-pill__x {
  border: none;
  background: rgb(255 255 255 / 0.25);
  color: #fff;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  line-height: 1;
  cursor: pointer;
  font-size: 1rem;
}

.pf-toolbar__filters {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.pf-select {
  min-width: 140px;
  border-radius: 10px;
  border-color: rgba(255, 255, 255, 0.25);
  padding: 10px 12px;
  color: rgba(255, 255, 255, 0.95);
  background: rgba(0, 0, 0, 0.2);
}

.pf-select option {
  color: #0f172a;
  background: #fff;
}

.pf-btn-find {
  background: #dc2626 !important;
  border: none !important;
  color: #fff !important;
  font-weight: 600;
  padding: 10px 28px !important;
  border-radius: 10px !important;
}

.pf-btn-find:hover:not(:disabled) {
  background: #b91c1c !important;
  color: #fff !important;
}

.pf-btn-find:disabled {
  opacity: 0.7;
}

.pf-areas-warn {
  font-size: 0.8rem;
  color: #fcd34d;
  margin: 0 0 8px;
}

.pf-split {
  display: grid;
  grid-template-columns: minmax(340px, 420px) 1fr;
  gap: 0;
  flex: 1;
  min-height: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

@media (max-width: 991px) {
  .pf-split {
    grid-template-columns: 1fr;
    grid-template-rows: minmax(280px, 40vh) minmax(360px, 50vh);
  }

  .pf-sidebar {
    order: 2;
    max-height: 50vh;
  }

  .pf-map-pane {
    order: 1;
    min-height: 40vh;
  }
}

.pf-sidebar {
  display: flex;
  flex-direction: column;
  background: transparent;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
  border-right: 1px solid rgba(255, 255, 255, 0.12);
  min-height: 0;
}

.pf-sidebar__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}

.pf-sidebar__title {
  font-weight: 700;
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.95);
}

.pf-sidebar__count {
  font-size: 0.8rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
  background: rgba(255, 255, 255, 0.12);
  padding: 4px 10px;
  border-radius: 999px;
}

.pf-card-list {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  -webkit-overflow-scrolling: touch;
}

.pf-card {
  display: flex;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.12);
  text-align: left;
  cursor: pointer;
  padding: 0;
  transition:
    box-shadow 0.15s,
    border-color 0.15s;
}

.pf-card:hover {
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.25);
  border-color: rgba(255, 255, 255, 0.3);
}

.pf-card--active {
  border-color: rgba(45, 212, 191, 0.75);
  box-shadow: 0 0 0 2px rgba(45, 212, 191, 0.25);
}

.pf-card__media {
  width: 112px;
  flex-shrink: 0;
}

.pf-card__img {
  width: 100%;
  height: 100%;
  min-height: 110px;
  object-fit: cover;
  background: transparent;
}

.pf-card__placeholder {
  width: 100%;
  height: 100%;
  min-height: 110px;
  background: transparent;
  border: 1px dashed rgba(255, 255, 255, 0.25);
}

.pf-card__body {
  flex: 1;
  padding: 12px 14px;
  min-width: 0;
}

.pf-card__title {
  font-size: 0.9rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.95);
  line-height: 1.35;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pf-card__loc {
  font-size: 0.78rem;
  color: rgba(255, 255, 255, 0.65);
  margin-top: 4px;
}

.pf-card__price {
  font-size: 0.95rem;
  font-weight: 700;
  color: #5eead4;
  margin-top: 6px;
}

.pf-card__type {
  font-size: 0.72rem;
  color: rgba(255, 255, 255, 0.5);
  margin-top: 4px;
}
.pf-card-list__empty {
  padding: 10px 0;
  color: rgba(255, 255, 255, 0.55) !important;
}

.pf-map-pane {
  position: relative;
  min-height: 0;
  background: transparent;
}

.pf-map {
  /*height: 100%;*/
  height: 520px;
  width: 100%;
  border-radius: 0;
  
}

/* Hide Leaflet attribution + any stray footer text */
.pf-map :deep(.leaflet-control-attribution) {
  display: none !important;
}

/* Bottom horizontal carousel */
.pf-carousel-section {
  flex-shrink: 0;
  padding: 16px 16px 24px;
  background: transparent;
  backdrop-filter: none;
  -webkit-backdrop-filter: none;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.pf-carousel-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.pf-carousel-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.95);
}

.pf-carousel-hint {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.55);
}

.pf-carousel {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  overflow-y: hidden;
  padding: 8px 4px 16px;
  scroll-snap-type: x mandatory;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  mask-image: linear-gradient(to right, transparent, #000 2%, #000 98%, transparent);
}

.pf-carousel::-webkit-scrollbar {
  height: 8px;
}

.pf-carousel::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.45);
  border-radius: 999px;
}

.pf-carousel-card {
  flex: 0 0 260px;
  scroll-snap-align: start;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  overflow: hidden;
  background: rgba(0, 0, 0, 0.12);
  backdrop-filter: none;
  cursor: pointer;
  text-align: left;
  padding: 0;
  transition:
    box-shadow 0.2s,
    border-color 0.2s,
    transform 0.2s;
}

.pf-carousel-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgb(0 0 0 / 0.3);
  border-color: rgba(255, 255, 255, 0.3);
}

.pf-carousel-card--active {
  border-color: rgba(45, 212, 191, 0.75);
  box-shadow: 0 0 0 2px rgba(45, 212, 191, 0.3);
}

.pf-carousel-card__media {
  height: 140px;
  overflow: hidden;
}

.pf-carousel-card__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  background: transparent;
}

.pf-carousel-card__placeholder {
  width: 100%;
  height: 100%;
  background: transparent;
  border: 1px dashed rgba(255, 255, 255, 0.25);
}

.pf-carousel-card__body {
  padding: 12px 14px 14px;
}

.pf-carousel-card__title {
  font-size: 0.88rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.95);
  line-height: 1.35;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pf-carousel-card__loc {
  font-size: 0.76rem;
  color: rgba(255, 255, 255, 0.65);
  margin-top: 4px;
}

.pf-carousel-card__price {
  font-size: 0.9rem;
  font-weight: 700;
  color: #5eead4;
  margin-top: 8px;
}

/* Bootstrap alerts on dark dashboard background */
.pf-shell :deep(.alert-danger) {
  background: rgba(127, 29, 29, 0.45);
  border-color: rgba(252, 165, 165, 0.4);
  color: rgba(255, 255, 255, 0.95);
}

.pf-shell :deep(.alert-light) {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.2);
  color: rgba(255, 255, 255, 0.85);
}
</style>

<style>
/* Unscoped: Leaflet popups + marker icon HTML */
.leaflet-container .pf-map-popup-shell .leaflet-popup-content-wrapper {
  padding: 0;
  border-radius: 14px;
  overflow: hidden;
  box-shadow:
    0 4px 6px rgb(0 0 0 / 0.07),
    0 18px 40px rgb(15 23 42 / 0.18);
  border: 1px solid rgba(226, 232, 240, 0.95);
}

.leaflet-container .pf-map-popup-shell .leaflet-popup-content {
  margin: 0;
  min-width: 0;
  line-height: 1.4;
}

.leaflet-container .pf-map-popup-shell .leaflet-popup-tip {
  box-shadow: none;
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.pf-map-popup-card {
  width: 272px;
  max-width: min(272px, 88vw);
  font-family:
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    sans-serif;
  background: #fff;
  color: #0f172a;
  text-align: left;
}

.pf-map-popup-card__media {
  position: relative;
  height: 132px;
  background: linear-gradient(145deg, #e2e8f0 0%, #f1f5f9 100%);
  overflow: hidden;
}

.pf-map-popup-card__media--empty {
  min-height: 88px;
  background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
  opacity: 0.92;
}

.pf-map-popup-card__media--empty::after {
  content: '';
  position: absolute;
  inset: 0;
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='1.2' opacity='0.35'%3E%3Cpath d='M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6'/%3E%3C/svg%3E")
    center / 48px 48px no-repeat;
}

.pf-map-popup-card__img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pf-map-popup-card__body {
  padding: 12px 14px 14px;
}

.pf-map-popup-card__title {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin: 0 0 4px;
}

.pf-map-popup-card__area {
  font-size: 0.78rem;
  color: #64748b;
  margin: 0 0 6px;
}

.pf-map-popup-card__meta {
  font-size: 0.72rem;
  color: #94a3b8;
  text-transform: capitalize;
  margin: 0 0 8px;
}

.pf-map-popup-card__price {
  font-size: 1rem;
  font-weight: 800;
  color: #0f766e;
  letter-spacing: -0.02em;
  margin: 0 0 12px;
}

.pf-map-popup-card__btn {
  display: block;
  width: 100%;
  padding: 9px 12px;
  border: none;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  color: #fff;
  background: linear-gradient(160deg, #0d9488 0%, #0f766e 55%, #115e59 100%);
  box-shadow: 0 2px 8px rgb(15 118 110 / 0.35);
  transition:
    transform 0.12s ease,
    box-shadow 0.12s ease;
}

.pf-map-popup-card__btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgb(15 118 110 / 0.45);
}

.pf-leaflet-pin {
  background: transparent !important;
  border: none !important;
}

.pf-leaflet-pin__shape {
  position: relative;
  width: 28px;
  height: 34px;
  background: linear-gradient(160deg, #0d9488 0%, #0f766e 50%, #115e59 100%);
  border-radius: 50% 50% 50% 0;
  transform: rotate(-45deg);
  margin: 4px 0 0 1px;
  box-shadow: 0 4px 12px rgb(15 23 42 / 0.35);
  border: 2px solid #fff;
}

.pf-leaflet-pin__shape::after {
  content: '';
  position: absolute;
  width: 10px;
  height: 10px;
  background: #fff;
  border-radius: 50%;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(45deg);
}
</style>
