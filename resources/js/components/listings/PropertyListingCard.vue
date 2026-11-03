<template>
  <div
    class="property-listing-card"
    :class="{
      'property-listing-card--missing-breakdown': needsBreakdown,
      'is-compare-selected': compareSelected,
    }"
  >
    <a
      :href="`/property-details/${property.id}`"
      class="property-card-link"
      :data-compare-market="listingPurpose(property) || undefined"
      :class="{ 'converted-card': property.status === 'converted', 'is-compare-armed': compareMode }"
      @click="$emit('open', $event, property)"
    >
      <div class="property-card" :class="{ 'inactive-card': dimInactive && !property.is_active }">
        <!-- Image -->
        <div class="property-image position-relative">
          <span v-if="compareMode" class="property-compare-mark" :class="{ 'is-on': compareSelected }">
            <span class="property-compare-mark__box" aria-hidden="true"></span>
            {{ compareSelected ? 'Selected' : 'Select' }}
          </span>
          <img
            :src="imageSrc"
            :alt="property.title"
            class="w-100 object-fit-cover"
            :class="dimInactive
              ? { 'inactive-image': !property.is_active }
              : { 'converted-image': property.status === 'converted' }"
            loading="lazy"
            @load="handleImageLoad"
            @error="handleImageError"
          />

          <div class="status-badges">
            <span v-if="property.status === 'converted'" class="badge-sold">
              <i class="ri-checkbox-circle-fill me-1"></i>Sold Out
            </span>
            <span v-if="property.status === 'rented'" class="badge-sold">
              <i class="ri-home-gear-line me-1"></i>Rented
            </span>
            <span v-else-if="property.status === 'draft'" class="badge-sold">
              <i class="ri-checkbox-circle-fill me-1"></i>draft
            </span>
            <span v-if="property.is_archived" class="badge-archived">
              <i class="ri-archive-fill me-1"></i>Archived
            </span>
            <span v-if="!property.is_active" class="badge-inactive">
              <i class="ri-eye-off-fill me-1"></i>Inactive
            </span>
            <span v-if="property.listing_status" class="status-badge">
              {{ property.listing_status === 'sale' ? 'For Sale' : 'For Rent' }}
            </span>
            <span v-if="property.completion_status === 'Under Construction'" class="badge-offplan">
              <i class="ri-building-line me-1"></i>Off Plan
            </span>
            <span v-else-if="property.completion_status === 'Completed'" class="badge-ready">
              <i class="ri-checkbox-circle-line me-1"></i>Ready
            </span>
            <span v-if="property.is_hot_deal == 'Yes'" class="badge-off_plan">
              Hot Deal
            </span>
            <span v-if="property.occupancy_status && property.completion_status != 'Under Construction'" class="badge-occupancy_status">
              {{ property.occupancy_status }}
            </span>
            <span v-if="rejected" class="badge-sold bg-danger">
              <i class="ri-close-circle-fill me-1"></i>Rejected
            </span>
            <span v-else-if="needsApproval" class="badge-sold bg-danger">
              <i class="ri-time-line me-1"></i>Need Approve
            </span>
            <span v-if="needsBreakdown" class="badge-missing-breakdown">
              <i class="ri-error-warning-line me-1"></i>No breakdown
            </span>
          </div>

          <span class="badge-images" v-if="property.total_images > 1 || (property.gallery_images && property.gallery_images.length)">
            <i class="ri-image-line me-1"></i>{{ property.total_images || property.gallery_images.length }}
          </span>
        </div>

        <!-- Content -->
        <div class="property-content p-3">
          <div class="d-flex align-items-end mb-1">
            <h6 class="property-price mb-0 me-1">{{ formatPrice(property.price) }}</h6>
            <small class="text-muted price-unit">AED</small>
          </div>

          <h6 class="property-title mb-2">{{ property.title || 'No Title' }}</h6>
          <p class="property-location mb-3" :title="property.area">
            <i class="ri-map-pin-line me-1"></i>{{ property.area }}
          </p>

          <div class="property-details d-flex justify-content-between text-muted small mb-3">
            <span class="d-flex justify-content-between icons">
              <img :src="propertyIcon" class="imgicon" />
              <span>{{ getPropertyType(property) }}</span>
            </span>
            <span class="d-flex justify-content-between icons" v-if="!hidesBedsBaths(property.property_type) && property.number_of_bedrooms !== null && property.number_of_bedrooms !== undefined">
              <img :src="bedIcon" class="imgicon" />
              <span>{{ property.number_of_bedrooms == 0 ? 'Studio' : property.number_of_bedrooms }}</span>
            </span>
            <span class="d-flex justify-content-between icons" v-if="!hidesBedsBaths(property.property_type) && property.number_of_bathrooms !== null && property.number_of_bathrooms !== undefined && property.number_of_bathrooms != 0">
              <img :src="bathIcon" class="imgicon" />
              <span>{{ property.number_of_bathrooms || 0 }}</span>
            </span>
            <span class="d-flex justify-content-between icons">
              <img :src="sqftIcon" class="imgicon" />
              <span>{{ property.size_sqft || property.size_sqmt || 0 }} {{ getAreaUnit(property) }}</span>
            </span>
          </div>
          <div class="d-flex align-items-end justify-between">
            <p class="property-agent text-muted small mb-2" v-if="property.agent">
              Listed by: {{ agentLabel }}
            </p>
            <div class="property-listed-date mb-2">
              <i class="ri-calendar-line me-1"></i>
              <small class="text-muted">Updated at: {{ formatDate(property.updated_at) }}</small>
            </div>
          </div>
          <span class="view-more-btn">
            View Details
          </span>
        </div>
      </div>
    </a>
    <button
      v-if="breakdownAction && needsBreakdown && canQuickEditPaymentBreakdown(property)"
      type="button"
      class="btn btn-sm btn-light breakdown-quick-btn"
      @click.stop.prevent="$emit('breakdown', property)"
    >
      <i class="ri-bank-card-line me-1"></i>
      Add payment breakdown
    </button>
  </div>
