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
            <span v-else-if="needsApproval" class="badge-approval">
              Need Approval
            </span>
            <span v-if="needsBreakdown" class="badge-missing-breakdown">
              <i class="ri-error-warning-line me-1"></i>No breakdown
            </span>
          </div>

          <button
            type="button"
            class="card-heart"
            :class="{ 'is-saved': saved }"
            :aria-label="saved ? 'Open saved listings' : 'Save listing'"
            @click.stop.prevent="onHeart"
          >
            <i :class="saved ? 'ri-heart-fill' : 'ri-heart-line'"></i>
          </button>
          <span v-if="property.listing_status" class="listing-purpose">
            {{ property.listing_status === 'sale' ? 'For Sale' : 'For Rent' }}
          </span>
        </div>

        <div class="property-content">
          <div class="headline-row">
            <span class="property-title">
              <i class="ri-community-line" aria-hidden="true"></i>
              <span>{{ property.title || 'No Title' }}</span>
            </span>
            <div class="price-row">
              <span class="property-price">{{ formatPrice(property.price) }}</span>
              <span class="price-currency">AED</span>
              <span v-if="property.listing_status && property.listing_status !== 'sale'" class="price-period">/ year</span>
            </div>
          </div>
          <p class="property-location" :title="property.area">
            <i class="ri-map-pin-line"></i>
            <span>{{ property.area || '—' }}</span>
          </p>

          <div class="spec-box">
            <div class="spec-item">
              <img :src="propertyIcon" alt="" />
              <span>{{ getPropertyType(property) }}</span>
            </div>
            <div class="spec-item">
              <img :src="bedIcon" alt="" />
              <span>{{ bedLabel(property) }}</span>
            </div>
            <div class="spec-item">
              <img :src="bathIcon" alt="" />
              <span>{{ bathLabel(property) }}</span>
            </div>
            <div class="spec-item">
              <img :src="sqftIcon" alt="" />
              <span>{{ formatSize(property) }}</span>
            </div>
          </div>

          <div class="card-footer-row">
            <div class="agent-block" v-if="property.agent">
              <span class="agent-avatar">{{ agentInitials() }}</span>
              <div class="agent-copy">
                <span class="property-agent">{{ agentLabel }}</span>
                <span class="property-listed-date">
                  <i class="ri-calendar-line"></i>
                  Updated {{ formatDate(property.updated_at) }}
                </span>
              </div>
            </div>
            <div v-else class="agent-block">
              <span class="property-listed-date">
                <i class="ri-calendar-line"></i>
                Updated {{ formatDate(property.updated_at) }}
              </span>
            </div>
            <span class="view-more-btn">Details <i class="ri-arrow-right-line"></i></span>
          </div>
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
import api from '@/plugins/axios';
import { listingPurpose } from '@/composables/propertyCompareSelection';
import { isListingSaved, loadSavedListingIds, rememberSavedListing } from '@/composables/savedListings';
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
    savedPage: { type: Boolean, default: false },
  },
  emits: ['open', 'breakdown', 'unsaved'],
  data() {
    return {
      propertyIcon: '/assets/icons/property-icon.svg',
      bedIcon: '/assets/icons/bedroom-icon.svg',
      bathIcon: '/assets/icons/bathroom-icon.svg',
      sqftIcon: '/assets/icons/area-size.svg',
      saved: false,
      saving: false,
    };
  },
  watch: {
    'property.id': {
      immediate: true,
      handler() {
        this.syncSaved();
      },
    },
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
    bedLabel(property) {
      if (hidesBedsBaths(property.property_type)) return '—';
      if (property.number_of_bedrooms === null || property.number_of_bedrooms === undefined || property.number_of_bedrooms === '') return '—';
      if (Number(property.number_of_bedrooms) === 0) return 'Studio';
      const n = Number(property.number_of_bedrooms);
      return `${n} Bed${n === 1 ? '' : 's'}`;
    },
    bathLabel(property) {
      if (hidesBedsBaths(property.property_type)) return '—';
      const n = Number(property.number_of_bathrooms);
      if (!Number.isFinite(n) || n <= 0) return '—';
      return `${n} Bath${n === 1 ? '' : 's'}`;
    },
    formatSize(property) {
      const raw = property.size_sqft || property.size_sqmt || 0;
      const n = Number(String(raw).replace(/,/g, ''));
      const value = Number.isFinite(n) ? new Intl.NumberFormat().format(n) : '0';
      return `${value} ${property.size_sqft ? 'ft²' : 'm²'}`;
    },
    agentInitials() {
      const name = this.getAgentName(this.property.agent) || '';
      const parts = name.split(/\s+/).filter(Boolean);
      if (!parts.length || parts[0] === 'Unknown') return 'SA';
      return ((parts[0][0] || '') + (parts[1]?.[0] || '')).toUpperCase();
    },
    async syncSaved() {
      if (!this.property?.id) return;
      try {
        await loadSavedListingIds();
        this.saved = isListingSaved(this.property.id);
      } catch {
        this.saved = false;
      }
    },
    async onHeart() {
      if (this.saving || !this.property?.id) return;
      if (this.saved && !this.savedPage) {
        this.$router.push('/saved-listings');
        return;
      }
      this.saving = true;
      try {
        const response = await api.post(`/listings/saved/${this.property.id}`);
        this.saved = !!response?.data?.data?.saved;
        rememberSavedListing(this.property.id, this.saved);
        if (this.savedPage && !this.saved) this.$emit('unsaved', this.property);
      } finally {
        this.saving = false;
      }
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
.property-image {
  border-radius: 18px 18px 0 0;
  overflow: hidden;
  border: none;
}

.property-image img {
  height: 196px;
  width: 100%;
  display: block;
  transition: transform 0.4s ease, opacity 0.3s ease;
  opacity: 0;
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
  padding: 0;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
  border: 1px solid #e8e3ee;
  box-shadow: 0 8px 18px -14px rgba(40, 20, 60, 0.28);
}

.property-agent {
  font-size: 12px;
  font-weight: 700;
  color: #1c1730;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.property-card:hover {
  border-color: color-mix(in srgb, var(--color-primary, #733e87) 28%, #e8e3ee);
  box-shadow: 0 14px 28px -16px rgba(80, 30, 110, 0.35);
}

.property-card:hover .property-image img {
  transform: scale(1.03);
}

.listing-purpose {
  position: absolute;
  bottom: 10px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #fff;
  color: #1c1730;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 4px 10px rgba(20, 10, 40, 0.12);
  text-shadow: 0 1px 0 rgba(255, 255, 255, 0.8);
}

.listing-purpose {
  left: 10px;
}

.property-content {
  background: #fff;
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.price-row,
.property-title,
.property-location,
.spec-item span,
.property-agent,
.property-listed-date {
  text-shadow: none;
}

.headline-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
}

.price-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-shrink: 0;
}

.price-currency,
.price-period {
  font-size: 12px;
  font-weight: 600;
  color: #9aa0a6;
}

.property-price {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  letter-spacing: -0.02em;
  line-height: 1.15;
  margin: 0;
}

.property-title {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  flex: 1;
  min-width: 0;
  font-size: 13px;
  font-weight: 600;
  color: #1a1a1a;
  line-height: 1.25;
  text-align: left;
}

.property-title i {
  flex-shrink: 0;
  color: #8a8494;
  font-size: 14px;
}

.property-title span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.property-location {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  font-size: 12px;
  color: #6b6578;
  overflow: hidden;
}

.property-location span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.property-location i {
  color: #8a8494;
  flex-shrink: 0;
}

.spec-box {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-items: center;
  width: 100%;
  min-width: 0;
  margin-top: 2px;
  padding: 8px 2px;
  border: 1px solid #ece7f1;
  border-radius: 12px;
  background: #faf9fb;
  box-sizing: border-box;
}

.spec-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-width: 0;
  padding: 2px 4px;
  border-right: 1px solid #ece7f1;
  text-align: center;
}

.spec-item:last-child {
  border-right: none;
}

.spec-item img {
  width: 16px;
  height: 16px;
  object-fit: contain;
}

.spec-item span {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 11px;
  font-weight: 600;
  color: #3f3a48;
}

.card-footer-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 6px;
}

.agent-block {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.agent-avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #f3e9fb;
  border: 1px solid #e4d4ef;
  color: var(--color-primary, #733e87);
  font-size: 11px;
  font-weight: 700;
}

.agent-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.card-heart {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  color: #4b5563;
  border: 1px solid rgba(255, 255, 255, 0.9);
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
  font-size: 16px;
  cursor: pointer;
  padding: 0;
}

.card-heart.is-saved {
  color: var(--color-primary, #733e87);
}

.view-more-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  gap: 4px;
  height: 32px;
  margin-top: 0;
  padding: 0 12px;
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 999px;
  border: none;
  font-size: 12px;
  text-decoration: none;
  text-shadow: none;
}

.view-more-btn:hover {
  filter: brightness(1.05);
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
  top: 10px;
  left: 10px;
  right: 44px;
  display: flex;
  flex-direction: row;
  gap: 6px;
  flex-wrap: wrap;
}

.badge-sold,
.badge-archived,
.badge-inactive,
.badge-off_plan,
.badge-offplan,
.badge-ready,
.badge-occupancy_status,
.badge-missing-breakdown,
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.35);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1.1;
  text-shadow: 0 1px 0 rgba(0, 0, 0, 0.12);
}

