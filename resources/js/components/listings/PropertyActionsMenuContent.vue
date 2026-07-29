<template>
  <div class="property-actions-menu" :class="`property-actions-menu--${variant}`">
    <button
      v-if="canApproveListings && !property.approved && property.status !== 'converted' && property.status !== 'rented'"
      type="button"
      :class="itemClass('success')"
      @click="emit('approve-listing')"
    >
      <i class="ri-checkbox-circle-line"></i>
      <span>Approve Listing</span>
    </button>
      <button
          v-if="canViewHistory"
          type="button"
          :class="itemClass()"
          @click="emit('view-history')"
      >
          <i class="ri-history-line"></i>
          <span>View History</span>
      </button>
    <button
      v-if="canApproveListings && property.approved"
      type="button"
      :class="itemClass('warning')"
      @click="emit('reject-listing')"
    >
      <i class="ri-close-circle-line"></i>
      <span>Remove Approval / Reject</span>
    </button>

    <button v-if="canGenerateOffer" type="button" :class="itemClass()" @click="emit('create-offer')">
      <i class="ri-file-pdf-line"></i>
      <span>Create Offer</span>
    </button>

    <button v-if="canShowOffers" type="button" :class="itemClass()" @click="emit('view-offers')">
      <i class="ri-history-line"></i>
      <span>View Offer History</span>
    </button>

    <button v-if="canDeleteProperty" type="button" :class="itemClass()" @click="emit('delete-property')">
      <i class="ri-delete-bin-line"></i>
      <span>Delete Property</span>
    </button>

    <button v-if="canEditProperty" type="button" :class="itemClass()" @click="emit('edit-property')">
      <i class="ri-edit-line"></i>
      <span>Edit Property</span>
    </button>

    <button v-if="canEditProperty" type="button" :class="itemClass()" @click="emit('toggle-active')">
      <i class="ri-toggle-line" v-if="property.is_active"></i>
      <i class="ri-toggle-fill" v-else></i>
      <span>{{ property.is_active ? 'Set Inactive' : 'Set Active' }}</span>
    </button>

    <button
      v-if="canAssignAgent && property.status != 'converted' && property.status != 'rented'"
      type="button"
      :class="itemClass()"
      @click="emit('assign-agent')"
    >
      <i class="ri-user-shared-line"></i>
      <span>Assign to Agent</span>
    </button>

    <button
      v-if="canUsePropertyChat && property?.agent"
      type="button"
      :class="itemClass()"
      @click="emit('chat-agent')"
    >
      <i class="ri-chat-3-fill"></i>
      <span>Chat with Agent</span>
    </button>

    <button
      v-if="canMarkAsConverted && property.status !== 'converted' && property.listing_status === 'sale'"
      type="button"
      :class="itemClass('success')"
      @click="emit('mark-sold')"
    >
      <i class="ri-checkbox-circle-line"></i>
      <span>Mark as Sold Out</span>
    </button>

    <button
      v-if="canMarkAsConverted && property.status === 'converted' && property.listing_status === 'sale'"
      type="button"
      :class="itemClass('warning')"
      @click="emit('revert-sold')"
    >
      <i class="ri-arrow-go-back-line"></i>
      <span>Revert from Sold Out</span>
    </button>

    <button
      v-if="canMarkAsConverted && property.status !== 'rented' && property.listing_status === 'rent'"
      type="button"
      :class="itemClass('success')"
      @click="emit('mark-rented')"
    >
      <i class="ri-home-gear-line"></i>
      <span>Mark as Rented</span>
    </button>

    <button
      v-if="canMarkAsConverted && property.status === 'rented' && property.listing_status === 'rent'"
      type="button"
      :class="itemClass('warning')"
      @click="emit('revert-rented')"
    >
      <i class="ri-arrow-go-back-line"></i>
      <span>Revert from Rented</span>
    </button>

    <div v-if="!isPropertyOwner && property?.completion_status == 'Completed'" class="property-actions-menu__group">
      <div v-if="requestStatus?.viewing_status === 'approved'" class="property-actions-menu__status property-actions-menu__status--approved">
        <div>
          <i class="ri-checkbox-circle-line text-success"></i>
          <span>Viewing Approved</span>
        </div>
        <small v-if="requestStatus?.viewing_details" class="property-actions-menu__meta">
          {{ formatDate(requestStatus.viewing_details.date) }} at {{ formatTime(requestStatus.viewing_details.time) }}
        </small>
      </div>

      <div v-else-if="requestStatus?.viewing_status === 'in_progress'" class="property-actions-menu__status property-actions-menu__status--approved">
        <div class="property-actions-menu__status-row">
          <div>
            <div><i class="ri-checkbox-circle-line text-success"></i> <span>Viewing In Progress</span></div>
            <small v-if="requestStatus?.viewing_details" class="property-actions-menu__meta">
              {{ formatDate(requestStatus.viewing_details.date) }} at {{ formatTime(requestStatus.viewing_details.time) }}
            </small>
          </div>
          <button
            type="button"
            class="property-actions-menu__cancel"
            @click="emit('cancel-viewing')"
            :disabled="cancellingSpecificRequest"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>

      <div v-else-if="requestStatus?.viewing_status === 'pending'" class="property-actions-menu__status property-actions-menu__status--pending">
        <div class="property-actions-menu__status-row">
          <div>
            <div><i class="ri-time-line text-warning"></i> <span class="text-warning">Viewing Pending</span></div>
            <small v-if="requestStatus?.viewing_details" class="property-actions-menu__meta">
              {{ formatDate(requestStatus.viewing_details.date) }} {{ formatTime(requestStatus.viewing_details.time) }}
            </small>
          </div>
          
          <button
            type="button"
            class="property-actions-menu__cancel"
            @click="emit('cancel-request', 'viewing')"
            :disabled="cancellingRequest"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>

      <button
        v-else
        type="button"
        :class="itemClass()"
        @click="emit('request-viewing')"
        :disabled="loadingRequest || cancellingRequest"
      >
        <i class="ri-calendar-line"></i>
        <span>Request Viewing</span>
      </button>
    </div>

    <div v-if="!isPropertyOwner" class="property-actions-menu__group">
      <div v-if="requestStatus?.unit_number_status === 'approved'" class="property-actions-menu__status property-actions-menu__status--approved">
        <i class="ri-checkbox-circle-line text-success"></i>
        <span>Unit Number Approved</span>
      </div>

      <div v-else-if="requestStatus?.unit_number_status === 'pending'" class="property-actions-menu__status property-actions-menu__status--pending">
        <div class="property-actions-menu__status-row">
          <div><i class="ri-time-line text-warning"></i> <span class="text-warning">Unit Number Pending</span></div>
          <button
            type="button"
            class="property-actions-menu__cancel"
            @click="emit('cancel-request', 'unit_number')"
            :disabled="cancellingRequest"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>

      <button
        v-else
        type="button"
        :class="itemClass()"
        @click="emit('request-unit-number')"
        :disabled="loadingRequest || cancellingRequest"
      >
        <i class="ri-home-4-line"></i>
        <span>{{ loadingRequest ? 'Sending...' : 'Request Unit Number' }}</span>
      </button>
    </div>

    <div v-if="!isPropertyOwner" class="property-actions-menu__group">
      <div v-if="requestStatus?.owner_info_status === 'approved'" class="property-actions-menu__status property-actions-menu__status--approved">
        <i class="ri-checkbox-circle-line text-success"></i>
        <span>Owner Info Approved</span>
      </div>

      <div v-else-if="requestStatus?.owner_info_status === 'pending'" class="property-actions-menu__status property-actions-menu__status--pending">
        <div class="property-actions-menu__status-row">
          <div><i class="ri-time-line text-warning"></i> <span class="text-warning">Owner Info Pending</span></div>
          <button
            type="button"
            class="property-actions-menu__cancel"
            @click="emit('cancel-request', 'owner_data')"
            :disabled="cancellingRequest"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>
      </div>

      <button
        v-else
        type="button"
        :class="itemClass()"
        @click="emit('request-owner-info')"
        :disabled="loadingRequest || cancellingRequest"
      >
        <i class="ri-user-search-line"></i>
        <span>{{ loadingRequest ? 'Sending...' : 'Request Owner Info' }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>

const props = defineProps({
  property: { type: Object, required: true },
  requestStatus: { type: Object, default: null },
  canApproveListings: { type: Boolean, default: false },
  canGenerateOffer: { type: Boolean, default: false },
  canShowOffers: { type: Boolean, default: false },
  canDeleteProperty: { type: Boolean, default: false },
  canEditProperty: { type: Boolean, default: false },
  canAssignAgent: { type: Boolean, default: false },
  canUsePropertyChat: { type: Boolean, default: false },
  canMarkAsConverted: { type: Boolean, default: false },
  isPropertyOwner: { type: Boolean, default: false },
  loadingRequest: { type: Boolean, default: false },
  cancellingRequest: { type: Boolean, default: false },
  cancellingSpecificRequest: { type: Boolean, default: false },
  formatDate: { type: Function, required: true },
  formatTime: { type: Function, required: true },
  variant: { type: String, default: 'dropdown' },
   canViewHistory: { type: Boolean, default: false },
})

const itemClass = (modifier) => {
  const base = 'property-actions-menu__item'
  if (props.variant === 'dropdown') {
    return modifier ? [base, `${base}--${modifier}`] : base
  }
  return modifier
    ? [base, `${base}--${modifier}`]
    : base
}

const emit = defineEmits([
  'approve-listing',
  'reject-listing',
  'create-offer',
  'view-offers',
  'delete-property',
  'edit-property',
  'toggle-active',
  'assign-agent',
  'chat-agent',
  'mark-sold',
  'revert-sold',
  'mark-rented',
  'revert-rented',
  'cancel-viewing',
  'request-viewing',
  'cancel-request',
  'request-unit-number',
  'request-owner-info',
  'view-history',
])
</script>

<style scoped>
.property-actions-menu {
  font-family: Montserrat, sans-serif;
}

.property-actions-menu--dropdown {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
}

.property-actions-menu--dropdown .property-actions-menu__item,
.property-actions-menu--sheet .property-actions-menu__item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  min-height: 34px;
  line-height: 1.3;
  background: #fff;
  border: none;
  border-bottom: 1px solid #f0f0f0;
  text-align: left;
  font-family: Montserrat, sans-serif;
  font-size: 12px;
  font-weight: 500;
  color: #222;
  cursor: pointer;
  transition: background-color 0.15s ease;
  border-radius: 0;
  box-sizing: border-box;
}

