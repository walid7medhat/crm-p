<template>
  <div v-if="slides.length" class="ps-gallery">
    <div
      ref="trackWrap"
      class="ps-gallery__viewport"
      @touchstart.passive="onTouchStart"
      @touchmove="onTouchMove"
      @touchend="onTouchEnd"
    >
      <div
        class="ps-gallery__track"
        :style="trackStyle"
      >
        <div
          v-for="(slide, index) in slides"
          :key="slide.id ?? index"
          class="ps-gallery__slide"
          @click="emit('open', index)"
        >
          <img
            :src="slide.url"
            :alt="`Property photo ${index + 1}`"
            class="ps-gallery__image"
            loading="lazy"
            @error="onImageError(index)"
          />
        </div>
      </div>
    </div>

    <template v-if="slides.length > 1">
      <button
        type="button"
        class="ps-gallery__arrow ps-gallery__arrow--prev"
        aria-label="Previous photo"
        @click.stop="goPrev"
      >
        <i class="ri-arrow-left-s-line"></i>
      </button>
      <button
        type="button"
        class="ps-gallery__arrow ps-gallery__arrow--next"
        aria-label="Next photo"
        @click.stop="goNext"
      >
        <i class="ri-arrow-right-s-line"></i>
      </button>
      <div class="ps-gallery__dots" aria-hidden="true">
        <span
          v-for="(_, index) in slides"
          :key="index"
          class="ps-gallery__dot"
          :class="{ 'ps-gallery__dot--active': index === activeIndex }"
        />
      </div>
    </template>

    <div v-if="badges.length" class="ps-gallery__badges">
      <span
        v-for="badge in badges"
        :key="badge"
        class="ps-gallery__badge"
      >{{ badge }}</span>
    </div>

    <span v-if="slides.length > 1" class="ps-gallery__counter">
      {{ activeIndex + 1 }} / {{ slides.length }}
    </span>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  images: { type: Array, default: () => [] },
  getImageUrl: { type: Function, required: true },
  badges: { type: Array, default: () => [] },
})

const emit = defineEmits(['open'])

const activeIndex = ref(0)
const trackWrap = ref(null)
const touchStartX = ref(0)
const touchDeltaX = ref(0)
const isDragging = ref(false)
const failedIndexes = ref(new Set())

const slides = computed(() =>
  (props.images || []).map((image, index) => ({
    id: image.id ?? index,
    url: failedIndexes.value.has(index)
      ? '/default-property.jpg'
      : props.getImageUrl(image.image_url_final),
  })),
)

const trackStyle = computed(() => {
  const offset = isDragging.value ? touchDeltaX.value : 0
  return {
    transform: `translateX(calc(-${activeIndex.value * 100}% + ${offset}px))`,
    transition: isDragging.value ? 'none' : 'transform 0.32s ease',
  }
})

watch(
  () => props.images?.length,
  () => {
    activeIndex.value = 0
    failedIndexes.value = new Set()
  },
)

function goPrev() {
  if (activeIndex.value > 0) activeIndex.value -= 1
}

function goNext() {
  if (activeIndex.value < slides.value.length - 1) activeIndex.value += 1
}

function onTouchStart(event) {
  if (slides.value.length <= 1) return
  touchStartX.value = event.touches[0].clientX
  touchDeltaX.value = 0
  isDragging.value = true
}

function onTouchMove(event) {
  if (!isDragging.value) return
  touchDeltaX.value = event.touches[0].clientX - touchStartX.value
}

function onTouchEnd() {
  if (!isDragging.value) return
  const threshold = 48
  if (touchDeltaX.value < -threshold) goNext()
  else if (touchDeltaX.value > threshold) goPrev()
  touchDeltaX.value = 0
  isDragging.value = false
}

function onImageError(index) {
  failedIndexes.value = new Set([...failedIndexes.value, index])
}
</script>
