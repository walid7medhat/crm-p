<template>
  <div class="dashboard-main-body roi-calculator-page">
    <Breadcrumb
      title="ROI Calculator"
      :breadcrumbs="[{ name: 'Calculators' }, { name: 'ROI Calculator' }]"
    />
    <div class="roi-calculator-page__body">
      <RoiCalculator />
    </div>
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import RoiCalculator from '@/components/Settings/roi/RoiCalculator.vue'
</script>