</template>

<script>
import { listingPurpose } from '@/composables/propertyCompareSelection';
import { hidesBedsBaths } from '@/composables/listingPropertyType';
import {
  listingNeedsPaymentBreakdownHighlight,
  canQuickEditPaymentBreakdown,
} from '@/utils/listingPaymentBreakdownStatus';

const DEFAULT_IMAGES = ['/assets/images/a.jpeg', '/assets/images/b.jpeg', '/assets/images/c.jpeg', '/assets/images/a.jpeg'];

/**
 * Desktop listing card shared by All Listing, My Listing and the agent profile's Properties tab.
 * The parent decides what a click does (`open`) and whether the
 * "Add payment breakdown" shortcut is offered (`breakdownAction` → `breakdown`).
 * `isRejected` swaps in a page's own rejection rule; `dimInactive` greys out inactive listings.
 */
export default {
  name: 'PropertyListingCard',
  props: {
    property: { type: Object, required: true },
    compareMode: { type: Boolean, default: false },
    compareSelected: { type: Boolean, default: false },
    breakdownAction: { type: Boolean, default: false },
    isRejected: { type: Function, default: null },
    dimInactive: { type: Boolean, default: false },
  },
  emits: ['open', 'breakdown'],
  data() {
    return {
      propertyIcon: '/assets/icons/property-icon.svg',
      bedIcon: '/assets/icons/bedroom-icon.svg',
      bathIcon: '/assets/icons/bathroom-icon.svg',
      sqftIcon: '/assets/icons/area-size.svg',
    };
  },
  computed: {
    imageSrc() {
      const property = this.property;
      if (property.main_image) {
        return property.main_image.image_url || property.main_image;
      }
      if (property.gallery_images && property.gallery_images.length > 0) {
        const firstImage = property.gallery_images[0];
        return firstImage.image_url || firstImage.url || firstImage;
      }
      return DEFAULT_IMAGES[Math.floor(Math.random() * DEFAULT_IMAGES.length)];
    },
    rejected() {
      const p = this.property;
      if (this.isRejected) return this.isRejected(p);
      return !p.approved && !!p.rejection_reason && !!p.is_active;
    },
    needsApproval() {
      const p = this.property;
      if (p.approved || p.status !== 'published') return false;
      // The default rule only flags listings that were never rejected
      return this.isRejected ? true : !p.rejection_reason;
    },
    needsBreakdown() {
      return listingNeedsPaymentBreakdownHighlight(this.property);
    },
    agentLabel() {
      const name = this.getAgentName(this.property.agent) || '';
      return name.length > 20 ? name.slice(0, 20) + '...' : name;
    },
  },
  methods: {
    listingPurpose,
    hidesBedsBaths,
    canQuickEditPaymentBreakdown,
    handleImageLoad(event) {
      event.target.classList.add('loaded');
    },
    handleImageError(event) {
      event.target.src = DEFAULT_IMAGES[0];
    },
    formatPrice(price) {
      if (!price) return '0';
      return new Intl.NumberFormat().format(price);
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
        });
      } catch (error) {
        return 'Invalid Date';
      }
    },
    getPropertyType(property) {
      if (property.property_type) return property.property_type;
      if (property.type) return property.type;
      return 'Property';
    },
    getAreaUnit(property) {
      return property.size_sqft ? 'Sqft' : 'Sqm';
    },
    getAgentName(agent) {
      if (!agent) return 'Unknown Agent';
      if (agent.name) return agent.name;
      if (agent.first_name && agent.last_name) return `${agent.first_name} ${agent.last_name}`;
      if (agent.first_name) return agent.first_name;
      return 'Unknown Agent';
    },
  },
};
</script>

