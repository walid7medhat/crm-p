<template>
  <div class="emp-filter-fields">
    <div class="emp-filter-field">
      <label>Date from</label>
      <input type="date" :value="modelValue.start_date" @input="update('start_date', $event.target.value)" />
    </div>
    <div class="emp-filter-field">
      <label>Date to</label>
      <input type="date" :value="modelValue.end_date" @input="update('end_date', $event.target.value)" />
    </div>
    <div class="emp-filter-field">
      <label>Department</label>
      <select :value="modelValue.department" @change="update('department', $event.target.value)">
        <option value="">All</option>
        <option v-for="d in departments" :key="d.id" :value="d.name">{{ d.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Attendance status</label>
      <select :value="modelValue.attendance_status" @change="update('attendance_status', $event.target.value)">
        <option value="">All</option>
        <option value="present">Present</option>
        <option value="absent">Absent</option>
        <option value="late">Late</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Leave type</label>
      <select :value="modelValue.leave_type_id" @change="update('leave_type_id', $event.target.value)">
        <option value="">All</option>
        <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Manager / Team</label>
      <select :value="modelValue.manager_id" @change="update('manager_id', $event.target.value)">
        <option value="">All</option>
        <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Object, required: true },
  departments: { type: Array, default: () => [] },
  leaveTypes: { type: Array, default: () => [] },
  managers: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<style scoped>
.emp-filter-fields { display: contents; }
</style>
