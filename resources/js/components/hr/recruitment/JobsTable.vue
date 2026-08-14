<template>
  <div class="emp-directory-table">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Job Openings</h6>
      <div class="emp-directory-table__head-actions">
        <div class="emp-directory-table__search-wrap" ref="searchWrapRef">
          <label class="emp-directory-table__search">
            <span class="emp-directory-table__search-plus" aria-hidden="true">+</span>
            <input
              :value="searchQuery"
              type="text"
              placeholder="Filter and search job openings"
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
            <CareerSearchPopup
              v-if="showFilters"
              class="emp-search-popup--portal"
              :style="popupStyle"
              :search="searchQuery"
              :filters="filters"
              :jobs="allJobs"
              :departments="departments"
              :branches="branches"
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
      <table class="emp-directory-table__grid emp-directory-table__grid--compact rec-jobs-table">
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
            <th>Job Title</th>
            <th>Department</th>
            <th>Branch</th>
            <th>Type</th>
            <th>Openings</th>
            <th>Posted Date</th>
            <th>C</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!jobs.length">
            <td colspan="9" class="emp-directory-table__empty">
              <iconify-icon icon="lucide:briefcase" />
              <p>{{ hasActiveFilters ? 'No job openings match your search or filters.' : 'No job openings to display.' }}</p>
            </td>
          </tr>
          <tr v-for="job in jobs" :key="job.id">
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selectedIds.includes(job.id)"
                @change="toggleRow(job.id)"
              />
            </td>
            <td class="rec-jobs-table__title">{{ job.title }}</td>
            <td>{{ job.department }}</td>
            <td>{{ job.location }}</td>
            <td>{{ job.employmentType }}</td>
            <td>{{ padCount(job.openings) }}</td>
            <td>{{ formatDate(job.postedDate) }}</td>
            <td>{{ job.applicantsCount ?? 0 }}</td>
            <td class="col-action">
              <button type="button" class="emp-directory-table__menu-btn" @click.stop="openMenu(job, $event)">
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
      <div v-if="openMenuId" class="emp-directory-table__menu rec-job-menu" :style="menuStyle" @click.stop>
        <button type="button" @click="onEdit">
          <iconify-icon icon="lucide:pencil" /> Edit Job Opening
        </button>
        <button type="button" class="is-danger" @click="onDelete">
          <iconify-icon icon="lucide:trash-2" /> Delete Opening
        </button>
        <button type="button" class="rec-job-menu__applicants" @click="onViewApplicants">
          <iconify-icon icon="lucide:users" /> View Applicants
        </button>
        <button type="button" @click="onRejectMail">
          <iconify-icon icon="lucide:mail" /> Send Rejection Mail
        </button>
        <button type="button" @click="onHistory">
          <iconify-icon icon="lucide:history" /> History
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { formatAttendanceDate } from '@/services/leaveAttendanceApi'
import CareerSearchPopup from '@/components/hr/recruitment/CareerSearchPopup.vue'
import { isInsideHrSearchPopup, useHrSearchPopupPortal } from '@/composables/useHrSearchPopupPortal'

const props = defineProps({
  jobs: { type: Array, default: () => [] },
  allJobs: { type: Array, default: () => [] },
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
  branches: { type: Array, default: () => [] },
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
  'delete',
  'view-applicants',
  'reject-mail',
  'history',
])

const perPageOptions = [10, 25, 50, 100]
const showFilters = ref(false)
const searchWrapRef = ref(null)
const { popupStyle } = useHrSearchPopupPortal(searchWrapRef, showFilters)
const openMenuId = ref(null)
const menuJob = ref(null)
const menuStyle = ref({})

const allSelected = computed(
  () => props.jobs.length > 0 && props.jobs.every((job) => props.selectedIds.includes(job.id))
)
const someSelected = computed(() => props.selectedIds.length > 0)

function padCount(value) {
  return String(value ?? 0).padStart(2, '0')
}

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
  emit('update:selectedIds', event.target.checked ? props.jobs.map((job) => job.id) : [])
}

function openMenu(job, event) {
  if (openMenuId.value === job.id) {
    closeMenu()
    return
  }
  menuJob.value = job
  openMenuId.value = job.id
  const rect = event.currentTarget.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.max(12, rect.right - 220)}px`,
  }
}

function closeMenu() {
  openMenuId.value = null
  menuJob.value = null
}

function onEdit() {
  emit('edit', menuJob.value)
  closeMenu()
}

function onDelete() {
  emit('delete', menuJob.value)
  closeMenu()
}

function onViewApplicants() {
  emit('view-applicants', menuJob.value)
  closeMenu()
}

function onRejectMail() {
  emit('reject-mail', menuJob.value)
  closeMenu()
}

function onHistory() {
  emit('history', menuJob.value)
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
