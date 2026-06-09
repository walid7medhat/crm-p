<template>
  <div class="dashboard-main-body roi-calculator-page">
    <Breadcrumb
      title="ROI Calculator"
      :breadcrumbs="[{ name: 'Settings' }, { name: 'ROI Calculator' }]"
    />
    <RoiCalculator />
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import RoiCalculator from '@/components/settings/roi/RoiCalculator.vue'
</script>
