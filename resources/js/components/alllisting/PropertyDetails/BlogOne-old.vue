<template>
  <div class="dashboard-main-body">
    <div class="row gy-4">
      <!-- Main Content -->
      <div class="col-lg-8">
        <div class="card p-0 radius-12 overflow-hidden">
          <div class="card-body p-0">
            <!-- Carousel Section -->
            <div class="property-gallery" v-if="property && property.gallery_images">
              <div class="gallery-container">
                <div class="main-image-section" @click="openLightbox(0)">
                  <img :src="currentMainImage || getFirstGalleryImage()" alt="Property main image" class="main-image" />
                  <div class="image-overlay">
                    <i class="ri-fullscreen-line"></i>
                    <span>View All Photos</span>
                  </div>
                </div>
                <div class="side-images">
                  <div 
                    v-for="(image, index) in getGalleryThumbnails()" 
                    :key="index" 
                    class="side-image"
                    :class="{ active: currentMainImage === getImageUrl(image.image_url) }"
                    @click="setMainImage(getImageUrl(image.image_url))"
                  >
                    <img :src="getImageUrl(image.image_url)" alt="Property thumbnail" />
                  </div>
                  <div class="side-image view-all" @click="openLightbox(0)" v-if="property.gallery_images && property.gallery_images.length > 3">
                    <div class="view-all-content">
                      <i class="ri-gallery-view-2"></i>
                      <span>View All</span>
                      <small>{{ property.gallery_images?.length || 0 }} photos</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Property Details Section -->
            <div class="property-content p-32" v-if="property">
              <!-- Action Buttons for authorized users -->
              <div class="property-actions mb-24" v-if="canEditOrDelete">
                <button v-if="canEditProperty" class="btn-action btn-edit" @click="editProperty">
                  <i class="ri-edit-line"></i>
                  Edit Property
                </button>
                <button v-if="canDeleteProperty" class="btn-action btn-delete" @click="confirmDeleteProperty">
                  <i class="ri-delete-bin-line"></i>
                  Delete Property
                </button>
              </div>

              <h2 class="property-title">{{ property.title || 'Property Title' }}</h2>

              <div class="location-section mb-24">
                <div class="location-breadcrumb">
                  <span class="location-item">{{ property.area?.name || 'Area' }}</span>
                  <i class="ri-arrow-right-s-line"></i>
                  <span class="location-item">{{ property.developer?.name || 'Developer' }}</span>
                  <i class="ri-arrow-right-s-line"></i>
                  <span class="location-item active">{{ property.property_type?.name || 'Property Type' }}</span>
                </div>
                <div class="property-categories">
                  <span class="category-tag sale-rent">FOR {{ property.sale_or_rent || 'SALE' }}</span>
                  <span class="category-tag residence-type">{{ property.completion_status || 'COMPLETED' }}</span>
                  <span class="category-tag type-label">{{ property.property_type?.name || 'PROPERTY' }}</span>
                </div>
              </div>
                                            
              <!-- Property Price and Basic Info -->
              <div class="property-main-info mb-24">
                <div class="price-main">
                  <h3 class="property-price">AED {{ formatPrice(property.price) }}</h3>
                </div>

                <div class="specs-grid-main">
                  <div class="spec-main-item">
                    <div class="spec-icon">
                      <i class="ri-home-5-line"></i>
                    </div>
                    <div class="spec-main-info">
                      <span class="spec-main-value">{{ property.size_sqft || 'N/A' }}</span>
                      <span class="spec-main-label">Sq Ft</span>
                    </div>
                  </div>
                  
                  <div class="spec-main-item">
                    <div class="spec-icon">
                      <i class="ri-hotel-bed-line"></i>
                    </div>
                    <div class="spec-main-info">
                      <span class="spec-main-value">{{ property.number_of_bedrooms || '0' }}</span>
                      <span class="spec-main-label">Bedrooms</span>
                    </div>
                  </div>
                  
                  <div class="spec-main-item">
                    <div class="spec-icon">
                      <i class="ri-contrast-drop-line"></i>
                    </div>
                    <div class="spec-main-info">
                      <span class="spec-main-value">{{ property.number_of_bathrooms || '0' }}</span>
                      <span class="spec-main-label">Bathrooms</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Basic Information Section -->
              <div class="detailed-info-section mb-24">
                <!-- Basic Information Section -->
                <div class="info-section mb-32">
                  <h3 class="section-title mb-20"> Property Details</h3>
                  <div class="info-grid">
                    <div class="info-item">
                      <span class="info-label">Sale/Rent</span>
                      <span class="info-value">{{ property.sale_or_rent || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Completion Status</span>
                      <span class="info-value">{{ property.completion_status || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Developer</span>
                      <span class="info-value">{{ property.developer?.name || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Area</span>
                      <span class="info-value">{{ property.area?.name || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Bedrooms</span>
                      <span class="info-value">{{ property.number_of_bedrooms || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Bathrooms</span>
                      <span class="info-value">{{ property.number_of_bathrooms || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">View</span>
                      <span class="info-value">{{ property.unit_view || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Layout Type</span>
                      <span class="info-value">{{ property.layout_type || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Furnished Status</span>
                      <span class="info-value">{{ property.furnished_status || "Not specified" }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Ownership Type</span>
                      <span class="info-value">{{ property.ownership_type || "Not specified" }}</span>
                    </div>
                  </div>
                </div>

                <!-- Mortgage Information Section -->
                <div class="info-section mb-32" v-if="hasMortgageInfo">
                  <h3 class="section-title mb-20">Mortgage Information</h3>
                  <div class="info-grid">
                    <div class="info-item">
                      <span class="info-label">Mortgage Status</span>
                      <span class="info-value">{{ property.mortgage_status || "Not specified" }}</span>
                    </div>
                    <div class="info-item" v-if="property.mortgage_amount">
                      <span class="info-label">Mortgage Amount</span>
                      <span class="info-value">AED {{ formatPrice(property.mortgage_amount) }}</span>
                    </div>
                    <div class="info-item">
                      <span class="info-label">Occupancy Status</span>
                      <span class="info-value">{{ property.occupancy_status || "Not specified" }}</span>
                    </div>
                    <div class="info-item" v-if="property.rent_amount">
                      <span class="info-label">Rent Amount</span>
                      <span class="info-value">AED {{ formatPrice(property.rent_amount) }}</span>
                    </div>
                    <div class="info-item" v-if="property.rent_expiry_date">
                      <span class="info-label">Rent Expiry Date</span>
                      <span class="info-value">{{ formatDate(property.rent_expiry_date) }}</span>
                    </div>
                    <div class="info-item full-width" v-if="property.mortgage_comment">
                      <span class="info-label">Mortgage Comment</span>
                      <span class="info-value">{{ property.mortgage_comment }}</span>
                    </div>
                  </div>
                </div>

                <!-- Description Section -->
                <div class="info-section" v-if="property.comment">
                  <h3 class="section-title mb-20">Notes</h3>
                  <div class="description-content">
                    <p class="description-text">{{ property.comment }}</p>
                  </div>
                </div>

           
              </div>
              <div class="comment-container">
                   <!-- Comments Section -->
                <div class="comments-section " v-if="property">
                  <div class="card">
                    <div class="card-body">
                      <h3 class="section-title mb-4">
                        <i class="ri-chat-3-line me-2"></i>
                        Comments 
                      </h3>

                      <!-- Add Comment Form -->
                      <div class="add-comment-form mb-4" v-if="isAuthenticated">
                        <div class="form-header">
                          <h5>Add Your Comment</h5>
                        </div>
                        <div class="form-body">
                          <!-- Comment Input -->
                          <div class="comment-input mb-3">
                            <textarea 
                              v-model="newComment.text" 
                              placeholder="Share your thoughts about this property..."
                              class="form-control"
                              rows="4"
                              :maxlength="1000"
                            ></textarea>
                            <div class="char-counter">
                              {{ newComment.text.length }}/1000
                            </div>
                          </div>

                          <div class="form-actions">
                            <button 
                              class="btn btn-primary"
                              @click="submitComment"
                              :disabled="!newComment.text.trim() || submittingComment"
                            >
                              <i class="ri-send-plane-line me-2"></i>
                              {{ submittingComment ? 'Submitting...' : 'Submit Comment' }}
                            </button>
                          </div>
                        </div>
                      </div>

                      <!-- Login Prompt -->
                      <div class="login-prompt mb-4" v-else>
                        <div class="alert alert-info">
                          <i class="ri-information-line me-2"></i>
                          Please <a href="/login" class="alert-link">login</a> to add comments and reviews.
                        </div>
                      </div>

                     
                    </div>
                    
                  </div>
                  
                </div>
                
               <!-- Comments List -->
                      <div class="comments-list">
                        <div v-if="loadingComments" class="text-center py-4">
                          <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading comments...</span>
                          </div>
                        </div>

                        <div v-else-if="comments.length === 0" class="no-comments text-center py-5">
                          <i class="ri-chat-3-line no-comments-icon"></i>
                          <h5>No Comments Yet</h5>
                          <p class="text-muted">Be the first to share your thoughts about this property!</p>
                        </div>

                        <div v-else class="comments-container">
                          <div 
                            v-for="comment in comments" 
                            :key="comment.id" 
                            class="comment-item"
                            :class="{ 'has-replies': comment.replies && comment.replies.length > 0 }"
                          >
                            <div class="comment-main">
                              <!-- Comment Header -->
                              <div class="comment-header">
                                <div class="user-info">
                                  <img 
                                    :src="comment.user.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                                    :alt="comment.user.name"
                                    class="user-avatar"
                                    @error="handleAvatarError"
                                  />
                                  <div class="user-details">
                                    <h6 class="user-name">{{ comment.user.name }}</h6>
                                    <span class="comment-date">{{ formatCommentDate(comment.created_at) }}</span>
                                  </div>
                                </div>
                              </div>

                              <!-- Comment Body -->
                              <div class="comment-body">
                                <p class="comment-text">{{ comment.comment }}</p>
                              </div>

                              <!-- Comment Actions -->
                              <div class="comment-actions">
                                <button 
                                  class="btn-action btn-reply"
                                  @click="toggleReply(comment.id)"
                                  v-if="isAuthenticated"
                                >
                                  <i class="ri-reply-line"></i>
                                  Reply
                                </button>
                                
                                <button 
                                  class="btn-action btn-edit"
                                  @click="editComment(comment)"
                                  v-if="canEditComment(comment)"
                                >
                                  <i class="ri-edit-line"></i>
                                  Edit
                                </button>
                                
                                <button 
                                  class="btn-action btn-delete"
                                  @click="deleteComment(comment.id)"
                                  v-if="canEditComment(comment)"
                                >
                                  <i class="ri-delete-bin-line"></i>
                                  Delete
                                </button>
                              </div>

                              <!-- Reply Form -->
                              <div class="reply-form" v-if="activeReply === comment.id">
                                <textarea 
                                  v-model="replyText" 
                                  placeholder="Write your reply..."
                                  class="form-control"
                                  rows="2"
                                ></textarea>
                                <div class="reply-actions">
                                  <button 
                                    class="btn btn-sm btn-primary"
                                    @click="submitReply(comment.id)"
                                    :disabled="!replyText.trim()"
                                  >
                                    Submit Reply
                                  </button>
                                  <button 
                                    class="btn btn-sm btn-secondary"
                                    @click="cancelReply"
                                  >
                                    Cancel
                                  </button>
                                </div>
                              </div>
                            </div>

                            <!-- Replies -->
                            <div class="replies-container" v-if="comment.replies && comment.replies.length > 0">
                              <div 
                                v-for="reply in comment.replies" 
                                :key="reply.id" 
                                class="reply-item"
                              >
                                <div class="reply-main">
                                  <div class="reply-header">
                                    <div class="user-info">
                                      <img 
                                        :src="reply.user.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                                        :alt="reply.user.name"
                                        class="user-avatar small"
                                        @error="handleAvatarError"
                                      />
                                      <div class="user-details">
                                        <h6 class="user-name">{{ reply.user.name }}</h6>
                                        <span class="comment-date">{{ formatCommentDate(reply.created_at) }}</span>
                                      </div>
                                    </div>
                                  </div>
                                  
                                  <div class="reply-body">
                                    <p class="comment-text">{{ reply.comment }}</p>
                                  </div>

                                  <div class="comment-actions">
                                    <button 
                                      class="btn-action btn-edit"
                                      @click="editComment(reply)"
                                      v-if="canEditComment(reply)"
                                    >
                                      <i class="ri-edit-line"></i>
                                      Edit
                                    </button>
                                    
                                    <button 
                                      class="btn-action btn-delete"
                                      @click="deleteComment(reply.id)"
                                      v-if="canEditComment(reply)"
                                    >
                                      <i class="ri-delete-bin-line"></i>
                                      Delete
                                    </button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
              </div>
            </div>

            <!-- Loading State -->
            <div v-else-if="loading" class="property-content p-32">
              <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading property details...</p>
              </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="property-content p-32">
              <div class="text-center py-5">
                <i class="ri-error-warning-line text-danger mb-3" style="font-size: 48px;"></i>
                <h5>Failed to Load Property</h5>
                <p class="text-muted">{{ error }}</p>
                <button class="btn btn-primary" @click="fetchProperty">
                  <i class="ri-refresh-line me-2"></i>
                  Try Again
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <div class="agent-sidebar-card">
          <!-- Agent Profile Section - Updated with Link -->
          <div class="agent-profile" v-if="property && property.agent">
            <img 
              :src="property.agent.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
              alt="Agent" 
              class="agent-sidebar-avatar" 
            />
            <div class="agent-sidebar-info">
              <h5 class="agent-sidebar-name">{{ property.agent.name || 'Agent Name' }}</h5>
              <p class="agent-sidebar-company">{{ property.agent.company || 'Real Estate Company' }}</p>
            </div>
            <button 
              class="btn-show-agent-details" 
              @click="goToAgentDetails"
            >
              <i class="ri-user-line me-1"></i>
              Show Agent Details
            </button>
          </div>
   <div class="sidebar-section" v-if="!isPropertyOwner">
              <h6 class="sidebar-title">Request Access
                <span v-if="loadingRequest" class="loading-spinner-small"></span>
              </h6>
              
              <div class="request-actions-grid">
                <!-- Unit Number Request -->
                <div class="request-action-item">
                  <!-- If unit number is approved -->
                  <div v-if="requestStatus?.unit_number_status === 'approved'" class="approved-info">
                    <div class="info-display">
                      <i class="ri-home-4-line"></i>
                      <span class="info-value">{{ getUnitNumber() }}</span>
                    </div>
                  </div>

                  <!-- If has pending request -->
                  <div v-else-if="requestStatus?.unit_number_status === 'pending'" class="pending-info">
                    <div class="pending-status">
                      <i class="ri-time-line"></i>
                      <span class="status-text">Pending</span>
                    </div>
                    <button class="btn-cancel-small" @click="cancelRequest('unit_number')">
                      <i class="ri-close-line"></i>
                    </button>
                  </div>

                  <!-- If can request -->
                  <div v-else-if="canRequestUnitNumber" class="request-action">
                    <button 
                      class="btn-request-compact" 
                      @click="requestUnitNumber" 
                      :disabled="loadingRequest"
                    >
                      <i class="ri-home-4-line"></i>
                      <span v-if="loadingRequest">Sending...</span>
                      <span v-else>Unit Number</span>
                    </button>
                  </div>

                  <!-- Default state -->
                  <div v-else class="cannot-request-info">
                    <i class="ri-information-line"></i>
                    <span>Cannot Request</span>
                  </div>
                </div>

                <!-- Owner Information Request -->
                <div class="request-action-item">
                  <!-- If owner info is approved -->
                  <div v-if="requestStatus?.owner_info_status === 'approved' && getApprovedOwnerData()" class="approved-info">
                    <div class="info-display" @click="openOwnerDetailsModal" style="cursor: pointer;">
                      <i class="ri-user-line"></i>
                      <span class="info-value">view Owner Info</span>
                    </div>
                  </div>

                  <!-- If has pending request -->
                  <div v-else-if="requestStatus?.owner_info_status === 'pending'" class="pending-info">
                    <div class="pending-status">
                      <i class="ri-time-line"></i>
                      <span class="status-text">Pending</span>
                    </div>
                    <button class="btn-cancel-small" @click="cancelRequest('owner_data')">
                      <i class="ri-close-line"></i>
                    </button>
                  </div>

                  <!-- If can request -->
                  <div v-else-if="canRequestOwnerInfo" class="request-action">
                    <button 
                      class="btn-request-compact" 
                      @click="requestOwnerInfo" 
                      :disabled="loadingRequest"
                    >
                      <i class="ri-user-search-line"></i>
                      <span v-if="loadingRequest">Sending...</span>
                      <span v-else>Owner Info</span>
                    </button>
                  </div>

                  <!-- Default state -->
                  <div v-else class="cannot-request-info">
                    <i class="ri-information-line"></i>
                    <span>Cannot Request</span>
                  </div>
                </div>
              </div>
            </div>
          <!-- Unit Number Section -->
          <div class="sidebar-section">
            <h6 class="sidebar-title">Unit Number 
              <span v-if="loadingRequest" class="loading-spinner-small"></span>
            </h6>
            
            <!-- If property owner - show unit number directly -->
            <div v-if="isPropertyOwner" class="owner-unit-info">
              <div class="unit-display">
                <i class="ri-home-4-line"></i>
                <span class="unit-value">{{ property.unit_number || 'Not Set' }}</span>
              </div>
              <p class="unit-note">You have full access as the property owner</p>
            </div>

            <!-- If not owner and unit number is approved -->
            <div v-else-if="requestStatus?.unit_number_status === 'approved'" class="approved-unit-info">
              <div class="unit-display">
                <i class="ri-home-4-line"></i>
                <span class="unit-value">{{ getUnitNumber() }}</span>
              </div>
              <div class="approval-info">
                <i class="ri-shield-check-line"></i>
                <span>Approved</span>
              </div>
            </div>

            <!-- If not owner and has pending request -->
            <div v-else-if="requestStatus?.unit_number_status === 'pending'" class="pending-request-info">
              <div class="request-status">
                <i class="ri-time-line"></i>
                <span class="status-text">Pending Request</span>
              </div>
              <div class="request-details">
                <small class="request-date">Requested: {{ formatDate(requestStatus.unit_number_requested_at) }}</small>
                <button class="btn-cancel-small" @click="cancelRequest('unit_number')">
                  <i class="ri-close-line"></i>
                </button>
              </div>
            </div>

            <!-- If not owner and can request -->
            <div v-else-if="canRequestUnitNumber" class="request-section">
              <button 
                class="btn-sidebar btn-request-unit" 
                @click="requestUnitNumber" 
                :disabled="loadingRequest"
              >
                <i class="ri-home-4-line"></i>
                <span v-if="loadingRequest">Sending...</span>
                <span v-else>Request Unit Number</span>
              </button>
            </div>

            <!-- Default state - can't request -->
            <div v-else class="cannot-request-info">
              <div class="cannot-request-status">
                <i class="ri-information-line"></i>
                <span class="status-text">Cannot Request</span>
              </div>
            </div>
          </div>

          <!-- Owner Information Section -->
          <div class="sidebar-section">
            <h6 class="sidebar-title">Owner Information
              <span v-if="loadingRequest" class="loading-spinner-small"></span>
            </h6>
            
            <!-- If property owner - show owner info directly -->
            <div v-if="isPropertyOwner && property.owner" class="owner-info-direct">
              <div class="info-display">
                <div class="info-item-sidebar" v-if="property.owner.full_name">
                  <i class="ri-user-line"></i>
                  <span>{{ property.owner.full_name }}</span>
                </div>
                <div class="info-item-sidebar" v-if="property.owner.phone_number">
                  <i class="ri-phone-line"></i>
                  <span>{{ property.owner.phone_number }}</span>
                </div>
                <div class="info-item-sidebar" v-if="property.owner.email">
                  <i class="ri-mail-line"></i>
                  <span>{{ property.owner.email }}</span>
                </div>
              </div>
              <button class="btn-show-owner-modal" @click="openOwnerDetailsModal">
                <i class="ri-eye-line"></i>
                View Full Details
              </button>
            </div>

            <!-- If not owner and owner info is approved -->
            <div v-else-if="requestStatus?.owner_info_status === 'approved' && getApprovedOwnerData()" class="approved-owner-info">
              <div class="info-display">
                <div class="info-item-sidebar" v-if="getApprovedOwnerData().full_name">
                  <i class="ri-user-line"></i>
                  <span>{{ getApprovedOwnerData().full_name }}</span>
                </div>
                <div class="info-item-sidebar" v-if="getApprovedOwnerData().phone_number">
                  <i class="ri-phone-line"></i>
                  <span>{{ getApprovedOwnerData().phone_number }}</span>
                </div>
              </div>
              <div class="approval-info">
                <i class="ri-shield-check-line"></i>
                <span>Approved</span>
              </div>
              <button class="btn-show-owner-modal" @click="openOwnerDetailsModal">
                <i class="ri-eye-line"></i>
                View Full Details
              </button>
            </div>

            <!-- If not owner and has pending request -->
            <div v-else-if="requestStatus?.owner_info_status === 'pending'" class="pending-request-info">
              <div class="request-status">
                <i class="ri-time-line"></i>
                <span class="status-text">Pending Request</span>
              </div>
              <div class="request-details">
                <small class="request-date">Requested: {{ formatDate(requestStatus.owner_info_requested_at) }}</small>
                <button class="btn-cancel-small" @click="cancelRequest('owner_data')">
                  <i class="ri-close-line"></i>
                </button>
              </div>
            </div>

            <!-- If not owner and can request -->
            <div v-else-if="canRequestOwnerInfo" class="request-section">
              <button 
                class="btn-sidebar btn-request-owner" 
                @click="requestOwnerInfo" 
                :disabled="loadingRequest"
              >
                <i class="ri-user-search-line"></i>
                <span v-if="loadingRequest">Sending...</span>
                <span v-else>Request Owner Info</span>
              </button>
            </div>

            <!-- Default state - can't request -->
            <div v-else class="cannot-request-info">
              <div class="cannot-request-status">
                <i class="ri-information-line"></i>
                <span class="status-text">Cannot Request</span>
              </div>
            </div>
          </div>

          <!-- Refresh Button -->
          <!-- <div class="sidebar-section">
            <button 
              class="btn-sidebar btn-refresh" 
              @click="refreshRequests"
              :disabled="loadingRequest"
            >
              <i class="ri-refresh-line"></i>
              {{ loadingRequest ? 'Updating...' : 'Refresh Status' }}
            </button>
          </div> -->
        </div>
      </div>
    </div>

    <!-- Owner Details Modal -->
    <div v-if="showOwnerDetailsModal" class="modal-overlay" @click="showOwnerDetailsModal = false">
      <div class="modal-content owner-details-modal" @click.stop>
        <div class="modal-header">
          <h4>
            <i class="ri-user-line me-2"></i>
            Owner Information
          </h4>
          <button class="modal-close" @click="showOwnerDetailsModal = false">
            <i class="ri-close-line"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Profile Section -->
          <div class="owner-profile-section mb-4" v-if="getOwnerDataForModal()">
            <div class="owner-avatar-container">
              <img 
                :src="getOwnerDataForModal()?.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
                alt="Owner Avatar" 
                class="owner-avatar"
                @error="handleAvatarError"
              />
              <div class="owner-verified-badge">
                <i class="ri-shield-check-line"></i>
              </div>
            </div>
            <div class="owner-profile-info">
              <h5 class="owner-name">{{ getOwnerDataForModal()?.full_name || 'N/A' }}</h5>
              <p class="owner-email">{{ getOwnerDataForModal()?.email || 'N/A' }}</p>
              <div class="owner-badges">
                <span class="badge badge-primary">{{ getOwnerDataForModal()?.nationality || 'N/A' }}</span>
                <span v-if="getOwnerDataForModal()?.residency_status" 
                      class="badge" 
                      :class="getOwnerDataForModal()?.residency_status === 'resident' ? 'badge-success' : 'badge-warning'">
                  {{ getOwnerDataForModal()?.residency_status === 'resident' ? 'Resident' : 'Non-Resident' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Contact Information -->
          <div class="modal-info-section mb-4" v-if="getOwnerDataForModal()">
            <h6 class="modal-section-title">
              <i class="ri-contacts-line me-2"></i>
              Contact Information
            </h6>
            <div class="modal-info-grid">
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-phone-line"></i>
                  Phone Number
                </div>
                <div class="modal-info-value">
                  {{ getOwnerDataForModal()?.phone_number || 'N/A' }}
                  <button v-if="getOwnerDataForModal()?.phone_number" 
                          class="btn-action-small btn-call" 
                          @click="callOwner(getOwnerDataForModal()?.phone_number)">
                    <i class="ri-phone-fill"></i>
                  </button>
                </div>
              </div>
              
              <div class="modal-info-item" v-if="getOwnerDataForModal()?.whatsapp_number">
                <div class="modal-info-label">
                  <i class="ri-whatsapp-line"></i>
                  WhatsApp
                </div>
                <div class="modal-info-value">
                  {{ getOwnerDataForModal()?.whatsapp_number }}
                  <button class="btn-action-small btn-whatsapp" 
                          @click="whatsappOwner(getOwnerDataForModal()?.whatsapp_number)">
                    <i class="ri-whatsapp-fill"></i>
                  </button>
                </div>
              </div>
              
              <div class="modal-info-item" v-if="getOwnerDataForModal()?.second_phone_number">
                <div class="modal-info-label">
                  <i class="ri-phone-line"></i>
                  Second Phone
                </div>
                <div class="modal-info-value">
                  {{ getOwnerDataForModal()?.second_phone_number }}
                  <button class="btn-action-small btn-call" 
                          @click="callOwner(getOwnerDataForModal()?.second_phone_number)">
                    <i class="ri-phone-fill"></i>
                  </button>
                </div>
              </div>
              
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-mail-line"></i>
                  Email Address
                </div>
                <div class="modal-info-value">
                  {{ getOwnerDataForModal()?.email || 'N/A' }}
                  <button v-if="getOwnerDataForModal()?.email" 
                          class="btn-action-small btn-email" 
                          @click="emailOwner(getOwnerDataForModal()?.email)">
                    <i class="ri-mail-fill"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Personal Information -->
          <div class="modal-info-section mb-4" v-if="getOwnerDataForModal()">
            <h6 class="modal-section-title">
              <i class="ri-user-settings-line me-2"></i>
              Personal Information
            </h6>
            <div class="modal-info-grid">
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-user-line"></i>
                  Salutation
                </div>
                <div class="modal-info-value">
                  <span class="badge badge-light">{{ getOwnerDataForModal()?.salutation || 'N/A' }}</span>
                </div>
              </div>
              
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-profile-line"></i>
                  First Name
                </div>
                <div class="modal-info-value">{{ getOwnerDataForModal()?.first_name || 'N/A' }}</div>
              </div>
              
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-profile-line"></i>
                  Last Name
                </div>
                <div class="modal-info-value">{{ getOwnerDataForModal()?.last_name || 'N/A' }}</div>
              </div>
              
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-flag-line"></i>
                  Nationality
                </div>
                <div class="modal-info-value">
                  <span class="badge badge-info">{{ getOwnerDataForModal()?.nationality || 'N/A' }}</span>
                </div>
              </div>
              
              <div class="modal-info-item">
                <div class="modal-info-label">
                  <i class="ri-map-pin-line"></i>
                  {{ getLocationLabel() }}
                </div>
                <div class="modal-info-value">{{ getOwnerDataForModal()?.location_name || getOwnerDataForModal()?.location_id || 'N/A' }}</div>
              </div>
            </div>
          </div>

          <!-- Documents Section -->
          <div class="modal-info-section" v-if="hasOwnerDocuments">
            <h6 class="modal-section-title">
              <i class="ri-folder-line me-2"></i>
              Documents
            </h6>
            <div class="documents-grid">
              <div class="document-item" v-if="getOwnerDataForModal()?.id_front_path || getOwnerDataForModal()?.id_front_url">
                <div class="document-icon">
                  <i class="ri-id-card-line"></i>
                </div>
                <div class="document-info">
                  <span class="document-name">ID Front</span>
                  <button class="btn-view-document" 
                          @click="viewDocument(getOwnerDataForModal()?.id_front_path || getOwnerDataForModal()?.id_front_url)">
                    View
                  </button>
                </div>
              </div>
              
              <div class="document-item" v-if="getOwnerDataForModal()?.id_back_path || getOwnerDataForModal()?.id_back_url">
                <div class="document-icon">
                  <i class="ri-id-card-line"></i>
                </div>
                <div class="document-info">
                  <span class="document-name">ID Back</span>
                  <button class="btn-view-document" 
                          @click="viewDocument(getOwnerDataForModal()?.id_back_path || getOwnerDataForModal()?.id_back_url)">
                    View
                  </button>
                </div>
              </div>
              
              <div class="document-item" v-if="getOwnerDataForModal()?.passport_copy_path || getOwnerDataForModal()?.passport_copy_url">
                <div class="document-icon">
                  <i class="ri-passport-line"></i>
                </div>
                <div class="document-info">
                  <span class="document-name">Passport Copy</span>
                  <button class="btn-view-document" 
                          @click="viewDocument(getOwnerDataForModal()?.passport_copy_path || getOwnerDataForModal()?.passport_copy_url)">
                    View
                  </button>
                </div>
              </div>
              
              <div class="document-item" v-if="getOwnerDataForModal()?.visa_copy_path || getOwnerDataForModal()?.visa_copy_url">
                <div class="document-icon">
                  <i class="ri-file-text-line"></i>
                </div>
                <div class="document-info">
                  <span class="document-name">Visa Copy</span>
                  <button class="btn-view-document" 
                          @click="viewDocument(getOwnerDataForModal()?.visa_copy_path || getOwnerDataForModal()?.visa_copy_url)">
                    View
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- No Documents Message -->
          <div v-else class="no-documents text-center py-4">
            <i class="ri-folder-open-line no-documents-icon"></i>
            <p class="no-documents-text">No documents available</p>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-modal btn-modal-secondary" @click="showOwnerDetailsModal = false">
            Close
          </button>
          <button class="btn-modal btn-modal-primary" @click="copyOwnerModalInfo">
            <i class="ri-file-copy-line me-2"></i>
            Copy All Information
          </button>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="showLightbox && property && property.gallery_images" class="lightbox-overlay" @click="closeLightbox">
      <div class="lightbox-content" @click.stop>
        <div class="lightbox-header">
          <button class="lightbox-close" @click="closeLightbox">
            <i class="ri-close-line"></i>
          </button>
          <div class="lightbox-counter">
            {{ currentImageIndex + 1 }} / {{ property.gallery_images.length }}
          </div>
        </div>
        
        <div class="lightbox-main">
          <button class="lightbox-nav lightbox-prev" @click="prevImage" :disabled="currentImageIndex === 0">
            <i class="ri-arrow-left-s-line"></i>
          </button>
          
          <div class="lightbox-image-container">
            <img 
              :src="getImageUrl(property.gallery_images[currentImageIndex]?.image_url)" 
              :alt="'Property image ' + (currentImageIndex + 1)" 
              class="lightbox-image" 
            />
          </div>
          
          <button class="lightbox-nav lightbox-next" @click="nextImage" :disabled="currentImageIndex === (property.gallery_images.length - 1)">
            <i class="ri-arrow-right-s-line"></i>
          </button>
        </div>

        <div class="lightbox-thumbnails">
          <div 
            v-for="(image, index) in property.gallery_images" 
            :key="index" 
            class="lightbox-thumbnail"
            :class="{ active: currentImageIndex === index }"
            @click="setCurrentImage(index)"
          >
            <img :src="getImageUrl(image.image_url)" :alt="'Thumbnail ' + (index + 1)" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, getCurrentInstance, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

export default {
  name: "PropertyDetails",
  setup() {
    const route = useRoute();
    const router = useRouter();
    const { proxy } = getCurrentInstance();
    
    const property = ref(null);
    const loading = ref(true);
    const error = ref(null);
    const currentMainImage = ref(null);
    const chatMessage = ref("");
    const showLightbox = ref(false);
    const currentImageIndex = ref(0);
    const loadingRequest = ref(false);
    const requestStatus = ref({
      unit_number_status: null,
      owner_info_status: null,
      created_at: null,
      responded_at: null
    });
    const showOwnerDetailsModal = ref(false);

    // Comments system variables
    const comments = ref([]);
    const commentsStats = ref(null);
    const loadingComments = ref(false);
    const submittingComment = ref(false);
    const newComment = ref({
      text: '',
      rating: 0
    });
    const activeReply = ref(null);
    const replyText = ref('');
    const editingComment = ref(null);

    // Echo listeners
    const echoListeners = ref([]);

    // Computed properties
    const isPropertyOwner = computed(() => {
      return property.value?.is_owner || false;
    });

    const canEditProperty = computed(() => {
      return property.value?.user_permissions?.can_edit || false;
    });

    const canDeleteProperty = computed(() => {
      return property.value?.user_permissions?.can_delete || false;
    });

    const canEditOrDelete = computed(() => {
      return canEditProperty.value || canDeleteProperty.value;
    });

    const hasMortgageInfo = computed(() => {
      if (!property.value) return false;
      return property.value.mortgage_status || 
             property.value.mortgage_amount || 
             property.value.occupancy_status || 
             property.value.rent_amount || 
             property.value.rent_expiry_date || 
             property.value.mortgage_comment;
    });

    // Computed properties for request permissions
    const canRequestUnitNumber = computed(() => {
      if (isPropertyOwner.value) return false;
      const status = requestStatus.value?.unit_number_status;
      return status !== 'pending' && status !== 'approved';
    });

    const canRequestOwnerInfo = computed(() => {
      if (isPropertyOwner.value) return false;
      const status = requestStatus.value?.owner_info_status;
      return status !== 'pending' && status !== 'approved';
    });

    const hasOwnerDocuments = computed(() => {
      const owner = getOwnerDataForModal();
      if (!owner) return false;
      return owner.id_front_path || owner.id_back_path || owner.passport_copy_path || owner.visa_copy_path ||
             owner.id_front_url || owner.id_back_url || owner.passport_copy_url || owner.visa_copy_url;
    });

    // Authentication computed
    const isAuthenticated = computed(() => {
      return proxy.$store?.state?.auth?.user !== null;
    });

    // Helper functions
    const getApprovedOwnerData = () => {
      if (isPropertyOwner.value) {
        return property.value.owner;
      }
      
      if (requestStatus.value?.owner_info_status === 'approved' && property.value?.owner) {
        return property.value.owner;
      }
      
      return null;
    };

    const getOwnerDataForModal = () => {
      return getApprovedOwnerData();
    };

    const getUnitNumber = () => {
      if (isPropertyOwner.value) {
        return property.value?.unit_number || 'Not Set';
      }
      
      if (requestStatus.value?.unit_number_status === 'approved') {
        return property.value?.unit_number || 'N/A';
      }
      
      return 'N/A';
    };

    // دالة جديدة للانتقال لصفحة تفاصيل الـ agent
    const goToAgentDetails = () => {
      if (property.value?.agent?.id) {
        router.push(`/users/${property.value.agent.id}`);
      } else {
        proxy.$showNotification('Agent information not available', 'warning');
      }
    };

    // Real-time updates methods
    const listenForAccessRequestUpdates = () => {
      const user = JSON.parse(localStorage.getItem('user'));
      if (!user || !window.Echo) {
        console.log('❌ User or Echo not available for real-time updates');
        return;
      }

      console.log('🔔 PropertyDetails: Listening for access request updates');

      // استمع لـ user channel
      const userChannel = window.Echo.private(`user.${user.id}`);
      userChannel.listen('.access.request.updated', (event) => {
        console.log('🎉 PropertyDetails: Received user update:', event);
        if (event.listing_id == route.params.id) {
          handleAccessRequestUpdate(event);
        }
      });

      // استمع لـ listing channel
      const listingChannel = window.Echo.private(`listing.${route.params.id}`);
      listingChannel.listen('.access.request.updated', (event) => {
        console.log('🎉 PropertyDetails: Received listing update:', event);
        handleAccessRequestUpdate(event);
      });

      // حفظ الـ listeners علشان نوقفهم لاحقاً
      echoListeners.value.push(userChannel, listingChannel);
    };

    const handleAccessRequestUpdate = (event) => {
      console.log('🔄 PropertyDetails: Handling update:', event);
      
      // تحديث حالة الطلب
      if (event.request_type === 'unit_number') {
        requestStatus.value.unit_number_status = event.status;
        requestStatus.value.unit_number_approved_at = event.status === 'approved' ? event.responded_at : null;
        requestStatus.value.unit_number_cancelled_at = event.status === 'cancelled' ? event.cancelled_at : null;
      } else if (event.request_type === 'owner_data') {
        requestStatus.value.owner_info_status = event.status;
        requestStatus.value.owner_info_approved_at = event.status === 'approved' ? event.responded_at : null;
        requestStatus.value.owner_info_cancelled_at = event.status === 'cancelled' ? event.cancelled_at : null;
      }

      // إعادة تحميل البيانات للتأكد من المزامنة
      fetchRequestStatus();
      
      // إظهار إشعار
      showRealTimeNotification(event);
    };

    const showRealTimeNotification = (event) => {
      const messages = {
        'requested': 'New request submitted',
        'approved': `Your ${event.request_type} request was approved! 🎉`,
        'rejected': `Your ${event.request_type} request was rejected`,
        'cancelled': 'Request cancelled'
      };
      
      const type = event.status === 'approved' ? 'success' : 
                  event.status === 'rejected' ? 'warning' : 'info';
      
      const message = messages[event.action_type] || `Request ${event.status}`;
      
      proxy.$showNotification(message, type);

      // إظهار تفاصيل أكثر إذا كان مرفوض
      if (event.status === 'rejected') {
        setTimeout(() => {
          proxy.$showNotification('You can request again if needed', 'info');
        }, 2000);
      }
    };

    const cleanupEchoListeners = () => {
      echoListeners.value.forEach(listener => {
        if (listener && typeof listener.stopListening === 'function') {
          listener.stopListening('.access.request.updated');
        }
      });
      echoListeners.value = [];
    };

    // Main data fetching
    const fetchProperty = async () => {
      try {
        loading.value = true;
        error.value = null;
        const propertyId = route.params.id;
        
        const response = await api.get(`/listings/properties/${propertyId}`);
        
        if (response.data.status) {
          property.value = response.data.data;
          
          if (property.value.gallery_images && property.value.gallery_images.length > 0) {
            currentMainImage.value = getImageUrl(property.value.gallery_images[0].image_url);
          }
          
          await fetchRequestStatus();
          
        } else {
          throw new Error(response.data.message || 'Failed to fetch property');
        }
      } catch (err) {
        handleApiError(err, 'Failed to load property details');
      } finally {
        loading.value = false;
      }
    };

    const fetchRequestStatus = async () => {
      try {
        const response = await api.get(`/listings/access-requests/status/${route.params.id}`);
        if (response.data.status) {
          requestStatus.value = response.data.data;
          console.log('✅ Request status loaded:', requestStatus.value);
        } else {
          requestStatus.value = {
            unit_number_status: null,
            owner_info_status: null,
            created_at: null,
            responded_at: null
          };
        }
      } catch (err) {
        console.error('Error fetching request status:', err);
        requestStatus.value = {
          unit_number_status: null,
          owner_info_status: null,
          created_at: null,
          responded_at: null
        };
      }
    };

    // Comments system methods
    const fetchComments = async () => {
      try {
        loadingComments.value = true;
        const response = await api.get(`/listings/${route.params.id}/comments`);
        
        if (response.data.status) {
          comments.value = response.data.data;
        }
      } catch (error) {
        console.error('Error fetching comments:', error);
        proxy.$showNotification('Failed to load comments', 'error');
      } finally {
        loadingComments.value = false;
      }
    };

    const fetchCommentsStats = async () => {
      try {
        const response = await api.get(`/listings/${route.params.id}/comments/stats`);
        
        if (response.data.status) {
          commentsStats.value = response.data.data;
        }
      } catch (error) {
        console.error('Error fetching comments stats:', error);
      }
    };

    const getRatingPercentage = (rating) => {
      if (!commentsStats.value?.rating_distribution) return 0;
      
      const ratingData = commentsStats.value.rating_distribution.find(r => r.rating === rating);
      const total = commentsStats.value.rating_distribution.reduce((sum, r) => sum + r.count, 0);
      
      return ratingData ? (ratingData.count / total) * 100 : 0;
    };

    const getRatingCount = (rating) => {
      if (!commentsStats.value?.rating_distribution) return 0;
      
      const ratingData = commentsStats.value.rating_distribution.find(r => r.rating === rating);
      return ratingData ? ratingData.count : 0;
    };

    const submitComment = async () => {
      if (!newComment.value.text.trim()) return;
      
      try {
        submittingComment.value = true;
        
        const response = await api.post(`/listings/${route.params.id}/comments`, {
          comment: newComment.value.text,
          rating: newComment.value.rating || null
        });
        
        if (response.data.status) {
          proxy.$showNotification('Comment added successfully!', 'success');
          comments.value.unshift(response.data.data);
          newComment.value = { text: '', rating: 0 };
          await fetchCommentsStats();
        }
      } catch (error) {
        handleApiError(error, 'Failed to submit comment');
      } finally {
        submittingComment.value = false;
      }
    };

    const toggleReply = (commentId) => {
      activeReply.value = activeReply.value === commentId ? null : commentId;
      replyText.value = '';
    };

    const cancelReply = () => {
      activeReply.value = null;
      replyText.value = '';
    };

    const submitReply = async (parentId) => {
      if (!replyText.value.trim()) return;
      
      try {
        const response = await api.post(`/listings/${route.params.id}/comments`, {
          comment: replyText.value,
          parent_id: parentId
        });
        
        if (response.data.status) {
          proxy.$showNotification('Reply added successfully!', 'success');
          
          // Find the parent comment and add the reply
          const parentComment = comments.value.find(c => c.id === parentId);
          if (parentComment) {
            if (!parentComment.replies) {
              parentComment.replies = [];
            }
            parentComment.replies.push(response.data.data);
          }
          
          activeReply.value = null;
          replyText.value = '';
          await fetchCommentsStats();
        }
      } catch (error) {
        handleApiError(error, 'Failed to submit reply');
      }
    };

    const editComment = (comment) => {
      editingComment.value = comment;
      newComment.value.text = comment.comment;
      newComment.value.rating = comment.rating || 0;
      
      // Scroll to comment form
      document.querySelector('.add-comment-form')?.scrollIntoView({ behavior: 'smooth' });
    };

    const updateComment = async () => {
      if (!newComment.value.text.trim()) return;
      
      try {
        const response = await api.put(`/listings/comments/${editingComment.value.id}`, {
          comment: newComment.value.text,
          rating: newComment.value.rating || null
        });
        
        if (response.data.status) {
          proxy.$showNotification('Comment updated successfully!', 'success');
          
          // Update the comment in the list
          if (editingComment.value.parent_id) {
            // This is a reply
            const parentComment = comments.value.find(c => 
              c.replies?.some(r => r.id === editingComment.value.id)
            );
            if (parentComment) {
              const replyIndex = parentComment.replies.findIndex(r => r.id === editingComment.value.id);
              if (replyIndex !== -1) {
                parentComment.replies[replyIndex] = response.data.data;
              }
            }
          } else {
            // This is a main comment
            const commentIndex = comments.value.findIndex(c => c.id === editingComment.value.id);
            if (commentIndex !== -1) {
              comments.value[commentIndex] = response.data.data;
            }
          }
          
          editingComment.value = null;
          newComment.value = { text: '', rating: 0 };
        }
      } catch (error) {
        handleApiError(error, 'Failed to update comment');
      }
    };

    const deleteComment = async (commentId) => {
      const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await api.delete(`/listings/comments/${commentId}`);
        
        if (response.data.status) {
          proxy.$showNotification('Comment deleted successfully!', 'success');
          
          // Remove the comment from the list
          let commentFound = false;
          
          // Check main comments
          const mainIndex = comments.value.findIndex(c => c.id === commentId);
          if (mainIndex !== -1) {
            comments.value.splice(mainIndex, 1);
            commentFound = true;
          } else {
            // Check replies
            for (const comment of comments.value) {
              if (comment.replies) {
                const replyIndex = comment.replies.findIndex(r => r.id === commentId);
                if (replyIndex !== -1) {
                  comment.replies.splice(replyIndex, 1);
                  commentFound = true;
                  break;
                }
              }
            }
          }
          
          await fetchCommentsStats();
        }
      } catch (error) {
        handleApiError(error, 'Failed to delete comment');
      }
    };

    const canEditComment = (comment) => {
      const currentUser = proxy.$store?.state?.auth?.user;
      return currentUser && currentUser.id === comment.user_id;
    };

    const formatCommentDate = (dateString) => {
      const date = new Date(dateString);
      const now = new Date();
      const diffTime = Math.abs(now - date);
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
      
      if (diffDays === 0) {
        return 'Today';
      } else if (diffDays === 1) {
        return 'Yesterday';
      } else if (diffDays < 7) {
        return `${diffDays} days ago`;
      } else {
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      }
    };

    // Request methods
    const sendAccessRequest = async (requestType, reason) => {
      try {
        loadingRequest.value = true;
        
        const formData = new FormData();
        formData.append('request_type', requestType);
        formData.append('reason', reason);
        formData.append('listing_id', property.value.id);

        const response = await api.post(`/listings/access-requests/${property.value.id}/request`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        
        if (response.data.status) {
          const message = requestType === 'unit_number' 
            ? 'Unit number request sent successfully!' 
            : 'Owner info request sent successfully!';
          
          proxy.$showNotification(message, 'success');
          await fetchRequestStatus();
        } else {
          throw new Error(response.data.message || 'Failed to send request');
        }
      } catch (err) {
        handleApiError(err, 'Failed to send request');
      } finally {
        loadingRequest.value = false;
      }
    };

    const requestUnitNumber = () => {
      sendAccessRequest('unit_number', 'Requesting unit number information');
    };

    const requestOwnerInfo = () => {
      sendAccessRequest('owner_data', 'Requesting owner information');
    };

    const cancelRequest = async (requestType) => {
      const result = await Swal.fire({
        title: 'Cancel Request?',
        text: `Are you sure you want to cancel this ${requestType.replace('_', ' ')} request?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'Keep Request'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await api.post(`/listings/access-requests/${property.value.id}/cancel`, {
          request_type: requestType
        });
        
        if (response.data.status) {
          proxy.$showNotification('Request cancelled successfully!', 'success');
          await fetchRequestStatus();
        } else {
          throw new Error(response.data.message || 'Failed to cancel request');
        }
      } catch (err) {
        handleApiError(err, 'Failed to cancel request');
      }
    };

    const refreshRequests = async () => {
      loadingRequest.value = true;
      try {
        await fetchRequestStatus();
        proxy.$showNotification('Status updated', 'success');
      } catch (err) {
        console.error('Refresh error:', err);
      } finally {
        loadingRequest.value = false;
      }
    };

    // Edit and Delete functions
    const editProperty = () => {
      router.push(`/properties/${property.value.id}/edit`);
    };

    const confirmDeleteProperty = async () => {
      const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      });

      if (result.isConfirmed) {
        await deleteProperty();
      }
    };

    const deleteProperty = async () => {
      try {
        loading.value = true;
        const response = await api.delete(`/listings/properties/${property.value.id}`);
        
        if (response.data.status) {
          await Swal.fire({
            title: 'Deleted!',
            text: 'Property has been deleted successfully.',
            icon: 'success',
            confirmButtonColor: '#01062d',
            timer: 2000,
            showConfirmButton: false
          });
          router.push('/my-listing');
        } else {
          throw new Error(response.data.message || 'Failed to delete property');
        }
      } catch (err) {
        handleApiError(err, 'Failed to delete property');
      } finally {
        loading.value = false;
      }
    };

    // Owner modal functions
    const openOwnerDetailsModal = () => {
      showOwnerDetailsModal.value = true;
    };

    const copyOwnerModalInfo = () => {
      const owner = getOwnerDataForModal();
      if (!owner) return;
      
      const info = `
Owner Information:
Name: ${owner.full_name || 'N/A'}
Phone: ${owner.phone_number || 'N/A'}
Email: ${owner.email || 'N/A'}
${owner.nationality ? `Nationality: ${owner.nationality}` : ''}
${owner.emirates_id ? `Emirates ID: ${owner.emirates_id}` : ''}
${owner.passport_number ? `Passport: ${owner.passport_number}` : ''}
${owner.address ? `Address: ${owner.address}` : ''}
      `.trim();
      
      copyToClipboard(info, 'Owner Information');
    };

    const copyToClipboard = async (text, type = 'Text') => {
      try {
        await navigator.clipboard.writeText(text);
        await Swal.fire({
          title: 'Copied!',
          text: `${type} has been copied to clipboard.`,
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
      } catch (err) {
        console.error('Failed to copy:', err);
        proxy.$showNotification('Failed to copy to clipboard', 'error');
      }
    };

    // Contact functions
    const callOwner = (phoneNumber) => {
      if (!phoneNumber) {
        Swal.fire({
          title: 'Phone Not Available',
          text: 'Phone number is not available for this owner.',
          icon: 'warning',
          confirmButtonColor: '#01062d'
        });
        return;
      }
      window.open(`tel:${phoneNumber}`, '_self');
    };

    const emailOwner = (email) => {
      if (!email) {
        Swal.fire({
          title: 'Email Not Available',
          text: 'Email address is not available for this owner.',
          icon: 'warning',
          confirmButtonColor: '#01062d'
        });
        return;
      }
      window.open(`mailto:${email}`, '_blank');
    };

    const whatsappOwner = (phoneNumber) => {
      if (!phoneNumber) {
        Swal.fire({
          title: 'Phone Not Available',
          text: 'Phone number is not available for WhatsApp.',
          icon: 'warning',
          confirmButtonColor: '#01062d'
        });
        return;
      }
      const message = `Hello, I'm interested in your property at ${property.value.title}`;
      window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
    };

    // Image functions
    const getImageUrl = (path) => {
      if (!path) return '/default-property.jpg';
      if (path.startsWith('http://') || path.startsWith('https://')) return path;
      if (path.includes('/storage/http://') || path.includes('/storage/https://')) {
        const urlParts = path.split('/storage/');
        return urlParts[1] || urlParts[0];
      }
      if (path.startsWith('/storage/')) return path;
      return `/storage/${path}`;
    };

    const getFirstGalleryImage = () => {
      if (property.value?.gallery_images?.length > 0) {
        return getImageUrl(property.value.gallery_images[0].image_url);
      }
      return '/default-property.jpg';
    };

    const getGalleryThumbnails = () => {
      if (!property.value?.gallery_images) return [];
      return property.value.gallery_images.slice(0, 3);
    };

    // Gallery methods
    const setMainImage = (image) => {
      currentMainImage.value = image;
    };

    const openLightbox = (index) => {
      if (!property.value?.gallery_images || property.value.gallery_images.length === 0) {
        Swal.fire({
          title: 'No Images',
          text: 'No gallery images available for this property.',
          icon: 'warning',
          confirmButtonColor: '#01062d'
        });
        return;
      }
      currentImageIndex.value = index;
      showLightbox.value = true;
      document.body.style.overflow = 'hidden';
    };

    const closeLightbox = () => {
      showLightbox.value = false;
      document.body.style.overflow = 'auto';
    };

    const nextImage = () => {
      if (property.value?.gallery_images && currentImageIndex.value < property.value.gallery_images.length - 1) {
        currentImageIndex.value++;
      }
    };

    const prevImage = () => {
      if (currentImageIndex.value > 0) {
        currentImageIndex.value--;
      }
    };

    const setCurrentImage = (index) => {
      currentImageIndex.value = index;
    };

    // Utility functions
    const handleApiError = (error, defaultMessage = 'An error occurred') => {
      console.error('API Error:', error);
      
      const errorMessage = error.response?.data?.message || 
                      error.message || 
                      defaultMessage;
      
      proxy.$showNotification(errorMessage, 'error');
      error.value = errorMessage;
      
      if (error.response?.status === 404) {
        setTimeout(() => {
          router.push('/properties');
        }, 3000);
      }
    };

    const handleAvatarError = (event) => {
      event.target.src = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
    };

    const getLocationLabel = () => {
      const owner = getOwnerDataForModal();
      if (!owner) return 'Location';
      if (owner.nationality === 'UAE') {
        return 'Country';
      } else if (owner.residency_status === 'resident') {
        return 'Emirate';
      } else if (owner.residency_status === 'non_resident') {
        return 'Country';
      }
      return 'Location';
    };

    const viewDocument = (documentUrl) => {
      if (documentUrl) {
        window.open(documentUrl, '_blank');
      } else {
        proxy.$showNotification('Document not available', 'warning');
      }
    };

    const formatPrice = (price) => {
      if (!price) return '0';
      return new Intl.NumberFormat('en-US').format(price);
    };

    const formatDate = (dateString) => {
      if (!dateString) return 'Not specified';
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };

    const handleKeydown = (event) => {
      if (!showLightbox.value) return;
      switch(event.key) {
        case 'Escape': closeLightbox(); break;
        case 'ArrowLeft': prevImage(); break;
        case 'ArrowRight': nextImage(); break;
      }
    };

    onMounted(() => {
      fetchProperty();
      fetchComments();
      fetchCommentsStats();
      document.addEventListener('keydown', handleKeydown);
      
      // 🔥 استمع لـ real-time updates
      setTimeout(() => {
        listenForAccessRequestUpdates();
      }, 1000);
    });

    // Cleanup listeners
    const cleanup = () => {
      document.removeEventListener('keydown', handleKeydown);
      cleanupEchoListeners();
    };

    return {
      property,
      loading,
      error,
      currentMainImage,
      chatMessage,
      showLightbox,
      currentImageIndex,
      loadingRequest,
      requestStatus,
      showOwnerDetailsModal,
      isPropertyOwner,
      canEditProperty,
      canDeleteProperty,
      canEditOrDelete,
      hasMortgageInfo,
      canRequestUnitNumber,
      canRequestOwnerInfo,
      hasOwnerDocuments,
      getApprovedOwnerData,
      getOwnerDataForModal,
      getUnitNumber,
      setMainImage,
      openLightbox,
      closeLightbox,
      nextImage,
      prevImage,
      setCurrentImage,
      editProperty,
      confirmDeleteProperty,
      requestUnitNumber,
      requestOwnerInfo,
      cancelRequest,
      refreshRequests,
      copyOwnerModalInfo,
      callOwner,
      emailOwner,
      whatsappOwner,
      getImageUrl,
      getFirstGalleryImage,
      getGalleryThumbnails,
      formatPrice,
      formatDate,
      openOwnerDetailsModal,
      handleAvatarError,
      getLocationLabel,
      viewDocument,
      // Comments system
      comments,
      commentsStats,
      loadingComments,
      submittingComment,
      newComment,
      activeReply,
      replyText,
      editingComment,
      isAuthenticated,
      fetchComments,
      fetchCommentsStats,
      getRatingPercentage,
      getRatingCount,
      submitComment,
      toggleReply,
      cancelReply,
      submitReply,
      editComment,
      updateComment,
      deleteComment,
      canEditComment,
      formatCommentDate,
      // New method
      goToAgentDetails,
      // Cleanup
      cleanup
    };
  },

  beforeUnmount() {
    this.cleanup();
  }
};
</script>


<style scoped>
.loading-spinner-small {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #01062d;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-left: 8px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
/* Gallery Styles */
.property-gallery {
  position: relative;
}

.gallery-container {
  display: grid;
  grid-template-columns: 1fr 120px;
  gap: 12px;
  height: 400px;
}

.main-image-section {
  border-radius: 12px 0 0 0;
  overflow: hidden;
  position: relative;
  cursor: pointer;
}

.main-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.main-image-section:hover .main-image {
  transform: scale(1.05);
}

.image-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: white;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.main-image-section:hover .image-overlay {
  opacity: 1;
}

.image-overlay i {
  font-size: 32px;
  margin-bottom: 8px;
}

.image-overlay span {
  font-size: 14px;
  font-weight: 600;
}

.side-images {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.side-image {
  height: calc(33.333% - 5.333px);
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.3s ease;
  position: relative;
}

.side-image.active {
  border-color: #01062d;
}

.side-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.side-image:hover {
  transform: scale(1.02);
}

.side-image.view-all {
  background: #01062d;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.view-all-content {
  text-align: center;
}

.view-all-content i {
  font-size: 20px;
  display: block;
  margin-bottom: 4px;
}

.view-all-content span {
  font-size: 12px;
  font-weight: 600;
  display: block;
}

.view-all-content small {
  font-size: 10px;
  opacity: 0.8;
}

/* Lightbox Styles */
.lightbox-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.lightbox-content {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 1200px;
  height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.lightbox-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
}

.lightbox-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6c757d;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.lightbox-close:hover {
  background: #e9ecef;
  color: #01062d;
}

.lightbox-counter {
  font-weight: 600;
  color: #01062d;
}

.lightbox-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  position: relative;
}

.lightbox-nav {
  background: rgba(255, 255, 255, 0.9);
  border: none;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  cursor: pointer;
  transition: all 0.3s ease;
  z-index: 2;
}

.lightbox-nav:hover:not(:disabled) {
  background: #01062d;
  color: white;
}

.lightbox-nav:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.lightbox-image-container {
  flex: 1;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
}

.lightbox-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: 8px;
}

.lightbox-thumbnails {
  display: flex;
  gap: 8px;
  padding: 16px 24px;
  background: #f8f9fa;
  border-top: 1px solid #e9ecef;
  overflow-x: auto;
}

.lightbox-thumbnail {
  width: 80px;
  height: 60px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.lightbox-thumbnail.active {
  border-color: #01062d;
}

.lightbox-thumbnail:hover {
  transform: scale(1.05);
}

.lightbox-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Property Content Styles */
.property-content {
  background: white;
}

.location-section {
  padding-bottom: 20px;
  border-bottom: 1px solid #e9ecef;
}

.p-32{
  padding: 32px !important;
}

.location-breadcrumb {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.location-item {
  font-size: 14px;
  color: #6c757d;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.location-breadcrumb i {
  color: #6c757d;
  font-size: 16px;
}

.property-categories {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.category-tag {
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #01062d;
  color: white;
}

.property-main-info {
  padding: 24px;
  border-radius: 16px;
  border: 1px solid #01062d;
  margin-bottom: 20px;
}

.property-price {
  font-size: 28px !important;
  font-weight: 800;
  color: #01062d;
  margin: 0;
  margin-bottom: 20px;
  line-height: 1;
}

.specs-grid-main {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.spec-main-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 12px;
  border: 1px solid #01062d;
}

.spec-icon {
  width: 48px;
  height: 48px;
  background: #01062d;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spec-icon i {
  font-size: 20px;
  color: white;
}

.spec-main-info {
  display: flex;
  flex-direction: column;
}

.spec-main-value {
  font-size: 20px;
  font-weight: 800;
  color: #01062d;
  line-height: 1;
}

.spec-main-label {
  font-size: 12px;
  color: #01062d;
  font-weight: 600;
  margin-top: 4px;
}

.detailed-info-section {
  background: #f8f9fa;
  padding: 24px;
  border-radius: 12px;
  border: 1px solid #e9ecef;
}

.info-section {
  margin-bottom: 32px;
}

.section-title {
  font-size: 28px !important;
  font-weight: 700;
  color: #01062d;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid #01062d;
}

/* Info Grid for Property Details */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.info-label {
  font-weight: 600;
  color: #555;
  font-size: 14px;
}

.info-value {
  font-weight: 500;
  color: #01062d;
  font-size: 14px;
}

.description-content {
  background: white;
  padding: 20px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.description-text {
  line-height: 1.6;
  color: #555;
  font-size: 16px;
  margin: 0;
}

/* Agent Sidebar Card - Improved Styles */
.agent-sidebar-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #e9ecef;
  position: sticky;
  top: 90px; /* Adjusted to stay below navbar */
  max-height: calc(100vh - 120px);
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #c1c1c1 transparent;
}

.agent-sidebar-card::-webkit-scrollbar {
  width: 6px;
}

.agent-sidebar-card::-webkit-scrollbar-track {
  background: transparent;
}

.agent-sidebar-card::-webkit-scrollbar-thumb {
  background-color: #c1c1c1;
  border-radius: 3px;
}

.agent-sidebar-card::-webkit-scrollbar-thumb:hover {
  background-color: #a8a8a8;
}

/* Agent Profile - Improved Styles */
.agent-profile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e9ecef;
  text-align: center;
}

.agent-sidebar-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #01062d;
}

.agent-sidebar-info {
  width: 100%;
}

.agent-sidebar-name {
  font-size: 18px;
  font-weight: 700;
  color: #333;
  margin: 0 0 8px 0;
  line-height: 1.3;
}

.agent-sidebar-company {
  font-size: 14px;
  color: #6c757d;
  margin: 0 0 12px 0;
  line-height: 1.4;
}

.btn-show-agent-details {
  width: 100%;
  padding: 12px 16px;
  background: #01062d;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-show-agent-details:hover {
  background: #0056b3;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Sidebar Sections - Improved Responsive */
.sidebar-section {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e9ecef;
}

.sidebar-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
}

.sidebar-title {
  font-size: 16px;
  font-weight: 700;
  color: #01062d;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid #01062d;
}

/* Unit Display Styles */
.owner-unit-info,
.approved-unit-info {
  background: #f8f9fa;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.unit-display {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.unit-display i {
  font-size: 24px;
  color: #01062d;
}

.unit-value {
  font-size: 20px;
  font-weight: 700;
  color: #01062d;
}

.unit-note {
  font-size: 12px;
  color: #28a745;
  margin: 0;
}

.approval-info {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #28a745;
  font-size: 12px;
  font-weight: 600;
}

/* Pending Request Styles */
.pending-request-info {
  background: #fff3cd;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #ffeaa7;
}

.request-status {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.request-status i {
  color: #856404;
}

.status-text {
  font-weight: 600;
  color: #856404;
}

.request-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.request-date {
  color: #856404;
  font-size: 12px;
}

.btn-cancel-small {
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 12px;
}

/* Owner Info Display */
.owner-info-direct,
.approved-owner-info {
  background: #f8f9fa;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.info-display {
  margin-bottom: 12px;
}

.info-item-sidebar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-size: 14px;
}

.info-item-sidebar i {
  color: #01062d;
  width: 16px;
}

.btn-show-owner-modal {
  width: 100%;
  padding: 10px;
  background: #01062d;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.btn-show-owner-modal:hover {
  background: #0056b3;
}

/* Request Section Styles */
.request-section {
  text-align: center;
}

.btn-sidebar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 100%;
}

.btn-request-unit {
  background: #01062d;
  color: white;
}

.btn-request-owner {
  background: #FAA300;
  color: white;
}

.btn-sidebar:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Cannot Request Styles */
.cannot-request-info {
  background: #f8d7da;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
}

.cannot-request-status {
  display: flex;
  align-items: center;
  gap: 8px;
}

.cannot-request-status i {
  color: #721c24;
}

.cannot-request-status .status-text {
  color: #721c24;
  font-weight: 600;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e9ecef;
}

.modal-header h4 {
  margin: 0;
  color: #01062d;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6c757d;
  padding: 4px;
  border-radius: 4px;
}

.modal-close:hover {
  background: #e9ecef;
}

.modal-body {
  padding: 24px;
}

/* Owner Details Modal Styles */
.owner-details-modal {
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
}

.owner-profile-section {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
  border-radius: 12px;
  border: 1px solid #e9ecef;
  margin-bottom: 20px;
}

.owner-avatar-container {
  position: relative;
}

.owner-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.owner-verified-badge {
  position: absolute;
  bottom: 5px;
  right: 5px;
  background: #28a745;
  color: white;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  border: 2px solid white;
}

.owner-profile-info {
  flex: 1;
}

.owner-name {
  font-size: 24px;
  font-weight: 700;
  color: #01062d;
  margin: 0 0 4px 0;
}

.owner-email {
  color: #6c757d;
  margin: 0 0 12px 0;
  font-size: 16px;
}

.owner-badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge-primary {
  background: #01062d;
  color: white;
}

.badge-success {
  background: #28a745;
  color: white;
}

.badge-warning {
  background: #ffc107;
  color: #212529;
}

.badge-info {
  background: #17a2b8;
  color: white;
}

.badge-light {
  background: #f8f9fa;
  color: #212529;
  border: 1px solid #dee2e6;
}

/* Info Sections in Modal */
.modal-info-section {
  background: white;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  padding: 20px;
  margin-bottom: 16px;
}

.modal-section-title {
  font-size: 18px;
  font-weight: 700;
  color: #01062d;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
}

.modal-info-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.modal-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

.modal-info-item:hover {
  border-color: #01062d;
  background: white;
}

.modal-info-item.full-width {
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.modal-info-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: #555;
  font-size: 14px;
}

.modal-info-label i {
  color: #01062d;
  font-size: 16px;
}

.modal-info-value {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  color: #01062d;
  font-size: 14px;
}

.address-text {
  line-height: 1.5;
  color: #555;
}

/* Action Buttons */
.btn-action-small {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-call {
  color: #28a745;
}

.btn-call:hover {
  background: rgba(40, 167, 69, 0.1);
}

.btn-whatsapp {
  color: #25D366;
}

.btn-whatsapp:hover {
  background: rgba(37, 211, 102, 0.1);
}

.btn-email {
  color: #007bff;
}

.btn-email:hover {
  background: rgba(0, 123, 255, 0.1);
}

/* Documents Section */
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

.document-item:hover {
  border-color: #01062d;
  transform: translateY(-2px);
}

.document-icon {
  width: 40px;
  height: 40px;
  background: #01062d;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
}

.document-info {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.document-name {
  font-weight: 600;
  color: #01062d;
}

.btn-view-document {
  background: #01062d;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-view-document:hover {
  background: #0056b3;
  transform: translateY(-1px);
}

/* No Documents */
.no-documents {
  background: #f8f9fa;
  border-radius: 12px;
  border: 2px dashed #dee2e6;
  padding: 40px 20px;
  text-align: center;
}

.no-documents-icon {
  font-size: 48px;
  color: #6c757d;
  margin-bottom: 12px;
}

.no-documents-text {
  color: #6c757d;
  margin: 0;
  font-size: 16px;
}

/* Modal Buttons */
.btn-modal {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  flex: 1;
}

.btn-modal-secondary {
  background: #6c757d;
  color: white;
}

.btn-modal-secondary:hover {
  background: #5a6268;
}

.btn-modal-primary {
  background: #01062d;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-modal-primary:hover {
  background: #0056b3;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #e9ecef;
  background: #f8f9fa;
}

/* Property Actions */
.property-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.btn-action {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-edit {
  background: #01062d;
  color: white;
}

.btn-delete {
  background: #dc3545;
  color: white;
}

.btn-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* General Styles */
.property-title {
  font-size: 28px !important;
  font-weight: 800;
  color: #01062d;
  margin-bottom: 16px;
  line-height: 1.2;
}

.text-center {
  text-align: center;
}

.spinner-border {
  width: 3rem;
  height: 3rem;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.full-width {
  grid-column: 1 / -1;
}

/* Comments Section Styles */
.comments-section {
  margin-top: 0px;
}

.add-comment-form {
  background: white;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  overflow: hidden;
  margin-top: 5px;
}

.form-header {
  /* background: #f8f9fa; */
  padding: 16px 20px;
  /* border-bottom: 1px solid #e9ecef; */
}

.form-header h5 {
  margin: 0;
  color: #01062d;
}

.form-body {
  padding: 20px;
}

.comment-input {
  position: relative;
}

.char-counter {
  position: absolute;
  bottom: 8px;
  right: 8px;
  font-size: 12px;
  color: #6c757d;
  background: white;
  padding: 2px 6px;
  border-radius: 4px;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

/* Comments List */
.no-comments {
  color: #6c757d;
}

.no-comments-icon {
  font-size: 48px;
  color: #e9ecef;
  margin-bottom: 16px;
}

.comment-item {
  border: 1px solid #e9ecef;
  border-radius: 12px;
  margin-bottom: 16px;
  overflow: hidden;
}

.comment-item.has-replies {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}

.comment-main {
  padding: 20px;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.user-avatar.small {
  width: 32px;
  height: 32px;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #01062d;
  margin: 0;
  font-size: 14px;
}

.comment-date {
  font-size: 12px;
  color: #6c757d;
}

.comment-body {
  margin-bottom: 12px;
}

.comment-text {
  margin: 0;
  line-height: 1.6;
  color: #555;
}

.comment-actions {
  display: flex;
  gap: 12px;
}

.btn-action {
  background: none;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-reply {
  color: #01062d;
  background: #f8f9fa;
}

.btn-reply:hover {
  background: #01062d;
  color: white;
}

.btn-edit {
  color: #28a745;
  background: rgba(40, 167, 69, 0.1);
}

.btn-edit:hover {
  background: #28a745;
  color: white;
}

.btn-delete {
  color: #dc3545;
  background: rgba(220, 53, 69, 0.1);
}

.btn-delete:hover {
  background: #dc3545;
  color: white;
}

/* Reply Form */
.reply-form {
  margin-top: 12px;
  padding: 16px;
  background: #f8f9fa;
  border-radius: 8px;
}

.reply-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

/* Replies Container */
.replies-container {
  background: #f8f9fa;
  border-top: 1px solid #e9ecef;
  padding: 16px 20px 16px 60px;
}

.reply-item {
  border: 1px solid #e9ecef;
  border-radius: 8px;
  background: white;
  margin-bottom: 12px;
}

.reply-item:last-child {
  margin-bottom: 0;
}

.reply-main {
  padding: 16px;
}

.reply-header {
  margin-bottom: 8px;
}

/* Login Prompt */
.login-prompt .alert {
  margin: 0;
  border-radius: 8px;
}

/* Improved Responsive Design for Mobile */
@media (max-width: 768px) {
  .agent-sidebar-card {
    position: relative;
    top: 0;
    margin-bottom: 20px;
    max-height: none;
  }
  
  .agent-profile {
    flex-direction: row;
    text-align: left;
    gap: 12px;
  }
  
  .agent-sidebar-avatar {
    width: 60px;
    height: 60px;
  }
  
  .agent-sidebar-info {
    flex: 1;
  }
  
  .agent-sidebar-name {
    font-size: 16px;
    margin-bottom: 4px;
  }
  
  .agent-sidebar-company {
    font-size: 13px;
    margin-bottom: 8px;
  }
  
  .btn-show-agent-details {
    padding: 10px 12px;
    font-size: 13px;
  }
  
  .sidebar-title {
    font-size: 15px;
  }

  .gallery-container {
    grid-template-columns: 1fr;
    grid-template-rows: 300px 80px;
    height: auto;
  }
  
  .side-images {
    flex-direction: row;
  }
  
  .side-image {
    height: 80px;
    flex: 1;
  }
  
  .specs-grid-main {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .lightbox-content {
    height: 95vh;
    margin: 10px;
  }
  
  .lightbox-main {
    padding: 10px;
  }
  
  .lightbox-nav {
    width: 40px;
    height: 40px;
    font-size: 20px;
  }
  
  .lightbox-thumbnails {
    padding: 12px 16px;
  }
  
  .lightbox-thumbnail {
    width: 60px;
    height: 45px;
  }
  
  .property-actions {
    /* flex-direction: column; */
  }
  
  .owner-details-modal {
    margin: 10px;
    max-height: 95vh;
  }
  
  .owner-profile-section {
    flex-direction: column;
    text-align: center;
    gap: 16px;
  }
  
  .documents-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .btn-modal {
    width: 100%;
  }

  .comment-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .comment-actions {
    flex-wrap: wrap;
  }
  
  .replies-container {
    padding-left: 20px;
  }
  
  .form-actions {
    justify-content: stretch;
  }
  
  .form-actions .btn {
    width: 100%;
  }
}

@media (max-width: 576px) {
  .agent-sidebar-card {
    padding: 16px;
  }
  
  .agent-profile {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }
  
  .agent-sidebar-name {
    font-size: 15px;
  }
  
  .agent-sidebar-company {
    font-size: 12px;
  }
  
  .sidebar-title {
    font-size: 14px;
  }
  
  /* Improved text sizes for mobile in sidebar sections */
  .unit-value,
  .info-item-sidebar span {
    font-size: 14px;
  }
  
  .unit-note,
  .approval-info,
  .status-text,
  .request-date,
  .cannot-request-status .status-text {
    font-size: 11px;
  }
  
  .btn-sidebar,
  .btn-show-owner-modal {
    font-size: 13px;
    padding: 12px 14px;
  }

  .gallery-container {
    grid-template-rows: 250px 70px;
  }
  
  .side-image {
    height: 70px;
  }
  
  .spec-main-item {
    padding: 12px;
  }
  
  .spec-icon {
    width: 40px;
    height: 40px;
  }
  
  .spec-icon i {
    font-size: 16px;
  }
  
  .lightbox-header {
    padding: 12px 16px;
  }
  
  .lightbox-main {
    padding: 5px;
  }
  
  .owner-avatar {
    width: 80px;
    height: 80px;
  }
  
  .owner-name {
    font-size: 20px;
  }
  
  .modal-info-section {
    padding: 16px;
  }
  
  .modal-section-title {
    font-size: 16px;
  }
}

/* Additional Mobile Optimizations */
@media (max-width: 480px) {
  .agent-sidebar-card {
    padding: 12px;
  }
  
  .agent-sidebar-name {
    font-size: 14px;
  }
  
  .agent-sidebar-company {
    font-size: 11px;
  }
  
  .btn-show-agent-details {
    font-size: 12px;
    padding: 10px;
  }
  
  .sidebar-title {
    font-size: 13px;
    margin-bottom: 10px;
  }
}

/* Enhanced Scroll Behavior */
@media (min-width: 769px) {
  .agent-sidebar-card {
    transition: all 0.3s ease;
  }
  
  .agent-sidebar-card.scrolling {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  }
}
/* إضافة هذه الأنماط في نهاية ملف الـ CSS الحالي */

/* تحسينات للجوال - تقليل البادنج والخطوط */
@media (max-width: 768px) {
  /* تقليل البادنج العام */
  .p-32 {
    padding: 20px !important;
  }
  .mb-32{
        margin-block-end: 5px !important;
  }
  .property-content {
    padding: 16px !important;
  }
  
  .card-body {
    padding: 0 !important;
  }
  
  /* تقليل بادنج الأقسام الداخلية */
  .detailed-info-section,
  .info-section
 {
    padding: 10px 0 !important;
  }
  .property-main-info{
    padding: 16px;
  }
  
  .specs-grid-main {
    gap: 12px;
  }
  
  .spec-main-item {
    padding: 12px !important;
  }
  
  .info-item {
    padding: 10px 12px !important;
  }
  
  /* تقليل حجم الخطوط في الجوال */
  .property-title {
    font-size: 22px !important;
    margin-bottom: 12px;
  }
  
  .section-title {
    font-size: 20px !important;
    margin-bottom: 16px;
  }
  
  .property-price {
    font-size: 24px !important;
    margin-bottom: 16px;
  }
  
  .spec-main-value {
    font-size: 18px !important;
  }
  
  .spec-main-label {
    font-size: 11px !important;
  }
  
  .info-label,
  .info-value {
    font-size: 13px !important;
  }
  
  .description-text {
    font-size: 14px !important;
  }
  
  /* تحسين الجاليري في الموبايل */
  .gallery-container {
    grid-template-rows: 250px 70px;
    gap: 8px;
  }
  
  .side-images {
    gap: 6px;
  }
  
  .side-image {
    height: 70px;
  }
  
  /* تحسين الـ sidebar في الموبايل */
  .agent-sidebar-card {
    padding: 16px;
  }
  
  .sidebar-section {
    margin-bottom: 16px;
    padding-bottom: 12px;
  }
  
  .sidebar-title {
    font-size: 15px;
    margin-bottom: 10px;
  }
  
  .agent-sidebar-name {
    font-size: 16px;
  }
  
  .agent-sidebar-company {
    font-size: 13px;
  }
  
  .unit-value {
    font-size: 18px;
  }
  
  .info-item-sidebar {
    font-size: 13px;
  }
  
  .btn-sidebar,
  .btn-show-owner-modal,
  .btn-show-agent-details {
    font-size: 13px;
    padding: 12px 14px;
  }
  
  /* تحسين التعليقات في الموبايل */
  .comments-section .card-body {
    padding: 16px !important;
  }
  
  .form-header,
  .form-body {
    padding: 16px;
  }
  
  .comment-main {
    padding: 16px;
  }
  
  .user-name {
    font-size: 13px;
  }
  
  .comment-text {
    font-size: 14px;
  }
  
  .btn-action {
    font-size: 11px;
    padding: 5px 10px;
  }
}

@media (max-width: 576px) {
  /* تحسينات إضافية للشاشات الصغيرة */
  .p-32 {
    padding: 16px !important;
  }
  
  .property-title {
    font-size: 20px !important;
  }
  
  .section-title {
    font-size: 18px !important;
  }
  
  .property-price {
    font-size: 22px !important;
  }
  
  .spec-main-value {
    font-size: 16px !important;
  }
  
  .agent-sidebar-card {
    padding: 14px;
  }
  
  .sidebar-title {
    font-size: 14px;
  }
  
  .agent-sidebar-name {
    font-size: 15px;
  }
  
  .agent-sidebar-company {
    font-size: 12px;
  }
  
  /* تحسين الأزرار في الشاشات الصغيرة */
  .btn-sidebar,
  .btn-show-owner-modal,
  .btn-show-agent-details {
    font-size: 12px;
    padding: 10px 12px;
  }
  
  /* تحسين الـ grid في الشاشات الصغيرة */
  .info-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  /* .specs-grid-main {
    grid-template-columns: 1fr;
    gap: 10px;
  } */
  
  /* تحسين النصوص الصغيرة */
  .unit-note,
  .approval-info,
  .status-text,
  .request-date {
    font-size: 10px !important;
  }
}

@media (max-width: 480px) {
  /* تحسينات نهائية للشاشات الصغيرة جداً */
  .agent-sidebar-card {
    padding: 12px;
  }
  
  .agent-sidebar-name {
    font-size: 14px;
  }
  
  .agent-sidebar-company {
    font-size: 11px;
  }
  
  .sidebar-title {
    font-size: 13px;
  }
  
  .property-actions {
    gap: 8px;
  }
  
  .btn-action {
    font-size: 10px;
    padding: 4px 8px;
  }
  
  /* تحسين الجاليري في الشاشات الصغيرة */
  .gallery-container {
    grid-template-rows: 200px 60px;
  }
  
  .side-image {
    height: 60px;
  }
  
  .image-overlay i {
    font-size: 24px;
  }
  
  .image-overlay span {
    font-size: 12px;
  }
}

/* تحسينات إضافية للأداء في الموبايل */
@media (max-width: 768px) {
  /* تحسين الأداء البصري */
  .property-main-info {
    margin-bottom: 16px;
  }
  
  .info-section {
    margin-bottom: 24px;
  }
  
  /* تقليل المسافات بين العناصر */
  .location-section {
    padding-bottom: 16px;
    margin-bottom: 16px;
  }
  
  .property-categories {
    gap: 6px;
    margin-top: 12px;
  }
  
  .category-tag {
    padding: 6px 10px;
    font-size: 10px;
  }
  
  /* تحسين الـ lightbox في الموبايل */
  .lightbox-content {
    margin: 5px;
    height: 95vh;
  }
  
  .lightbox-header {
    padding: 12px 16px;
  }
  
  .lightbox-main {
    padding: 10px;
  }
  
  .lightbox-thumbnails {
    padding: 10px 12px;
  }
}

/* تحسينات للوضع الأفقي في الموبايل */
@media (max-width: 768px) and (orientation: landscape) {
  .gallery-container {
    grid-template-rows: 180px 60px;
  }
  
  .agent-sidebar-card {
    max-height: 80vh;
  }
}
.comment-container{
      background: #f8f9fa;

}
</style>