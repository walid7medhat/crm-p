<template>
  <div class="la-attendance-table-wrap emp-directory-table">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Leave</h6>
      <div class="emp-directory-table__head-actions">
        <div class="emp-directory-table__search-wrap" ref="searchWrapRef">
          <label class="emp-directory-table__search">
            <span class="emp-directory-table__search-plus" aria-hidden="true">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search Leave"
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
            <LeaveAttendanceSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              mode="leave"
              :search="searchQuery"
              :filters="filters"
              :departments="departments"
              :leave-types="leaveTypes"
              :managers="managers"
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

    <div class="la-attendance-table-scroll">
      <table class="la-attendance-table">
        <thead>
          <tr>
            <th class="la-attendance-table__check">
              <input
                type="checkbox"
                :checked="allSelected"
                :indeterminate.prop="someSelected && !allSelected"
                @change="toggleSelectAll"
              />
            </th>
            <th>Date</th>
            <th>Employee</th>
            <th>Leave Period</th>
            <th>Type</th>
            <th>Status</th>
            <th class="la-attendance-table__action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!records.length">
            <td colspan="7" class="la-attendance-table__empty">
              <iconify-icon icon="lucide:calendar-days" />
              <p>No leave requests match your search.</p>
            </td>
          </tr>
          <tr v-for="leave in records" :key="`leave-${leave.id}`">
            <td class="la-attendance-table__check">
              <input
                type="checkbox"
                :checked="selectedIds.includes(leave.id)"
                @change="toggleRow(leave.id)"
              />
            </td>
            <td class="la-attendance-table__date">{{ formatDate(leave.startDate) }}</td>
            <td>
              <div class="la-attendance-table__employee">
                <img :src="leave.avatar" :alt="leave.employeeName" class="la-attendance-table__avatar" loading="lazy" />
                <div>
                  <p class="la-attendance-table__name">{{ leave.employeeName }}</p>
                  <span class="la-attendance-table__emp-id">{{ formatEmpId(leave.empCode) }}</span>
                </div>
              </div>
            </td>
            <td>
              <div class="la-attendance-table__flow">
                <span class="la-attendance-table__time">{{ formatDate(leave.startDate) }}</span>
                <span class="la-attendance-table__duration-wrap">
                  <i class="la-attendance-table__dot" />
                  <i class="la-attendance-table__line" />
                  <span class="la-attendance-table__duration">{{ leave.duration }}d</span>
                  <i class="la-attendance-table__line" />
                  <i class="la-attendance-table__dot" />
                </span>
                <span class="la-attendance-table__time">{{ formatDate(leave.endDate) }}</span>
              </div>
            </td>
            <td class="la-attendance-table__muted">{{ leave.leaveType }}</td>
            <td>
              <span class="la-leave-status" :class="`is-${leave.status}`">{{ leave.statusLabel }}</span>
            </td>
            <td class="la-attendance-table__action">
              <button type="button" class="la-attendance-table__menu-btn" @click.stop="openMenu(leave, $event)">
                <iconify-icon icon="lucide:more-vertical" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="la-attendance-table__footer">
      <div class="la-attendance-table__footer-left">
        <span>Showing {{ startEntry }} to {{ endEntry }} of {{ total }} Entries</span>
        <label class="la-attendance-table__per-page">
          <select :value="perPage" @change="$emit('update:perPage', Number($event.target.value))">
            <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
          </select>
          <iconify-icon icon="lucide:chevrons-up-down" />
        </label>
      </div>
      <div class="la-attendance-table__pagination">
        <button type="button" class="la-attendance-table__page-btn" :disabled="page <= 1" @click="$emit('update:page', page - 1)">
          <iconify-icon icon="lucide:chevron-left" />
          Previous
        </button>
        <template v-for="(item, idx) in paginationItems" :key="item.type === 'page' ? `p-${item.n}` : `d-${idx}`">
          <span v-if="item.type === 'dots'" class="la-attendance-table__dots">...</span>
          <button
            v-else
            type="button"
            class="la-attendance-table__page-number"
            :class="{ 'is-active': page === item.n }"
            @click="$emit('update:page', item.n)"
          >
            {{ item.n }}
          </button>
        </template>
        <button type="button" class="la-attendance-table__page-btn" :disabled="page >= totalPages" @click="$emit('update:page', page + 1)">
          Next
          <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="openMenuId"
        class="la-attendance-table__menu"
        :style="menuStyle"
        @click.stop
      >
        <button v-if="menuRecord?.canApproveParent || menuRecord?.canApproveHr" type="button" @click="onApprove(menuRecord)">
          <iconify-icon icon="lucide:check" /> Approve
        </button>
        <button v-if="menuRecord?.canReject" type="button" @click="onReject(menuRecord)">
          <iconify-icon icon="lucide:x" /> Reject
        </button>
        <button type="button" @click="onView(menuRecord)">
          <iconify-icon icon="lucide:eye" /> View
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import LeaveAttendanceSearchPopup from '@/components/hr/leave-attendance/LeaveAttendanceSearchPopup.vue'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'

const props = defineProps({
  records: { type: Array, default: () => [] },
  page: { type: Number, default: 1 },
  perPage: { type: Number, default: 10 },
  total: { type: Number, default: 0 },
  totalPages: { type: Number, default: 1 },
  startEntry: { type: Number, default: 0 },
  endEntry: { type: Number, default: 0 },
  paginationItems: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  searchQuery: { type: String, default: '' },
  filters: { type: Object, default: () => ({}) },
  departments: { type: Array, default: () => [] },
  leaveTypes: { type: Array, default: () => [] },
  managers: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:page',
  'update:perPage',
  'update:selectedIds',
  'update:searchQuery',
  'approve',
  'reject',
  'view',
  'export',
  'apply-filters',
  'clear-filters',
])

const perPageOptions = [10, 25, 50, 100]
const openMenuId = ref(null)
const menuRecord = ref(null)
const menuStyle = ref({})
const showFilters = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)

const allSelected = computed(
  () => props.records.length > 0 && props.records.every((r) => props.selectedIds.includes(r.id))
)
const someSelected = computed(() => props.selectedIds.length > 0)

function formatDate(value) {
  return formatAttendanceDate(value)
}

function formatEmpId(code) {
  if (!code || code === '—') return 'ID : —'
  const raw = String(code).replace(/^ID\s*:\s*/i, '').replace(/^#/, '')
  return `ID : #${raw}`
}

function toggleRow(id) {
  const next = props.selectedIds.includes(id)
    ? props.selectedIds.filter((item) => item !== id)
    : [...props.selectedIds, id]
  emit('update:selectedIds', next)
}

function toggleSelectAll(event) {
  emit('update:selectedIds', event.target.checked ? props.records.map((r) => r.id) : [])
}

function openMenu(leave, event) {
  if (openMenuId.value === leave.id) {
    closeMenu()
    return
  }
  menuRecord.value = leave
  openMenuId.value = leave.id
  const rect = event.currentTarget.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.max(12, rect.right - 180)}px`,
  }
}

function closeMenu() {
  openMenuId.value = null
  menuRecord.value = null
}

function onApprove(leave) {
  emit('approve', leave)
  closeMenu()
}

function onReject(leave) {
  emit('reject', leave)
  closeMenu()
}

function onView(leave) {
  emit('view', leave)
  closeMenu()
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
