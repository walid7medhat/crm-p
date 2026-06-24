<template>
  <div class="emp-directory-table" :class="{ 'is-searching': searching }">
    <div class="emp-directory-table__head">
      <h6 class="emp-directory-table__title">Manage Employees</h6>
      <div class="emp-directory-table__head-actions">
        <label class="emp-directory-table__search">
          <input
            v-model="draftSearch"
            type="search"
            placeholder="Filter and search employees"
            autocomplete="off"
            @keydown.enter.prevent="commitSearch"
          />
          <span class="emp-directory-table__search-icon" aria-hidden="true">
            <iconify-icon icon="lucide:search" />
          </span>
        </label>
        <button type="button" class="emp-directory-table__export" @click="$emit('export')">
          <iconify-icon icon="lucide:file-spreadsheet" />
          <span>Export Excel</span>
        </button>
      </div>
    </div>

    <div class="emp-directory-table__wrap">
      <table class="emp-directory-table__grid">
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
            <th class="col-id">ID</th>
            <th class="col-name">Name</th>
            <th class="col-designation">Designation</th>
            <th class="col-email">Email</th>
            <th class="col-department">Department</th>
            <th class="col-phone">Phone</th>
            <th class="col-branch">Branch</th>
            <th class="col-manager">Manager</th>
            <th class="col-joined">Joining Date</th>
            <th class="col-status">Status</th>
            <th class="col-action">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!employees.length">
            <td colspan="12" class="emp-directory-table__empty">
              <iconify-icon icon="lucide:search-x" />
              <p>{{ hasActiveFilters ? 'No employees match your search or filters.' : 'No employees to display.' }}</p>
              <button v-if="hasActiveFilters" type="button" class="emp-directory-table__clear-btn" @click="$emit('clear-filters')">
                Clear search &amp; filters
              </button>
            </td>
          </tr>
          <tr v-for="employee in employees" :key="employee.id">
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selectedIds.includes(employee.id)"
                @change="toggleRow(employee.id)"
              />
            </td>
            <td class="col-id">#{{ employee.employeeCode }}</td>
            <td class="col-name">
              <div class="emp-directory-table__person">
                <img :src="employee.avatar" :alt="employee.name" loading="lazy" />
                <span>{{ employee.name }}</span>
              </div>
            </td>
            <td class="col-designation">{{ employee.designation }}</td>
            <td class="col-email">{{ employee.email }}</td>
            <td class="col-department">{{ employee.department }}</td>
            <td class="col-phone">{{ employee.phone }}</td>
            <td class="col-branch">{{ employee.branch }}</td>
            <td class="col-manager">{{ employee.manager }}</td>
            <td class="col-joined">{{ formatDate(employee.joiningDate) }}</td>
            <td class="col-status">
              <span class="emp-directory-table__status" :class="statusClass(employee)">
                {{ statusLabel(employee) }}
              </span>
            </td>
            <td class="col-action">
              <button
                type="button"
                class="emp-directory-table__menu-btn"
                @click.stop="openMenu(employee, $event)"
              >
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
        <button
          type="button"
          class="emp-directory-table__page-btn"
          :disabled="page <= 1"
          @click="$emit('update:page', page - 1)"
        >
          <iconify-icon icon="lucide:chevron-left" />
          Previous
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
        <button
          type="button"
          class="emp-directory-table__page-btn"
          :disabled="page >= totalPages"
          @click="$emit('update:page', page + 1)"
        >
          Next
          <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="openMenuId" class="emp-directory-table__menu" :style="menuStyle" @click.stop>
        <button type="button" @click="onAction('view')">
          <iconify-icon icon="lucide:eye" /> View
        </button>
        <button type="button" @click="onAction('edit')">
          <iconify-icon icon="lucide:pencil" /> Edit
        </button>
        <button type="button" @click="onAction('assets')">
          <iconify-icon icon="lucide:laptop" /> Assets
        </button>
        <button type="button" @click="onAction('attendance')">
          <iconify-icon icon="lucide:clock" /> Attendance
        </button>
        <button type="button" @click="onAction('leave')">
          <iconify-icon icon="lucide:calendar-days" /> Leave
        </button>
        <button type="button" class="is-danger" @click="onAction('delete')">
          <iconify-icon icon="lucide:trash-2" /> Delete
        </button>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  employees: { type: Array, default: () => [] },
  page: { type: Number, default: 1 },
  perPage: { type: Number, default: 10 },
  total: { type: Number, default: 0 },
  totalPages: { type: Number, default: 1 },
  startEntry: { type: Number, default: 0 },
  endEntry: { type: Number, default: 0 },
  paginationItems: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  searchQuery: { type: String, default: '' },
  searching: { type: Boolean, default: false },
  hasActiveFilters: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:page',
  'update:perPage',
  'update:selectedIds',
  'update:searchQuery',
  'clear-filters',
  'export',
  'view',
  'edit',
  'assets',
  'attendance',
  'leave',
  'delete',
])

const perPageOptions = [10, 25, 50, 100]
const draftSearch = ref(props.searchQuery)
const openMenuId = ref(null)
const menuEmployee = ref(null)
const menuStyle = ref({})
let searchTimer = null

const allSelected = computed(
  () => props.employees.length > 0 && props.employees.every((e) => props.selectedIds.includes(e.id))
)
const someSelected = computed(() => props.selectedIds.length > 0)

watch(
  () => props.searchQuery,
  (value) => {
    if (value !== draftSearch.value) draftSearch.value = value
  }
)

watch(draftSearch, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    if (value !== props.searchQuery) emit('update:searchQuery', value)
  }, 500)
})

function commitSearch() {
  clearTimeout(searchTimer)
  if (draftSearch.value !== props.searchQuery) emit('update:searchQuery', draftSearch.value)
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusLabel(employee) {
  const map = {
    active: 'Active',
    on_leave: 'On Leave',
    terminated: 'Terminated',
    suspended: 'Suspended',
  }
  return map[employee.employmentStatus] || employee.employmentStatus || 'Active'
}

function statusClass(employee) {
  if (employee.status !== 'active') return 'is-inactive'
  return `is-${employee.employmentStatus || 'active'}`
}

function toggleRow(id) {
  const next = props.selectedIds.includes(id)
    ? props.selectedIds.filter((rowId) => rowId !== id)
    : [...props.selectedIds, id]
  emit('update:selectedIds', next)
}

function toggleSelectAll(event) {
  if (event.target.checked) {
    emit('update:selectedIds', props.employees.map((e) => e.id))
  } else {
    emit('update:selectedIds', [])
  }
}

function openMenu(employee, event) {
  if (openMenuId.value === employee.id) {
    closeMenu()
    return
  }
  menuEmployee.value = employee
  openMenuId.value = employee.id
  const rect = event.currentTarget.getBoundingClientRect()
  menuStyle.value = {
    top: `${rect.bottom + 6}px`,
    left: `${Math.max(12, rect.right - 180)}px`,
  }
}

function closeMenu() {
  openMenuId.value = null
  menuEmployee.value = null
}

function onAction(action) {
  if (!menuEmployee.value) return
  emit(action, menuEmployee.value)
  closeMenu()
}

function onDocClick() {
  closeMenu()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  clearTimeout(searchTimer)
})
</script>
