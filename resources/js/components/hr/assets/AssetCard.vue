<template>
  <article
    class="ast-card"
    :class="{ 'is-active': active, 'is-swiped': swipeOpen }"
    @click="$emit('view', asset)"
    @touchstart.passive="onTouchStart"
    @touchmove.passive="onTouchMove"
  >
    <div class="ast-card__main">
      <div class="ast-card__image">
        <img v-if="asset.assignedAvatar && false" :src="asset.assignedAvatar" alt="" />
        <iconify-icon :icon="asset.imageIcon || 'lucide:package'" />
      </div>
      <div class="ast-card__body">
        <div class="ast-card__head">
          <h3>{{ asset.name }}</h3>
          <span class="ast-card__badge" :class="`ast-card__badge--${asset.status}`">{{ asset.statusLabel }}</span>
        </div>
        <p class="ast-card__id">{{ asset.assetId }}</p>
        <div class="ast-card__meta">
          <span><iconify-icon icon="lucide:layers" /> {{ asset.category }}</span>
          <span><iconify-icon icon="lucide:user" /> {{ asset.assignedEmployee }}</span>
          <span><iconify-icon icon="lucide:calendar" /> {{ formatDate(asset.purchaseDate) }}</span>
        </div>
      </div>
    </div>

    <div class="ast-card__swipe" @click.stop>
      <button type="button" class="ast-swipe-btn ast-swipe-btn--assign" @click="$emit('assign', asset)">
        <iconify-icon icon="lucide:user-plus" />
      </button>
      <button type="button" class="ast-swipe-btn ast-swipe-btn--maint" @click="$emit('maintenance', asset)">
        <iconify-icon icon="lucide:wrench" />
      </button>
      <button type="button" class="ast-swipe-btn ast-swipe-btn--edit" @click="$emit('edit', asset)">
        <iconify-icon icon="lucide:pencil" />
      </button>
    </div>
  </article>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  asset: { type: Object, required: true },
  active: { type: Boolean, default: false },
})

defineEmits(['view', 'assign', 'maintenance', 'edit'])

const swipeOpen = ref(false)
let startX = 0

function onTouchStart(e) {
  startX = e.touches[0].clientX
}

function onTouchMove(e) {
  swipeOpen.value = startX - e.touches[0].clientX > 40
}

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
