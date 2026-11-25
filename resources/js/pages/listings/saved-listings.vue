<template>
  <div class="saved-page">
    <header class="saved-bar">
      <span class="saved-mark" aria-hidden="true">
        <i class="ri-heart-fill"></i>
      </span>
      <div class="saved-copy">
        <span class="saved-title">Saved listings</span>
        <span class="saved-note">Properties you kept with the heart.</span>
      </div>
      <span v-if="!loading" class="saved-count">{{ properties.length }}</span>
    </header>

    <div v-if="loading" class="saved-empty" role="status">
      <span class="saved-empty__mark" aria-hidden="true"><i class="ri-heart-line"></i></span>
      <p>Loading your saved listings</p>
    </div>

    <div v-else-if="!properties.length" class="saved-empty">
      <span class="saved-empty__mark" aria-hidden="true"><i class="ri-heart-line"></i></span>
      <p>Nothing saved yet</p>
      <span>Open a listing and tap the heart to keep it here.</span>
      <router-link to="/alllisting" class="saved-go">Browse listings</router-link>
    </div>

    <div v-else class="row g-3">
      <div
        v-for="property in properties"
        :key="property.id"
        class="col-12 col-md-6 col-xl-4"
      >
        <PropertyListingCard
          :property="property"
          saved-page
          @open="onOpen"
          @unsaved="onUnsaved"
        />
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/plugins/axios'
import PropertyListingCard from '@/components/listings/PropertyListingCard.vue'
import { openPropertyDetailsFromClick } from '@/composables/usePropertyDetailsModal'

export default {
  name: 'SavedListings',
  components: { PropertyListingCard },
  data() {
    return {
      loading: true,
      properties: [],
    }
  },
  mounted() {
    this.load()
  },
  methods: {
    async load() {
      this.loading = true
      try {
        const response = await api.get('/listings/saved')
        this.properties = response?.data?.data || []
      } finally {
        this.loading = false
      }
    },
    onOpen(event, property) {
      openPropertyDetailsFromClick(event, property.id)
    },
    onUnsaved(property) {
      this.properties = this.properties.filter((item) => item.id !== property.id)
    },
  },
}
</script>

<style scoped>
.saved-page {
  padding: 8px 4px 24px;
}

.saved-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  max-width: 100%;
  margin-bottom: 12px;
  padding: 5px 8px 5px 5px;
  background: #fff;
  border: 1px solid #ebe6f0;
  border-radius: 12px;
}

.saved-mark {
  width: 22px;
  height: 22px;
  border-radius: 7px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: var(--gradient-primary, linear-gradient(135deg, #8b5cf6, #733e87));
  color: #fff;
  font-size: 11px;
}

.saved-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.saved-title {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
  color: #1c1730;
}

.saved-note {
  font-size: 10px;
  line-height: 1.3;
  color: #8a8494;
}

.saved-count {
  margin-left: 6px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #f4eef8;
  color: var(--color-primary, #733e87);
  font-size: 10px;
  font-weight: 600;
}

.saved-empty {
  width: min(360px, 100%);
  margin: 28px auto 0;
  padding: 28px 22px 22px;
  text-align: center;
  background: #fff;
  border: 1px solid #ebe6f0;
  border-radius: 18px;
  box-shadow: 0 10px 24px -18px rgba(80, 30, 110, 0.45);
}

.saved-empty__mark {
  width: 36px;
  height: 36px;
  margin: 0 auto 10px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f4eef8;
  color: var(--color-primary, #733e87);
  font-size: 16px;
}

.saved-empty p {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #1c1730;
}

.saved-empty span {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  line-height: 1.45;
  color: #8a8494;
}

.saved-go {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 30px;
  margin-top: 14px;
  padding: 0 14px;
  border-radius: 999px;
  background: var(--gradient-primary, linear-gradient(135deg, #8b5cf6, #733e87));
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}
</style>
