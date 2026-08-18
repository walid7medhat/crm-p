<template>
  <div class="emp-directory-table ast-inventory-table">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Assets</h6>
      <div class="emp-directory-table__head-actions">
        <div class="emp-directory-table__search-wrap" ref="searchWrapRef">
          <label class="emp-directory-table__search">
            <span class="emp-directory-table__search-plus" aria-hidden="true">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search Assets"
              autocomplete="off"
              @input="$emit('update:searchQuery', $event.target.value)"
              @focus="showFilters = true"
              @click="showFilters = true"
            />
            <span class="emp-directory-table__search-icon" aria-hidden="true">
              <iconify-icon icon="lucide:search" />
            </span>
          </label>
          <Teleport to="body">
            <AssetsSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              :search="searchQuery"
              :filters="filters"
              :asset-types="assetTypes"
              :departments="departments"
              :employees="employees"
              @update:search="$emit('update:searchQuery', $event)"
              @search="onPopupSearch"
              @reset="onPopupReset"
              @close="showFilters = false"
            />
          </Teleport>
        </div>
        <button type="button" class="emp-directory-table__export" @click="$emit('export')">
          <iconify-icon icon="lucide:file-spreadsheet" />
          <span>Export Excel</span>
        </button>
      </div>
    </div>

    <div class="emp-directory-table__wrap">
      <table class="emp-directory-table__grid emp-directory-table__grid--compact ast-inventory-grid">
        <thead>
          <tr>
            <th class="col-check">
              <input
                type="checkbox"
                :checked="allSelected"
                :indeterminate.prop="someSelected && !allSelected"
                @change="toggleSelectAll"
              />
            </th>
            <th>Asset</th>
            <th>ID</th>
            <th>Category</th>
            <th>Assigned To</th>
            <th>Status</th>
            <th>Purchase Date</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!assets.length">
            <td colspan="8" class="emp-directory-table__empty">
              <iconify-icon icon="lucide:package-search" />
              <p>{{ hasActiveFilters ? 'No assets match your search or filters.' : 'No assets to display.' }}</p>
              <button v-if="hasActiveFilters" type="button" class="emp-directory-table__clear-btn" @click="$emit('clear-filters')">
                Clear search &amp; filters
              </button>
            </td>
          </tr>
          <tr v-for="asset in assets" :key="asset.id">
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selectedIds.includes(asset.id)"
                @change="toggleRow(asset.id)"
              />
            </td>
            <td>
              <div class="ast-inventory-asset">
                <span class="ast-inventory-asset__icon">
                  <iconify-icon :icon="asset.imageIcon || 'lucide:package'" />
                </span>
                <div>
                  <strong>{{ asset.name }}</strong>
                  <span>{{ asset.serialNumber && asset.serialNumber !== '—' ? asset.serialNumber : 'No serial' }}</span>
                </div>
              </div>
            </td>
            <td class="ast-inventory-id">{{ asset.assetId || asset.assetCode || '—' }}</td>
            <td>{{ asset.category }}</td>
            <td>
              <div class="ast-inventory-user">
                <img v-if="asset.assignedAvatar" :src="asset.assignedAvatar" :alt="asset.assignedEmployee" />
                <span>{{ asset.assignedEmployee || '—' }}</span>
              </div>
            </td>
            <td>
              <span class="ast-card__badge" :class="`ast-card__badge--${asset.status}`">{{ asset.statusLabel }}</span>
            </td>
            <td>{{ formatDate(asset.purchaseDate) }}</td>
            <td class="col-action">
              <button type="button" class="emp-directory-table__menu-btn" @click.stop="openMenu(asset, $event)">
                <iconify-icon icon="lucide:more-vertical" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="emp-directory-table__footer">
      <div class="emp-directory-table__footer-left">
        <span>Showing {{ startEntry }} to {{ endEntry }} of {{ total }} Entries</span>
        <label class="emp-directory-table__per-page">
          <select :value="perPage" @change="$emit('update:perPage', Number($event.target.value))">
            <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
          </select>
          <iconify-icon icon="lucide:chevrons-up-down" />
        </label>
      </div>
      <div class="emp-directory-table__pagination">
        <button type="button" class="emp-directory-table__page-btn" :disabled="page <= 1" @click="$emit('update:page', page - 1)">
          <iconify-icon icon="lucide:chevron-left" /> Previous
        </button>
        <template v-for="(item, idx) in paginationItems" :key="item.type === 'page' ? `p-${item.n}` : `d-${idx}`">
          <span v-if="item.type === 'dots'" class="emp-directory-table__dots">...</span>
          <button
            v-else
            type="button"
            class="emp-directory-table__page-number"
            :class="{ 'is-active': page === item.n }"
            @click="$emit('update:page', item.n)"
          >
            {{ item.n }}
          </button>
        </template>
        <button type="button" class="emp-directory-table__page-btn" :disabled="page >= totalPages" @click="$emit('update:page', page + 1)">
          Next <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="openMenuId" class="emp-directory-table__menu ast-inventory-menu" :style="menuStyle" @click.stop>
        <button type="button" @click="onAction('view')">
          <iconify-icon icon="lucide:eye" /> View
        </button>
        <button type="button" @click="onAction('edit')">
          <iconify-icon icon="lucide:pencil" /> Edit
        </button>
        <button type="button" @click="onAction('assign')">
          <iconify-icon icon="lucide:user-plus" /> Assign / Transfer
        </button>
        <button v-if="menuAsset?.assignedUserId" type="button" @click="onAction('add-another')">
          <iconify-icon icon="lucide:copy-plus" /> Add another for this person
        </button>
        <button type="button" @click="onAction('maintenance')">
          <iconify-icon icon="lucide:wrench" /> Maintenance
        </button>
        <button type="button" class="is-danger" @click="onAction('delete')">
          <iconify-icon icon="lucide:trash-2" /> Delete
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import AssetsSearchPopup from '@/components/hr/assets/AssetsSearchPopup.vue'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'