.property-actions-menu--dropdown > .property-actions-menu__item:last-of-type,
.property-actions-menu--dropdown .property-actions-menu__group:last-child .property-actions-menu__item:last-child {
  border-bottom: none;
}

.property-actions-menu--dropdown .property-actions-menu__item:hover:not(:disabled),
.property-actions-menu--sheet .property-actions-menu__item:hover:not(:disabled) {
  background: #f7f2fa;
  color: #111;
}

.property-actions-menu--dropdown .property-actions-menu__item:disabled,
.property-actions-menu--sheet .property-actions-menu__item:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.property-actions-menu--dropdown .property-actions-menu__item i,
.property-actions-menu--sheet .property-actions-menu__item i {
  font-size: 15px;
  width: 16px;
  min-width: 16px;
  text-align: center;
  flex-shrink: 0;
  line-height: 1;
  color: #222;
  background: transparent;
}

.property-actions-menu--dropdown .property-actions-menu__item span,
.property-actions-menu--sheet .property-actions-menu__item span {
  flex: 1;
  min-width: 0;
}

.property-actions-menu--dropdown .property-actions-menu__group {
  padding: 0;
  margin: 0;
  border-top: 1px solid #f0f0f0;
}

.property-actions-menu--dropdown .property-actions-menu__group:first-child {
  border-top: none;
}

