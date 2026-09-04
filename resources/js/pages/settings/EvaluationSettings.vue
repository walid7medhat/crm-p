<template>
  <div class="dashboard-main-body">
    <Breadcrumb
      title="Evaluations"
      :breadcrumbs="[
        { name: 'HR', path: '/hr' },
        { name: 'Evaluations' },
      ]"
    />
    <EvaluationSettingsPanel />
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import EvaluationSettingsPanel from '@/pages/hr/settings/EvaluationSettingsPanel.vue'
</script>

<style scoped>
.dashboard-main-body {
  padding: 1rem;
}
</style>
