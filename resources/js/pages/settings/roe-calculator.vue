<template>
  <div class="dashboard-main-body roi-calculator-page">
    <Breadcrumb
      title="ROE Calculator"
      :breadcrumbs="[{ name: 'Settings' }, { name: 'ROE Calculator' }]"
    />
    <div class="roi-calculator-page__body">
      <RoeCalculator />
    </div>
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import RoeCalculator from '@/components/Settings/roe/RoeCalculator.vue'
</script>
