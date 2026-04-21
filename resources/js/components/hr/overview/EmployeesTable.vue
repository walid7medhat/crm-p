<template>
  <div class="employee-table-card">
    <div class="employee-table-head">
      <h6 class="overview-section-title">Employee Details</h6>
      <button type="button" class="all-btn" @click="$emit('select-all')">All Employees</button>
    </div>

    <div class="employee-table-wrap">
      <table>
        <thead>
          <tr>
            <th></th>
            <th>ID</th>
            <th>Responsible Person</th>
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
            :class="{ selected: selectedEmployeeId === employee.id }"
            @click="$emit('select-employee', employee)"
          >
            <td><input type="checkbox" /></td>
            <td>#EMP-{{ employee.id }}</td>
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
              <button type="button" class="menu-btn" @click.stop="$emit('select-employee', employee)">
                <iconify-icon icon="lucide:more-vertical" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination-row">
      <p>Showing {{ startIndex }} to {{ endIndex }} of {{ employees.length }} Entries</p>
      <div class="pagination">
        <button type="button" :disabled="page === 1" @click="page = Math.max(1, page - 1)">Previous</button>
        <button
          v-for="pageNo in totalPages"
          :key="pageNo"
          type="button"
          class="page-no"
          :class="{ active: pageNo === page }"
          @click="page = pageNo"
        >
          {{ pageNo }}
        </button>
        <button type="button" :disabled="page === totalPages" @click="page = Math.min(totalPages, page + 1)">Next</button>
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
    type: Number,
    default: null,
  },
})

defineEmits(['select-employee', 'select-all'])

const page = ref(1)
const perPage = 10
const totalPages = computed(() => Math.max(1, Math.ceil(props.employees.length / perPage)))
const paginatedEmployees = computed(() => {
  const start = (page.value - 1) * perPage
  return props.employees.slice(start, start + perPage)
})
const startIndex = computed(() => (props.employees.length ? (page.value - 1) * perPage + 1 : 0))
const endIndex = computed(() => Math.min(page.value * perPage, props.employees.length))

watch(
  () => props.employees,
  () => {
    page.value = 1
  },
  { deep: true },
)
</script>

<style scoped>
.employee-table-card {
  margin-top: 8px;
  border: 1px solid #edf1f6;
  border-radius: 12px;
  background: #ffffff;
  padding: 9px;
}
.employee-table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.overview-section-title {
  margin: 0;
  font-size: 15px !important;
  font-weight: 600;
  color: #111827;
}
.all-btn {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #ffffff;
  color: #111827;
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 500;
}
.employee-table-wrap {
  margin-top: 8px;
  border: 1px solid #edf1f6;
  border-radius: 10px;
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th {
  background: #fafafa;
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
  text-align: left;
  padding: 9px 10px;
  border-bottom: 1px solid #edf1f6;
}
td {
  color: #374151;
  font-size: 13px;
  font-weight: 400;
  padding: 8px 10px;
  border-bottom: 1px solid #edf1f6;
}
tbody tr {
  cursor: pointer;
  transition: background-color 0.2s ease;
}
tbody tr:hover {
  background: #f9fafb;
}
tbody tr.selected {
  background: #fff7e8;
}
.person-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
.person-cell img {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  object-fit: cover;
}
.action-cell {
  text-align: right;
}
.menu-btn {
  border: none;
  background: transparent;
  color: #6b7280;
}
.pagination-row {
  margin-top: 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.pagination-row p {
  margin: 0;
  color: #9ca3af;
  font-size: 12px;
}
.pagination {
  display: flex;
  align-items: center;
  gap: 6px;
}
.pagination button {
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #ffffff;
  color: #374151;
  padding: 5px 10px;
  font-size: 11px;
}
.pagination .page-no {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.pagination .page-no.active {
  background: #f3f4f6;
}
.pagination button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
</style>
