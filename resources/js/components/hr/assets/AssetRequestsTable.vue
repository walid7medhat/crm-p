<template>
  <div class="emp-directory-table ast-req-table">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Assets Requests</h6>
      <div class="emp-directory-table__head-actions">
        <div class="emp-directory-table__search-wrap" ref="searchWrapRef">
          <label class="emp-directory-table__search">
            <span class="emp-directory-table__search-plus" aria-hidden="true">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search Assets Requests"
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
            <AssetRequestsSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              :search="searchQuery"
              :filters="filters"
              :departments="departments"
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
      <table class="emp-directory-table__grid emp-directory-table__grid--compact ast-req-grid">
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
            <th>Applied Date</th>
            <th>User</th>
            <th>Department</th>
            <th>Asset Item</th>
            <th>Status</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!requests.length">
            <td colspan="7" class="emp-directory-table__empty">
              <iconify-icon icon="lucide:package-search" />
              <p>{{ hasActiveFilters ? 'No asset requests match your search or filters.' : 'No asset requests to display.' }}</p>
            </td>
          </tr>
          <tr v-for="row in requests" :key="row.id">
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selectedIds.includes(row.id)"
                @change="toggleRow(row.id)"
              />
            </td>
            <td class="ast-req-date">{{ formatDate(row.appliedAt) }}</td>
            <td>
              <div class="ast-req-user">
                <img :src="row.avatar" :alt="row.userName" loading="lazy" />
                <div>
                  <strong>{{ row.userName }}</strong>
                  <span>{{ formatEmpId(row.employeeCode) }}</span>
                </div>
              </div>
            </td>
            <td>{{ row.department }}</td>
            <td>{{ row.assetItem }}</td>
            <td>
              <span class="ast-req-status" :class="`is-${row.status}`">
                <i />
                {{ row.statusLabel }}
              </span>
            </td>
            <td class="col-action">
              <button type="button" class="emp-directory-table__menu-btn" @click.stop="openMenu(row, $event)">
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
      <div v-if="openMenuId" class="emp-directory-table__menu ast-req-menu" :style="menuStyle" @click.stop>
        <button type="button" :disabled="menuRow?.status !== 'pending'" @click="onAction('edit')">
          <iconify-icon icon="lucide:pencil" /> Edit Asset Request
        </button>
        <button type="button" class="ast-req-menu__view" @click="onAction('view')">
          <iconify-icon icon="lucide:eye" /> View Request Details
        </button>
        <button type="button" class="is-danger" @click="onAction('delete')">
          <iconify-icon icon="lucide:trash-2" /> Delete Request
        </button>
        <button type="button" class="ast-req-menu__approve" :disabled="menuRow?.status !== 'pending'" @click="onAction('approve')">
          <iconify-icon icon="lucide:check-circle" /> Approve Request
        </button>
        <button type="button" class="is-danger" :disabled="menuRow?.status !== 'pending'" @click="onAction('reject')">
          <iconify-icon icon="lucide:ban" /> Reject Request
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import AssetRequestsSearchPopup from '@/components/hr/assets/AssetRequestsSearchPopup.vue'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'

const props = defineProps({
  requests: { type: Array, default: () => [] },
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
  departments: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:page',
  'update:perPage',
  'update:selectedIds',
  'update:searchQuery',
  'apply-filters',
  'clear-filters',
  'export',
  'edit',
  'view',
  'delete',
  'approve',
  'reject',
])

const perPageOptions = [10, 25, 50, 100]
const showFilters = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)
const openMenuId = ref(null)
const menuRow = ref(null)
const menuStyle = ref({})

const allSelected = computed(
  () => props.requests.length > 0 && props.requests.every((row) => props.selectedIds.includes(row.id))
)
const someSelected = computed(() => props.selectedIds.length > 0)

function formatDate(value) {
  return formatAttendanceDate(value)
}

function formatEmpId(code) {
  if (!code || code === '—') return 'ID: —'
  const raw = String(code).replace(/^ID\s*:?\s*/i, '').replace(/^#/, '')
  return `ID: #${raw}`
}

function toggleRow(id) {
  const next = props.selectedIds.includes(id)
    ? props.selectedIds.filter((item) => item !== id)
    : [...props.selectedIds, id]
  emit('update:selectedIds', next)
}

function toggleSelectAll(event) {
  emit('update:selectedIds', event.target.checked ? props.requests.map((row) => row.id) : [])
}

function openMenu(row, event) {
  if (openMenuId.value === row.id) {
    closeMenu()
    return
  }
  menuRow.value = row
  openMenuId.value = row.id
  const rect = event.currentTarget.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.max(12, rect.right - 240)}px`,
  }
}

function closeMenu() {
  openMenuId.value = null
  menuRow.value = null
}

function onAction(type) {
  const row = menuRow.value
  closeMenu()
  if (!row) return
  emit(type, row)
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
