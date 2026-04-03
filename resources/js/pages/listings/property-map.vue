<template>
  <div class="dashboard-main-body">
    <Breadcrumb title="Property map" :breadcrumbs="[{ name: 'Property map' }]" />
    <PropertyMap />
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import PropertyMap from '@/components/alllisting/PropertyMap.vue'
</script>

<style scoped>
/* Match kanban.vue: transparent body so dashboard background image shows through */
.dashboard-main-body {
  color: rgba(255, 255, 255, 0.95);
  background: transparent;
}
</style>
