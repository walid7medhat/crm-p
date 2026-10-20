<template>
  <Teleport to="body">
    <div v-if="visible" class="property-compare-dock" role="region" aria-label="Selected listings">
      <div class="property-compare-dock__main">
        <div class="property-compare-dock__count">
          <strong>{{ selected.length }}</strong>
          <span>/ {{ maxCompare }}</span>
        </div>
        <p class="property-compare-dock__hint" :class="{ 'is-notice': notice }">
          {{ notice || 'Select listings on the page. Change pages to add more.' }}
        </p>
        <div class="property-compare-dock__actions">
          <button type="button" class="property-compare-dock__cancel" @click="cancelCompare">Cancel</button>
          <button type="button" class="property-compare-dock__go" :disabled="selected.length < 2" @click="openResults">
            Compare Now
          </button>
        </div>
      </div>
      <ul v-if="selected.length" class="property-compare-dock__picks">
        <li v-for="item in selected" :key="item.id">
          <img v-if="item.image" :src="item.image" alt="" />
          <span>{{ item.title }}</span>
          <button type="button" class="property-compare-dock__remove" :aria-label="`Remove ${item.title}`" @click="removeListing(item.id)">×</button>
        </li>
      </ul>
    </div>
    <PropertyCompareResults />
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import PropertyCompareResults from '@/pages/listings/property-compare.vue'
import { canUsePropertyCompare, usePropertyCompareSelection } from '@/composables/propertyCompareSelection'

const route = useRoute()
const {
  compareMode,
  selected,
  resultOpen,
  notice,
  maxCompare,
  removeListing,
  openResults,
  cancelCompare,
  startPropertyCompare,
} = usePropertyCompareSelection()

const visible = computed(() => canUsePropertyCompare() && (compareMode.value || selected.value.length > 0 || resultOpen.value))

watch(visible, (on) => {
  document.body.classList.toggle('property-compare-active', on)
}, { immediate: true })

watch(() => route.fullPath, () => {
  try {
    if (sessionStorage.getItem('open_property_compare') === '1') {
      sessionStorage.removeItem('open_property_compare')
      startPropertyCompare()
    }
  } catch {
    /* ignore */
  }
}, { immediate: true })

onBeforeUnmount(() => {
  document.body.classList.remove('property-compare-active')
})
</script>

<style>
.property-compare-dock {
  position: fixed;
  z-index: 1100;
  left: 50%;
  bottom: calc(5.4rem + env(safe-area-inset-bottom, 0px));
  transform: translateX(-50%);
  width: min(880px, calc(100% - 1.5rem));
  padding: 0.7rem 0.75rem 0.7rem 0.85rem;
  border-radius: 18px;
  background: var(--bg-card, #fff);
  color: var(--text-primary, #0f172a);
  border: 1px solid color-mix(in srgb, var(--color-primary, #6d28d9) 16%, #e2e8f0);
  box-shadow: 0 16px 40px color-mix(in srgb, #0f172a 16%, transparent);
}
@media (min-width: 992px) {
  .property-compare-dock {
    bottom: 1.25rem;
  }
}
.property-compare-dock__main {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}
.property-compare-dock__count {
  display: inline-flex;
  align-items: baseline;
  gap: 0.15rem;
  flex: 0 0 auto;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-primary, #6d28d9) 12%, #fff);
  color: var(--color-primary, #6d28d9);
  line-height: 1;
}
.property-compare-dock__count strong {
  font-size: 1.05rem;
  font-weight: 750;
}
.property-compare-dock__count span {
  font-size: 0.85rem;
  font-weight: 650;
  opacity: 0.75;
}
.property-compare-dock__hint {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--text-secondary, #64748b);
  font-size: 0.95rem;
  line-height: 1.35;
  white-space: nowrap;
}
.property-compare-dock__hint.is-notice {
  color: #b45309;
  font-weight: 650;
}
.property-compare-dock__actions {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex: 0 0 auto;
}
.property-compare-dock__cancel,
.property-compare-dock__go,
.property-compare-dock__remove {
  font: inherit;
  cursor: pointer;
}
.property-compare-dock__cancel {
  border: 0;
  background: transparent;
  color: var(--text-secondary, #64748b);
  padding: 0.45rem 0.7rem;
  border-radius: 999px;
  font-size: 0.95rem;
  font-weight: 650;
}
.property-compare-dock__cancel:hover {
  color: var(--text-primary, #0f172a);
  background: color-mix(in srgb, var(--text-secondary, #64748b) 10%, transparent);
}
.property-compare-dock__go {
  border: 0;
  border-radius: 999px;
  padding: 0.62rem 1.15rem;
  background: var(--color-primary, #6d28d9);
  color: #fff;
  font-size: 0.98rem;
  font-weight: 700;
}
.property-compare-dock__go:disabled {
  background: color-mix(in srgb, var(--color-primary, #6d28d9) 28%, #fff);
  color: color-mix(in srgb, var(--color-primary, #6d28d9) 55%, #fff);
  cursor: not-allowed;
}
.property-compare-dock__picks {
  list-style: none;
  display: flex;
  gap: 0.45rem;
  margin: 0.65rem 0 0;
  padding: 0.65rem 0 0;
  border-top: 1px solid var(--border-color, #e2e8f0);
}
.property-compare-dock__picks li {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.28rem 0.35rem 0.28rem 0.28rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-primary, #6d28d9) 8%, #fff);
}
.property-compare-dock__picks img {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  flex: 0 0 auto;
}
.property-compare-dock__picks span {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.9rem;
  font-weight: 650;
}
.property-compare-dock__remove {
  width: 1.4rem;
  height: 1.4rem;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--text-secondary, #64748b);
  font-size: 1.05rem;
  line-height: 1;
  flex: 0 0 auto;
}
.property-compare-dock__remove:hover {
  background: #fff;
  color: var(--text-primary, #0f172a);
}
@media (max-width: 720px) {
  .property-compare-dock__main {
    flex-wrap: wrap;
  }
  .property-compare-dock__hint {
    order: 3;
    flex-basis: 100%;
    white-space: normal;
  }
}
body.property-compare-active {
  padding-bottom: 8.5rem;
}
.property-compare-mark {
  position: absolute;
  left: 50%;
  bottom: 0.75rem;
  z-index: 4;
  transform: translateX(-50%);
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.38rem 0.85rem 0.38rem 0.4rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.96);
  color: #1e1b4b;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1;
  white-space: nowrap;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
  pointer-events: none;
}
.property-compare-mark__box {
  width: 1.15rem;
  height: 1.15rem;
  border-radius: 50%;
  border: 2px solid var(--color-primary, #6d28d9);
  background: #fff;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
}
.property-compare-mark.is-on {
  background: var(--color-primary, #6d28d9);
  color: #fff;
  border-color: transparent;
}
.property-compare-mark.is-on .property-compare-mark__box {
  border-color: #fff;
  background: #fff;
}
.property-compare-mark.is-on .property-compare-mark__box::after {
  content: "";
  width: 0.28rem;
  height: 0.5rem;
  margin-top: -0.12rem;
  border: solid var(--color-primary, #6d28d9);
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}
.property-listing-card.is-compare-selected .property-card,
.ml-card.is-compare-selected {
  outline: 2px solid var(--color-primary, #6d28d9);
  outline-offset: 2px;
}
.is-compare-armed {
  cursor: pointer;
}
.ml-card__image-wrap {
  position: relative;
}
</style>
