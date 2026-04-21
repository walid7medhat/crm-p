<template>
  <div class="employee-details-card">
    <h6 class="overview-section-title">Employee Details</h6>
    <div v-if="employee" class="details-grid">
      <div class="detail">
        <span>ID</span>
        <strong>#EMP-{{ employee.id }}</strong>
      </div>
      <div class="detail">
        <span>Name</span>
        <strong>{{ employee.name }}</strong>
      </div>
      <div class="detail">
        <span>Designation</span>
        <strong>{{ employee.designation }}</strong>
      </div>
      <div class="detail">
        <span>Email</span>
        <strong>{{ employee.email }}</strong>
      </div>
      <div class="detail">
        <span>Department</span>
        <strong>{{ employee.department }}</strong>
      </div>
      <div class="detail">
        <span>Status</span>
        <strong>{{ employee.status }}</strong>
      </div>
    </div>
    <p v-else class="empty-state">Select an employee row to view details.</p>
  </div>
</template>

<script setup>
defineProps({
  employee: {
    type: Object,
    default: null,
  },
})
</script>

<style scoped>
.employee-details-card {
  margin-top: 10px;
  border: 1px solid #edf1f6;
  border-radius: 12px;
  padding: 10px;
  background: #ffffff;
}
.overview-section-title {
  margin: 0;
  font-size: 15px !important;
  font-weight: 600;
  color: #111827;
}
.details-grid {
  margin-top: 8px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.detail {
  border: 1px solid #eef2f7;
  border-radius: 8px;
  padding: 8px 10px;
}
.detail span {
  display: block;
  font-size: 10px;
  color: #6b7280;
  margin-bottom: 3px;
}
.detail strong {
  color: #111827;
  font-size: 12px;
  font-weight: 600;
}
.empty-state {
  margin: 8px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
