<template>
  <article class="ml-card">
    <router-link :to="`/property-details/${property.id}`" class="ml-card__link">
      <div class="ml-card__image-wrap">
        <img
          :src="imageUrl"
          :alt="property.title || 'Property'"
          class="ml-card__image"
          loading="lazy"
          @error="onImageError"
        />
        <div class="ml-card__badges">
          <span v-if="property.listing_status" class="ml-card__badge" :class="purposeClass">
            {{ property.listing_status === 'sale' ? 'For Sale' : 'For Rent' }}
          </span>
          <span v-if="property.completion_status === 'Under Construction'" class="ml-card__badge ml-card__badge--offplan">
            Off Plan
          </span>
          <span v-else-if="property.completion_status === 'Completed'" class="ml-card__badge ml-card__badge--ready">
            Ready
          </span>
          <span v-if="property.status === 'converted'" class="ml-card__badge ml-card__badge--sold">Sold</span>
          <span v-if="property.status === 'rented'" class="ml-card__badge ml-card__badge--sold">Rented</span>
          <span v-if="property.is_hot_deal === 'Yes'" class="ml-card__badge ml-card__badge--hot">Hot Deal</span>
        </div>
        <span v-if="photoCount > 0" class="ml-card__photos">
          <i class="ri-image-line"></i>{{ photoCount }}
        </span>
      </div>
      <div class="ml-card__body">
        <p class="ml-card__price">
          AED {{ formattedPrice }}
          <span v-if="property.listing_status === 'rent'" class="ml-card__price-unit">/ year</span>
        </p>
        <div v-if="showSpecs" class="ml-card__specs">
          <span v-if="bedsLabel" class="ml-card__spec">
            <i class="ri-hotel-bed-line"></i>{{ bedsLabel }}
          </span>
          <span v-if="bathsLabel" class="ml-card__spec">
            <i class="ri-drop-line"></i>{{ bathsLabel }}
          </span>
          <span v-if="sizeLabel" class="ml-card__spec">
            <i class="ri-ruler-line"></i>{{ sizeLabel }}
          </span>
        </div>
        <h6 class="ml-card__title">{{ property.title || 'Untitled property' }}</h6>
        <p v-if="property.area" class="ml-card__location">{{ property.area }}</p>
        <div class="ml-card__meta">
          <span v-if="property.agent" class="ml-card__agent">{{ agentName }}</span>
          <span v-if="property.created_at">{{ listedDate }}</span>
        </div>
      </div>
    </router-link>
  </article>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  property: { type: Object, required: true },
  fallbackImage: { type: String, default: '/assets/images/a.jpeg' },
})

const imageErrored = ref(false)

const imageUrl = computed(() => {
  if (imageErrored.value) return props.fallbackImage
  const p = props.property
  if (p.main_image) return p.main_image.image_url || p.main_image
  const gallery = p.gallery_images
  if (gallery?.length) {
    const first = gallery[0]
    return first.image_url || first.url || first
  }
  return props.fallbackImage
})

const photoCount = computed(() => {
  const total = props.property.total_images
  if (total) return Number(total)
  return props.property.gallery_images?.length || 0
})

const formattedPrice = computed(() => {
  const price = Number(props.property.price) || 0
  return new Intl.NumberFormat().format(price)
})

const typeName = computed(() => (props.property.property_type || '').toLowerCase())

const isLand = computed(() => typeName.value.includes('plot') || typeName.value.includes('land'))

const bedsLabel = computed(() => {
  if (isLand.value) return null
  const n = props.property.number_of_bedrooms
  if (n === null || n === undefined) return null
  return n === 0 || n === '0' ? 'Studio' : String(n)
})

const bathsLabel = computed(() => {
  if (isLand.value) return null
  const n = props.property.number_of_bathrooms
  if (!n) return null
  return String(n)
})

const sizeLabel = computed(() => {
  const sqft = props.property.size_sqft
  const sqmt = props.property.size_sqmt
  if (sqft) return `${sqft} sqft`
  if (sqmt) return `${sqmt} sqm`
  return null
})

const showSpecs = computed(() => bedsLabel.value || bathsLabel.value || sizeLabel.value)

const purposeClass = computed(() =>
  props.property.listing_status === 'rent' ? 'ml-card__badge--rent' : 'ml-card__badge--sale',
)

const agentName = computed(() => {
  const agent = props.property.agent
  if (!agent) return ''
  const name = agent.name || [agent.first_name, agent.last_name].filter(Boolean).join(' ')
  return name.length > 22 ? `${name.slice(0, 22)}…` : name
})

const listedDate = computed(() => {
  if (!props.property.created_at) return ''
  try {
    return new Date(props.property.created_at).toLocaleDateString('en-GB', {
      day: 'numeric', month: 'short', year: 'numeric',
    })
  } catch {
    return ''
  }
})

function onImageError() {
  imageErrored.value = true
}
</script>