.badge-sold {
  background: #16a34a;
  color: white;
}

.badge-archived {
  background: #f5c542;
  color: #1c1730;
}

.badge-inactive {
  background: #6b6578;
  color: white;
}

.property-listing-card {
  position: relative;
  height: 100%;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.property-listing-card--missing-breakdown {
  border: none;
  background: transparent;
  padding: 0;
}

.property-listing-card--missing-breakdown .property-card {
  border-color: #f5c542;
  box-shadow: none;
  background: #fff;
}

.badge-missing-breakdown {
  background: #f59e0b;
  color: #1f2937;
}

.breakdown-quick-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: calc(100% - 24px);
  margin: 8px 12px 0;
  box-sizing: border-box;
  border: 1px solid #f5c542;
  border-radius: 10px;
  background: #fff8e8;
  color: #92400e;
  font-weight: 600;
  font-size: 12px;
  box-shadow: none;
}

.breakdown-quick-btn:hover {
  background: #fef3c7;
  color: #78350f;
  border-color: #f59e0b;
}

.property-card-link {
  display: block;
  width: 100%;
  min-width: 0;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.property-card-link:hover {
  transform: translateY(-3px);
}

.property-card-link:hover .property-card {
  box-shadow: none;
}

.property-card-link .view-more-btn {
  display: inline-flex;
  pointer-events: none;
}
.icons {
  gap: 2px;
}
.badge-off_plan {
  background: #e11d48;
  color: white;
}
.badge-offplan {
  background: #f5c542;
  color: #1c1730;
}

.badge-ready {
  background: #16a34a;
  color: white;
}

.badge-approval {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.35);
  background: var(--gradient-primary);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  line-height: 1.1;
}
.badge-occupancy_status {
  background: #ffffff !important;
  color: #1c1730 !important;
  border-color: #ece7f1 !important;
  text-shadow: none;
}

.status-badge {
  background: var(--gradient-primary) !important;
  color: #ffffff !important;
}

.property-listed-date {
  font-size: 11px;
  margin: 0 !important;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #8a8494;
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
