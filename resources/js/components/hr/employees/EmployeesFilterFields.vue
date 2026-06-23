<template>
  <div class="emp-filter-fields">
    <div class="emp-filter-field">
      <label>Department</label>
      <select :value="modelValue.department_id" @change="update('department_id', $event.target.value)">
        <option value="">All departments</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Position</label>
      <select :value="modelValue.designation_id" @change="update('designation_id', $event.target.value)">
        <option value="">All positions</option>
        <option v-for="d in designations" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Employment status</label>
      <select :value="modelValue.employment_status" @change="update('employment_status', $event.target.value)">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="on_leave">On Leave</option>
        <option value="terminated">Terminated</option>
        <option value="suspended">Suspended</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Account status</label>
      <select :value="modelValue.status" @change="update('status', $event.target.value)">
        <option value="">All</option>
        <option value="active">Active</option>
        <option value="in_active">Inactive</option>
        <option value="blocked">Blocked</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Employment type</label>
      <select :value="modelValue.salary_type" @change="update('salary_type', $event.target.value)">
        <option value="">All types</option>
        <option value="daily">Daily</option>
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Location / Branch</label>
      <select :value="modelValue.company_branch_id" @change="update('company_branch_id', $event.target.value)">
        <option value="">All locations</option>
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Manager</label>
      <select :value="modelValue.parent_id" @change="update('parent_id', $event.target.value)">
        <option value="">All managers</option>
        <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Joining from</label>
      <input type="date" :value="modelValue.joining_date_from" @input="update('joining_date_from', $event.target.value)" />
    </div>
    <div class="emp-filter-field">
      <label>Joining to</label>
      <input type="date" :value="modelValue.joining_date_to" @input="update('joining_date_to', $event.target.value)" />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Object, required: true },
  departments: { type: Array, default: () => [] },
  designations: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
  managers: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<style scoped>
.emp-filter-fields {
  display: contents;
}
</style>
