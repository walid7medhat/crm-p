<template>
  <div class="employee-table-card">
    <div class="employee-table-head">
      <h6 class="overview-section-title">Employee Details</h6>
      <button type="button" class="all-btn" @click="$emit('select-all')">
        All Employees
        <iconify-icon icon="lucide:chevron-right" />
      </button>
    </div>

    <div class="employee-table-wrap">
      <table>
        <thead>
          <tr>
            <th class="check-col">
              <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll" />
            </th>
            <th>ID</th>
            <th>Name</th>
            <th>Designation</th>
            <th>Email</th>
            <th>Department</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="employee in paginatedEmployees"
            :key="employee.id"
            :class="{ selected: selectedEmployeeId === employee.id || selectedIds.has(employee.id) }"
            @click="$emit('select-employee', employee)"
          >
            <td class="check-col" @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.has(employee.id)"
                @change="toggleRow(employee.id)"
              />
            </td>
            <td>{{ formatEmpId(employee) }}</td>
            <td>
              <div class="person-cell">
                <img :src="employee.avatar" :alt="employee.name" />
                <span>{{ employee.name }}</span>
              </div>
            </td>
            <td>{{ employee.designation }}</td>
            <td>{{ employee.email }}</td>
            <td>{{ employee.department }}</td>
            <td class="action-cell">
              <button type="button" class="menu-btn" @click.stop="$emit('view', employee)">
                <iconify-icon icon="lucide:more-vertical" />
              </button>
            </td>
          </tr>
          <tr v-if="!paginatedEmployees.length">
            <td colspan="7" class="empty-cell">No employees found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <p>
        Showing {{ startIndex }} to {{ endIndex }} of {{ employees.length }} Entries
        <iconify-icon icon="lucide:chevron-down" />
      </p>
      <div class="pagination">
        <button type="button" class="nav-btn" :disabled="page === 1" @click="page = Math.max(1, page - 1)">
          <iconify-icon icon="lucide:chevron-left" />
          Previous
        </button>
        <template v-for="(pageNo, idx) in visiblePages" :key="`${pageNo}-${idx}`">
          <span v-if="pageNo === '...'" class="page-ellipsis">...</span>
          <button
            v-else
            type="button"
            class="page-no"
            :class="{ active: pageNo === page }"
            @click="page = pageNo"
          >
            {{ pageNo }}
          </button>
        </template>
        <button type="button" class="nav-btn" :disabled="page === totalPages" @click="page = Math.min(totalPages, page + 1)">
          Next
          <iconify-icon icon="lucide:chevron-right" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  employees: {
    type: Array,
    default: () => [],
  },
  selectedEmployeeId: {
    type: [Number, String],
    default: null,
  },
})

defineEmits(['select-employee', 'select-all', 'view'])

const page = ref(1)
const perPage = 10
const selectedIds = ref(new Set())

const totalPages = computed(() => Math.max(1, Math.ceil(props.employees.length / perPage)))
const paginatedEmployees = computed(() => {
  const start = (page.value - 1) * perPage
  return props.employees.slice(start, start + perPage)
})
const startIndex = computed(() => (props.employees.length ? (page.value - 1) * perPage + 1 : 0))
const endIndex = computed(() => Math.min(page.value * perPage, props.employees.length))
const allVisibleSelected = computed(() => {
  const rows = paginatedEmployees.value
  return rows.length > 0 && rows.every((row) => selectedIds.value.has(row.id))
})
const visiblePages = computed(() => {
  const total = totalPages.value
  const current = page.value
  if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
  const pages = [1]
  if (current > 3) pages.push('...')
  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)
  for (let i = start; i <= end; i += 1) {
    if (!pages.includes(i)) pages.push(i)
  }
  if (current < total - 2) pages.push('...')
  if (!pages.includes(total)) pages.push(total)
  return pages
})

function formatEmpId(employee) {
  const code = employee.employee_code
  if (code) {
    const raw = String(code)
    return raw.startsWith('#') ? raw : `#${raw}`
  }
  return `#EMP-${employee.id}`
}

function toggleRow(id) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function toggleSelectAll(event) {
  const next = new Set(selectedIds.value)
  if (event.target.checked) {
    paginatedEmployees.value.forEach((row) => next.add(row.id))
  } else {
    paginatedEmployees.value.forEach((row) => next.delete(row.id))
  }
  selectedIds.value = next
}

watch(
  () => props.employees,
  () => {
    page.value = 1
    selectedIds.value = new Set()
  },
)
</script>

<style scoped>
.employee-table-card {
  margin-top: 0;
  border: none;
  border-radius: 14px;
  background: #ffffff;
  padding: 20px 22px 16px;
  box-shadow: 0 10px 24px rgba(32, 19, 68, 0.08);
}
.employee-table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.overview-section-title {
  margin: 0;
  font-size: 16px !important;
  font-weight: 700;
  color: #111827;
}
.all-btn {
  border: none;
  background: transparent;
  color: #111827;
  padding: 0;
  font-size: 13px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.all-btn iconify-icon {
  font-size: 16px;
  color: #9ca3af;
}
.employee-table-wrap {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th {
  background: #f6f7fb;
  color: #6b7280;
  font-size: 12px;
  font-weight: 600;
  text-align: left;
  padding: 12px 14px;
  border-bottom: 1px solid #eef0f4;
}
th:first-child {
  border-radius: 10px 0 0 0;
}
th:last-child {
  border-radius: 0 10px 0 0;
}
td {
  color: #374151;
  font-size: 13px;
  font-weight: 400;
  padding: 12px 14px;
  border-bottom: 1px solid #f0f2f5;
  background: #fff;
}
tbody tr {
  cursor: pointer;
  transition: background-color 0.2s ease;
}
tbody tr:hover td {
  background: #f9fafb;
}
tbody tr.selected td {
  background: #fff7e8;
}
.check-col {
  width: 42px;
}
.check-col input {
  width: 15px;
  height: 15px;
  accent-color: #f99f1c;
  cursor: pointer;
}
.person-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.person-cell img {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  object-fit: cover;
}
.person-cell span {
  font-weight: 500;
  color: #111827;
}
.action-cell {
  text-align: right;
}
.menu-btn {
  border: none;
  background: transparent;
  color: #9ca3af;
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.empty-cell {
  text-align: center;
  color: #9ca3af;
  padding: 28px 14px;
}
.pagination-row {
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.pagination-row p {
  margin: 0;
  color: #9ca3af;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.pagination {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pagination .nav-btn {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #ffffff;
  color: #374151;
  padding: 6px 12px;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.pagination .page-no {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: transparent;
  color: #6b7280;
  font-size: 13px;
}
.pagination .page-no.active {
  background: #eef1f6;
  color: #111827;
  font-weight: 600;
}
.page-ellipsis {
  color: #9ca3af;
  font-size: 13px;
  padding: 0 2px;
}
.pagination button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .employee-table-card {
    padding: 14px;
  }
  .pagination-row {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