<style scoped>
.property-image img {
  height: 230px;
  transition: transform 0.4s ease;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.property-image img.loaded {
  opacity: 1;
}

.property-card {
  will-change: transform;
}

.property-image img {
  will-change: transform;
}
.property-card {
  background: #fff;
  border-radius: 18px;
  overflow: hidden;
  transition: all 0.3s ease;
  border: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
}

.property-agent {
  font-size: 0.75rem;
  color: #555;
}

.property-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 8px 26px rgba(0, 0, 0, 0.1);
}

.property-card:hover .property-image img {
  transform: scale(1.03);
}

.badge-images {
  position: absolute;
  bottom: 12px;
  right: 12px;
  background: #fff;
  color: #333;
  font-size: 0.8rem;
  padding: 4px 9px;
  border-radius: 10px;
  font-weight: 500;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.property-content {
  background: #fff;
}

.property-price {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1d1d1d;
  letter-spacing: -0.2px;
}

.price-unit {
  font-size: 0.75rem;
}

.property-title {
  font-size: 0.9rem !important;
  font-weight: 500;
  color: #2a2a2a;
  line-height: 1.4;
}

.property-location {
  font-size: 0.8rem;
  color: #777;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.view-more-btn {
  display: inline-block;
  text-align: center;
  width: 100%;
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
  font-weight: 500;
  border-radius: 10px;
  padding: 8px 0;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.3s;
}

.view-more-btn:hover {
  background: #581c87;
  color: #fff;
}

/* Converted Card Styles */
.converted-card {
  opacity: 0.7;
}

.converted-card:hover {
  transform: none;
  box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
  cursor: not-allowed;
}

.converted-image {
  filter: grayscale(20%);
}

.inactive-card {
  opacity: 0.8;
}

.inactive-image {
  filter: grayscale(40%);
}

.status-badges {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-direction: row;
  gap: 5px;
  flex-wrap: wrap;
}

.badge-sold {
  background: #28a745;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.badge-archived {
  background: #ffc107;
  color: #212529;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.badge-inactive {
  background: #6c757d;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.property-listing-card {
  position: relative;
  height: 100%;
}

.property-listing-card--missing-breakdown .property-card {
  box-shadow: 0 0 0 2px #fbbf24, 0 4px 14px rgba(251, 191, 36, 0.25);
  background: linear-gradient(180deg, #fffbeb 0%, #ffffff 28%);
}

.badge-missing-breakdown {
  background: #f59e0b;
  color: #1f2937;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 600;
}

.breakdown-quick-btn {
  display: block;
  width: calc(100% - 1.5rem);
  margin: 0 0.75rem 0.75rem;
  border: 1px solid #fbbf24;
  background: #fffbeb;
  color: #92400e;
  font-weight: 600;
  font-size: 0.8rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.breakdown-quick-btn:hover {
  background: #fef3c7;
  color: #78350f;
  border-color: #f59e0b;
}

.property-card-link {
  display: block;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.property-card-link:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.property-card-link:hover .property-card {
  box-shadow: none;
}

.property-card-link .view-more-btn {
  display: inline-block;
  pointer-events: none;
}
.icons {
  gap: 2px;
}
.badge-off_plan {
  background: #B60F1C;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}
.badge-offplan {
  background: #ffc107;
  color: #212529;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.badge-ready {
  background: #28a745;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}
.badge-occupancy_status {
  background: #EDEBEB !important;
  color: #0B0736 !important;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.status-badge {
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}

.property-listed-date {
  font-size: 0.75rem;
  margin-bottom: 0 !important;
  display: flex;
  align-items: center;
}

.property-listed-date i {
  font-size: 0.8rem;
  color: var(--color-primary);
}

.justify-between {
  justify-content: space-between;
}
.icons img {
  width: 17px;
  height: 17px;
}
</style>
