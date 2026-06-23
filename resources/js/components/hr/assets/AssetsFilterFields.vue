<template>
  <div class="emp-filter-fields">
    <div class="emp-filter-field">
      <label>Asset type</label>
      <select :value="modelValue.asset_type_id" @change="update('asset_type_id', $event.target.value)">
        <option value="">All types</option>
        <option v-for="t in assetTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Asset status</label>
      <select :value="modelValue.status" @change="update('status', $event.target.value)">
        <option value="">All statuses</option>
        <option value="available">Available</option>
        <option value="assigned">Assigned</option>
        <option value="maintenance">Under Maintenance</option>
        <option value="disposed">Lost / Disposed</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Department</label>
      <select :value="modelValue.department_id" @change="update('department_id', $event.target.value)">
        <option value="">All departments</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Assigned employee</label>
      <select :value="modelValue.user_id" @change="update('user_id', $event.target.value)">
        <option value="">All employees</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
      </select>
    </div>
    <div class="emp-filter-field">
      <label>Purchase from</label>
      <input type="date" :value="modelValue.purchase_date_from" @input="update('purchase_date_from', $event.target.value)" />
    </div>
    <div class="emp-filter-field">
      <label>Purchase to</label>
      <input type="date" :value="modelValue.purchase_date_to" @input="update('purchase_date_to', $event.target.value)" />
    </div>
    <div class="emp-filter-field">
      <label>Warranty status</label>
      <select :value="modelValue.warranty_status" @change="update('warranty_status', $event.target.value)">
        <option value="">All</option>
        <option value="active">Active</option>
        <option value="expiring_soon">Expiring soon</option>
        <option value="expired">Expired</option>
      </select>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Object, required: true },
  assetTypes: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

function update(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>
