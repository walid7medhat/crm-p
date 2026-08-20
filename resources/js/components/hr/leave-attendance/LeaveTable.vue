<template>
  <div class="la-attendance-table-wrap emp-directory-table">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Leaves</h6>
      <div class="emp-directory-table__head-actions">
        <div class="emp-directory-table__search-wrap" ref="searchWrapRef">
          <label class="emp-directory-table__search">
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search Leaves"
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

    <div class="la-leave-grid">
      <template v-if="records.length">
        <LeaveRequestCard
          v-for="leave in records"
          :key="`leave-${leave.id}`"
          :leave="leave"
          @approve="$emit('approve', $event)"
          @reject="$emit('reject', $event)"
          @view="$emit('view', $event)"
        />
      </template>
      <div v-else class="la-leave-grid__empty">
        <iconify-icon icon="lucide:calendar-days" />
        <p>No leave requests match your search.</p>
      </div>
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
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import LeaveAttendanceSearchPopup from '@/components/hr/leave-attendance/LeaveAttendanceSearchPopup.vue'
import LeaveRequestCard from '@/components/hr/leave-attendance/LeaveRequestCard.vue'
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
const showFilters = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)

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
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<style scoped>
.la-leave-grid {
  padding: 14px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.la-leave-grid__empty {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 44px 16px;
  color: #94a3b8;
  gap: 10px;
  font-size: 13px;
}

.la-leave-grid__empty iconify-icon {
  font-size: 34px;
  color: #c4b5fd;
}

@media (max-width: 1100px) {
  .la-leave-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .la-leave-grid {
    grid-template-columns: 1fr;
    padding: 10px;
  }
}
</style>
