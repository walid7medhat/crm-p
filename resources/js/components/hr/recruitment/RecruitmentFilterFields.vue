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
      <label>Location</label>
      <select :value="modelValue.branch_id" @change="update('branch_id', $event.target.value)">
        <option value="">All locations</option>
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Employment type</label>
      <select :value="modelValue.job_type" @change="update('job_type', $event.target.value)">
        <option value="">All types</option>
        <option value="full_time">Full-time</option>
        <option value="part_time">Part-time</option>
        <option value="contract">Contract</option>
        <option value="internship">Internship</option>
        <option value="remote">Remote</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Experience level</label>
      <select :value="modelValue.experience_level" @change="update('experience_level', $event.target.value)">
        <option value="">All levels</option>
        <option value="Junior">Junior</option>
        <option value="Mid-level">Mid-level</option>
        <option value="Senior">Senior</option>
        <option value="Lead">Lead</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Hiring status</label>
      <select :value="modelValue.status" @change="update('status', $event.target.value)">
        <option value="">All statuses</option>
        <option value="open">Open</option>
        <option value="on_hold">On Hold</option>
        <option value="closed">Closed</option>
        <option value="draft">Draft</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Recruiter</label>
      <select :value="modelValue.hiring_manager_id" @change="update('hiring_manager_id', $event.target.value)">
        <option value="">All recruiters</option>
        <option v-for="r in recruiters" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Object, required: true },
  departments: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
  recruiters: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>