.property-actions-menu--dropdown .property-actions-menu__status,
.property-actions-menu--sheet .property-actions-menu__status {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 14px 20px;
  font-size: 14px;
  line-height: 1.35;
  border-bottom: 1px solid #f0f0f0;
}

.property-actions-menu--dropdown .property-actions-menu__status--approved,
.property-actions-menu--sheet .property-actions-menu__status--approved {
  background: #f3faf4;
  color: #2e7d32;
}

.property-actions-menu--dropdown .property-actions-menu__status--pending,
.property-actions-menu--sheet .property-actions-menu__status--pending {
  background: #fffaf0;
  color: #b45309;
}

.property-actions-menu--dropdown .property-actions-menu__status-row,
.property-actions-menu--sheet .property-actions-menu__status-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  width: 100%;
  gap: 12px;
}

.property-actions-menu--dropdown .property-actions-menu__meta,
.property-actions-menu--sheet .property-actions-menu__meta {
  font-size: 12px;
  opacity: 0.75;
  margin-top: 2px;
}

.property-actions-menu--dropdown .property-actions-menu__cancel,
.property-actions-menu--sheet .property-actions-menu__cancel {
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 4px;
  width: 22px;
  height: 22px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 12px;
}

.property-actions-menu--dropdown .property-actions-menu__cancel:hover:not(:disabled),
.property-actions-menu--sheet .property-actions-menu__cancel:hover:not(:disabled) {
  opacity: 0.9;
}

.property-actions-menu--dropdown .property-actions-menu__cancel:disabled,
.property-actions-menu--sheet .property-actions-menu__cancel:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.property-actions-menu--sheet {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0;
}

@media (max-width: 768px) {
  .property-actions-menu--sheet .property-actions-menu__item {
    min-height: 48px;
    padding: 12px 8px;
    font-size: 18px;
    font-weight: 500;
    gap: 10px;
  }

  .property-actions-menu--sheet .property-actions-menu__item i {
    width: 18px;
    min-width: 18px;
    font-size: 17px;
  }
}
</style>
