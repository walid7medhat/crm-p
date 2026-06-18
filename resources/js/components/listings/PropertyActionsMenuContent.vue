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
})

const itemClass = (modifier) => {
  if (props.variant === 'dropdown') {
    return modifier ? ['dropdown-item', modifier] : 'dropdown-item'
  }
  return modifier
    ? ['property-actions-menu__item', `property-actions-menu__item--${modifier}`]
    : 'property-actions-menu__item'
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
])
</script>
