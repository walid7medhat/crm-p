<template>
  <div class="device-location">
    <div class="device-location__label">Location</div>

    <template v-if="view.state === 'device'">
      <p class="device-location__quality">{{ qualityText }}</p>
      <p class="device-location__coords">{{ coordinateText }}</p>
      <p v-if="capturedText" class="device-location__meta">Captured {{ capturedText }}</p>
      <p v-if="view.address" class="device-location__address">Nearest address: {{ view.address }}</p>
      <iframe
        v-if="view.map"
        class="device-location__map"
        :src="view.map.embed"
        title="Saved device location"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
      />
      <a
        v-if="view.map"
        class="device-location__link"
        :href="view.map.external"
        target="_blank"
        rel="noopener noreferrer"
      >View on map</a>
    </template>

    <p v-else class="device-location__unavailable">{{ unavailableText }}</p>
  </div>
</template>

<script>
import { computed } from 'vue'
import { deviceLocationView } from '@/utils/deviceLocation.mjs'

export default {
  name: 'UserDeviceLocation',
  props: {
    latitude: { default: null },
    longitude: { default: null },
    accuracy: { default: null },
    capturedAt: { default: null },
    address: { default: null },
    source: { default: null },
  },
  setup(props) {
    const view = computed(() => deviceLocationView({
      latitude: props.latitude,
      longitude: props.longitude,
      accuracy: props.accuracy,
      capturedAt: props.capturedAt,
      address: props.address,
      source: props.source,
    }))

    const qualityText = computed(() => {
      const current = view.value
      if (current.state !== 'device') return ''
      if (current.quality === 'precise') {
        return `Device position · reported accuracy ${current.accuracy} m`
      }
      if (current.quality === 'approximate') {
        return `Approximate device position · reported accuracy ${current.accuracy} m. The device did not report a precise fix.`
      }
      return 'Device position · accuracy was not reported'
    })

    const coordinateText = computed(() => {
      const current = view.value
      if (current.state !== 'device') return ''
      return `${current.latitude}, ${current.longitude}`
    })

    const capturedText = computed(() => {
      const raw = view.value.capturedAt
      if (!raw) return ''
      const date = new Date(raw)
      if (Number.isNaN(date.getTime())) return ''
      return date.toLocaleString()
    })

    const unavailableText = computed(() => {
      if (view.value.unavailableReason === 'network') {
        return 'Location unavailable. The saved point was a network estimate, not this device, so it is not shown.'
      }
      return 'Location unavailable. No device position has been captured.'
    })

    return { view, qualityText, coordinateText, capturedText, unavailableText }
  },
}
</script>

<style scoped>
.device-location {
  margin-top: 12px;
}

.device-location__label {
  font-weight: 700;
  color: #111827;
  margin-bottom: 4px;
}

.device-location__quality,
.device-location__coords,
.device-location__meta,
.device-location__address,
.device-location__unavailable {
  margin: 0 0 4px;
  color: #4b5563;
  font-size: 14px;
  line-height: 1.4;
}

.device-location__coords {
  font-variant-numeric: tabular-nums;
  color: #111827;
}

.device-location__map {
  display: block;
  width: 100%;
  max-width: 420px;
  height: 180px;
  margin: 8px 0;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.device-location__link {
  display: inline-block;
  margin-top: 2px;
  font-size: 14px;
}
</style>
