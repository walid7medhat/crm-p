<template>
  <div class="property-map-page">
    <div class="property-map-page__frame">
      <!-- Floated over map so the map pane keeps full frame height -->
      <div class="property-map-page__toolbar" aria-label="Page heading">
        <Breadcrumb title="Property map" :breadcrumbs="[{ name: 'Property map' }]" />
      </div>
      <div class="property-map-page__map-slot">
        <PropertyMap />
      </div>
    </div>
  </div>
</template>

<script setup>
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue'
import PropertyMap from '@/components/alllisting/PropertyMap.vue'
</script>

<style scoped>
/* Fill dashboard column: map uses full frame height (breadcrumb overlays map corner) */
.property-map-page {
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
  max-height: 100%;
  overflow: hidden;
  padding: 0 0.5rem 0.25rem;
  color: var(--bs-body-color, inherit);
  background: transparent;
}

.property-map-page__map-slot {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.property-map-page__map-slot :deep(.pf-shell) {
  flex: 1 1 auto;
  min-height: 0;
}

.property-map-page__frame {
  position: relative;
  flex: 1 1 auto;
  min-height: 0;
  max-height: 100%;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border-radius: 1rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
  box-shadow:
    0 0 0 1px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.14),
    0 8px 32px rgba(var(--bs-emphasis-color-rgb, 33, 37, 41), 0.08),
    0 20px 56px rgba(var(--bs-primary-rgb, 13, 110, 253), 0.06);
  background: var(--bs-body-bg, #fff);
}

.property-map-page__toolbar {
  position: absolute;
  top: 0.45rem;
  left: 0.55rem;
  right: 0.55rem;
  z-index: 1100;
  pointer-events: none;
}

@media (min-width: 992px) {
  .property-map-page__toolbar {
    right: auto;
    /* Stay over map pane, not the 350px sidebar */
    max-width: min(26rem, calc(100% - 22rem));
  }
}

.property-map-page__toolbar :deep(*) {
  pointer-events: auto;
}
</style>