const props = defineProps({
  assets: { type: Array, default: () => [] },
  page: { type: Number, default: 1 },
  perPage: { type: Number, default: 10 },
  total: { type: Number, default: 0 },
  totalPages: { type: Number, default: 1 },
  startEntry: { type: Number, default: 0 },
  endEntry: { type: Number, default: 0 },
  paginationItems: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  searchQuery: { type: String, default: '' },
  hasActiveFilters: { type: Boolean, default: false },
  filters: { type: Object, default: () => ({}) },
  assetTypes: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:page',
  'update:perPage',
  'update:selectedIds',
  'update:searchQuery',
  'apply-filters',
  'clear-filters',
  'export',
  'view',
  'edit',
  'assign',
  'add-another',
  'maintenance',
  'delete',
])

const perPageOptions = [10, 25, 50, 100]
const showFilters = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)
const openMenuId = ref(null)
const menuAsset = ref(null)
const menuStyle = ref({})

const allSelected = computed(
  () => props.assets.length > 0 && props.assets.every((row) => props.selectedIds.includes(row.id)),
)
const someSelected = computed(() => props.selectedIds.length > 0)

function formatDate(value) {
  return formatAttendanceDate(value)
}

function toggleRow(id) {
  const next = props.selectedIds.includes(id)
    ? props.selectedIds.filter((item) => item !== id)
    : [...props.selectedIds, id]
  emit('update:selectedIds', next)
}

function toggleSelectAll(event) {
  emit('update:selectedIds', event.target.checked ? props.assets.map((row) => row.id) : [])
}

function openMenu(asset, event) {
  if (openMenuId.value === asset.id) {
    closeMenu()
    return
  }
  menuAsset.value = asset
  openMenuId.value = asset.id
  const rect = event.currentTarget.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.max(12, rect.right - 240)}px`,
  }
}

function closeMenu() {
  openMenuId.value = null
  menuAsset.value = null
}

function onAction(type) {
  const asset = menuAsset.value
  closeMenu()
  if (!asset) return
  emit(type, asset)
}

function onPopupSearch(payload) {
  emit('apply-filters', payload)
  showFilters.value = false
}

function onPopupReset() {
  emit('clear-filters')
  showFilters.value = false
}

function onDocClick(event) {
  if (isInsideHrSearchPopup(event)) return
  if (showFilters.value && !searchWrapRef.value?.contains(event.target)) {
    showFilters.value = false
  }
  closeMenu()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>
