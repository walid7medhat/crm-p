<template>
  <div class="dashboard-main-body-inner property-show-inner">
    <div class="row gy-4 property-show-row">
      <!-- Main Content -->
      <div class="col-lg-9">
        <div class="card card-main p-0 radius-12 overflow-hidden">
          <div class="card-body ">
         
            <!-- Carousel Section -->
            <div class="property-gallery" v-if="property && property.gallery_images">
              <div
                class="gallery-container"
                :class="{ 'is-single': property.gallery_images.length === 1 }"
              >
                <div class="main-image-section" @click="openLightbox(0)">
                  <img :src="getFirstGalleryImage()" alt="Property main image" class="main-image" />
                  <!-- <div class="image-overlay">
                    <i class="ri-image-fill"></i>
                    <span>View All Photos</span>
                  </div> -->
                    <div class="image-overlay image-overlay-right"   v-if="property?.drive_link"
                        @click.stop="openDriveLink">
                
                        <i class="fab fa-google-drive"></i>
                        <span>GOOGLE DRIVE</span> 
                      </div>
                </div>
                <div class="side-images">
                  <div
                    v-for="(image, index) in getGalleryThumbnails()"
                    :key="index"
                    class="side-image"
                    :class="{
                      active: currentMainImage === getImageUrl(image.image_url_final),
                      'has-view-all': index === getGalleryThumbnails().length - 1 && property.gallery_images && property.gallery_images.length > 3,
                    }"
                    @click="index === getGalleryThumbnails().length - 1 && property.gallery_images && property.gallery_images.length > 3
                      ? openLightbox(0)
                      : setMainImage(getImageUrl(image.image_url_final))"
                  >
                    <img :src="getImageUrl(image.image_url_final)" alt="Property thumbnail" />
                    <div
                      v-if="index === getGalleryThumbnails().length - 1 && property.gallery_images && property.gallery_images.length > 3"
                      class="view-all-overlay"
                    >
                      <div class="view-all-content">
                        <i class="ri-gallery-view-2"></i>
                        <span>View All</span>
                        <small>{{ property.gallery_images?.length || 0 }} photos</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
           <div v-if="hasRejectionReason" class="rejection-alert-container mb-4">
              <div class="alert alert-danger rejection-alert" role="alert">
                <div class="rejection-alert-header">
                  <i class="ri-close-circle-fill me-2"></i>
                  <strong>Listing Rejected</strong>
                </div>
                <div class="rejection-alert-body d-flex">
                  <div class="rejection-reason-wrapper">
                    <p class="rejection-reason-label">Reason:</p>
                    <p class="rejection-reason-text">{{ property.rejection_reason }}</p>
                  </div>
                  <div class="rejection-meta" v-if="property.rejected_at">
                    <small class="rejection-date">
                      <i class="ri-calendar-line me-1"></i>
                      {{ formatRejectionDate(property.rejected_at) }}
                    </small>
                  </div>
                </div>
              </div>
            </div>
            <!-- Property Details Section -->
            <div class="property-content " v-if="property">
              
              <!-- Action Buttons for authorized users -->
              <!-- <div class="property-actions mb-16" v-if="canEditOrDelete">
                <button v-if="canEditProperty" class="btn-action btn-edit" @click="editProperty">
                  <i class="ri-edit-line"></i>
                  Edit Property
                </button>
                <button v-if="canDeleteProperty" class="btn-action btn-delete" @click="confirmDeleteProperty">
                  <i class="ri-delete-bin-line"></i>
                  Delete Property
                </button>
              </div> -->

              <!-- Property Price and Basic Info -->
              <div class="property-main-info mb-16">
                <div class="property-actions mb-16">
                  <button class="btn btn-primary" >
                 {{ property.listing_status || "Not specified" }}
                </button>
                  <!-- <button class="btn btn-success" @click="openFloorPlanSlider(0)" v-if="property?.floor_plans?.length > 0">
                    <iconify-icon icon="iconamoon:eye-light"></iconify-icon>
                    VIEW FLOOR PLAN
                  </button> -->
                </div>
                <div class="price-main">
                  <h3 class="property-price">AED {{ formatPrice(property.price) }}</h3>
                  <h4 class="property-title">{{ property.area?.area_title || '' }}</h4>
                   <!--<h4 class="property-title"> Old Area {{ property.old_area || "" }}</h4>-->
                </div>

                <div class="specs-grid-main">
                    
                 <div class="spec-main-item">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                          <!--<i class="ri-building-line"></i>-->
                          <img :src="propertyIcon" class="imgicon"/>
                          {{  property.property_type.name  || 'N/A' }} </span>
                      <!-- <span class="spec-main-label"></span> -->
                    </div>
                  </div>
                   <div class="spec-main-item" v-if="!property.property_type.name.toLowerCase().includes('plot') && !property.property_type.name.toLowerCase().includes('land') && property.number_of_bedrooms !== null && property.number_of_bedrooms !== undefined">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                          <!--<i class="ri-hotel-bed-line"></i>-->
                          <img :src="bedIcon" class="imgicon"/>
                          {{ property.number_of_bedrooms==0?'Studio':property.number_of_bedrooms  +' Bedrooms' }} </span>
                      <!-- <span class="spec-main-label">Bed</span> -->
                    </div>
                  </div>
                  
                  <div class="spec-main-item"  v-if="!property.property_type.name.toLowerCase().includes('plot') && !property.property_type.name.toLowerCase().includes('land') && property.number_of_bathrooms !== null && property.number_of_bathrooms !== undefined && property.number_of_bathrooms!=0">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                          <!--<i class="ri-contrast-drop-line"></i>-->
                          <img :src="bathIcon" class="imgicon"/>
                          {{ property.number_of_bathrooms }} Bathrooms</span>
                      <!-- <span class="spec-main-label">Bath</span> -->
                    </div>
                  </div>

                  <div class="spec-main-item">
                    <div class="spec-main-info">
                      <span class="spec-main-value">
                          <!--<i class="ri-home-5-line"></i>-->
                          <img :src="sqftIcon" class="imgicon"/>
                          {{ property.size_sqft || 'N/A' }} Sq Ft</span>
                      <!-- <span class="spec-main-label"></span> -->
                    </div>
                  </div>
                  
            
                </div>
              </div>

            <!-- Basic Information Section -->
            <div class="detailed-info-section mb-16">
              <div class="info-section">
                <h3 class="section-title mb-20">Property Details</h3>
                <div class="info-grid">
                  <div class="info-item">
                    <span class="info-label">Sale/Rent</span>
                    <span class="info-value">{{ property.listing_status || "Not specified" }}</span>
                  </div>
                  
                  <div class="info-item">
                    <span class="info-label">Price</span>
                    <span class="info-value">AED {{ formatPrice(property.price) }}</span>
                  </div>
                  
                  <div class="info-item">
                    <span class="info-label">Property Type</span>
                    <span class="info-value">{{ property.property_type?.name || "Not specified" }}</span>
                  </div>
                  <div class="info-item" v-if="property.project">
                    <span class="info-label">Project</span>
                    <span class="info-value">{{ property.project?.title || "Not specified" }}</span>
                  </div>
                  <div class="info-item"  v-if="property.project">
                    <span class="info-label">Developer</span>
                    <span class="info-value">{{ property.project?.developer_name || "Not specified" }}</span>
                  </div>
                  
                  
                  <div class="info-item" v-if="!property.property_type.name.toLowerCase().includes('plot') && !property.property_type.name.toLowerCase().includes('land') && property.number_of_bedrooms !== null && property.number_of_bedrooms !== undefined">
                    <span class="info-label">Bedrooms</span>
                    <span class="info-value"> {{ property.number_of_bedrooms==0?'Studio':property.number_of_bedrooms  +'Bedrooms' }} </span>
                  </div>
                  
                  <div class="info-item"  v-if="!property.property_type.name.toLowerCase().includes('plot') && !property.property_type.name.toLowerCase().includes('land') && property.number_of_bathrooms !== null && property.number_of_bathrooms !== undefined && property.number_of_bathrooms!=0">
                    <span class="info-label">Bathrooms</span>
                    <span class="info-value">{{ property.number_of_bathrooms || "0" }}</span>
                  </div>
                  
                  <div class="info-item">
                    <span class="info-label">Size</span>
                    <span class="info-value">{{ property.size_sqft || property.size_sqmt || "N/A" }} {{ property.size_sqft ? 'Sq Ft' : property.size_sqmt ? 'Sq M' : '' }}</span>
                  </div>
                    <div class="info-item" v-if="property.is_hot_deal == 'Yes'">
                    <span class="info-label">Hot Deal</span>
                    <span class="info-value">{{ property.is_hot_deal }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Completion Status</span>
                    <span class="info-value">{{ property?.completion_status || "Not specified" }}</span>
                  </div>
                  
                  <!--<div class="info-item" v-if="false">-->
                  <!--  <span class="info-label">Furnished Status</span>-->
                  <!--  <span class="info-value">{{ property.furnished_status || "Not specified" }}</span>-->
                  <!--</div>-->
                 
                  <div class="info-item" v-if="property.reference_number">
                    <span class="info-label">Reference Number</span>
                    <span class="info-value">{{ property.reference_number }}</span>
                  </div>
                  
                 
                  
                  <div class="info-item" v-if="property.developer?.name">
                    <span class="info-label">Developer</span>
                    <span class="info-value">{{ property.developer.name }}</span>
                  </div>
                  <div class="info-item" v-if="property.rented_status && property.rented_until">
                    <span class="info-label">Rented</span>
                    <span class="info-value">until {{ property.rented_until }}</span>
                  </div>
                
                    <div class="info-item" v-if="hasPaymentPlans(property)">
                      <span class="info-label">Payment Plans</span>
                      <div class="info-value">
                        <div class="payment-plans-container">
                          <template v-if="isArrayPaymentPlan(property.payment_plan_json || property.payment_plan)">
                            <span v-for="(plan, index) in parsePaymentPlans(property)" 
                                  :key="index" 
                                  class="badge bg-primary me-1 mb-1">
                              {{ plan }}
                            </span>
                          </template>
                          <template v-else>
                            <span class="badge bg-primary me-1 mb-1">
                              {{ formatPaymentPlan(property.payment_plan_json || property.payment_plan) }}
                            </span>
                          </template>
                        </div>
                      </div>
                    </div>
                    <div class="info-item full-width flex-column" v-if="hasAdditionalFeatures">
                      <span class="info-label">Features</span>
                      <div class="info-value">
                        <div class="payment-plans-container">
                          <span
                            v-for="(feature, index) in additionalFeaturesList"
                            :key="index"
                            class="badge bg-primary me-1 mb-1"
                          >
                            {{ feature }}
                          </span>
                        </div>
                      </div>
                    </div>
                   
                  <!--<div class="info-item" v-if="false">-->
                  <!--  <span class="info-label">View</span>-->
                  <!--  <span class="info-value">{{ property.unit_view }}</span>-->
                  <!--</div>-->
                  
                  <!--<div class="info-item" v-if="false">-->
                  <!--  <span class="info-label">Layout Type</span>-->
                  <!--  <span class="info-value">{{ property.layout_type }}</span>-->
                  <!--</div>-->
                  
                  <!--<div class="info-item" v-if="false">-->
                  <!--  <span class="info-label">Ownership Type</span>-->
                  <!--  <span class="info-value">{{ property.ownership_type }}</span>-->
                  <!--</div>-->
                </div>
              </div>
            </div>
             

            <!-- Mortgage Information Section -->
            <div class="detailed-info-section mb-16" v-if="hasMortgageInfo">
              <div class="info-section">
                <h3 class="section-title mb-20">Mortgage Information</h3>
                <div class="info-grid">
            
                  <div class="info-item" v-if="property.mortgage_status">
                    <span class="info-label">Mortgage Status</span>
                    <span class="info-value">{{ property.mortgage_status }}</span>
                  </div>
            
                  <div class="info-item" v-if="property.mortgage_amount">
                    <span class="info-label">Mortgage Amount</span>
                    <span class="info-value">AED {{ formatPrice(property.mortgage_amount) }}</span>
                  </div>
            
                  <div class="info-item" v-if="property.occupancy_status">
                    <span class="info-label">Occupancy Status</span>
                    <span class="info-value">{{ property.occupancy_status }}</span>
                  </div>
            
                  <div class="info-item" v-if="property.rent_amount">
                    <span class="info-label">Rent Amount</span>
                    <span class="info-value">AED {{ formatPrice(property.rent_amount) }}</span>
                  </div>
            
                  <div class="info-item" v-if="property.rent_expiry_date">
                    <span class="info-label">Rent Expiry Date</span>
                    <span class="info-value">{{ formatDate(property.rent_expiry_date) }}</span>
                  </div>
            
                  <div class="description-content full-width" v-if="property.mortgage_comment">
                    <span class="info-label">Mortgage Comment</span>
                    <p class="description-text">{{ property.mortgage_comment }}</p>
                  </div>
            
                </div>
              </div>
            </div>
            
            <!-- Floor Plans Section -->
            <div class="detailed-info-section mb-16" v-if="property.floor_plans && property.floor_plans.length > 0">
              <div class="info-section">
                <h3 class="section-title mb-20">
                  <i class="ri-layout-grid-line me-2"></i>
                  Floor Plans
                  <small class="text-muted ms-2">({{ property.floor_plans.length }})</small>
                </h3>
                <div class="floor-plans-grid">
                  <div
                    v-for="(plan, idx) in property.floor_plans"
                    :key="plan.id || idx"
                    class="floor-plan-card"
                    @click="openFloorPlanSlider(idx)"
                  >
                    <div class="floor-plan-card-thumb">
                      <img
                        v-if="plan.image_url"
                        :src="plan.image_url"
                        :alt="plan.name || 'Floor Plan'"
                        loading="lazy"
                      />
                      <div v-else class="floor-plan-card-placeholder">
                        <i class="ri-image-line"></i>
                      </div>
                      <div class="floor-plan-card-overlay">
                        <i class="ri-zoom-in-line"></i>
                      </div>
                    </div>
                    <div class="floor-plan-card-name">{{ plan.name || `Floor Plan ${idx + 1}` }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Details Section (breakdown + assignment costs) -->
            <div class="detailed-info-section mb-16" v-if="hasPaymentDetails">
              <div class="info-section">
                <h3 class="section-title mb-20">Payment Details</h3>
                <PaymentDetailsSection :listing="property" />
              </div>
            </div>

            <!-- Notes Section -->
            <div class="detailed-info-section mb-16" v-if="property.comment && property.comment.trim()">
              <div class="info-section">
                <h3 class="section-title mb-20">Notes</h3>
                <div class="description-content">
                  <p class="description-text">{{ property.comment }}</p>
                </div>
              </div>
            </div>
  <!-- Property Documents -->
            <div class="detailed-info-section mb-16" v-if="property.additional_documents && property.additional_documents.length > 0 && property.user_permissions?.showDocuments">
              <div class="info-section">
                <h3 class="section-title mb-20">
                  <i class="ri-file-text-line me-2"></i>
                  Property Documents
                </h3>
                <div class="documents-grid">
                  <a v-for="doc in property.additional_documents" :key="doc.id"
                     :href="doc.url" target="_blank" rel="noopener" class="document-card">
                    <i class="ri-file-add-line document-icon"></i>
                    <span class="document-name text-truncate">{{ doc.name || 'Document' }}</span>
                    <i class="ri-external-link-line document-action"></i>
                  </a>
                </div>
              </div>
            </div>
              <div class="comment-container" v-if="!onlyShow">
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
                <div class="comments-list" v-if="!onlyShow">
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
            <div v-else-if="loading" class="property-content ">
              <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading property details...</p>
              </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="property-content ">
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
      <div ref="propertySidebarColRef" class="col-lg-3 property-show-sidebar-col">
        <div ref="propertySidebarSpacerRef" class="property-sidebar-spacer" aria-hidden="true"></div>
        <div ref="propertySidebarStickyRef" class="sidebar-sticky-container">
          <div class="agent-sidebar-card">
            
            <!-- Agent Profile Section -->
        <div class="agent-profile" v-if="property && property.agent">
          <img 
            :src="property.agent.avatar || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'" 
            alt="Agent" 
            class="agent-sidebar-avatar" 
          />
          <div class="agent-sidebar-info">
            <h5 class="agent-sidebar-name">{{ property.agent.name || 'Agent Name' }}</h5>
            <button v-if="!onlyShow"
              class="btn-show-agent-details" 
              @click="goToAgentDetails(property.agent.id)"
            >
              <i class="ri-user-line me-1"></i>
              Show Agent Details
            </button>
          </div>
        </div>


            <div class="sidebar-section" v-if="isPropertyOwner ">
                <br>
              <div class="request-actions-grid">
                <!-- Unit Number Info -->
                <div class="request-action-item">
                  <div class="approved-info">
                    <div class="info-display">
                      <i class="ri-home-4-line"></i>
                      <span class="info-value">{{ property.unit_number || 'Not Set' }}</span>
                    </div>
                  
                  </div>
                </div>

                <!-- Owner Information -->
                <div class="request-action-item">
                  <div class="approved-info">
                    <div class="info-display" @click="openOwnerDetailsModal" style="cursor: pointer;">
                      <i class="ri-user-line"></i>
                      <span class="info-value">View Owner Info</span>
                    </div>
                  
                  </div>
                </div>
              </div>
              
              <!-- Note for owner -->
            
            </div>
            <div class="sidebar-section" v-if="((requestStatus?.owner_info_status === 'approved' && property?.canShowOwner) || (requestStatus?.unit_number_status === 'approved' && property?.canShowUnitNumber)) && !onlyShow">
              <h6 class="sidebar-title">Request Access
                <span v-if="loadingRequest" class="loading-spinner-small"></span>
              </h6>
              
              <div class="request-actions-grid">
                <!-- Unit Number Request -->
                <div class="request-action-item">
                  <!-- If unit number is approved -->
                  <div v-if="requestStatus?.unit_number_status === 'approved' && property?.canShowUnitNumber" class="approved-info">
                    <div class="info-display">
                      <i class="ri-home-4-line"></i>
                      <span class="info-value">{{ getUnitNumber() }}</span>
                    </div>
                  </div>
                 <div v-else-if="requestStatus?.unit_number_status === 'pending'" class="pending-info">
                    <div class="pending-status">
                      <i class="ri-time-line"></i>
                      <span class="status-text">Pending</span>
                    </div>
                    <button class="btn-cancel-small" @click="cancelRequest('unit_number')">
                      <i class="ri-close-line"></i>
                    </button>
                  </div>
                  
                

                </div>

                <!-- Owner Information Request -->
                <div class="request-action-item">
                  <!-- If owner info is approved -->
                  <div v-if="requestStatus?.owner_info_status === 'approved' && property?.canShowOwner && getApprovedOwnerData()" class="approved-info">
                    <div class="info-display" @click="openOwnerDetailsModal" style="cursor: pointer;">
                      <i class="ri-user-line"></i>
                      <span class="info-value">view Owner Info</span>
                    </div>
                  </div>

                 

             
                </div>
              </div>
            </div>
           <!-- Property Actions Dropdown -->
            <div class="sidebar-section " v-if="!onlyShow">
              <div class="property-actions-dropdown-wrapper">
                <div class="property-actions-dropdown">
                  <button 
                    class="dropdown-toggle"
                    @click="toggleActionsDropdown"
                  >
                    Property Actions
                  </button>
                  
                  <div class="dropdown-container" :class="{ expanded: showActionsDropdown }">
                    <div class="dropdown-menu" :class="{ show: showActionsDropdown }">
                        <button 
                          v-if="canApproveListings && !property.approved && property.status !== 'converted' && property.status !== 'rented'"
                          class="dropdown-item success"
                          @click="approveListing"
                        >
                          <i class="ri-checkbox-circle-line"></i>
                          Approve Listing
                        </button>
                        
                        <button 
                          v-if="canApproveListings && property.approved"
                          class="dropdown-item warning"
                          @click="openRejectFromDetailsModal"
                        >
                          <i class="ri-close-circle-line"></i>
                          Remove Approval / Reject
                        </button>
                          <!-- Create Offer -->
                              <button   v-if="canGenerateOffer"
                                class="dropdown-item"
                                @click="generatePDF"
                              >
                                <i class="ri-file-pdf-line"></i>
                                Create Offer
                              </button>
                              <button  v-if="canShowOffers"
                                  class="dropdown-item"
                                  @click="showOfferHistory"
                                >
                                  <i class="ri-history-line"></i>
                                  View Offer History
                                </button>
                                <button v-if="canDeleteProperty" class="dropdown-item" @click="confirmDeleteProperty">
                                  <i class="ri-delete-bin-line"></i>
                                  Delete Property
                                </button> 
                              <!-- Edit Property -->
                              <button 
                                v-if="canEditProperty" 
                                class="dropdown-item"
                                @click="editProperty"
                              >
                                <i class="ri-edit-line"></i>
                                Edit Property
                              </button>
                    
                              <!-- Active/Inactive -->
                              <button 
                                v-if="canEditProperty"
                                class="dropdown-item"
                                @click="toggleActive"
                              >
                                <i class="ri-toggle-line" v-if="property.is_active"></i>
                                <i class="ri-toggle-fill" v-else></i>
                                {{ property.is_active ? 'Set Inactive' : 'Set Active' }}
                              </button>
                    
                              <!-- Assign to Agent -->
                              <button 
                                v-if="canAssignAgent && property.status != 'converted' && property.status != 'rented'"
                                class="dropdown-item"
                                @click="openAssignAgentModal"
                              >
                                <i class="ri-user-shared-line"></i>
                                Assign to Agent
                              </button>

                              <!-- Chat with Agent -->
                              <button
                                v-if="canUsePropertyChat && property?.agent"
                                class="dropdown-item"
                                @click="handleChatWithAgentClick"
                              >
                                <i class="ri-chat-3-fill"></i>
                                Chat with Agent
                              </button>
                    
                              <!-- Mark as Converted (Sold Out) -->
                              <button 
                                v-if="canMarkAsConverted && property.status !== 'converted' && property.listing_status === 'sale'"
                                class="dropdown-item success"
                                @click="openSoldOutModal"
                              >
                                <i class="ri-checkbox-circle-line"></i>
                                Mark as Sold Out
                              </button>
                    
                              <!-- Revert from Sold Out -->
                              <button 
                                v-if="canMarkAsConverted && property.status === 'converted' && property.listing_status === 'sale'"
                                class="dropdown-item warning"
                                @click="revertFromConverted"
                              >
                                <i class="ri-arrow-go-back-line"></i>
                                Revert from Sold Out
                              </button>
                              
                              <!-- Mark as Rented - only for RENT -->
                            <button 
                                v-if="canMarkAsConverted && property.status !== 'rented' && property.listing_status === 'rent'"
                                class="dropdown-item success"
                                @click="openRentedModal"
                            >
                                <i class="ri-home-gear-line"></i>
                                Mark as Rented
                            </button>
                            
                            <!-- Revert from Rented -->
                            <button 
                                v-if="canMarkAsConverted && property.status === 'rented' && property.listing_status === 'rent'"
                                class="dropdown-item warning"
                                @click="revertFromRented"
                            >
                                <i class="ri-arrow-go-back-line"></i>
                                Revert from Rented
                            </button>
                              <!-- Viewing -->
            
                    <div v-if="!isPropertyOwner && property?.completion_status=='Completed'" class="dropdown-item-btn">
                          <div v-if="requestStatus?.viewing_status === 'approved'" class="dropdown-item approved-info viewing">
                            <div>
                              <i class="ri-checkbox-circle-line text-success"></i>
                              <span>Viewing Approved</span>
                            </div>
                            <div>
                              <small v-if="requestStatus?.viewing_details" class="request-time viewing">
                                {{ formatDate(requestStatus.viewing_details.date) }} at {{ formatTime(requestStatus.viewing_details.time) }}
                              </small>
                            </div>
                          </div>
                        
                          <div v-else-if="requestStatus?.viewing_status === 'in_progress'" class="dropdown-item approved-info viewing" style="width:100%">
                              <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                <div>
                                  <div>
                                    <i class="ri-checkbox-circle-line text-success"></i>
                                    <span>Viewing In Progress</span>
                                  </div>
                                  <div>
                                    <small v-if="requestStatus?.viewing_details" class="request-time viewing">
                                      {{ formatDate(requestStatus.viewing_details.date) }} at {{ formatTime(requestStatus.viewing_details.time) }}
                                    </small>
                                  </div>
                                </div>
                                <div>
                                  <button
                                    class="btn-cancel-small"
                                            @click="handleCancelViewingClick($event)"

                                    :disabled="cancellingSpecificRequest"
                                    style="background: #dc3545; border: none; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                                  >
                                    <i class="ri-close-line" style="color: white; font-size: 12px;"></i>
                                  </button>
                                </div>
                              </div>
                            </div>
                          <div v-else-if="requestStatus?.viewing_status === 'pending'" class="dropdown-item pending-info">
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                              <div style="display: flex; align-items: center; gap: 8px; flex-direction:column">
                                <div  style="display: flex; gap: 8px;">
                                  <i class="ri-time-line text-warning"></i>
                                  <span class="text-warning">Viewing Pending</span>
                                </div>
                                <small v-if="requestStatus?.viewing_details" class="request-time viewing">
                                  {{ formatDate(requestStatus.viewing_details.date) }} {{ formatTime(requestStatus.viewing_details.time) }}
                                </small>
                              </div>
                              <button
                                class="btn-cancel-small"
                                @click.stop="cancelRequest('viewing')"
                                :disabled="cancellingRequest"
                                style="background: #dc3545; border: none; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                              >
                                <i class="ri-close-line" style="color: white; font-size: 12px;"></i>
                              </button>
                            </div>
                          </div>
                        
                          <button
                            v-else
                            class="dropdown-item"
                            @click.stop="openViewingModal"
                            :disabled="loadingRequest || cancellingRequest"
                            style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;"
                          >
                            <i class="ri-calendar-line"></i>
                            <span>Request Viewing</span>
                          </button>
                        </div>

                                  <!-- Unit Number Request -->
            
                     <div v-if="!isPropertyOwner" class="dropdown-item-btn">
                        <div v-if="requestStatus?.unit_number_status === 'approved'" class="dropdown-item approved-info">
                          <i class="ri-checkbox-circle-line text-success"></i>
                          <span>Unit Number Approved</span>
                        </div>
                        
                        <div v-else-if="requestStatus?.unit_number_status === 'pending'" class="dropdown-item pending-info">
                          <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                              <i class="ri-time-line text-warning"></i>
                              <span class="text-warning">Unit Number Pending</span>
                            </div>
                            <button 
                              class="btn-cancel-small" 
                              @click.stop="cancelRequest('unit_number')"
                              :disabled="cancellingRequest"
                              style="background: #dc3545; border: none; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                            >
                              <i class="ri-close-line" style="color: white; font-size: 12px;"></i>
                            </button>
                          </div>
                        </div>
                        
                        <button 
                          v-else
                          class="dropdown-item"
                          @click.stop="requestUnitNumber" 
                          :disabled="loadingRequest || cancellingRequest"
                          style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;"
                        >
                          <i class="ri-home-4-line"></i>
                          <span v-if="loadingRequest">Sending...</span>
                          <span v-else>Request Unit Number</span>
                        </button>
                      </div>
            
                      <!-- Owner Info Request -->
                      <div v-if="!isPropertyOwner" class="dropdown-item-btn">
                        <div v-if="requestStatus?.owner_info_status === 'approved'" class="dropdown-item approved-info">
                          <i class="ri-checkbox-circle-line text-success"></i>
                          <span>Owner Info Approved</span>
                        </div>
                        
                        <div v-else-if="requestStatus?.owner_info_status === 'pending'" class="dropdown-item pending-info">
                          <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                              <i class="ri-time-line text-warning"></i>
                              <span class="text-warning">Owner Info Pending</span>
                            </div>
                            <button 
                              class="btn-cancel-small" 
                              @click.stop="cancelRequest('owner_data')"
                              :disabled="cancellingRequest"
                              style="background: #dc3545; border: none; width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                            >
                              <i class="ri-close-line" style="color: white; font-size: 12px;"></i>
                            </button>
                          </div>
                        </div>
                        
                        <button 
                          v-else
                          class="dropdown-item"
                          @click.stop="requestOwnerInfo" 
                          :disabled="loadingRequest || cancellingRequest"
                          style="display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;"
                        >
                          <i class="ri-user-search-line"></i>
                          <span v-if="loadingRequest">Sending...</span>
                          <span v-else>Request Owner Info</span>
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
      
    </div>

    <!-- Assign Agent Modal -->
    <div v-if="showAssignAgentModal" class="modal-overlay" @click="showAssignAgentModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h4 class="text-white">
            <i class="ri-user-shared-line me-2"></i>
            Assign Property to Agent
          </h4>
          <button class="modal-close" @click="showAssignAgentModal = false">
            <i class="ri-close-line"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Select Agent</label>
       
          <v-select 
          v-model="selectedAgentId"
          :options="availableAgents"
          placeholder="Choose an agent..."
          label="name"
          :reduce="agent => agent.id"
          @update:modelValue="handleAgentChange"
        >
          <template #option="agent">
            {{ agent.name }} 
          </template>
        </v-select>

          </div>
          
          <div class="form-group">
            <label class="form-label">Assignment Notes (Optional)</label>
            <textarea 
              v-model="assignmentNotes" 
              class="form-control" 
              rows="3" 
              placeholder="Add any notes about this assignment..."
            ></textarea>
          </div>
        </div>
        
        <div class="modal-footer">
          <button class="btn-modal btn-modal-secondary" @click="showAssignAgentModal = false">
            Cancel
          </button>
          <button 
            class="btn-modal btn-modal-primary" 
            @click="assignToAgent"
            :disabled="!selectedAgentId || assigningAgent"
          >
            <i class="ri-user-shared-line me-2"></i>
            {{ assigningAgent ? 'Assigning...' : 'Assign Agent' }}
          </button>
        </div>
      </div>
    </div>

<!-- Owner Details Modal -->
<div v-if="showOwnerDetailsModal" class="modal-overlay" @click="showOwnerDetailsModal = false">
  <div class="modal-content owner-details-modal" @click.stop>
    <div class="modal-header">
      <div class="header-content">
        <i class="ri-user-3-line header-icon"></i>
        <div>
          <h4 class="modal-title">Owner Information</h4>
          <p class="modal-subtitle">Complete owner details</p>
        </div>
      </div>
      <button class="modal-close" @click="showOwnerDetailsModal = false">
        <i class="ri-close-line"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <!-- Owner Summary Card -->
      <div class="owner-summary-card" v-if="getOwnerDataForModal()">
        <div class="owner-name-section">
          <h3 class="owner-name">
            {{ getOwnerDataForModal()?.first_name || '' }} 
            {{ getOwnerDataForModal()?.last_name || '' }}
          </h3>
          <div class="owner-identifiers">
            <span class="owner-id" v-if="getOwnerDataForModal()?.salutation">
              {{ getOwnerDataForModal()?.salutation }}
            </span>
            <span class="owner-nationality" v-if="getOwnerDataForModal()?.nationality">
              {{ getOwnerDataForModal()?.nationality }}
            </span>
            <span class="owner-status" 
                  v-if="getOwnerDataForModal()?.residency_status"
                  :class="getOwnerDataForModal()?.residency_status === 'resident' ? 'resident' : 'non-resident'">
              {{ getOwnerDataForModal()?.residency_status === 'resident' ? 'Resident' : 'Non-Resident' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="contact-section">
        <h5 class="section-title">
          <i class="ri-phone-line"></i>
          Contact Information
        </h5>
        
        <div class="contact-list">
          <!-- Primary Phone -->
          <div class="contact-item" v-if="getOwnerDataForModal()?.phone_number">
            <div class="contact-label">Primary Phone:</div>
            <div class="contact-value">
              {{ getOwnerDataForModal()?.phone_number }}
              <div class="contact-actions">
                <button class="contact-btn call" @click="callOwner(getOwnerDataForModal()?.phone_number)" title="Call">
                  <i class="ri-phone-line"></i>
                </button>
                <button class="contact-btn whatsapp" @click="whatsappOwner(getOwnerDataForModal()?.phone_number)" title="WhatsApp">
                  <i class="ri-whatsapp-line"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- WhatsApp Number -->
          <div class="contact-item" v-if="getOwnerDataForModal()?.whatsapp_number">
            <div class="contact-label">WhatsApp:</div>
            <div class="contact-value">
              {{ getOwnerDataForModal()?.whatsapp_number }}
              <div class="contact-actions">
                <button class="contact-btn whatsapp" @click="whatsappOwner(getOwnerDataForModal()?.whatsapp_number)" title="Open WhatsApp">
                  <i class="ri-whatsapp-line"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Secondary Phone -->
          <div class="contact-item" v-if="getOwnerDataForModal()?.second_phone_number">
            <div class="contact-label">Secondary Phone:</div>
            <div class="contact-value">
              {{ getOwnerDataForModal()?.second_phone_number }}
              <div class="contact-actions">
                <button class="contact-btn call" @click="callOwner(getOwnerDataForModal()?.second_phone_number)" title="Call">
                  <i class="ri-phone-line"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Email -->
          <div class="contact-item" v-if="getOwnerDataForModal()?.email">
            <div class="contact-label">Email:</div>
            <div class="contact-value">
              {{ getOwnerDataForModal()?.email }}
              <div class="contact-actions">
                <button class="contact-btn email" @click="emailOwner(getOwnerDataForModal()?.email)" title="Send Email">
                  <i class="ri-mail-line"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Personal Information -->
      <div class="personal-section" v-if="hasPersonalInfo">
        <h5 class="section-title">
          <i class="ri-user-line"></i>
          Personal Information
        </h5>
        
        <div class="info-grid">
          <div class="info-item" v-if="getOwnerDataForModal()?.first_name">
            <span class="info-label">First Name</span>
            <span class="info-value">{{ getOwnerDataForModal()?.first_name }}</span>
          </div>
          
          <div class="info-item" v-if="getOwnerDataForModal()?.last_name">
            <span class="info-label">Last Name</span>
            <span class="info-value">{{ getOwnerDataForModal()?.last_name }}</span>
          </div>
          
          <div class="info-item" v-if="getOwnerDataForModal()?.nationality">
            <span class="info-label">Nationality</span>
            <span class="info-value">{{ getOwnerDataForModal()?.nationality }}</span>
          </div>
          
          <div class="info-item" v-if="getOwnerDataForModal()?.location_name || getOwnerDataForModal()?.location_id">
            <span class="info-label">{{ getLocationLabel() }}</span>
            <span class="info-value">{{ getOwnerDataForModal()?.location_name || getOwnerDataForModal()?.location_id }}</span>
          </div>
        </div>
      </div>

      <!-- Documents -->
      <div class="documents-section" v-if="hasOwnerDocuments">
        <h5 class="section-title">
          <i class="ri-file-text-line"></i>
          Documents
        </h5>
        
        <div class="documents-grid">
          <button class="document-card" 
                  v-if="getOwnerDataForModal()?.id_front_path || getOwnerDataForModal()?.id_front_url"
                  @click="viewDocument(getOwnerDataForModal()?.id_front_path || getOwnerDataForModal()?.id_front_url)">
            <i class="ri-id-card-line document-icon"></i>
            <span class="document-name">ID Front</span>
            <i class="ri-external-link-line document-action"></i>
          </button>
          
          <button class="document-card" 
                  v-if="getOwnerDataForModal()?.id_back_path || getOwnerDataForModal()?.id_back_url"
                  @click="viewDocument(getOwnerDataForModal()?.id_back_path || getOwnerDataForModal()?.id_back_url)">
            <i class="ri-id-card-line document-icon"></i>
            <span class="document-name">ID Back</span>
            <i class="ri-external-link-line document-action"></i>
          </button>
          
          <button class="document-card" 
                  v-if="getOwnerDataForModal()?.passport_copy_path || getOwnerDataForModal()?.passport_copy_url"
                  @click="viewDocument(getOwnerDataForModal()?.passport_copy_path || getOwnerDataForModal()?.passport_copy_url)">
            <i class="ri-passport-line document-icon"></i>
            <span class="document-name">Passport</span>
            <i class="ri-external-link-line document-action"></i>
          </button>
          
          <button class="document-card" 
                  v-if="getOwnerDataForModal()?.visa_copy_path || getOwnerDataForModal()?.visa_copy_url"
                  @click="viewDocument(getOwnerDataForModal()?.visa_copy_path || getOwnerDataForModal()?.visa_copy_url)">
            <i class="ri-file-text-line document-icon"></i>
            <span class="document-name">Visa Copy</span>
            <i class="ri-external-link-line document-action"></i>
          </button>
        </div>
      </div>
    </div>
    
    <div class="modal-footer">
      <button class="btn-close-modal" @click="showOwnerDetailsModal = false">
        Close
      </button>
    </div>
  </div>
</div>

<!-- Mark as Sold Out Modal -->
<div v-if="showSoldOutModal" class="modal-overlay" @click="closeSoldOutModal">
  <div class="modal-content sold-out-modal-content" @click.stop>
    <div class="modal-header sold-out-modal-header">
      <div class="sold-out-header-inner">
        <h4 class="sold-out-modal-title">
          <i class="ri-award-line me-2"></i>
          Mark as Sold Out
        </h4>
        <p class="sold-out-modal-subtitle">
          {{ !soldByChoice ? 'Choose how this property was sold' : 
             (soldByChoice === 'oia' ? 'Choose the selling agent' : 'Add the new owner details') }}
        </p>
      </div>
      <button type="button" class="modal-close" @click="closeSoldOutModal" aria-label="Close">
        <i class="ri-close-line"></i>
      </button>
    </div>

    <div class="modal-body sold-out-modal-body">
      <!-- المستوى الأول: الخيارات الرئيسية -->
      <template v-if="!soldByChoice">
        <div class="sold-out-options">
          <div class="option-card" @click="selectSoldBy('oia')">
            <div class="option-icon"><i class="ri-building-line"></i></div>
            <div class="option-content">
              <h6>Sold by OIA</h6>
              <p>Sold by OIA Properties</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
          
          <div class="option-card" @click="selectSoldBy('owner')">
            <div class="option-icon"><i class="ri-home-2-line"></i></div>
            <div class="option-content">
              <h6>Sold by Owner</h6>
              <p>Direct owner sale</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
        </div>
      </template>

      <!-- المستوى الثاني: اختيار نوع البيع داخل OIA -->
      <template v-else-if="soldByChoice === 'oia' && !showOIAgentSelection">
        <div class="sold-out-options">
          <div class="option-card" @click="openOIAgentSelection">
            <div class="option-icon"><i class="ri-user-star-line"></i></div>
            <div class="option-content">
              <h6>OIA Agent</h6>
              <p>Sold by an OIA agent (you or another)</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
          
          <div class="option-card" @click="openAToAModalForSale">
            <div class="option-icon"><i class="ri-user-shared-line"></i></div>
            <div class="option-content">
              <h6>A to A Transaction</h6>
              <p>Sold to another agent/company</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
        </div>
        <!-- زر Back للرجوع للمستوى الأول -->
        <div class="text-center mt-3">
          <button type="button" class="btn-modal btn-modal-secondary" @click="soldByChoice = null">
            Back
          </button>
        </div>
      </template>

      <!-- اختيار الـ OIA Agent -->
      <template v-else-if="showOIAgentSelection">
        <div class="sold-out-add-owner-step">
          <p class="sold-out-step-text">Select the OIA agent who sold this property</p>
          
          <div class="form-group mb-3">
            <label class="form-label">Select Agent *</label>
            <v-select 
                v-model="selectedSoldByAgent"
                :options="availableAgentsForSoldBy"
                placeholder="Choose an agent..."
                label="name"
                :reduce="agent => agent.id"
                :loading="loadingAgentsForSoldBy"
            >
                <template #option="agent">
                    {{ agent.name }}
                </template>
            </v-select>
            <div v-if="availableAgentsForSoldBy.length === 0 && !loadingAgentsForSoldBy" class="text-danger mt-1">
                No agents available. Please contact administrator.
            </div>
          </div>
          
          <div class="d-flex gap-2">
            <button type="button" class="btn-modal btn-modal-secondary" @click="backToOIAOptions">
              Back
            </button>
            <button 
                type="button" 
                class="btn-modal btn-modal-primary" 
                @click="markAsSoldByOIAgent"
                :disabled="!selectedSoldByAgent"
            >
              Confirm
            </button>
          </div>
        </div>
      </template>

      <!-- إضافة مالك جديد (حالة me) -->
      <template v-else-if="soldByChoice === 'me'">
        <div class="sold-out-add-owner-step">
          <p class="sold-out-step-text">Add the new owner details before marking as sold.</p>
          <div class="d-flex gap-2">
            <button type="button" class="btn-modal btn-modal-secondary" @click="backToOIAOptions">
              Back
            </button>
            <button type="button" class="btn-modal btn-modal-primary" @click="openAddOwnerModal('owner')">
              Add New Owner
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- مفيش Footer مع Cancel -->
  </div>
</div>
<!-- Mark as Rented Modal -->
<div v-if="showRentedModal" class="modal-overlay" @click="closeRentedModal">
  <div class="modal-content sold-out-modal-content" @click.stop>
    <div class="modal-header sold-out-modal-header">
      <div class="sold-out-header-inner">
        <h4 class="sold-out-modal-title">
          <i class="ri-home-gear-line me-2"></i>
          Mark as Rented
        </h4>
        <p class="sold-out-modal-subtitle">
          {{ !rentedByChoice ? 'Choose how this property was rented' : 
             (rentedByChoice === 'oia' ? 'Choose the renting agent' : 'Add the new tenant details') }}
        </p>
      </div>
      <button type="button" class="modal-close" @click="closeRentedModal">
        <i class="ri-close-line"></i>
      </button>
    </div>

    <div class="modal-body sold-out-modal-body">
      <!-- المستوى الأول -->
      <template v-if="!rentedByChoice">
        <div class="sold-out-options">
          <div class="option-card" @click="selectRentedBy('oia')">
            <div class="option-icon"><i class="ri-building-line"></i></div>
            <div class="option-content">
              <h6>Rented by OIA</h6>
              <p>Rented by OIA Properties</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
          
          <div class="option-card" @click="selectRentedBy('owner')">
            <div class="option-icon"><i class="ri-home-2-line"></i></div>
            <div class="option-content">
              <h6>Rented by Owner</h6>
              <p>Direct owner rental</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
        </div>
      </template>

      <!-- المستوى الثاني: OIA Options -->
      <template v-else-if="rentedByChoice === 'oia' && !showOIAgentSelectionRent">
        <div class="sold-out-options">
          <div class="option-card" @click="openOIAgentSelectionRent">
            <div class="option-icon"><i class="ri-user-star-line"></i></div>
            <div class="option-content">
              <h6>OIA Agent</h6>
              <p>Rented by an OIA agent (you or another)</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
          
          <div class="option-card" @click="openAToAModalForRent">
            <div class="option-icon"><i class="ri-user-shared-line"></i></div>
            <div class="option-content">
              <h6>A to A Transaction</h6>
              <p>Rented to another agent/company</p>
            </div>
            <div class="option-arrow"><i class="ri-arrow-right-s-line"></i></div>
          </div>
        </div>
            <div class="text-center mt-3">
                <button type="button" class="btn-modal btn-modal-secondary" @click="rentedByChoice = null">
                    Back
                </button>
            </div>
      </template>

      <!-- اختيار الـ OIA Agent للإيجار -->
      <template v-else-if="showOIAgentSelectionRent">
        <div class="sold-out-add-owner-step">
          <p class="sold-out-step-text">Select the OIA agent who rented this property</p>
          
          <div class="form-group mb-3">
            <label class="form-label">Rented Date *</label>
            <input type="date" v-model="rentedDate" class="form-control" :min="today" required />
          </div>
          
          <div class="form-group mb-3">
            <label class="form-label">Select Agent *</label>
            <v-select 
                v-model="selectedRentedByAgent"
                :options="availableAgentsForRentedBy"
                placeholder="Choose an agent..."
                label="name"
                :reduce="agent => agent.id"
                :loading="loadingAgentsForRentedBy"
            />
          </div>
          
          <div class="d-flex gap-2">
            <button type="button" class="btn-modal btn-modal-secondary" @click="backToOIAOptionsRent">
              Back
            </button>
            <button 
                type="button" 
                class="btn-modal btn-modal-primary" 
                @click="markAsRentedByOIAgent"
                :disabled="!selectedRentedByAgent || !rentedDate"
            >
              Confirm
            </button>
          </div>
        </div>
      </template>

      <!-- حالة me (نفس المستخدم) -->
      <template v-else-if="rentedByChoice === 'me'">
        <div class="sold-out-add-owner-step">
          <p class="sold-out-step-text">Add the new tenant details before marking as rented.</p>
          <div class="form-group mb-3">
            <label class="form-label">Rented Date *</label>
            <input type="date" v-model="rentedDate" class="form-control" :min="today" required />
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn-modal btn-modal-secondary" @click="backToOIAOptionsRent">
              Back
            </button>
            <button type="button" class="btn-modal btn-modal-primary" @click="openAddOwnerModal('tenant')">
              Add New Tenant
            </button>
          </div>
        </div>
      </template>

      <!-- حالة owner -->
      <template v-else-if="rentedByChoice === 'owner'">
        <div class="sold-out-add-owner-step">
          <p class="sold-out-step-text">Confirm rental by owner</p>
          <div class="form-group mb-3">
            <label class="form-label">Rented Date *</label>
            <input type="date" v-model="rentedDate" class="form-control" :min="today" required />
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn-modal btn-modal-secondary" @click="rentedByChoice = null">
              Back
            </button>
            <button type="button" class="btn-modal btn-modal-primary" @click="markAsRentedByOwnerConfirm">
              Confirm
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- مفيش Cancel هنا -->
  </div>
</div>
<!-- A to A Modal -->
<div v-if="showAToAModal" class="modal-overlay" @click="closeAToAModal">
  <div class="modal-content" style="max-width: 500px;" @click.stop>
    <div class="modal-header">
      <div class="header-content">
        <i class="ri-user-shared-line header-icon"></i>
        <div>
          <h4 class="modal-title">A to A Transaction</h4>
          <p class="modal-subtitle">Enter the receiving agent/company details</p>
        </div>
      </div>
      <button class="modal-close" @click="closeAToAModal">
        <i class="ri-close-line"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <div class="form-group mb-3">
        <label class="form-label">Company Name <span class="text-danger">*</span></label>
        <input type="text" v-model="aToAData.company_name" class="form-control" placeholder="Enter company name" />
      </div>
      <div class="form-group mb-3">
        <label class="form-label">Agent Name <span class="text-danger">*</span></label>
        <input type="text" v-model="aToAData.agent_name" class="form-control" placeholder="Enter agent name" />
      </div>
      
      <div class="form-group mb-3">
        <label class="form-label">Agent Phone <span class="text-danger">*</span></label>
        <input type="tel" v-model="aToAData.agent_phone" class="form-control" placeholder="Enter agent phone number" />
      </div>
      
      <div class="form-group mb-3">
        <label class="form-label">Commercial License Number <span class="text-danger">*</span></label>
        <input type="text" v-model="aToAData.commercial_license" class="form-control" placeholder="Enter commercial license number" />
      </div>
      
      <div class="form-group" v-if="aToAType === 'rented'">
        <label class="form-label">Rented Date <span class="text-danger">*</span></label>
        <input type="date" v-model="rentedDate" class="form-control" :min="today" required />
      </div>
    </div>
    
    <div class="modal-footer">
      <button 
        class="btn-modal btn-modal-primary w-100" 
        @click="aToAType === 'sold' ? submitAToA() : submitAToAForRent()"
        :disabled="submittingATO || !aToAData.company_name.trim() || !aToAData.agent_name.trim() || !aToAData.agent_phone.trim() || !aToAData.commercial_license.trim() || (aToAType === 'rented' && !rentedDate)"
      >
        <i class="ri-checkbox-circle-line me-2"></i>
        {{ submittingATO ? 'Submitting...' : 'Confirm Transaction' }}
      </button>
    </div>
  </div>
</div>
<!-- Other Company Modal -->
<div v-if="showOtherCompanyModal" class="modal-overlay" @click="closeOtherCompanyModal">
  <div class="modal-content" style="max-width: 500px;" @click.stop>
    <div class="modal-header">
      <div class="header-content">
        <i class="ri-building-line header-icon"></i>
        <div>
          <h4 class="modal-title">Other Company Details</h4>
          <p class="modal-subtitle">Enter the company and agent information</p>
        </div>
      </div>
      <button class="modal-close" @click="closeOtherCompanyModal">
        <i class="ri-close-line"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <!-- Company Name -->
      <div class="form-group mb-3">
        <label class="form-label">Company Name <span class="text-danger">*</span></label>
        <input 
          type="text" 
          v-model="otherCompanyData.company_name" 
          class="form-control" 
          placeholder="Enter company name"
          :class="{ 'is-invalid': !otherCompanyData.company_name.trim() && submittedOtherCompany }"
        />
        <div class="invalid-feedback" v-if="!otherCompanyData.company_name.trim() && submittedOtherCompany">
          Company name is required
        </div>
      </div>
      
      <!-- Agent Name -->
      <div class="form-group mb-3">
        <label class="form-label">Agent Name <span class="text-danger">*</span></label>
        <input 
          type="text" 
          v-model="otherCompanyData.agent_name" 
          class="form-control" 
          placeholder="Enter agent name"
          :class="{ 'is-invalid': !otherCompanyData.agent_name.trim() && submittedOtherCompany }"
        />
        <div class="invalid-feedback" v-if="!otherCompanyData.agent_name.trim() && submittedOtherCompany">
          Agent name is required
        </div>
      </div>
      
      <!-- Agent Phone -->
      <div class="form-group mb-3">
        <label class="form-label">Agent Phone <span class="text-danger">*</span></label>
        <input 
          type="tel" 
          v-model="otherCompanyData.agent_phone" 
          class="form-control" 
          placeholder="Enter agent phone number"
          :class="{ 'is-invalid': !otherCompanyData.agent_phone.trim() && submittedOtherCompany }"
        />
        <div class="invalid-feedback" v-if="!otherCompanyData.agent_phone.trim() && submittedOtherCompany">
          Agent phone is required
        </div>
      </div>
      
      <!-- Agent Email -->
      <div class="form-group mb-3">
        <label class="form-label">Agent Email</label>
        <input 
          type="email" 
          v-model="otherCompanyData.agent_email" 
          class="form-control" 
          placeholder="Enter agent email (optional)"
        />
      </div>
      
      <!-- Rented Date (only for rent type) -->
      <div class="form-group" v-if="otherCompanyType === 'rented'">
        <label class="form-label">Rented Date <span class="text-danger">*</span></label>
        <input 
          type="date" 
          v-model="rentedDate" 
          class="form-control"
          :min="today"
          required
          :class="{ 'is-invalid': !rentedDate && submittedOtherCompany }"
        />
        <div class="invalid-feedback" v-if="!rentedDate && submittedOtherCompany">
          Rented date is required
        </div>
      </div>
    </div>
    
    <div class="modal-footer">
      <button class="btn-modal btn-modal-secondary" @click="closeOtherCompanyModal">
        Cancel
      </button>
      <button 
        class="btn-modal btn-modal-primary" 
        @click="submitOtherCompany"
        :disabled="submittingOtherCompany || !otherCompanyData.company_name.trim() || !otherCompanyData.agent_name.trim() || !otherCompanyData.agent_phone.trim()"
      >
        <i class="ri-checkbox-circle-line me-2"></i>
        {{ submittingOtherCompany ? 'Submitting...' : (otherCompanyType === 'sold' ? 'Mark as Sold' : 'Mark as Rented') }}
      </button>
    </div>
  </div>
</div>
<!-- Add New Owner Modal (for Sold by Me / Oia) -->
<div v-if="showAddOwnerModal" class="modal-overlay add-owner-modal-overlay" @click="showAddOwnerModal = false">
  <div class="modal-content add-owner-modal-content" style="max-width: 1200px; width: 95%; max-height: 95vh; overflow-y: auto;" @click.stop>
    <div class="modal-header sold-out-modal-header">
      <div class="sold-out-header-inner">
        <h4 class="sold-out-modal-title">
          <i class="ri-user-add-line me-2"></i>
          Add New {{ addPersonType === 'tenant' ? 'Tenant' : 'Owner' }}
        </h4>
        <p class="sold-out-modal-subtitle">Enter the new owner details for this property</p>
      </div>
      <button type="button" class="modal-close" @click="showAddOwnerModal = false" aria-label="Close">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="modal-body add-owner-modal-body" style="padding: 1.5rem;">
      <div class="row g-3">
        <!-- Salutation -->
        <div class="col-md-4">
          <label class="form-label">Salutation <span class="text-danger">*</span></label>
          <select v-model="newOwner.salutation" class="form-select">
            <option value="">Select...</option>
            <option>Mr</option>
            <option>Mrs</option>
            <option>Ms</option>
            <option>Dr</option>
          </select>
        </div>
        <!-- First Name -->
        <div class="col-md-4">
          <label class="form-label">First Name <span class="text-danger">*</span></label>
          <input v-model="newOwner.first_name" type="text" class="form-control" placeholder="First name" @input="onlyLettersOwner('first_name')" />
        </div>
        <!-- Last Name -->
        <div class="col-md-4">
          <label class="form-label">Last Name <span class="text-danger">*</span></label>
          <input v-model="newOwner.last_name" type="text" class="form-control" placeholder="Last name" @input="onlyLettersOwner('last_name')" />
        </div>
        <!-- Email -->
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input v-model="newOwner.email" type="email" class="form-control" placeholder="Email" />
        </div>
        <!-- Phone -->
        <div class="col-md-6">
          <label class="form-label">Phone <span class="text-danger">*</span></label>
          <input v-model="newOwner.phone_number" type="text" class="form-control" placeholder="Phone number" @input="onlyNumbersOwner('phone_number')" />
        </div>
        <!-- Whatsapp Number -->
        <div class="col-md-6">
          <label class="form-label">Whatsapp Number</label>
          <input v-model="newOwner.whatsapp_number" type="text" class="form-control" placeholder="Whatsapp number" @input="onlyNumbersOwner('whatsapp_number')" />
        </div>
        <!-- Second Phone -->
        <div class="col-md-6">
          <label class="form-label">Second Phone</label>
          <input v-model="newOwner.second_phone_number" type="text" class="form-control" placeholder="Second phone" @input="onlyNumbersOwner('second_phone_number')" />
        </div>
        <!-- Nationality -->
        <div class="col-md-6">
          <label class="form-label">Nationality</label>
          <v-select v-model="newOwner.nationality" :options="ownerNationalities" placeholder="Select nationality" @update:modelValue="handleOwnerNationalityChange" />
        </div>
        <!-- Residency Status -->
        <div class="col-md-3">
          <label class="form-label">Residency Status</label>
          <select v-model="newOwner.residency_status" class="form-select" :disabled="newOwner.nationality === 'UAE'">
            <option value="">Select...</option>
            <option value="resident">Resident</option>
            <option value="non_resident">Non Resident</option>
          </select>
        </div>
        <!-- Location -->
        <div class="col-md-3">
          <label class="form-label">{{ getOwnerLocationLabel() }}</label>
          <v-select v-model="newOwner.location_id" :options="ownerLocations" label="name" :reduce="(loc) => loc.id" :placeholder="getOwnerLocationPlaceholder()" :disabled="!newOwner.residency_status || ownerLocations.length === 0" />
        </div>
        <!-- Documents Section -->
        <div class="col-12">
          <hr class="my-3">
          <h6 class="mb-3">Documents</h6>
        </div>
        <!-- ID Front -->
        <div class="col-md-6 col-lg-3">
          <label class="form-label">ID Front</label>
          <input type="file" class="form-control" @change="handleOwnerFileUpload($event, 'id_front')" accept="image/*,.pdf">
          <div v-if="newOwner.id_front" class="mt-1">
            <small class="text-success">File selected: {{ newOwner.id_front.name }}</small>
          </div>
        </div>
        <!-- ID Back -->
        <div class="col-md-6 col-lg-3">
          <label class="form-label">ID Back</label>
          <input type="file" class="form-control" @change="handleOwnerFileUpload($event, 'id_back')" accept="image/*,.pdf">
          <div v-if="newOwner.id_back" class="mt-1">
            <small class="text-success">File selected: {{ newOwner.id_back.name }}</small>
          </div>
        </div>
        <!-- Visa Copy -->
        <div class="col-md-6 col-lg-3">
          <label class="form-label">Visa Copy</label>
          <input type="file" class="form-control" @change="handleOwnerFileUpload($event, 'visa_copy')" accept="image/*,.pdf">
          <div v-if="newOwner.visa_copy" class="mt-1">
            <small class="text-success">File selected: {{ newOwner.visa_copy.name }}</small>
          </div>
        </div>
        <!-- Passport Copy -->
        <div class="col-md-6 col-lg-3">
          <label class="form-label">Passport Copy</label>
          <input type="file" class="form-control" @change="handleOwnerFileUpload($event, 'passport_copy')" accept="image/*,.pdf">
          <div v-if="newOwner.passport_copy" class="mt-1">
            <small class="text-success">File selected: {{ newOwner.passport_copy.name }}</small>
          </div>
        </div>
        <!-- Additional Documents -->
        <div class="col-12">
          <label class="form-label">Additional Documents</label>
          <input type="file" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.svg" @change="handleOwnerAdditionalDocumentsUpload">
          <div v-if="newOwner.additionalDocuments && newOwner.additionalDocuments.length" class="mt-2">
            <small class="text-muted d-block mb-1">Selected ({{ newOwner.additionalDocuments.length }})</small>
            <div v-for="(item, idx) in newOwner.additionalDocuments" :key="'new-' + idx" class="d-flex align-items-center justify-content-between small mb-1">
              <span class="text-truncate">{{ item.name || item.file?.name }}</span>
              <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeOwnerAdditionalDocument(idx)">Remove</button>
            </div>
          </div>
          <small class="text-muted">PDF, JPG, PNG, SVG. Max 10MB per file.</small>
        </div>
        <!-- Notes -->
        <div class="col-12">
          <label class="form-label">Notes</label>
          <textarea v-model="newOwner.notes" rows="3" class="form-control" placeholder="Additional notes..."></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer sold-out-modal-footer">
      <button type="button" class="btn-modal btn-modal-secondary" @click="showAddOwnerModal = false">Cancel</button>
      <button
        type="button"
        class="btn-modal btn-modal-primary"
        :disabled="isSubmittingOwner || !newOwner.first_name?.trim() || !newOwner.last_name?.trim() || !newOwner.phone_number?.trim() || !newOwner.salutation"
        @click="submitNewOwnerAndMarkSold"
      >
        <span v-if="isSubmittingOwner" class="spinner-border spinner-border-sm me-2"></span>
        {{ isSubmittingOwner ? 'Saving...' : 'Save Owner' }}
      </button>
    </div>
  </div>
</div>
    <!-- Lightbox Modal -->
    <div v-if="showLightbox && property && property.gallery_images" class="lightbox-overlay" @click="closeLightbox">
      <div class="lightbox-content" @click.stop>
        <div class="lightbox-header">
          <div class="lightbox-header-right">
          <button class="lightbox-close" @click="closeLightbox">
            <i class="ri-close-line"></i>
          </button>
          </div>
        
        </div>

        
        <div class="lightbox-main">
          <button class="lightbox-nav lightbox-prev" @click="prevImage" :disabled="currentImageIndex === 0">
            <i class="ri-arrow-left-s-line"></i>
          </button>
          
          <div class="lightbox-image-container">
            <img 
              :src="getImageUrl(property.gallery_images[currentImageIndex]?.image_url_final)" 
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
            <img :src="getImageUrl(image.image_url_final)" :alt="'Thumbnail ' + (index + 1)" />
          </div>
        </div>
      </div>
    </div>
    </div>

  <!-- Floor Plan Slider Modal -->
<div v-if="showFloorPlanSlider && property && property.floor_plans" class="lightbox-overlay" @click="closeFloorPlanSlider">
  <div class="lightbox-content floor-plan-slider" @click.stop>
    <div class="lightbox-header">
      <!-- <div class="floor-plan-title">
        <i class="ri-building-line me-2"></i>
        Floor Plans
      </div> -->
      <div class="lightbox-header-right">
        <div class="lightbox-counter">
          {{ currentFloorPlanIndex + 1 }} / {{ property.floor_plans.length }}
        </div>
        <button class="lightbox-close" @click="closeFloorPlanSlider">
          <i class="ri-close-line"></i>
        </button>
      </div>
    </div>
    
    <div class="lightbox-main floor-plan-main">
      <button class="lightbox-nav lightbox-prev" @click="prevFloorPlan" :disabled="currentFloorPlanIndex === 0">
        <i class="ri-arrow-left-s-line"></i>
      </button>
      
      <div class="floor-plan-image-container">
        <img 
          :src="property.floor_plans[currentFloorPlanIndex]?.image_url" 
          :alt="property.floor_plans[currentFloorPlanIndex]?.name || 'Floor Plan'"
          class="floor-plan-slider-image"
          @error="handleFloorPlanError"
        />
        <div class="floor-plan-name-display" v-if="property.floor_plans[currentFloorPlanIndex]?.name" >
          {{ property.floor_plans[currentFloorPlanIndex].name }}
        </div>
      </div>
      
      <button class="lightbox-nav lightbox-next" @click="nextFloorPlan" :disabled="currentFloorPlanIndex === (property.floor_plans.length - 1)">
        <i class="ri-arrow-right-s-line"></i>
      </button>
    </div>

    <div class="lightbox-thumbnails floor-plan-thumbnails">
      <div 
        v-for="(floorPlan, index) in property.floor_plans" 
        :key="floorPlan.id" 
        class="lightbox-thumbnail floor-plan-thumbnail"
        :class="{ active: currentFloorPlanIndex === index }"
        @click="setCurrentFloorPlan(index)"
      >
        <img :src="floorPlan.image_url" :alt="floorPlan.name || 'Thumbnail'" @error="handleFloorPlanError" />
        <div class="floor-plan-thumbnail-name" v-if="floorPlan.name">
          {{ floorPlan.name }}
        </div>
      </div>
    </div>
    
    <div class="floor-plan-actions-bottom">
    
      
    </div>
  </div>
</div>
<!-- Viewing Request Modal -->
<div v-if="showViewingModal" class="modal-overlay" @click="showViewingModal = false">
  <div class="modal-content" @click.stop>
    <div class="modal-header">
      <h6 class="text-white">
        <i class="ri-calendar-line me-2"></i>
        Schedule Property Viewing
      </h6>
      <button class="modal-close" @click="showViewingModal = false">
        <i class="ri-close-line"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Viewing Date *</label>
        <input 
          type="date" 
          v-model="viewingRequest.date"
          :min="today"
          class="form-control"
          required
        />
      </div>
      
      <div class="form-group">
        <label class="form-label">Viewing Time *</label>
        <select v-model="viewingRequest.time" class="form-control" required>
          <option value="">Select Time</option>
          <option v-for="timeSlot in timeSlots" :key="timeSlot.value" :value="timeSlot.value">
            {{ timeSlot.label }}
          </option>
        </select>
      </div>
      
     
      
  
    </div>
    
    <div class="modal-footer">
      <button class="btn-modal btn-modal-secondary" @click="showViewingModal = false">
        Cancel
      </button>
      <button 
        class="btn-modal btn-modal-primary" 
        @click="submitViewingRequest"
        :disabled="!viewingRequest.date || !viewingRequest.time || submittingViewing"
      >
        <i class="ri-send-plane-line me-2"></i>
        {{ submittingViewing ? 'Submitting...' : 'Submit Request' }}
      </button>
    </div>
  </div>
</div>


<!-- Cancel Reason Modal -->
<div v-if="showCancelViewingModal" class="modal-overlay">
  <div class="modal-content" @click.stop>
    <div class="modal-header">
      <h6 class="text-white">
        <i class="ri-calendar-cancel-line me-2"></i>
        Cancel Viewing Request
      </h6>
      <button class="modal-close"  @click="closeCancelReasonModal" :disabled="cancellingSpecificRequest">
        <i class="ri-close-line"></i>
      </button>
    </div>
      
    <div class="modal-body">
      <div class="alert alert-info mb-3">
        <i class="ri-information-line me-2"></i>
        <span>Please provide a reason for cancelling the viewing request</span>
      </div>
      
      <div class="form-group">
        <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
        <textarea 
          v-model="cancelReason"
          class="form-control"
          rows="4"
          placeholder="Why are you cancelling this viewing request?"
          :disabled="cancellingSpecificRequest"
          maxlength="255"
          required
          :class="{ 'is-invalid': cancelReason.length === 0 && cancellingSpecificRequest }"
        ></textarea>
        <div class="char-counter mt-1">
          {{ cancelReason.length }}/255 characters
        </div>
        <div v-if="cancelReason.length === 0 && cancellingSpecificRequest" class="invalid-feedback">
          Please provide a cancellation reason
        </div>
      </div>
      
      <div v-if="requestStatus?.viewing_details" class="viewing-details mt-3">
        <h6 class="mb-2">Viewing Details:</h6>
        <div class="viewing-details-item">
          <i class="ri-calendar-event-line"></i>
          <span>Date: {{ formatDate(requestStatus.viewing_details.date) }}</span>
        </div>
        <div class="viewing-details-item">
          <i class="ri-time-line"></i>
          <span>Time: {{ formatTime(requestStatus.viewing_details.time) }}</span>
        </div>
      </div>
    </div>
    
    <div class="modal-footer">
      <button 
        class="btn-modal btn-modal-secondary" 
        @click="closeCancelReasonModal"
        :disabled="cancellingSpecificRequest"
      >
        Cancel
      </button>
      <button 
        class="btn-modal btn-modal-danger" 
        @click="confirmCancelViewingRequest"
        :disabled="!cancelReason.trim() || cancellingSpecificRequest"
      >
        <i class="ri-calendar-cancel-line me-2"></i>
        {{ cancellingSpecificRequest ? 'Cancelling...' : 'Confirm Cancellation' }}
      </button>
    </div>
  </div>
</div>
<!-- Reject Listing Modal -->
<div v-if="showRejectDetailsModal" class="modal-overlay" @click="showRejectDetailsModal = false">
  <div class="modal-content" @click.stop style="max-width: 500px;">
    <div class="modal-header">
      <h5 class="text-white">Reject Listing</h5>
      <button class="modal-close" @click="showRejectDetailsModal = false">
        <i class="ri-close-line"></i>
      </button>
    </div>
    
    <div class="modal-body">
      <div class="alert alert-warning mb-3 d-flex">
        <i class="ri-alert-line me-2"></i>
        <span>This listing will be marked as rejected and the agent will be notified.</span>
      </div>
      
      <div class="form-group">
        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
        <textarea 
          v-model="rejectReasonDetails"
          class="form-control"
          rows="4"
          placeholder="Please provide a detailed reason why this listing needs changes or is being rejected..."
          maxlength="255"
        ></textarea>
        <div class="char-counter mt-1">
          {{ rejectReasonDetails.length }}/255
        </div>
      </div>
      
      <!-- عرض معلومات القائمة للـ Preview -->
      <div class="listing-preview mt-3 p-2 bg-light rounded">
        <div class="d-flex gap-2 align-items-center">
          <img 
            :src="property?.main_image || '/default-image.jpg'" 
            class="rounded" 
            style="width: 50px; height: 50px; object-fit: cover;"
          />
          <div>
            <div class="fw-bold">{{ property?.reference_number || 'N/A' }}</div>
            <div class="small text-muted">{{ property?.area?.title || 'No area' }}</div>
            <div class="small fw-bold">AED {{ formatPrice(property?.price) }}</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="modal-footer">
      <button class="btn-modal btn-modal-secondary" @click="showRejectDetailsModal = false">
        Cancel
      </button>
      <button 
        class="btn-modal btn-modal-danger" 
        @click="confirmRejectListing"
        :disabled="!rejectReasonDetails.trim() || rejectingListing"
        style="background: #dc3545;color:#fff"
      >
        <i class="ri-close-circle-line me-2"></i>
        {{ rejectingListing ? 'Rejecting...' : 'Reject Listing' }}
      </button>
    </div>
  </div>
</div>
</template>

<script>
import { ref, onMounted, onUnmounted, getCurrentInstance, computed, watch, nextTick } from 'vue';
// import lastSlideBgImg from '@/assets/images/lastslide-bg.png';

import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import html2pdf from 'html2pdf.js';
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import PaymentDetailsSection from '@/components/payment-plans/PaymentDetailsSection.vue';
import { LISTING_FEATURE_LABELS } from '@/config/listingFeatures';

  const starIcon = '/assets/images/star.png';


export default {
  name: "PropertyDetails",
   components: {
    vSelect,
    PaymentDetailsSection,
  },
  data() {
    return {};
  },
  setup() {

        const propertyIcon = '/assets/icons/property-icon.svg';
  const bedIcon = '/assets/icons/bedroom-icon.svg';
  const bathIcon = '/assets/icons/bathroom-icon.svg';
  const sqftIcon = '/assets/icons/area-size.svg';
      
const logo  =  '/assets/images/oiaLogo.jpg';
const locationIcon  =  '/assets/images/Location.png';
const OiaLogo = '/assets/images/LogoWhite.png';

const LastSlide_bg = '/assets/images/lastslide-bg.png';

    const propertySidebarColRef = ref(null);
    const propertySidebarStickyRef = ref(null);
    const propertySidebarSpacerRef = ref(null);
    let propertySidebarScrollRoot = null;
    let propertySidebarSyncRaf = null;

    const SIDEBAR_TOP_GAP = 10;

    const getSidebarTopOffset = () => {
      const nav = document.querySelector('#app main.dashboard-main > .navbar-header');
      if (nav) {
        return nav.getBoundingClientRect().bottom + SIDEBAR_TOP_GAP;
      }
      const root = document.documentElement;
      const topbar =
        parseFloat(getComputedStyle(root).getPropertyValue('--app-topbar-height')) || 44;
      const gap =
        parseFloat(getComputedStyle(root).getPropertyValue('--app-header-below-gap')) || 8;
      return topbar + gap + SIDEBAR_TOP_GAP;
    };

    const resetPropertySidebarStyles = (sticky, spacer) => {
      if (!sticky) return;
      sticky.classList.remove('is-sidebar-fixed', 'is-sidebar-at-bottom');
      sticky.style.cssText = '';
      if (spacer) spacer.style.height = '0px';
    };

    const syncPropertySidebarPosition = () => {
      const col = propertySidebarColRef.value;
      const sticky = propertySidebarStickyRef.value;
      const spacer = propertySidebarSpacerRef.value;
      if (!col || !sticky) return;

      if (window.innerWidth < 992) {
        resetPropertySidebarStyles(sticky, spacer);
        return;
      }

      const topOffset = getSidebarTopOffset();
      const colRect = col.getBoundingClientRect();
      const stickyHeight = sticky.offsetHeight;

      if (colRect.top >= topOffset) {
        resetPropertySidebarStyles(sticky, spacer);
        return;
      }

      if (spacer) spacer.style.height = `${stickyHeight}px`;

      if (colRect.bottom <= topOffset + stickyHeight) {
        sticky.classList.add('is-sidebar-fixed', 'is-sidebar-at-bottom');
        sticky.style.top = 'auto';
        sticky.style.bottom = '0';
        sticky.style.left = '0';
        sticky.style.width = '100%';
        return;
      }

      sticky.classList.add('is-sidebar-fixed');
      sticky.classList.remove('is-sidebar-at-bottom');
      sticky.style.top = `${topOffset}px`;
      sticky.style.bottom = 'auto';
      sticky.style.left = `${colRect.left}px`;
      sticky.style.width = `${colRect.width}px`;
    };

    const schedulePropertySidebarSync = () => {
      if (propertySidebarSyncRaf) {
        cancelAnimationFrame(propertySidebarSyncRaf);
      }
      propertySidebarSyncRaf = requestAnimationFrame(() => {
        propertySidebarSyncRaf = null;
        syncPropertySidebarPosition();
      });
    };

    const bindPropertySidebarScroll = () => {
      window.addEventListener('scroll', schedulePropertySidebarSync, { passive: true });
      window.addEventListener('resize', schedulePropertySidebarSync, { passive: true });
      window.addEventListener('orientationchange', schedulePropertySidebarSync, { passive: true });
      propertySidebarScrollRoot = document.querySelector('.dashboard-main-router');
      if (propertySidebarScrollRoot) {
        propertySidebarScrollRoot.addEventListener('scroll', schedulePropertySidebarSync, {
          passive: true,
        });
      }
    };

    const unbindPropertySidebarScroll = () => {
      window.removeEventListener('scroll', schedulePropertySidebarSync);
      window.removeEventListener('resize', schedulePropertySidebarSync);
      window.removeEventListener('orientationchange', schedulePropertySidebarSync);
      if (propertySidebarScrollRoot) {
        propertySidebarScrollRoot.removeEventListener('scroll', schedulePropertySidebarSync);
      }
      propertySidebarScrollRoot = null;
    };
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
    const cancellingRequest = ref(false);
    const requestStatus = ref({
      unit_number_status: null,
      owner_info_status: null,
      created_at: null,
      responded_at: null,
      unit_number_requested_at: null,
      owner_info_requested_at: null,
      unit_number_approved_at: null,
      owner_info_approved_at: null,
      unit_number_rejected_at: null,
      owner_info_rejected_at: null
    });
    const showOwnerDetailsModal = ref(false);
    const showChatPopup = ref(false);
    const chatAgent = ref(null);
    const chatListingId = ref(null);
    const showFloorPlanSlider = ref(false);
    const currentFloorPlanIndex = ref(0);
    
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

    // Dropdown and Modal variables
    const showActionsDropdown = ref(false);
    const showAssignAgentModal = ref(false);
    const selectedAgentId = ref('');
    const assignmentNotes = ref('');
    const assigningAgent = ref(false);
    const availableAgents = ref([]);
    
  const selectedSoldByAgent = ref(null);
  const availableAgentsForSoldBy = ref([]);
  const loadingAgentsForSoldBy = ref(false);
  const submittedOtherCompany = ref(false);
// أضف هذه المتغيرات الجديدة
const showRejectDetailsModal = ref(false);
const rejectReasonDetails = ref('');
const rejectingListing = ref(false);

const canApproveListings = computed(() => {
  const currentUser = getCurrentUser();
  if (!currentUser) return false;
  
  const isManagerWithListingTeam = currentUser.roles?.includes('manager') && currentUser?.is_listing_team;
  
  const isSuperAdminUser = currentUser.roles?.includes('super_admin') || currentUser.roles?.includes('admin');
  return isManagerWithListingTeam || isSuperAdminUser;
});

const approveListing = async () => {
  const result = await Swal.fire({
    title: 'Approve Listing?',
    text: 'Are you sure you want to approve this listing? It will become visible to all users.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Approve',
    cancelButtonText: 'Cancel'
  });

  if (!result.isConfirmed) return;

  try {
    const response = await api.patch(`/listings/properties/${property.value.id}/approve`);
    
    if (response.data.status) {
      // تحديث الحالة المحلية
      property.value.approved = true;
      property.value.approval_status = 'approved';
      
      proxy.$showNotification('Listing approved successfully!', 'success');
      closeActionsDropdown();
      
      // إعادة تحميل البيانات
      await fetchProperty();
    }
  } catch (error) {
    console.error('Error approving listing:', error);
    proxy.$showNotification(
      error.response?.data?.message || 'Failed to approve listing',
      'error'
    );
  }
};

const openRejectFromDetailsModal = () => {
  rejectReasonDetails.value = '';
  showRejectDetailsModal.value = true;
  closeActionsDropdown();
};

const confirmRejectListing = async () => {
  if (!rejectReasonDetails.value.trim()) {
    proxy.$showNotification('Please provide a reason for rejection', 'warning');
    return;
  }
  
  try {
    rejectingListing.value = true;
    
    const response = await api.patch(`/listings/properties/${property.value.id}/reject`, {
      reason: rejectReasonDetails.value
    });
    
    if (response.data.status) {
      // تحديث الحالة المحلية
      property.value.approved = false;
      property.value.approval_status = 'rejected';
      
      proxy.$showNotification('Listing has been rejected and the agent has been notified.', 'success');
      showRejectDetailsModal.value = false;
      
      // إعادة تحميل البيانات
      await fetchProperty();
    }
  } catch (error) {
    console.error('Error rejecting listing:', error);
    proxy.$showNotification(
      error.response?.data?.message || 'Failed to reject listing',
      'error'
    );
  } finally {
    rejectingListing.value = false;
  }
};
 // في setup()
const isAnotherAgentSelected = computed(() => {
    // للبيع
    if (soldByChoice.value === 'another_agent') return true;
    // للإيجار
    if (rentedByChoice.value === 'another_agent') return true;
    return false;
});

const confirmCancelViewingRequest = async () => {
  if (!cancelReason.value.trim()) {
    proxy.$showNotification('Please provide a cancellation reason', 'warning');
    return;
  }

  try {
    cancellingSpecificRequest.value = true;
    
    const response = await api.post(`/listings/access-requests/${property.value.id}/cancel`, {
      request_type: 'viewing',
      reason: cancelReason.value
    });
    
    if (response.data.status) {
      proxy.$showNotification('Viewing request cancelled successfully!', 'success');
      
      // Update viewing status
      requestStatus.value.viewing_status = null;
      requestStatus.value.viewing_details = null;
      
      await fetchRequestStatus();
      
      // Close modal
      closeCancelReasonModal();
      closeActionsDropdown();
    }
  } catch (err) {
    handleApiError(err, 'Failed to cancel viewing request');
  } finally {
    cancellingSpecificRequest.value = false;
  }
};
const confirmCancelRequest = async () => {

  try {
    cancellingSpecificRequest.value = true;
    
    const response = await api.post(`/listings/access-requests/${property.value.id}/cancel`, {
      request_type: cancelRequestType.value,
      reason: cancelReason.value,
      priority: cancellationPriority.value || 'normal'
    });
    
    if (response.data.status) {
      proxy.$showNotification('Request cancelled successfully!', 'success');
      
      if (cancelRequestType.value === 'unit_number') {
        requestStatus.value.unit_number_status = null;
      } else if (cancelRequestType.value === 'owner_data') {
        requestStatus.value.owner_info_status = null;
      } else if (cancelRequestType.value === 'viewing') {
        requestStatus.value.viewing_status = null;
        requestStatus.value.viewing_details = null;
      }
      
      await fetchRequestStatus();
      
      closeCancelReasonModal();
      closeActionsDropdown();
    }
  } catch (err) {
    handleApiError(err, 'Failed to cancel request');
  } finally {
    cancellingSpecificRequest.value = false;
  }
};
    // Computed properties
const hasRejectionReason = computed(() => {
  console.log('🔍 hasRejectionReason computed called');
  console.log('  - property.value exists?', !!property.value);
  console.log('  - rejection_reason:', property.value?.rejection_reason);
  console.log('  - approved:', property.value?.approved);
  
  const hasReason = property.value?.rejection_reason && 
                    property.value?.rejection_reason.trim() !== '' && property.value.status =='draft';
  
  console.log('  - Result:', hasReason);
  return hasReason;
});
const rejectionDetails = computed(() => {
  if (!hasRejectionReason.value) return null;
  
  return {
    reason: property.value.rejection_reason,
    rejected_by: property.value.rejected_by_name || property.value.rejected_by,
    rejected_at: property.value.rejected_at,
    rejected_by_user: property.value.rejected_by_user
  };
});

const formatRejectionDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
    const checkAccessAndRedirect = () => {
      if (!property.value) return false;
    
      const currentUser = getCurrentUser();
      if (!currentUser) {
        return false;
      }
    
      const hasAccess = 
        isPropertyOwner.value ||         
        canApproveListings.value ; 
      if (!hasAccess && !property.value.approved) {
        console.warn('Access denied for user:', currentUser?.id);
        
        proxy.$showNotification('You do not have permission to view this property.', 'error');
        
        router.push('/alllisting');
        
        return false;
      }
    
      return true;
    };
    const isPropertyOwner = computed(() => {
      return property.value?.is_owner || false;
    });

    const canEditProperty = computed(() => {
      return property.value?.user_permissions?.can_edit || false;
    });
    const canShowOffers = computed(() => {
      return property.value?.user_permissions?.show_offers || false;
    });
      const canGenerateOffer = computed(() => {
      return property.value?.user_permissions?.genertae_offers || false;
    });

    const canDeleteProperty = computed(() => {
      return property.value?.user_permissions?.can_delete || false;
    });

    const canEditOrDelete = computed(() => {
      return canEditProperty.value || canDeleteProperty.value;
    });
// Single source of truth — see resources/js/config/listingFeatures.js.
// Reading `additional_features_labels` off the API takes priority so any
// new key added to ListingResource::FEATURE_LABELS shows up here without
// the frontend needing a code change.
const additionalFeaturesList = computed(() => {
  const data = property.value?.additional_features;
  if (!data) return [];

  const labels = property.value?.additional_features_labels || LISTING_FEATURE_LABELS;
  const isTruthy = (v) => v === true || v === 1 || v === '1';

  // Render order: keys present in the shared list first (preserves grouping),
  // then any extra keys the backend sent that we don't have a label for.
  const orderedKeys = Object.keys(labels);
  const extraKeys = Object.keys(data).filter((k) => !orderedKeys.includes(k));

  return [...orderedKeys, ...extraKeys]
    .filter((key) => isTruthy(data[key]))
    .map((key) => labels[key] || key);
});

const hasAdditionalFeatures = computed(() => {
  const result = additionalFeaturesList.value.length > 0;
  console.log('🔍 hasAdditionalFeatures:', result, 'length:', additionalFeaturesList.value.length);
  return result;
});
    const hasMortgageInfo = computed(() => {
      if (!property.value) return false;
      // Mortgage doesn't apply to under-construction units — hide the whole block.
      if (property.value.completion_status === 'Under Construction') return false;
      return property.value.mortgage_status ||
             property.value.mortgage_amount ||
             property.value.occupancy_status ||
             property.value.rent_amount ||
             property.value.rent_expiry_date ||
             property.value.mortgage_comment;
    });

    const parseListArray = (raw) => {
      if (Array.isArray(raw)) return raw;
      if (typeof raw === 'string' && raw.trim() !== '') {
        try {
          const parsed = JSON.parse(raw);
          return Array.isArray(parsed) ? parsed : [];
        } catch {
          return [];
        }
      }
      return [];
    };

    const hasPaymentDetails = computed(() => {
      if (!property.value) return false;
      const pb = parseListArray(property.value.payment_breakdown);
      const ae = parseListArray(property.value.assignment_expense_lines);
      return (
        pb.length > 0 ||
        ae.length > 0 ||
        Number(property.value.original_price || 0) > 0 ||
        !!property.value.payment_plan ||
        !!property.value.handover_date
      );
    });

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
const showViewingModal = ref(false);
const submittingViewing = ref(false);
const viewingRequest = ref({
  date: '',
  time: '',

});

const timeSlots = ref([
  { value: '09:00', label: '09:00 AM' },
  { value: '10:00', label: '10:00 AM' },
  { value: '11:00', label: '11:00 AM' },
  { value: '12:00', label: '12:00 PM' },
  { value: '13:00', label: '01:00 PM' },
  { value: '14:00', label: '02:00 PM' },
  { value: '15:00', label: '03:00 PM' },
  { value: '16:00', label: '04:00 PM' },
  { value: '17:00', label: '05:00 PM' },
  { value: '18:00', label: '06:00 PM' },
  { value: '19:00', label: '07:00 PM' },
  { value: '20:00', label: '08:00 PM' },
  { value: '21:00', label: '09:00 PM' },
  { value: '22:00', label: '10:00 PM' },
  { value: '23:00', label: '11:00 PM' },
  { value: '00:00', label: '12:00 AM' },
]);

const today = computed(() => {
  return new Date().toISOString().split('T')[0];
});

const openViewingModal = () => {
  viewingRequest.value = {
    date: '',
    time: '',
 
  };
  showViewingModal.value = true;
  closeActionsDropdown();
};

const submitViewingRequest = async () => {
  try {
    submittingViewing.value = true;
    
    const response = await api.post(`/listings/access-requests/${property.value.id}/request`, {
      request_type: 'viewing',
      reason: `Request for property viewing${viewingRequest.value.notes ? ': ' + viewingRequest.value.notes : ''}`,
      viewing_date: viewingRequest.value.date,
      viewing_time: viewingRequest.value.time,
      viewing_type: viewingRequest.value.type,
      viewing_notes: viewingRequest.value.notes
    });
    
    if (response.data.status) {
      proxy.$showNotification('Viewing request submitted successfully!', 'success');
      
      await fetchRequestStatus();
      
      showViewingModal.value = false;
      closeActionsDropdown();
    }
  } catch (err) {
    handleApiError(err, 'Failed to submit viewing request');
  } finally {
    submittingViewing.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatTime = (timeString) => {
  if (!timeString) return '';
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${hour12}:${minutes} ${ampm}`;
};

const canRequestViewing = computed(() => {
  if (isPropertyOwner.value) return false;
  const status = requestStatus.value?.viewing_status;
  return status !== 'pending' && status !== 'approved';
});


const cancelViewingRequest = async () => {
  await cancelRequest('viewing');
};


  const fetchRequestStatus = async () => {
      try {
        const response = await api.get(`/listings/access-requests/status/${route.params.id}`);
        if (response.data.status) {
          requestStatus.value = {
            ...response.data.data,
            unit_number_requested_at: response.data.data.unit_number_requested_at 
              ? formatDateTime(response.data.data.unit_number_requested_at)
              : null,
            owner_info_requested_at: response.data.data.owner_info_requested_at 
              ? formatDateTime(response.data.data.owner_info_requested_at)
              : null,
            unit_number_approved_at: response.data.data.unit_number_approved_at 
              ? formatDateTime(response.data.data.unit_number_approved_at)
              : null,
            owner_info_approved_at: response.data.data.owner_info_approved_at 
              ? formatDateTime(response.data.data.owner_info_approved_at)
              : null,
             viewing_requested_at: response.data.data.viewing_requested_at 
              ? formatDateTime(response.data.data.viewing_requested_at)
              : null,
            viewing_approved_at: response.data.data.viewing_approved_at 
              ? formatDateTime(response.data.data.viewing_approved_at)
              : null,
              };
          console.log('✅ Request status loaded:', requestStatus.value);
        } else {
          requestStatus.value = {
            unit_number_status: null,
            owner_info_status: null,
            unit_number_requested_at: null,
            owner_info_requested_at: null,
            unit_number_approved_at: null,
            owner_info_approved_at: null,
            unit_number_rejected_at: null,
            owner_info_rejected_at: null,
            created_at: null,
            responded_at: null,
            viewing_requested_at:null,
            viewing_approved_at:null,
          };
        }
      } catch (err) {
        console.error('Error fetching request status:', err);
        requestStatus.value = {
          unit_number_status: null,
          owner_info_status: null,
          unit_number_requested_at: null,
          owner_info_requested_at: null,
          unit_number_approved_at: null,
          owner_info_approved_at: null,
          unit_number_rejected_at: null,
          owner_info_rejected_at: null,
          created_at: null,
          responded_at: null
        };
      }
    };

    const hasOwnerDocuments = computed(() => {
      const owner = getOwnerDataForModal();
      if (!owner) return false;
      return owner.id_front_path || owner.id_back_path || owner.passport_copy_path || owner.visa_copy_path ||
             owner.id_front_url || owner.id_back_url || owner.passport_copy_url || owner.visa_copy_url;
    });

  // Floor Plan Methods
const openFloorPlanSlider = (index) => {
  if (!property.value?.floor_plans || property.value.floor_plans.length === 0) {
    Swal.fire({
      title: 'No Floor Plans',
      text: 'No floor plans available for this property.',
      icon: 'warning',
      confirmButtonColor: '#0B0736'
    });
    return;
  }
  currentFloorPlanIndex.value = index;
  showFloorPlanSlider.value = true;
  document.body.style.overflow = 'hidden';
};

const closeFloorPlanSlider = () => {
  showFloorPlanSlider.value = false;
  document.body.style.overflow = 'auto';
};

const nextFloorPlan = () => {
  if (property.value?.floor_plans && currentFloorPlanIndex.value < property.value.floor_plans.length - 1) {
    currentFloorPlanIndex.value++;
  }
};

const prevFloorPlan = () => {
  if (currentFloorPlanIndex.value > 0) {
    currentFloorPlanIndex.value--;
  }
};

const setCurrentFloorPlan = (index) => {
  currentFloorPlanIndex.value = index;
};

const handleFloorPlanError = (event) => {
  event.target.src = '/default-floor-plan.jpg';
  event.target.style.objectFit = 'contain';
};
const getCurrentUser = () => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData) : null;
  } catch (error) {
    console.error('Error getting user from localStorage:', error);
    return null;
  }
};

// Authentication computed
const isAuthenticated = computed(() => {
  return getCurrentUser() !== null;
});

// New computed properties for dropdown actions
const canAssignAgent = computed(() => {
  return property.value?.user_permissions?.can_assign_agent || false;
});

const canUsePropertyChat = computed(() => {
  const userRoles = Array.isArray(getCurrentUser()?.roles) ? getCurrentUser().roles : [];
  return true
  return userRoles.includes('super_admin') ;

});

const onlyShow = computed(() => {
  const userRoles = Array.isArray(getCurrentUser()?.roles) ? getCurrentUser().roles : [];
  return userRoles.includes('only show listings') ;

});
const canMarkAsConverted = computed(() => {
  return canEditProperty.value;
});

    // Area Hierarchy Computed Properties
    const areaHierarchy = computed(() => {
      if (!property.value?.area) return [];
      
      const hierarchy = [];
      let currentArea = property.value.area;
      
      // Build hierarchy from current area to root
      while (currentArea) {
        hierarchy.unshift(currentArea);
        currentArea = currentArea.parent;
      }
      
      return hierarchy;
    });

    const hasAreaHierarchy = computed(() => {
      return areaHierarchy.value.length > 1;
    });

    const hasAreaChildren = computed(() => {
      return property.value?.area?.children && property.value.area.children.length > 0;
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

    // Area Type Methods
    const getAreaType = (area) => {
      if (!area) return 'Area';
      
      // Use the area type if available
      if (area.type) {
        const typeMap = {
          'country': 'Country',
          'city': 'City', 
          'area': 'Area',
          'community': 'Community',
          'sub_community': 'Sub Community',
          'cluster': 'Cluster',
          'building': 'Building',
          'faces': 'Faces'
        };
        return typeMap[area.type] || area.type;
      }
      
      // Fallback based on hierarchy level
      const level = areaHierarchy.value.findIndex(a => a.id === area.id);
      const types = ['Country', 'City', 'Area', 'Community', 'Sub Community', 'Cluster', 'Building', 'Faces'];
      return types[level] || 'Area';
    };

    const getLevelType = (areaType) => {
      const descriptionMap = {
        'country': 'Top-level country',
        'city': 'City within a country', 
        'area': 'Area within a city',
        'community': 'Community within an area',
        'sub_community': 'Sub-community within a community',
        'cluster': 'Cluster of buildings',
        'building': 'Individual building',
        'faces': 'Building faces/units'
      };
      
      return descriptionMap[areaType] || '';
    };

    // Dropdown Methods
  const toggleActionsDropdown = () => {
  showActionsDropdown.value = !showActionsDropdown.value;
  
  const card = document.querySelector('.agent-sidebar-card');
  if (card) {
    if (showActionsDropdown.value) {
      card.classList.add('expanding');
    } else {
      card.classList.remove('expanding');
    }
  }
};

const closeActionsDropdown = () => {
  showActionsDropdown.value = false;
  
  const card = document.querySelector('.agent-sidebar-card');
  if (card) {
    card.classList.remove('expanding');
  }
};

    const toggleArchive = async () => {
      const result = await Swal.fire({
        title: property.value.is_archived ? 'Unarchive Property?' : 'Archive Property?',
        text: property.value.is_archived 
          ? 'This property will become visible in listings.' 
          : 'This property will be hidden from listings.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0B0736',
        cancelButtonColor: '#6c757d',
        confirmButtonText: property.value.is_archived ? 'Yes, Unarchive' : 'Yes, Archive',
        cancelButtonText: 'Cancel'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await api.patch(`/listings/properties/${property.value.id}/toggle-archive`);
        
        if (response.data.status) {
          property.value.is_archived = !property.value.is_archived;
          proxy.$showNotification(
            `Property ${property.value.is_archived ? 'archived' : 'unarchived'} successfully!`,
            'success'
          );
          closeActionsDropdown();
        }
      } catch (error) {
        handleApiError(error, 'Failed to update archive status');
      }
    };

    const toggleActive = async () => {
      const result = await Swal.fire({
        title: property.value.is_active ? 'Deactivate Property?' : 'Activate Property?',
        text: property.value.is_active 
          ? 'This property will be marked as inactive.' 
          : 'This property will be marked as active.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0B0736',
        cancelButtonColor: '#6c757d',
        confirmButtonText: property.value.is_active ? 'Yes, Deactivate' : 'Yes, Activate',
        cancelButtonText: 'Cancel'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await api.patch(`/listings/properties/${property.value.id}/toggle-status`);
        
        if (response.data.status) {
          property.value.is_active = !property.value.is_active;
          proxy.$showNotification(
            `Property ${property.value.is_active ? 'activated' : 'deactivated'} successfully!`,
            'success'
          );
          closeActionsDropdown();
        }
      } catch (error) {
        handleApiError(error, 'Failed to update active status');
      }
    };

    const openAssignAgentModal = async () => {
      try {
        const response = await api.get('/listings/agents');
        if (response.data.status) {
          availableAgents.value = response.data.data;
          showAssignAgentModal.value = true;
          closeActionsDropdown();
        }
      } catch (error) {
        handleApiError(error, 'Failed to load agents');
      }
    };

    const assignToAgent = async () => {
      try {
        assigningAgent.value = true;
        
        const response = await api.patch(`/listings/properties/${property.value.id}/assign-agent`, {
          agent_id: selectedAgentId.value,
          notes: assignmentNotes.value
        });
        
        if (response.data.status) {
          proxy.$showNotification('Property assigned to agent successfully!', 'success');
          property.value.agent = response.data.data.agent;
          showAssignAgentModal.value = false;
          selectedAgentId.value = '';
          assignmentNotes.value = '';
        }
      } catch (error) {
        handleApiError(error, 'Failed to assign agent');
      } finally {
        assigningAgent.value = false;
      }
    };
const showSoldOutModal = ref(false);

const soldByChoice = ref(null);
const showAddOwnerModal = ref(false);


const showOIAgentSelection = ref(false);
const showAToAModal = ref(false);
const aToAData = ref({
  company_name:'',
  agent_name: '',
  agent_phone: '',
  commercial_license: ''
});
const submittingATO = ref(false);
const aToAType = ref('sold'); // 'sold' or 'rented'

const showOIAgentSelectionRent = ref(false);

const isSubmittingOwner = ref(false);
const ownerNationalities = ref([
  "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria",
  "Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan",
  "Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi",
  "Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia",
  "Comoros","Congo (Congo-Brazzaville)","Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark",
  "Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
  "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana",
  "Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland",
  "India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan",
  "Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya",
  "Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta",
  "Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia",
  "Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand",
  "Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau",
  "Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar",
  "Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines",
  "Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles",
  "Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea",
  "South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan",
  "Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey",
  "Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States",
  "Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
]);
const ownerLocations = ref([]);
const newOwner = ref({
  salutation: '',
  first_name: '',
  last_name: '',
  email: '',
  phone_number: '',
  whatsapp_number: '',
  second_phone_number: '',
  nationality: '',
  residency_status: '',
  location_id: '',
  id_front: null,
  id_back: null,
  visa_copy: null,
  passport_copy: null,
  notes: '',
  additionalDocuments: []
});

const openSoldOutModal = () => {
  showSoldOutModal.value = true;
  soldByChoice.value = null;
  selectedSoldByAgent.value = null; 
  closeActionsDropdown();
};


// أضف هذه المتغيرات في setup() مع بقية المتغيرات
const showOtherCompanyModal = ref(false);
const otherCompanyData = ref({
  agent_name: '',
  agent_phone: '',
  agent_email: '',
  company_name: ''
});
const submittingOtherCompany = ref(false);
const otherCompanyType = ref('sold'); // 'sold' or 'rented'

// إضافة دالة لفتح مودال الشركة الأخرى
const openOtherCompanyModal = (type) => {
  submittedOtherCompany.value = false;

  otherCompanyType.value = type; // 'sold' or 'rented'
  otherCompanyData.value = {
    agent_name: '',
    agent_phone: '',
    agent_email: '',
    company_name: ''
  };
  showOtherCompanyModal.value = true;
  
  if (type === 'sold') {
    showSoldOutModal.value = false;
  } else {
    showRentedModal.value = false;
  }
};
const backToRentedOptions = () => {
    rentedByChoice.value = null;
};

const markAsRentedByOwnerConfirm = async () => {
    if (!rentedDate.value) {
        proxy.$showNotification('Please select rented date', 'warning');
        return;
    }
    await markAsRentedByOwner();
};
// دالة لإغلاق مودال الشركة الأخرى
const closeOtherCompanyModal = () => {
  showOtherCompanyModal.value = false;
  otherCompanyData.value = {
    agent_name: '',
    agent_phone: '',
    agent_email: '',
    company_name: ''
  };
  otherCompanyType.value = 'sold';
};


const submitOtherCompany = async () => {
  submittedOtherCompany.value = true;

  // التحقق من صحة البيانات
  if (!otherCompanyData.value.company_name.trim()) {
    proxy.$showNotification('Please enter company name', 'warning');
    submittedOtherCompany.value = false;
    return;
  }
  
  if (!otherCompanyData.value.agent_name.trim()) {
    proxy.$showNotification('Please enter agent name', 'warning');
    submittedOtherCompany.value = false;
    return;
  }
  
  if (!otherCompanyData.value.agent_phone.trim()) {
    proxy.$showNotification('Please enter agent phone number', 'warning');
    submittedOtherCompany.value = false;
    return;
  }
  
  // التحقق من تاريخ الإيجار إذا كان النوع rented
  if (otherCompanyType.value === 'rented' && !rentedDate.value) {
    proxy.$showNotification('Please select rented date', 'warning');
    submittedOtherCompany.value = false;
    return;
  }
  
  try {
    submittingOtherCompany.value = true;
    
    if (otherCompanyType.value === 'sold') {
      await markAsSoldWithOtherCompany();
    } else {
      await markAsRentedWithOtherCompany();
    }
  } catch (error) {
    console.error('Submit error:', error);
    proxy.$showNotification(error.response?.data?.message || 'Failed to submit other company data', 'error');
    submittedOtherCompany.value = false;
  } finally {
    submittingOtherCompany.value = false;
  }
};

const markAsSoldWithOtherCompany = async () => {
  try {
    const payload = {
      sold_by: 'other_company',
      other_company_details: {
        company_name: otherCompanyData.value.company_name,
        agent_name: otherCompanyData.value.agent_name,
        agent_phone: otherCompanyData.value.agent_phone,
        agent_email: otherCompanyData.value.agent_email || null
      }
    };
    
    const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
    
    if (response.data.status) {
      // تحديث البيانات المحلية
      property.value.status = 'converted';
      property.value.sold_by = 'other_company';
      property.value.sold_by_company_name = otherCompanyData.value.company_name;
      property.value.sold_by_agent_name = otherCompanyData.value.agent_name;
      property.value.sold_by_agent_phone = otherCompanyData.value.agent_phone;
      property.value.sold_by_agent_email = otherCompanyData.value.agent_email || null;
      
      proxy.$showNotification(`Property marked as sold by ${otherCompanyData.value.company_name}!`, 'success');
      
      // إغلاق المودالات
      closeOtherCompanyModal();
      closeSoldOutModal();
      
      // تحديث الصفحة أو إعادة تحميل البيانات
      await fetchProperty();
    } else {
      throw new Error(response.data.message || 'Failed to mark as sold');
    }
  } catch (error) {
    console.error('Mark as sold error:', error);
    throw error;
  }
};

const markAsRentedWithOtherCompany = async () => {
  if (!rentedDate.value) {
    proxy.$showNotification('Please select rented date', 'warning');
    return;
  }
  
  try {
    const payload = {
      rented_by: 'other_company',
      rented_date: rentedDate.value,
      other_company_details: {
        company_name: otherCompanyData.value.company_name,
        agent_name: otherCompanyData.value.agent_name,
        agent_phone: otherCompanyData.value.agent_phone,
        agent_email: otherCompanyData.value.agent_email || null
      }
    };
    
    const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
    
    if (response.data.status) {
      // تحديث البيانات المحلية
      property.value.status = 'rented';
      property.value.rented_by = 'other_company';
      property.value.rented_by_company_name = otherCompanyData.value.company_name;
      property.value.rented_by_agent_name = otherCompanyData.value.agent_name;
      property.value.rented_by_agent_phone = otherCompanyData.value.agent_phone;
      property.value.rented_by_agent_email = otherCompanyData.value.agent_email || null;
      property.value.rented_date = rentedDate.value;
      
      proxy.$showNotification(`Property marked as rented by ${otherCompanyData.value.company_name}!`, 'success');
      
      // إغلاق المودالات
      closeOtherCompanyModal();
      closeRentedModal();
      
      // تحديث الصفحة أو إعادة تحميل البيانات
      await fetchProperty();
    } else {
      throw new Error(response.data.message || 'Failed to mark as rented');
    }
  } catch (error) {
    console.error('Mark as rented error:', error);
    throw error;
  }
};



const selectSoldBy = async (soldBy) => {
    if (soldBy === 'owner') {
      closeSoldOutModal();
        // Sold by Owner - يظهر alert confirm فقط
        const result = await Swal.fire({
            title: 'Confirm Sold by Owner',
            text: 'Are you sure you want to mark this property as sold directly by the owner? This action cannot be undone.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel'
        });

        if (result.isConfirmed) {
            await markAsSoldByOwner();
        }
        return;
    }
    
    if (soldBy === 'oia') {
        // Sold by OIA - يفتح المستوى الثاني
        soldByChoice.value = 'oia';
        return;
    }
};

const markAsSoldByOwner = async () => {
    try {
        const payload = { sold_by: 'owner' };
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
        
        if (response.data.status) {
            property.value.status = 'converted';
            property.value.sold_by = 'owner';
            proxy.$showNotification('Property marked as sold by owner!', 'success');
            closeSoldOutModal();
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as sold by owner');
    }
};

// ✅ فتح مودال اختيار الـ OIA Agent
const openOIAgentSelection = async () => {
    console.log('🔍 Opening OIA Agent selection...');
    await fetchAvailableAgentsForSoldBy();
    showOIAgentSelection.value = true;
    console.log('✅ showOIAgentSelection =', showOIAgentSelection.value);
};

// ✅ جلب الأجنت من الـ API
const fetchAvailableAgentsForSoldBy = async () => {
    try {
        loadingAgentsForSoldBy.value = true;
        console.log('📡 Fetching agents from /listings/agents');
        const response = await api.get('/listings/agents');
        console.log('📦 Response:', response.data);
        
        if (response.data.status) {
            availableAgentsForSoldBy.value = response.data.data;
            console.log('✅ Agents loaded:', availableAgentsForSoldBy.value.length);
        } else {
            console.log('❌ No agents or status false');
            availableAgentsForSoldBy.value = [];
        }
    } catch (error) {
        console.error('❌ Error fetching agents:', error);
        handleApiError(error, 'Failed to load agents');
        availableAgentsForSoldBy.value = [];
    } finally {
        loadingAgentsForSoldBy.value = false;
    }
};

const backToOIAOptions = () => {
    showOIAgentSelection.value = false;
    soldByChoice.value = 'oia';
};

const markAsSoldByOIAgent = async () => {
    if (!selectedSoldByAgent.value) {
        proxy.$showNotification('Please select an agent', 'warning');
        return;
    }
    
    const currentUser = getCurrentUser();
    const isCurrentUser = selectedSoldByAgent.value === currentUser?.id;
    
    showOIAgentSelection.value = false;
    
    if (isCurrentUser) {
        // نفس المستخدم = Sold by Me
        soldByChoice.value = 'me';
    } else {
        // مستخدم آخر = Sold by Another Agent
        await markAsSoldByAnotherAgent(selectedSoldByAgent.value);
    }
};

// بيع عن طريق Agent آخر
const markAsSoldByAnotherAgent = async (agentId) => {
    try {
        const payload = { 
            sold_by: 'another_agent',
            agent_id: agentId
        };
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
        
        if (response.data.status) {
            property.value.status = 'converted';
            property.value.sold_by = 'another_agent';
            property.value.sold_by_agent_id = agentId;
            const selectedAgent = availableAgentsForSoldBy.value.find(a => a.id === agentId);
            property.value.sold_by_agent_name = selectedAgent?.name;
            proxy.$showNotification('Property marked as sold by another agent!', 'success');
            closeSoldOutModal();
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as sold by another agent');
    }
};

// فتح مودال A to A
const openAToAModalForSale = () => {
    aToAType.value = 'sold';
    aToAData.value = { company_name:'',agent_name: '', agent_phone: '', commercial_license: '' };
    showAToAModal.value = true;
    closeSoldOutModal();
};

// تأكيد A to A
const submitAToA = async () => {
    if (!aToAData.value.company_name.trim() || !aToAData.value.agent_name.trim() || !aToAData.value.agent_phone.trim() || !aToAData.value.commercial_license.trim()) {
        proxy.$showNotification('Please fill all required fields', 'warning');
        return;
    }

    try {
        submittingATO.value = true;
        
        const payload = {
            sold_by: 'a_to_a',
            a_to_a_details: {
              company_name:aToAData.value.company_name,
                agent_name: aToAData.value.agent_name,
                agent_phone: aToAData.value.agent_phone,
                commercial_license: aToAData.value.commercial_license
            }
        };
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
        
        if (response.data.status) {
            property.value.status = 'converted';
            property.value.sold_by = 'a_to_a';
            proxy.$showNotification('Property marked as sold via A to A!', 'success');
            closeAToAModal();
            closeSoldOutModal();
            await fetchProperty();
        }
    } catch (error) {
        handleApiError(error, 'Failed to submit A to A transaction');
    } finally {
        submittingATO.value = false;
    }
};

const closeAToAModal = () => {
    showAToAModal.value = false;
    aToAData.value = { company_name:'',agent_name: '', agent_phone: '', commercial_license: '' };
};

const closeSoldOutModal = () => {
    showSoldOutModal.value = false;
    soldByChoice.value = null;
    selectedSoldByAgent.value = null;
    showOIAgentSelection.value = false;
};
// في setup()
const showRentedModal = ref(false);
const rentedByChoice = ref(null);
const selectedRentedByAgent = ref(null);
const rentedDate = ref('');
const availableAgentsForRentedBy = ref([]);
const loadingAgentsForRentedBy = ref(false);
const addPersonType = ref('owner');

// دالة فتح مودال الإيجار
const openRentedModal = () => {
    showRentedModal.value = true;
    rentedByChoice.value = null;
    selectedRentedByAgent.value = null;
    rentedDate.value = new Date().toISOString().split('T')[0];
    closeActionsDropdown();
};

// دالة جلب الأجنت للإيجار
const fetchAvailableAgentsForRentedBy = async () => {
    try {
        loadingAgentsForRentedBy.value = true;
        const response = await api.get('/listings/agents');
        if (response.data.status) {
            availableAgentsForRentedBy.value = response.data.data;
        }
    } catch (error) {
        handleApiError(error, 'Failed to load agents');
    } finally {
        loadingAgentsForRentedBy.value = false;
    }
};

// دالة اختيار نوع الإيجار (المستوى الأول)
const selectRentedBy = async (rentedBy) => {
    if (rentedBy === 'owner') {
      closeRentedModal();
        const result = await Swal.fire({
            title: 'Confirm Rented by Owner',
            text: 'Are you sure you want to mark this property as rented directly by the owner?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel'
        });

        if (result.isConfirmed) {
            await markAsRentedByOwner();
        }
        return;
    }
    
    if (rentedBy === 'oia') {
        rentedByChoice.value = 'oia';
        return;
    }
};

const markAsRentedByOwner = async () => {
    // if (!rentedDate.value) {
    //     proxy.$showNotification('Please select rented date', 'warning');
    //     return;
    // }
    
    try {
        const payload = { rented_by: 'owner', rented_date: rentedDate.value };
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
        
        if (response.data.status) {
            property.value.status = 'rented';
            property.value.rented_by = 'owner';
            property.value.rented_date = rentedDate.value;
            proxy.$showNotification('Property marked as rented by owner!', 'success');
            closeRentedModal();
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as rented by owner');
    }
};

const openOIAgentSelectionRent = async () => {
    await fetchAvailableAgentsForRentedBy();
    showOIAgentSelectionRent.value = true;
};



const backToOIAOptionsRent = () => {
    showOIAgentSelectionRent.value = false;
    rentedByChoice.value = 'oia';
        selectedRentedByAgent.value = null;

};

const markAsRentedByOIAgent = async () => {
    if (!selectedRentedByAgent.value) {
        proxy.$showNotification('Please select an agent', 'warning');
        return;
    }
    
    if (!rentedDate.value) {
        proxy.$showNotification('Please select rented date', 'warning');
        return;
    }
    
    const currentUser = getCurrentUser();
    const isCurrentUser = selectedRentedByAgent.value === currentUser?.id;
    
    showOIAgentSelectionRent.value = false;
    
    if (isCurrentUser) {
        // نفس المستخدم = me
        rentedByChoice.value = 'me';
        // الـ form هيظهر عشان يضيف المستأجر
    } else {
        // مستخدم آخر = another_agent - ننفذ مباشرة
        await markAsRentedByAnotherAgent(selectedRentedByAgent.value);
    }
};

const markAsRentedByAnotherAgent = async (agentId) => {
    try {
        const payload = { 
            rented_by: 'another_agent',
            agent_id: agentId,
            rented_date: rentedDate.value
        };
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
        
        if (response.data.status) {
            property.value.status = 'rented';
            property.value.rented_by = 'another_agent';
            property.value.rented_by_agent_id = agentId;
            property.value.rented_date = rentedDate.value;
            const selectedAgent = availableAgentsForRentedBy.value.find(a => a.id === agentId);
            property.value.rented_by_agent_name = selectedAgent?.name;
            proxy.$showNotification('Property marked as rented by another agent!', 'success');
            closeRentedModal();
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as rented by another agent');
    }
};

const openAToAModalForRent = () => {
    aToAType.value = 'rented';
    aToAData.value = {company_name:'',agent_name: '', agent_phone: '', commercial_license: '' };
    showAToAModal.value = true;
    closeRentedModal();
};

const submitAToAForRent = async () => {
    if (!aToAData.value.company_name.trim() || !aToAData.value.agent_name.trim() || !aToAData.value.agent_phone.trim() || !aToAData.value.commercial_license.trim()) {
        proxy.$showNotification('Please fill all required fields', 'warning');
        return;
    }
    
    if (!rentedDate.value) {
        proxy.$showNotification('Please select rented date', 'warning');
        return;
    }

    try {
        submittingATO.value = true;
        
        const payload = {
            rented_by: 'a_to_a',
            rented_date: rentedDate.value,
            a_to_a_details: {
              company_name:aToAData.value.company_name,
                agent_name: aToAData.value.agent_name,
                agent_phone: aToAData.value.agent_phone,
                commercial_license: aToAData.value.commercial_license
            }
        };
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
        
        if (response.data.status) {
            property.value.status = 'rented';
            property.value.rented_by = 'a_to_a';
            property.value.rented_date = rentedDate.value;
            proxy.$showNotification('Property marked as rented via A to A!', 'success');
            closeAToAModal();
            closeRentedModal();
            await fetchProperty();
        }
    } catch (error) {
        handleApiError(error, 'Failed to submit A to A transaction');
    } finally {
        submittingATO.value = false;
    }
};

const closeRentedModal = () => {
    showRentedModal.value = false;
    rentedByChoice.value = null;
    selectedRentedByAgent.value = null;
    showOIAgentSelectionRent.value = false;
    rentedDate.value = '';
};
// دالة تنفيذ الإيجار
const markAsRented = async (rentedBy, agentId = null, ownerId = null) => {
    const rentedByText = rentedBy === 'oia' ? 'Oia' : 
                        rentedBy === 'other_company' ? 'Other Company' :
                        rentedBy === 'another_agent' ? 'Another Agent' : 
                        'you';
      showRentedModal.value = false;
    if (rentedBy === 'another_agent' && !agentId) {
        proxy.$showNotification('Please select an agent', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: `Mark as Rented by ${rentedByText}?`,
        text: rentedBy === 'another_agent' 
            ? `This property will be marked as rented by the selected agent.`
            : `This property will be marked as rented by ${rentedByText}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, Mark as Rented`,
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const payload = { 
            rented_by: rentedBy,
            rented_date: rentedDate.value
        };
        
        if (rentedBy === 'another_agent' && agentId) {
            payload.agent_id = agentId;
        }
        
        if (ownerId) {
            payload.owner_id = ownerId;
        }
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
        
        if (response.data.status) {
            property.value.status = 'rented';
            property.value.rented_by = rentedBy;
            property.value.rented_date = rentedDate.value;
            if (rentedBy === 'another_agent') {
                property.value.rented_by_agent_id = agentId;
                const selectedAgent = availableAgentsForRentedBy.value.find(a => a.id === agentId);
                property.value.rented_by_agent_name = selectedAgent?.name;
            }
            proxy.$showNotification(`Property marked as rented by ${rentedByText}!`, 'success');
            showRentedModal.value = false;
            selectedRentedByAgent.value = null;
            rentedDate.value = '';
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as rented');
    }
};

const submitNewOwnerAndMarkSold = async () => {
    if (!newOwner.value.first_name?.trim() || !newOwner.value.last_name?.trim() || 
        !newOwner.value.phone_number?.trim() || !newOwner.value.salutation) {
        proxy.$showNotification('Please fill required fields: Salutation, First name, Last name, Phone', 'warning');
        return;
    }
    
    // تحديد نوع العملية (بيع أم إيجار)
    const isRent = addPersonType.value === 'tenant';
    const typeChoice = isRent ? rentedByChoice.value : soldByChoice.value;
    const selectedAgent = isRent ? selectedRentedByAgent.value : selectedSoldByAgent.value;
    
    if (!typeChoice || !property.value?.id) return;

    try {
        isSubmittingOwner.value = true;
        const formData = new FormData();
        
        // إضافة جميع الحقول (نفس الكود)
        if (newOwner.value.salutation) formData.append('salutation', newOwner.value.salutation);
        formData.append('first_name', newOwner.value.first_name.trim());
        formData.append('last_name', newOwner.value.last_name.trim());
        if (newOwner.value.email?.trim()) formData.append('email', newOwner.value.email.trim());
        formData.append('phone_number', newOwner.value.phone_number.trim());
        if (newOwner.value.whatsapp_number?.trim()) formData.append('whatsapp_number', newOwner.value.whatsapp_number.trim());
        if (newOwner.value.second_phone_number?.trim()) formData.append('second_phone_number', newOwner.value.second_phone_number.trim());
        if (newOwner.value.nationality) formData.append('nationality', newOwner.value.nationality);
        if (newOwner.value.residency_status) formData.append('residency_status', newOwner.value.residency_status);
        if (newOwner.value.location_id) formData.append('location_id', newOwner.value.location_id);
        if (newOwner.value.notes?.trim()) formData.append('notes', newOwner.value.notes.trim());
        
        // رفع الملفات
        if (newOwner.value.id_front instanceof File) formData.append('id_front', newOwner.value.id_front);
        if (newOwner.value.id_back instanceof File) formData.append('id_back', newOwner.value.id_back);
        if (newOwner.value.visa_copy instanceof File) formData.append('visa_copy', newOwner.value.visa_copy);
        if (newOwner.value.passport_copy instanceof File) formData.append('passport_copy', newOwner.value.passport_copy);
        
        if (newOwner.value.additionalDocuments && newOwner.value.additionalDocuments.length > 0) {
            newOwner.value.additionalDocuments.forEach((item, index) => {
                const file = item.file || item;
                if (file instanceof File) {
                    formData.append(`additional_documents[${index}]`, file);
                }
            });
        }

        // إنشاء المالك في قاعدة البيانات
        const createRes = await api.post('/listings/owners', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        const createdOwner = createRes.data?.data || createRes.data;
        if (!createdOwner?.id) throw new Error('Owner created but no ID returned');

        // ========== هنا الفرق بين البيع والإيجار ==========
        if (isRent) {
            // ✅ حالة الإيجار - تذهب إلى API mark-rented
            const payload = { 
                rented_by: typeChoice, 
                rented_date: rentedDate.value 
            };
            if (typeChoice === 'another_agent' && selectedAgent) {
                payload.agent_id = selectedAgent;
            }
            payload.owner_id = createdOwner.id;
            
            const response = await api.patch(`/listings/properties/${property.value.id}/mark-rented`, payload);
            
            if (response.data?.data || response.data?.status !== false) {
                property.value.status = 'rented';
                property.value.rented_by = typeChoice;
                property.value.rented_date = rentedDate.value;
                if (typeChoice === 'another_agent' && selectedAgent) {
                    property.value.rented_by_agent_id = selectedAgent;
                    const selectedAgentData = availableAgentsForRentedBy.value.find(a => a.id === selectedAgent);
                    property.value.rented_by_agent_name = selectedAgentData?.name;
                }
                property.value.rented_owner_id = createdOwner.id;
                proxy.$showNotification('New tenant added and property marked as rented!', 'success');
            }
            showRentedModal.value = false;  // إغلاق مودال الإيجار
        } else {
            // ✅ حالة البيع - تذهب إلى API mark-converted
            await api.post(`/listings/properties/${property.value.id}/soldBy`, {
                owner_id: createdOwner.id
            });
            
            const payload = { sold_by: typeChoice };
            if (typeChoice === 'another_agent' && selectedAgent) {
                payload.agent_id = selectedAgent;
            }
            
            const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
            
            if (response.data?.data || response.data?.status !== false) {
                property.value.status = 'converted';
                property.value.sold_by = typeChoice;
                if (typeChoice === 'another_agent' && selectedAgent) {
                    property.value.sold_by_agent_id = selectedAgent;
                    const selectedAgentData = availableAgentsForSoldBy.value.find(a => a.id === selectedAgent);
                    property.value.sold_by_agent_name = selectedAgentData?.name;
                }
                property.value.owner_id = createdOwner.id;
                proxy.$showNotification('New owner added and property marked as sold!', 'success');
            }
            showSoldOutModal.value = false; // إغلاق مودال البيع
        }
        
        // تنظيف البيانات
        showAddOwnerModal.value = false;
        if (isRent) {
            rentedByChoice.value = null;
            selectedRentedByAgent.value = null;
            rentedDate.value = '';
        } else {
            soldByChoice.value = null;
            selectedSoldByAgent.value = null;
        }
        resetNewOwnerForm();
        
    } catch (error) {
        handleApiError(error, 'Failed to add owner or mark as ' + (isRent ? 'rented' : 'sold'));
    } finally {
        isSubmittingOwner.value = false;
    }
};
const revertFromRented = async () => {
    const result = await Swal.fire({
        title: 'Revert from Rented?',
        text: 'This property will be reverted from rented status.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Revert',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await api.patch(`/listings/properties/${property.value.id}/revert-rented`);
        
        if (response.data.status) {
            property.value.status = 'active';
            property.value.rented_by = null;
            property.value.rented_date = null;
            property.value.rented_by_agent_id = null;
            property.value.rented_owner_id = null;
            proxy.$showNotification('Property reverted from rented status successfully!', 'success');
            closeActionsDropdown();
        }
    } catch (error) {
        handleApiError(error, 'Failed to revert from rented');
    }
};
const openAddOwnerModal = (type = 'owner') => {
    console.log('🔓 openAddOwnerModal called with type:', type);
    console.log('soldByChoice:', soldByChoice.value);
    console.log('rentedByChoice:', rentedByChoice.value);
    
    addPersonType.value = type;
    
    if (type === 'tenant') {
        if (rentedByChoice.value === 'another_agent' && !selectedRentedByAgent.value) {
            proxy.$showNotification('Please select an agent first', 'warning');
            return;
        }
        if (!rentedDate.value) {
            proxy.$showNotification('Please select rented date', 'warning');
            return;
        }
    } else {
        if (soldByChoice.value === 'another_agent' && !selectedSoldByAgent.value) {
            proxy.$showNotification('Please select an agent first', 'warning');
            return;
        }
    }
    
    resetNewOwnerForm();
    showAddOwnerModal.value = true;
    console.log('✅ showAddOwnerModal set to true');
};

const resetNewOwnerForm = () => {
  newOwner.value = {
    salutation: '',
    first_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    whatsapp_number: '',
    second_phone_number: '',
    nationality: '',
    residency_status: '',
    location_id: '',
    id_front: null,
    id_back: null,
    visa_copy: null,
    passport_copy: null,
    notes: '',
    additionalDocuments: []
  };
  ownerLocations.value = [];
};

const onlyLettersOwner = (field) => {
  newOwner.value[field] = newOwner.value[field].replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '').replace(/[0-9\u0660-\u0669]/g, '');
};

const onlyNumbersOwner = (field) => {
  newOwner.value[field] = newOwner.value[field].replace(/[^0-9]/g, '');
};

const handleOwnerNationalityChange = (newNationality) => {
  if (newNationality === 'UAE') {
    newOwner.value.residency_status = 'resident';
    fetchOwnerLocations('resident');
  } else {
    newOwner.value.residency_status = '';
    newOwner.value.location_id = '';
    ownerLocations.value = [];
  }
};

const getOwnerLocationLabel = () => {
  if (newOwner.value.nationality === 'UAE') {
    return 'City';
  } else if (newOwner.value.residency_status === 'resident') {
    return 'Emirate';
  } else if (newOwner.value.residency_status === 'non_resident') {
    return 'Country';
  }
  return 'Location';
};

const getOwnerLocationPlaceholder = () => {
  if (newOwner.value.nationality === 'UAE') {
    return 'Select City';
  } else if (newOwner.value.residency_status === 'resident') {
    return 'Select Emirate';
  } else if (newOwner.value.residency_status === 'non_resident') {
    return 'Select Country';
  }
  return 'Select location';
};

const fetchOwnerLocations = async (residencyStatus) => {
  try {
    const response = await api.get(`/listings/owners/locations/available?residency_status=${residencyStatus}`);
    ownerLocations.value = response.data.data || response.data;
  } catch (error) {
    console.error("❌ Error fetching locations:", error);
    proxy.$showNotification("❌ Failed to load locations.", "error");
  }
};

watch(() => newOwner.value.residency_status, async (newStatus) => {
  if (newOwner.value.nationality === 'UAE') return;
  if (newStatus) {
    await fetchOwnerLocations(newStatus);
  } else {
    ownerLocations.value = [];
    newOwner.value.location_id = '';
  }
});

const MAX_OWNER_ADDITIONAL_SIZE = 10 * 1024 * 1024;
const ALLOWED_OWNER_ADDITIONAL_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];

const handleOwnerAdditionalDocumentsUpload = (event) => {
  const files = event.target.files ? Array.from(event.target.files) : [];
  if (!files.length) return;
  const list = newOwner.value.additionalDocuments || [];
  for (const file of files) {
    if (file.size > MAX_OWNER_ADDITIONAL_SIZE) {
      proxy.$showNotification(`File "${file.name}" exceeds 10MB`, "error");
      continue;
    }
    if (!ALLOWED_OWNER_ADDITIONAL_TYPES.includes(file.type)) {
      proxy.$showNotification(`File "${file.name}" has invalid type. Use PDF, JPG, PNG, SVG.`, "error");
      continue;
    }
    list.push({ file, name: file.name });
  }
  newOwner.value.additionalDocuments = list;
  event.target.value = '';
};

const removeOwnerAdditionalDocument = (index) => {
  newOwner.value.additionalDocuments.splice(index, 1);
};

const handleOwnerFileUpload = (event, field) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      proxy.$showNotification("❌ File size must be less than 5MB", "error");
      event.target.value = '';
      return;
    }
    newOwner.value[field] = file;
    proxy.$showNotification(`✅ ${field.replace('_', ' ')} uploaded successfully`, "success");
  }
};



const markAsSold = async (soldBy, agentId = null) => {
    const soldByText = soldBy === 'oia' ? 'Oia' : 
                      soldBy === 'other_company' ? 'Other Company' :
                      soldBy === 'another_agent' ? 'Another Agent' : 
                      'you';
    
    showSoldOutModal.value = false;
    
    if (soldBy === 'another_agent' && !agentId) {
        proxy.$showNotification('Please select an agent', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: `Mark as Sold by ${soldByText}?`,
        text: soldBy === 'another_agent' 
            ? `This property will be marked as sold by the selected agent.`
            : `This property will be marked as sold by ${soldByText}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, Mark as Sold`,
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const payload = { sold_by: soldBy };
        if (soldBy === 'another_agent' && agentId) {
            payload.agent_id = agentId;
        }
        
        const response = await api.patch(`/listings/properties/${property.value.id}/mark-converted`, payload);
        
        if (response.data.status) {
            property.value.status = 'converted';
            property.value.sold_by = soldBy;
            if (soldBy === 'another_agent') {
                property.value.sold_by_agent_id = agentId;
                const selectedAgent = availableAgentsForSoldBy.value.find(a => a.id === agentId);
                property.value.sold_by_agent_name = selectedAgent?.name;
            }
            proxy.$showNotification(`Property marked as sold by ${soldByText}!`, 'success');
            selectedSoldByAgent.value = null;
        }
    } catch (error) {
        handleApiError(error, 'Failed to mark as sold');
    }
};

const revertFromConverted = async () => {
  const result = await Swal.fire({
    title: 'Revert from Sold Out?',
    text: 'This property will be reverted from sold out status.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ffc107',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Revert',
    cancelButtonText: 'Cancel'
  });

  if (!result.isConfirmed) return;

  try {
    const response = await api.patch(`/listings/properties/${property.value.id}/revert-converted`);
    
    if (response.data.status) {
      property.value.status = 'active';
      property.value.sold_by = null;
      proxy.$showNotification('Property reverted from sold out status successfully!', 'success');
      closeActionsDropdown();
    }
  } catch (error) {
    handleApiError(error, 'Failed to revert from sold out');
  }
};

 const goToAgentDetails = (agentId) => {

  router.push(`/users/${agentId}`);

};

    const openChatWithAgent = () => {
      if (!getCurrentUser()) {
        Swal.fire({ title: 'Please log in', text: 'You need to be logged in to start a chat.', icon: 'info' });
        return;
      }
      if (!property.value?.agent) return;
      chatAgent.value = {
        id: property.value.agent.id,
        name: property.value.agent.name || property.value.agent.email,
        email: property.value.agent.email,
        avatar: property.value.agent.avatar_url || property.value.agent.avatar || null,
      };
      chatListingId.value = property.value.id ?? null;
      showChatPopup.value = true;
    };

    // Real-time updates methods
    const listenForAccessRequestUpdates = () => {
      const user = JSON.parse(localStorage.getItem('user'));
      if (!user || !window.Echo) {
        console.log('❌ User or Echo not available for real-time updates');
        return;
      }

      console.log('🔔 PropertyDetails: Listening for access request updates');

      const userChannel = window.Echo.private(`user.${user.id}`);
      userChannel.listen('.access.request.updated', (event) => {
        console.log('🎉 PropertyDetails: Received user update:', event);
        if (event.listing_id == route.params.id) {
          handleAccessRequestUpdate(event);
        }
      });

      const listingChannel = window.Echo.private(`listing.${route.params.id}`);
      listingChannel.listen('.access.request.updated', (event) => {
        console.log('🎉 PropertyDetails: Received listing update:', event);
        handleAccessRequestUpdate(event);
      });

      echoListeners.value.push(userChannel, listingChannel);
    };

    const handleAccessRequestUpdate = (event) => {
      console.log('🔄 PropertyDetails: Handling update:', event);
      
      if (event.request_type === 'unit_number') {
        requestStatus.value.unit_number_status = event.status;
        
        if (event.status === 'approved') {
          requestStatus.value.unit_number_approved_at = formatDateTime(event.responded_at);
        } else if (event.status === 'rejected') {
          requestStatus.value.unit_number_rejected_at = formatDateTime(event.responded_at);
        }
        
      } else if (event.request_type === 'owner_data') {
        requestStatus.value.owner_info_status = event.status;
        
        if (event.status === 'approved') {
          requestStatus.value.owner_info_approved_at = formatDateTime(event.responded_at);
        } else if (event.status === 'rejected') {
          requestStatus.value.owner_info_rejected_at = formatDateTime(event.responded_at);
        }
      }
      
      showRealTimeNotification(event);
      
      if (event.status === 'approved' || event.status === 'rejected') {
        setTimeout(() => {
          fetchRequestStatus();
        }, 1000);
      }
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
        //   console.log(response.data.data);
        //   console.log('Property Data:', property.value);
          console.log('Area Data:', property.value.area);
          console.log('Area Hierarchy:', property.value.area?.hierarchy);
          console.log('Area Parent:', property.value.area?.parent);
                console.log('📦 Additional features:', property.value.additional_features);

          if (property.value.gallery_images && property.value.gallery_images.length > 0) {
            currentMainImage.value = getImageUrl(property.value.main_image);
          }
          
          await fetchRequestStatus();
           const hasAccess = checkAccessAndRedirect();
              if (!hasAccess) {
                return;
              }
        } else {
          throw new Error(response.data.message || 'Failed to fetch property');
        }
      } catch (err) {
        handleApiError(err, 'Failed to load property details');
      } finally {
        loading.value = false;
      }
    };

    // Sync property agent to sessionStorage so global "Chat with Agent" (App.vue) can open chat
    const formatChatPrice = (value, currency = 'AED') => {
      if (value === undefined || value === null || value === '') return ''
      const numeric = typeof value === 'number'
        ? value
        : Number(String(value).replace(/,/g, ''))

      if (Number.isNaN(numeric)) return `${value} ${currency}`
      return `${numeric.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`
    }

    watch(property, (p) => {
      if (p?.agent) {
        try {
          const propertyContext = {
            propertyId: p.id ?? null,
            title: p.title || p.name || p.reference_number || `Property #${p.id ?? ''}`,
            reference: p.reference_number || p.reference || '',
            location: [p?.project?.name, p?.area?.title || p?.area?.name].filter(Boolean).join(' - '),
            price: formatChatPrice(p?.price, p?.currency || 'AED'),
          };
          sessionStorage.setItem('propertyChatAgent', JSON.stringify({
            id: p.agent.id,
            name: p.agent.name || p.agent.email,
            email: p.agent.email,
            avatar: p.agent.avatar_url || p.agent.avatar || null,
          }));
          sessionStorage.setItem('propertyChatListingId', String(p.id ?? ''));
          sessionStorage.setItem('propertyChatContext', JSON.stringify(propertyContext));
        } catch (_) {}
      } else {
        sessionStorage.removeItem('propertyChatAgent');
        sessionStorage.removeItem('propertyChatListingId');
        sessionStorage.removeItem('propertyChatContext');
      }
    }, { immediate: true });

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

    const formatDateTime = (dateString) => {
      if (!dateString) return '';
      
      const date = new Date(dateString);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);
      
      if (diffMins < 60) {
        return `${diffMins} min${diffMins !== 1 ? 's' : ''} ago`;
      } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
      } else if (diffDays < 7) {
        return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
      } else {
        return date.toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
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

    const requestUnitNumber = async () => {
      try {
        loadingRequest.value = true;
        
        const response = await api.post(`/listings/access-requests/${property.value.id}/request`, {
          request_type: 'unit_number',
          reason: 'Requesting unit number for property details'
        });
        
        if (response.data.status) {
          proxy.$showNotification('Unit number request sent successfully!', 'success');
          
          requestStatus.value.unit_number_status = 'pending';
          requestStatus.value.unit_number_requested_at = new Date().toISOString();
          
          await fetchRequestStatus();
          
          closeActionsDropdown();
        }
      } catch (err) {
        handleApiError(err, 'Failed to send unit number request');
      } finally {
        loadingRequest.value = false;
      }
    };

    const requestOwnerInfo = async () => {
      try {
        loadingRequest.value = true;
        
        const response = await api.post(`/listings/access-requests/${property.value.id}/request`, {
          request_type: 'owner_data',
          reason: 'Requesting owner information for contact purposes'
        });
        
        if (response.data.status) {
          proxy.$showNotification('Owner info request sent successfully!', 'success');
          
          requestStatus.value.owner_info_status = 'pending';
          requestStatus.value.owner_info_requested_at = new Date().toISOString();
          
          await fetchRequestStatus();
          
          closeActionsDropdown();
        }
      } catch (err) {
        handleApiError(err, 'Failed to send owner info request');
      } finally {
        loadingRequest.value = false;
      }
    };
const cancelRequestType = ref('');
const cancelReason = ref('');
const cancellingSpecificRequest = ref(false);
    const cancelRequest = async (requestType) => {
 
  
  const requestName =
  requestType === 'unit_number'
    ? 'Unit Number'
    : requestType === 'viewing'
    ? 'Viewing'
    : 'Owner Information';

  
  const result = await Swal.fire({
    title: 'Cancel Request?',
    text: `Are you sure you want to cancel your ${requestName} request?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Cancel Request',
    cancelButtonText: 'Keep Request',
    reverseButtons: true
  });

  if (!result.isConfirmed) return;

  try {
    cancellingRequest.value = true;
    
    const response = await api.post(`/listings/access-requests/${property.value.id}/cancel`, {
      request_type: requestType
    });
    
    if (response.data.status) {
      proxy.$showNotification('Request cancelled successfully!', 'success');
      
      if (requestType === 'unit_number') {
        requestStatus.value.unit_number_status = null;
      } else if (requestType === 'owner_data') {
        requestStatus.value.owner_info_status = null;
      }
      
      await fetchRequestStatus();
      closeActionsDropdown();
    }
  } catch (err) {
    handleApiError(err, 'Failed to cancel request');
  } finally {
    cancellingRequest.value = false;
  }
};
const showCancelViewingModal = ref(false);
const openCancelModal = (event) => {
  console.log('openCancelModal called');
  
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  setTimeout(() => {
    showCancelViewingModal.value = true;
    cancelRequestType.value = 'viewing';
    cancelReason.value = '';
    closeActionsDropdown();
  }, 10);
  
  console.log('showCancelViewingModal will be set to true after delay');
};
const closeCancelReasonModal = () => {
  showCancelViewingModal.value = false;
  cancelReason.value = '';
  cancellingSpecificRequest.value = false;
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
            confirmButtonColor: '#0B0736',
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
          confirmButtonColor: '#0B0736'
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
          confirmButtonColor: '#0B0736'
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
          confirmButtonColor: '#0B0736'
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
        return getImageUrl(property.value.gallery_images[0].image_url_final);
      }
      return '/default-property.jpg';
    };

    const getGalleryThumbnails = () => {
      if (!property.value?.gallery_images) return [];
      return property.value.gallery_images.slice(0, 2);
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
          confirmButtonColor: '#0B0736'
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
          router.push('/listings/properties');
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

    

    const handleKeydown = (event) => {
      if (!showLightbox.value) return;
      switch(event.key) {
        case 'Escape': closeLightbox(); break;
        case 'ArrowLeft': prevImage(); break;
        case 'ArrowRight': nextImage(); break;
      }
    };

  const PDF_CONFIG = {
  pageWidth: 210,   
  pageHeight: 148, 
  margin: 0,
  contentHeight: 128,
};

// Cache: stores icon URL → PNG data URL (rasterized via canvas)
const svgIconCache = {};

// Load any image URL into a canvas and return a PNG data URL.
// Works for SVG, PNG, JPG. No fetch needed — browser handles loading.
const imgUrlToPng = (url, size = 80) => {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        canvas.getContext('2d').drawImage(img, 0, 0, size, size);
        resolve(canvas.toDataURL('image/png'));
      } catch (e) {
        console.warn('Canvas draw failed:', url, e.message);
        resolve(null);
      }
    };
    img.onerror = () => {
      console.warn('Image load failed:', url);
      resolve(null);
    };
    img.src = url;
  });
};

const preloadSvgIcons = async () => {
  const features = property.value?.project?.features || [];
  if (!features.length) return;

  const urls = [...new Set(
    features.map(f => f?.img || f?.icon_url || f?.icon || null).filter(Boolean)
  )];

  console.log('Pre-loading icons:', urls);

  await Promise.all(urls.map(async (url) => {
    if (svgIconCache[url] !== undefined) return;
    svgIconCache[url] = await imgUrlToPng(url, 80);
    console.log('Icon', url, svgIconCache[url] ? '✅' : '❌');
  }));
};
// دالة لتحويل SVG URL إلى Base64 PNG
const convertSvgToPng = async (svgUrl) => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = "Anonymous";
    
    img.onload = () => {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      
      // تحديد أبعاد الصورة
      canvas.width = img.width || 100;
      canvas.height = img.height || 100;
      
      // رسم الصورة على canvas
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      
      // تحويل إلى PNG
      const pngDataUrl = canvas.toDataURL('image/png');
      resolve(pngDataUrl);
    };
    
    img.onerror = () => {
      console.error('Failed to load SVG:', svgUrl);
      reject(new Error(`Failed to load SVG: ${svgUrl}`));
    };
    
    img.src = svgUrl;
  });
};

// تعديل دالة preloadFeatureImages لتحويل SVG إلى PNG
const preloadFeatureImages = async () => {
  const project = property.value?.project;
  const features = Array.isArray(project?.features) ? project.features : [];
  
  const conversionPromises = features
    .filter(f => f?.img || f?.icon)
    .map(async (feature) => {
      const imageUrl = feature.img || feature.icon;
      if (imageUrl && imageUrl.toLowerCase().endsWith('.svg')) {
        try {
          // تحويل SVG إلى PNG Base64
          const pngBase64 = await convertSvgToPng(getImageUrl(imageUrl));
          // تخزين الصورة المحولة في ميزة مؤقتة
          feature.convertedImage = pngBase64;
          console.log('✅ SVG converted to PNG for:', feature.name);
        } catch (error) {
          console.error('Failed to convert SVG:', error);
          feature.convertedImage = null;
        }
      }
    });
  
  await Promise.all(conversionPromises);
  console.log('✅ All feature images preloaded and converted');
};
const generatePDF = async () => {
  try {
    // Show loading
    const loadingToast = Swal.fire({
      title: 'Generating Sales Offer...',
      text: 'Please wait while we prepare your document',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    const userData = localStorage.getItem('user');
    const currentUser = userData ? JSON.parse(userData) : null;

    // Pre-load SVG feature icons as base64 so html2canvas can render them
    await preloadSvgIcons();
    await preloadFeatureImages();
    // Prepare offer data
    const offerData = {
      generated_at: new Date().toISOString(),
      property_id: property.value.id,
      property_title: property.value.title || property.value.area?.area_title,
      client_name: 'Potential Client', 
      generated_by: currentUser?.name,
      offer_details: {
        price: property.value.price,
        bedrooms: property.value.number_of_bedrooms,
        bathrooms: property.value.number_of_bathrooms,
        area: property.value.area?.area_title
      }
    };

    // First, save offer to database
    const saveResponse = await api.post(`/listings/properties/${property.value.id}/generate-offer`, {
      offer_data: offerData,
      client_name: 'Potential Client' 
    });

    if (!saveResponse.data.status) {
      throw new Error('Failed to save offer record');
    }

    console.log('✅ Offer saved:', saveResponse.data);

    // Continue with PDF generation
    const pdfContent = createNewDesignContent(currentUser);
    const filename = `sales-offer-${saveResponse.data.data.offer.offer_number}.pdf`;

    const options = {
      margin: [0,0],
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 3, useCORS: true, logging: false, allowTaint: true },
      jsPDF: { unit: 'mm', format: [210, 148], orientation: 'landscape' },
      pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    const pdf = await html2pdf().set(options).from(pdfContent).toPdf().get('pdf');
    const pageCount = pdf.internal.getNumberOfPages();
    pdf.deletePage(pageCount);

    const pdfBlob = pdf.output('blob');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(pdfBlob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    await loadingToast.close();

    // Show success with offer number
    proxy.$showNotification(`Sales Offer ${saveResponse.data.data.offer.offer_number} generated successfully!`, 'success');

    // Optional: Show who created the offer
    Swal.fire({
      icon: 'success',
      title: 'Offer Generated!',
      html: `
        <div style="text-align: left;">
          <p><strong>Offer Number:</strong> ${saveResponse.data.data.offer.offer_number}</p>
          <p><strong>Created By:</strong> ${currentUser?.name || 'You'}</p>
          <p><strong>Date:</strong> ${new Date().toLocaleString()}</p>
        </div>
      `,
      confirmButtonColor: '#0B0736'
    });

  } catch (error) {
    console.error('PDF generation error:', error);
    proxy.$showNotification('Failed to generate PDF. Please try again.', 'error');
  }
};

const showOfferHistory = async () => {
  try {
    const response = await api.get(`/listings/properties/${property.value.id}/offers`);
    
    if (response.data.status) {
      const offers = response.data.data;
      
      if (offers.length === 0) {
        Swal.fire({
          title: 'No Offers',
          text: 'No offers have been generated for this property yet.',
          icon: 'info',
          confirmButtonColor: '#0B0736'
        });
        return;
      }

      // عرض قائمة العروض
      let html = '<div style="max-height: 400px; overflow-y: auto;">';
      offers.forEach(offer => {
        html += `
          <div style="border-bottom: 1px solid #e9ecef; padding: 12px; margin-bottom: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                
                <div style="font-size: 14px">
                  <strong style="font-size: 14px"> ${offer.creator?.name || 'Unknown'}</strong>
                </div>
                
              </div>
              <span style="font-size: 12px; color: #999;">
                 ${new Date(offer.created_at).toLocaleString()}
              </span>
            </div>
          </div>
        `;
      });
      html += '</div>';

      Swal.fire({
        title: 'Offer History',
        html: html,
        width: '600px',
        confirmButtonColor: '#0B0736',
        confirmButtonText: 'Close'
      });
    }
  } catch (error) {
    console.error('Error fetching offer history:', error);
    proxy.$showNotification('Failed to load offer history', 'error');
  }
};

const createNewDesignContent = (currentUser) => {
  const container = document.createElement('div');
  container.style.cssText = 'margin:0 !important; font-family:Arial, sans-serif !important; background:#ffffff !important; width:100% !important; height:100% !important;';

  const hasFloorPlans = property.value?.floor_plans && Array.isArray(property.value.floor_plans) && property.value.floor_plans.length > 0;
  const hasProject = property.value?.project && property.value.project.id;
  const features = hasProject ? (Array.isArray(property.value.project?.features) ? property.value.project.features.map(f => f?.name || f?.title || f).filter(Boolean) : []) : [];
  const gallerySlides = createGallerySlides() || [];
  const paymentDetailsSlide = createPaymentDetailsSlide();

  container.innerHTML = `
 <!-- Slide 1 - Cover -->
    ${createSlide1(currentUser)}
<!-- Slide 2 - Property Details -->
    ${createSlide2()}

    <!-- Slide 2.5 - Payment Details (only when data is present) -->
    ${paymentDetailsSlide}

    <!-- Slide 5 - Floor Plan (optional) -->
    ${hasFloorPlans ? createSlide5() : ''}

      <!-- Slide 6 - Additional Images -->
  
<!-- Slide 7 - Additional Images -->
   ${gallerySlides}

    ${hasProject ? createSlide4() : ''}

    ${features.length > 0 ? createSlide3() : ''}

    <!-- Slide 9 - Thank You Page -->
    ${createSlide9(currentUser)}
  `;

  return container;
};

const createGallerySlides = () => {
  const images = (property.value?.gallery_images || []).slice(0, 9); 

  const chunks = chunkArray(images, 3);

  return chunks.map(chunk => createGallerySlide(chunk)).join('');
};
const chunkArray = (arr, size = 3) => {
  const result = [];
  for (let i = 0; i < arr.length; i += size) {
    result.push(arr.slice(i, i + size));
  }
  return result;
};
const createGallerySlide = (imagesChunk) => {
  const img1 = imagesChunk[0]
    ? getImageUrl(imagesChunk[0].image_url)
    : 'placeholder.png';

  const img2 = imagesChunk[1]
    ? getImageUrl(imagesChunk[1].image_url)
    : 'placeholder.png';

  const img3 = imagesChunk[2]
    ? getImageUrl(imagesChunk[2].image_url)
    : 'placeholder.png';

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; background:#fff !important; position:relative !important; display:flex !important; flex-direction:column !important;">
    
    <div style="width:100% !important; height:90% !important; padding:4mm !important; box-sizing:border-box !important; display:flex !important; gap:3mm !important; overflow:hidden !important;">
      
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img1}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img2}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; position:relative !important; background-image:url('${img3}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;">
        
        <div style="position:absolute !important; top:5mm !important; right:5mm !important;">
          <img src="${OiaLogo}" style="width:15mm !important; display:block !important;" />
        </div>

      </div>

    </div>

    ${createFooter()}
  </div>
  `;
};
const createSlide1 = (currentUser) => {
  const bedroomsText = property.value?.number_of_bedrooms === 0 ? 'Studio' : `${property.value?.number_of_bedrooms || ''} Bedrooms`;
  const propertyTypeName = property.value?.property_type?.name || '';
  const location = property.value?.area?.title || property.value?.area?.area_title || 'Abu Dhabi, UAE';
  const price = formatPrice(property.value?.price) || '';
  const listingStatus = property.value?.listing_status || 'Sale';
  const bgImage = getMainImage();
  const projectTitle = property.value?.project?.title || property.value?.project?.name || '';

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; position:relative !important; overflow:hidden !important;">
    <div style="position:absolute !important; top:0 !important; left:0 !important; width:100% !important; height:100% !important; background-image:url('${bgImage}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
    <div style="position:absolute !important; top:0 !important; left:0 !important; width:100% !important; height:100% !important; background:rgba(0,0,0,0.40) !important;"></div>
    <div style="position:absolute !important; top:7mm !important; right:8mm !important; z-index:10 !important;">
      <img src="${OiaLogo}" style="width:18mm !important; display:block !important;" />
    </div>
    <div style="position:absolute !important; bottom:16% !important; left:10mm !important; width:42% !important; background:#fff !important; border-radius:5mm !important; padding:7mm 9mm 7mm 9mm !important; box-sizing:border-box !important;">
      <p style="font-size:16px; line-height: 25px; font-weight:normal; background:#733E87; display:inline-block; padding:0px 20px 10px 20px; text-transform:uppercase; border-radius:6px; color:#fff; margin:0px 0 18px 0; position:absolute !important; top:-10px !important;  font-family: 'Montserrat', sans-serif; ">For ${listingStatus}</p>
      <h1 style="color:#0B0736 !important; font-size:7mm !important; font-weight:bold; margin:0 0 12px 0; line-height:1.1; text-transform:uppercase;font-family: 'Montserrat', sans-serif;">${projectTitle}</h1>
      <p  style="font-size:20px; color:#733E87; font-weight:600; margin:0 0 18px 0;font-family: 'Montserrat', sans-serif;">${bedroomsText} ${propertyTypeName}</p>
      <p style="font-size:3.2mm !important; line-height:5mm !important; margin:0 0 4mm 0 !important; color:#818181 !important; display:flex !important; align-items:flex-start !important; gap:2mm !important;">
       <svg xmlns="http://www.w3.org/2000/svg" width="16" height="18" viewBox="0 0 24 30" style="flex-shrink:0 !important; margin-top:2px !important;" fill="#733E87"><path d="M12 0C7.6 0 4 3.6 4 8c0 6 8 16 8 16s8-10 8-16c0-4.4-3.6-8-8-8zm0 11c-1.7 0-3-1.3-3-3s1.3-3 3-3 3 1.3 3 3-1.3 3-3 3z"/></svg>
        <span  style="font-size: 14px; color: #818181;font-family: 'Montserrat', sans-serif;" >${location}</span>
      </p>
      <div style="border-top:0.3mm solid #ddd !important; margin-bottom:5mm !important; margin-top:15mm !important;"></div>
      <h2 style="font-size:7mm !important; color:#733E87 !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">AED ${price}</h2>
    </div>
    ${createFooter()}
  </div>
  `;
};

const createSlide2 = () => {
  const galleryImages = property.value?.gallery_images || [];
  const bgImage = galleryImages.length > 1
    ? getImageUrl(galleryImages[1].image_url)
    : galleryImages.length > 0
      ? getImageUrl(galleryImages[0].image_url)
      : getMainImage();
  const propertyType = property.value?.property_type?.name || 'N/A';
  const furnitureStatus = property.value?.furnished_status || 'N/A';
  const bedrooms = property.value?.number_of_bedrooms === 0 ? 'Studio' : (property.value?.number_of_bedrooms ?? 'N/A');
  const bathrooms = property.value?.number_of_bathrooms ?? 'N/A';
  const areaSize = property.value?.size_sqft ? `${property.value.size_sqft} SQFT` : 'N/A';
  const completionStatus = property.value?.completion_status || 'Under Construction';

  return `
  <div style="width:210mm !important; height:148mm !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; position:relative !important; overflow:hidden !important;">
    <div style="position:absolute !important; top:0 !important; left:0 !important; width:100% !important; height:100% !important; background-image:url('${bgImage}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
    <div style="position:absolute !important; top:0 !important; left:0 !important; width:100% !important; height:100% !important; background:rgba(0,0,0,0.68) !important;"></div>
    <div style="position:relative !important; z-index:5 !important; width:100% !important; height:90% !important; box-sizing:border-box !important; padding:12mm 16mm 10mm 16mm !important; display:flex !important; flex-direction:column !important; justify-content:space-between !important;">
      <div style="display:flex !important; justify-content:space-between !important; align-items:flex-start !important;">
        <h1 style="color:#fff !important; font-size:7mm !important; font-weight:700 !important; margin:0 !important; line-height:1.1 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">Property<br>Details</h1>
        <img src="${OiaLogo}" style="width:18mm !important; display:block !important;" />
      </div>
      <div style="width:100% !important;">
        <div style="display:flex !important; padding-bottom:5mm !important; margin-bottom:5mm !important;">
          <div style="flex:1 !important; padding-right:6mm !important; border-right:0.3mm solid rgba(255,255,255,0.3) !important;">
            <p style="color:#fff !important; font-size:2.8mm !important; margin:0 0 2mm 0 !important;  font-family: 'Montserrat', sans-serif;">Property Type</p>
            <p style="color:#fff !important; font-size:4.5mm !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif;">${propertyType}</p>
          </div>
          <div style="flex:1 !important; padding:0 6mm !important; border-right:0.3mm solid rgba(255,255,255,0.3) !important;">
            <p style="color:#fff !important; font-size:2.8mm !important; margin:0 0 2mm 0 !important;font-family: 'Montserrat', sans-serif;">Bedrooms</p>
            <p style="color:#fff !important; font-size:4.5mm !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif;">${bedrooms}</p>
          </div>
          <div style="flex:1 !important; padding:0 6mm !important; border-right:0.3mm solid rgba(255,255,255,0.3) !important;">
            <p style="color:#fff !important; font-size:2.8mm !important; margin:0 0 2mm 0 !important;font-family: 'Montserrat', sans-serif;">Bathrooms</p>
            <p style="color:#fff !important; font-size:4.5mm !important; font-weight:700 !important; margin:0 !important;font-family: 'Montserrat', sans-serif;">${bathrooms}</p>
          </div>
          <div style="flex:1 !important; padding-left:6mm !important;">
            <p style="color:#fff !important; font-size:2.8mm !important; margin:0 0 2mm 0 !important;font-family: 'Montserrat', sans-serif;">Area Size</p>
            <p style="color:#fff !important; font-size:4.5mm !important; font-weight:700 !important; margin:0 !important;font-family: 'Montserrat', sans-serif;">${areaSize}</p>
          </div>
        </div>
        <div style="display:flex !important;">
         
          <div style="flex:1 !important;  border-right:0.3mm solid rgba(255,255,255,0.3) !important;">
            <p style="color:#fff !important; font-size:2.8mm !important; margin:0 0 2mm 0 !important;font-family: 'Montserrat', sans-serif !important;">Completion Status</p>
            <p style="color:#fff !important; font-size:4.5mm !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">${completionStatus}</p>
          </div>

        </div>
      </div>
    </div>
    ${createFooter()}
  </div>
  `;
};

const createSlide3 = () => {
  const project = property.value?.project;
  const features = Array.isArray(project?.features)
  ? project.features
      .map(f => ({
        name: f?.name || f?.title || f,
        image: f?.img || f?.icon || null
      }))
      .filter(f => f.name)
  : [];
  const half = Math.ceil(features.length / 2);
  const col1 = features.slice(0, half);
  const col2 = features.slice(half);
  const projectImage = project?.image ? getImageUrl(project.image) : getMainImage();
  const renderItem = (feature) => {
      console.log(feature);
    const imageUrl = feature.image ? getImageUrl(feature.image) : null;

    return `
      <div style="display:flex !important; align-items:flex-start !important; gap:2mm !important; margin:0 0 3mm 0 !important;">
        
        ${
          imageUrl
            ? `<img src="${imageUrl}" 
                  style="width:4mm !important; height:4mm !important; object-fit:contain !important; flex-shrink:0 !important; margin-top:0.5mm !important;" />`
            : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#733E87"
                  style="width:3mm !important; height:3mm !important; flex-shrink:0 !important; margin-top:0.5mm !important;">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>`
        }

        <p style="margin:0 !important; font-size:3.2mm !important; line-height:4.5mm !important; color:#333 !important;">
          ${feature.name}
        </p>
      </div>
    `;
  };
  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; position:relative !important; overflow:hidden !important; display:flex !important; flex-direction:column !important;">
    <div style="width:100% !important; height:90% !important; display:flex !important; overflow:hidden !important;">
      <div style="width:50% !important; height:100% !important; background:#fff !important; padding:8mm 8mm 10mm 8mm !important; box-sizing:border-box !important; display:flex !important; flex-direction:column !important;">
        <h1 style="color:#0B0736 !important; font-size:7mm !important; font-weight:700 !important; margin:0 0 10mm 0 !important; line-height:1.1 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">Amenities &amp;<br>Features</h1>
        <div style="display:flex !important; gap:5mm !important; flex:1 !important;">
          <div style="flex:1 !important;">${col1.map(renderItem).join('')}</div>
          <div style="flex:1 !important;">${col2.map(renderItem).join('')}</div>
        </div>
      </div>
      <div style="width:50% !important; height:100% !important; position:relative !important;">
        <div style="width:100% !important; height:100% !important; background-image:url('${projectImage}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
        <div style="position:absolute !important; top:7mm !important; right:7mm !important;">
          <img src="${OiaLogo}" style="width:18mm !important; display:block !important;" />
        </div>
      </div>
    </div>
    ${createFooter()}
  </div>
  `;
};


          
const createSlide4 = () => {
  const project = property.value?.project;
  const projectTitle    = project?.title || project?.name || '';
  const projectAbout = project?.about || '';
  // const projectImage = project?.image2 ? getImageUrl(project.image2) : getMainImage();
  const projectImage = project?.image ? getImageUrl(project.image) : getMainImage();
  const aboutLimited = limitText(projectAbout, 800);

  return `
  <div style="width:210mm !important; height:148mm !important;  padding:0 !important; margin:0 !important; box-sizing:border-box !important; background:#0B0736 !important; position:relative !important; display:flex !important; align-items:center !important; justify-content:center !important;">
    <div style="width:95% !important; height:90% !important; background:#fff !important; border-radius:0mm !important; overflow:hidden !important; display:flex !important;">
      <div style="width:50% !important; padding:8mm !important; box-sizing:border-box !important; display:flex !important; flex-direction:column !important; justify-content:flex-start !important; overflow:hidden !important;">
        <h1 style="color:#0B0736 !important; font-size:7mm !important; font-weight:700 !important; margin:0 0 3mm 0 !important; line-height:1.1 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">About<br>The Project</h1>
        
        <p style="font-size:5mm !important; font-weight:bold !important; line-height:10mm !important; color:#0B0736 !important; margin:0 !important; text-align:justify !important; overflow:hidden !important;font-family: 'Montserrat', sans-serif !important; margin-bottom:2mm !important;">${projectTitle}</p>
        <p style="font-size:3.2mm !important; line-height:5.5mm !important; color:#444 !important; margin:0 !important; text-align:justify !important; overflow:hidden !important;font-family: 'Montserrat', sans-serif !important;">${formatTextForPDF(aboutLimited)}</p>
      </div>
      <div style="width:50% !important; height:100% !important; background-image:url('${projectImage}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
    </div>
  </div>
  `;
};




const createSlide5 = () => {
  const floorPlans = property.value?.floor_plans || [];
  if (!floorPlans.length) return '';
  const floorPlan1 = getImageUrl(floorPlans[0].image_url);
  const floorPlan2 = floorPlans.length > 1 ? getImageUrl(floorPlans[1].image_url) : null;

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; background:#fff !important; position:relative !important;">
    <div style="width:100% !important; height:90% !important; padding:10mm 14mm 7mm 14mm !important; box-sizing:border-box !important;">
      <div style="display:flex !important; justify-content:space-between !important; align-items:flex-start !important; margin-bottom:8mm !important;">
        <h1 style="color:#0B0736 !important;  font-size:6mm !important; font-weight:700 !important;  margin:0 !important; text-transform:uppercase !important;font-family: 'Montserrat', sans-serif !important;">Floor Plan</h1>
        <img src="${logo}" style="width:18mm !important; display:block !important;" />
      </div>
      <div style="display:flex !important; gap:6mm !important; height:75% !important;">
        <div style="flex:1 !important; background-image:url('${floorPlan1}') !important; background-size:contain !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
        ${floorPlan2 ? `
        <div style="flex:1 !important; background-image:url('${floorPlan2}') !important; background-size:contain !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
        ` : ''}
      </div>
    </div>
    ${createFooter()}
  </div>
  `;
};

const createSlide6 = () => {
  const images = property.value?.gallery_images || [];
  const img1 = images.length > 0 ? getImageUrl(images[0].image_url) : 'placeholder.png';
  const img2 = images.length > 1 ? getImageUrl(images[1].image_url) : 'placeholder.png';
  const img3 = images.length > 2 ? getImageUrl(images[2].image_url) : 'placeholder.png';

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; background:#fff !important; position:relative !important; display:flex !important; flex-direction:column !important;">
    <div style="width:100% !important; height:90% !important; padding:4mm !important; box-sizing:border-box !important; display:flex !important; gap:3mm !important; overflow:hidden !important;">
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img1}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img2}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; position:relative !important; background-image:url('${img3}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;">
        <div style="position:absolute !important; top:5mm !important; right:5mm !important;">
          <img src="${OiaLogo}" style="width:15mm !important; display:block !important;" />
        </div>
      </div>
    </div>
    ${createFooter()}
  </div>
  `;
};


 

const createSlide7 = () => {
  const images = property.value?.gallery_images || [];
  const img1 = images.length > 3 ? getImageUrl(images[3].image_url) : 'placeholder.png';
  const img2 = images.length > 4 ? getImageUrl(images[4].image_url) : 'placeholder.png';
  const img3 = images.length > 5 ? getImageUrl(images[5].image_url) : 'placeholder.png';

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; background:#fff !important; position:relative !important; display:flex !important; flex-direction:column !important;">
    <div style="width:100% !important; height:90% !important; padding:4mm !important; box-sizing:border-box !important; display:flex !important; gap:3mm !important; overflow:hidden !important;">
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img1}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; background-image:url('${img2}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;"></div>
      <div style="flex:1 !important; height:100% !important; overflow:hidden !important; position:relative !important; background-image:url('${img3}') !important; background-size:cover !important; background-position:center !important; background-repeat:no-repeat !important;">
        <div style="position:absolute !important; top:5mm !important; right:5mm !important;">
          <img src="${OiaLogo}" style="width:15mm !important; display:block !important;" />
        </div>
      </div>
    </div>
    ${createFooter()}
  </div>
  `;
};

 

const createSlide9 = (currentUser) => {
  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; position:relative !important; overflow:hidden !important;">
    <img src="${LastSlide_bg}" crossorigin="anonymous" style="position:absolute !important; top:0 !important; left:0 !important; width:130% !important; height:100% !important; object-fit:cover !important; display:block !important;" />
    <div style="position:absolute !important; top:0 !important; left:0 !important; width:100% !important; height:100% !important; background:rgba(1,6,44,0.65) !important;"></div>
     <div style="position:relative !important; z-index:5 !important; width:100% !important; height:100% !important; display:flex !important; flex-direction:column !important; align-items:center !important; justify-content:flex-start !important; padding-top:15mm !important;">      
      <h1 style="color:#fff !important; font-size:9mm !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important; text-align:center !important; letter-spacing:0.5mm !important; font-family:'Montserrat', sans-serif !important;margin-top:20mm  !important;">Thank You!</h1>
      <div style="position:absolute !important; bottom:12mm !important; right:14mm !important; text-align:left !important;">
        <p style="color:rgba(255,255,255,0.7) !important; font-size:3.5mm !important; margin:0 0 1.5mm 0 !important;">Contact</p>
        <p style="color:#fff !important; font-size:5mm !important; font-weight:700 !important; margin:0 !important; text-transform:uppercase !important; font-family:'Montserrat', sans-serif !important;">${currentUser?.name || ''}</p>
        <p style="color:#fff !important; font-size:4.5mm !important; font-weight:500 !important; margin:1mm 0 0 0 !important; font-family:'Montserrat', sans-serif !important;">${currentUser?.phone || ''}</p>
      </div>
    </div>
    ${createFooter2()}
  </div>
  `;
};

 


// Helper function to format text
const limitText = (text = '', max = 900) => {
  if (!text) return '';
  return text.length > max
    ? text.slice(0, max) + '...'
    : text;
};

const formatTextForPDF = (text) => {
  if (!text) return '';
  return text.replace(/\n/g, '<br>');
};

const createPaymentDetailsSlide = () => {
  const p = property.value || {};

  const parseList = (raw) => {
    if (Array.isArray(raw)) return raw;
    if (typeof raw === 'string' && raw.trim() !== '') {
      try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
      } catch {
        return [];
      }
    }
    return [];
  };

  const toNum = (v) => {
    if (v === null || v === undefined || v === '') return 0;
    const n = Number(String(v).replace(/[^0-9.-]/g, ''));
    return Number.isFinite(n) ? n : 0;
  };

  const installments = parseList(p.payment_breakdown);
  const expenses = parseList(p.assignment_expense_lines);
  const sellingPrice = toNum(p.price ?? p.selling_price);
  const originalPrice = toNum(p.original_price) || sellingPrice;
  const premium = sellingPrice - originalPrice;
  const nocPct = Math.max(0, Math.min(100, toNum(p.noc_percentage)));

  if (
    installments.length === 0 &&
    expenses.length === 0 &&
    originalPrice <= 0 &&
    !p.payment_plan &&
    !p.handover_date
  ) {
    return '';
  }

  let planLabel = '';
  if (p.payment_plan) {
    let raw = p.payment_plan;
    if (typeof raw === 'string') {
      try { raw = JSON.parse(raw); } catch { /* keep string */ }
    }
    if (Array.isArray(raw)) planLabel = String(raw[0] || '');
    else if (raw && typeof raw === 'object') planLabel = String(raw.label || '');
    else planLabel = String(raw || '');
  }
  const planMatch = String(planLabel).match(/(\d+)\s*\/\s*(\d+)/);
  const initialPct = planMatch ? Math.max(0, Math.min(100, Number(planMatch[1]))) : 0;
  const handoverPct = Math.max(0, 100 - initialPct);
  const handoverAmount = originalPrice - (originalPrice * initialPct) / 100;

  const startOfDay = (v) => {
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return null;
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  };
  const today = startOfDay(new Date());
  const isPaid = (d) => {
    const t = startOfDay(d);
    return t !== null && today !== null && t <= today;
  };

  const installmentAmount = (entry) => {
    if (!entry) return 0;
    if (entry.type === 'percentage') {
      return (originalPrice * toNum(entry.value)) / 100;
    }
    return toNum(entry.value);
  };

  const scheduledAed = installments.reduce((s, e) => s + installmentAmount(e), 0);
  const paidAed = installments.reduce((s, e) => (isPaid(e?.date) ? s + installmentAmount(e) : s), 0);
  const nocRequired = (originalPrice * nocPct) / 100;
  const nocRemaining = Math.max(0, nocRequired - scheduledAed);
  const nocMet = nocPct <= 0 || scheduledAed >= nocRequired - 0.01;

  const fmtAed = (v) =>
    new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', maximumFractionDigits: 0 }).format(toNum(v));

  const fmtDate = (d) => {
    if (!d) return '—';
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return '—';
    return dt.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const badgeStyle = (status) => {
    if (status === 'Paid') return 'background:#22c55e;color:#fff;';
    if (status === 'Due on transfer') return 'background:#bae6fd;color:#075985;';
    if (status === 'Selling below original price') return 'background:#fecaca;color:#b91c1c;';
    return 'background:#fecdd3;color:#9f1239;';
  };

  const sorted = installments.slice().sort((a, b) => new Date(a?.date || 0) - new Date(b?.date || 0));
  let cumulative = 0;
  const installmentRows = sorted.map((entry) => {
    const amount = installmentAmount(entry);
    cumulative += amount;
    let status = 'Upcoming';
    if (isPaid(entry?.date)) status = 'Paid';
    else if (nocPct > 0 && cumulative <= nocRequired + 0.01) status = 'Due on transfer';
    const pct = originalPrice > 0 ? ((amount / originalPrice) * 100).toFixed(2) : '—';
    return `
      <tr>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">Installment</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${pct}%</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${fmtAed(amount)}</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${fmtDate(entry?.date)}</td>
        <td style="padding:2mm 1.5mm;font-size:2.4mm;"><span style="display:inline-block;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;${badgeStyle(status)}">${status}</span></td>
      </tr>`;
  }).join('');

  const premiumStatus = premium < -0.01 ? 'Selling below original price' : 'Upcoming';
  const premiumRow = (installments.length > 0 || originalPrice > 0 || sellingPrice > 0)
    ? `<tr style="background:#f8fafc;">
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">Premium</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">—</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;${premium < 0 ? 'color:#b91c1c;' : ''}">${fmtAed(premium)}</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">—</td>
        <td style="padding:2mm 1.5mm;font-size:2.4mm;"><span style="display:inline-block;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;${badgeStyle(premiumStatus)}">${premiumStatus}</span></td>
      </tr>`
    : '';

  const handoverRow = Math.abs(handoverAmount) > 0.01
    ? `<tr>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">Handover (${handoverPct.toFixed(0)}%)</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${handoverPct.toFixed(2)}%</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${fmtAed(handoverAmount)}</td>
        <td style="padding:2mm 1.5mm;font-size:2.6mm;">${fmtDate(p.handover_date)}</td>
        <td style="padding:2mm 1.5mm;font-size:2.4mm;"><span style="display:inline-block;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;${badgeStyle(isPaid(p.handover_date) ? 'Paid' : 'Upcoming')}">${isPaid(p.handover_date) ? 'Paid' : 'Upcoming'}</span></td>
      </tr>`
    : '';

  const totalAmount = (installments.reduce((s, e) => s + installmentAmount(e), 0))
    + (installments.length > 0 || originalPrice > 0 || sellingPrice > 0 ? premium : 0)
    + (Math.abs(handoverAmount) > 0.01 ? handoverAmount : 0);

  const baseLabels = { op: 'OP', sp: 'SP', premium: 'premium' };
  const baseAmount = (b) => b === 'op' ? originalPrice : b === 'sp' ? sellingPrice : b === 'premium' ? premium : 0;
  const expLineAmount = (l) => {
    const calc = l?.calcType === 'fixed' ? 'fixed' : 'percentage';
    if (calc === 'percentage') return (baseAmount(l?.base) * toNum(l?.value)) / 100;
    return toNum(l?.value);
  };

  let expSubtotal = 0;
  let expVatTotal = 0;
  let expGrand = 0;
  const expenseRows = expenses.map((l) => {
    const amt = expLineAmount(l);
    const vat = l?.vatEnabled ? amt * 0.05 : 0;
    const total = amt + vat;
    expSubtotal += amt;
    expVatTotal += vat;
    expGrand += total;
    const calc = l?.calcType === 'fixed' ? 'fixed' : 'percentage';
    const detail = calc === 'percentage'
      ? `${toNum(l?.value)}% of ${baseLabels[l?.base] || 'OP'}`
      : fmtAed(toNum(l?.value));
    return `
      <tr>
        <td style="padding:1.8mm 1.5mm;font-size:2.6mm;">${l?.label || '—'}</td>
        <td style="padding:1.8mm 1.5mm;font-size:2.6mm;color:#64748b;">${detail}</td>
        <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;">${fmtAed(amt)}</td>
        <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;">${l?.vatEnabled ? fmtAed(vat) : '—'}</td>
        <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;font-weight:600;">${fmtAed(total)}</td>
      </tr>`;
  }).join('');

  const expensesBlock = expenses.length > 0 ? `
    <div style="margin-top:3mm;">
      <div style="font-size:2.6mm;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#64748b;margin-bottom:1.5mm;">Assignment deal costs</div>
      <div style="background:#ffffff;border-radius:3mm;padding:1.5mm 1.5mm 1mm;box-shadow:inset 0 0 0 0.2mm rgba(15,31,58,0.08);">
        <table style="width:100%;border-collapse:separate;border-spacing:0;font-size:2.6mm;">
          <thead>
            <tr>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:inline-block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Label</span></th>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:inline-block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Detail</span></th>
              <th style="padding:1mm 1.5mm;text-align:right;font-size:2.4mm;"><span style="display:inline-block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Amount</span></th>
              <th style="padding:1mm 1.5mm;text-align:right;font-size:2.4mm;"><span style="display:inline-block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">VAT</span></th>
              <th style="padding:1mm 1.5mm;text-align:right;font-size:2.4mm;"><span style="display:inline-block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Total</span></th>
            </tr>
          </thead>
          <tbody>
            ${expenseRows}
            <tr style="background:#f1f5f9;">
              <td colspan="2" style="padding:1.8mm 1.5mm;font-size:2.6mm;font-weight:700;">Total</td>
              <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;font-weight:700;">${fmtAed(expSubtotal)}</td>
              <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;font-weight:700;">${fmtAed(expVatTotal)}</td>
              <td style="padding:1.8mm 1.5mm;font-size:2.6mm;text-align:right;font-weight:700;">${fmtAed(expGrand)}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  ` : '';

  const nocStrip = nocPct > 0 ? `
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:1.5mm 5mm;background:#ffffff;border-radius:3mm;padding:1.6mm 2mm;margin-bottom:2mm;font-size:2.6mm;box-shadow:inset 0 0 0 0.2mm #ffffff;">
      <span>NOC <strong>${nocPct}%</strong> · Required <strong>${fmtAed(nocRequired)}</strong></span>
      <span>Paid <strong>${fmtAed(paidAed)}</strong></span>
      <span>Remaining <strong>${fmtAed(nocRemaining)}</strong></span>
      <span style="display:inline-block;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;font-size:2.4mm;${nocMet ? 'background:#22c55e;color:#fff;' : 'background:#fecdd3;color:#9f1239;'}">${nocMet ? 'NOC met' : 'Below NOC'}</span>
    </div>
  ` : '';

  const installmentTable = (installmentRows || premiumRow || handoverRow) ? `
    <div style="margin-bottom:2.5mm;">
      <div style="font-size:2.6mm;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#64748b;margin-bottom:1.5mm;">Installment breakdown</div>
      <div style="background:#ffffff !important;border-radius:3mm;padding:1.5mm 1.5mm 1mm;box-shadow:inset 0 0 0 0.2mm #ffffff;">
        <table style="width:100%;border-collapse:separate;border-spacing:0;font-size:2.6mm;">
          <thead>
            <tr>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Payment type</span></th>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Percentage</span></th>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Amount</span></th>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Date</span></th>
              <th style="padding:1mm 1.5mm;text-align:left;font-size:2.4mm;"><span style="display:block;background:#0f1f3a;color:#fff;padding:0.6mm 2mm;border-radius:6mm;font-weight:700;">Status</span></th>
            </tr>
          </thead>
          <tbody>
            ${installmentRows}
            ${premiumRow}
            ${handoverRow}
            <tr style="background:#f1f5f9;">
              <td style="padding:2mm 1.5mm;font-size:2.6mm;font-weight:700;" colspan="2">Total</td>
              <td style="padding:2mm 1.5mm;font-size:2.6mm;font-weight:700;">${fmtAed(totalAmount)}</td>
              <td colspan="2"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  ` : '';

  return `
  <div style="width:210mm !important; height:148mm !important; border-radius:12px !important; padding:0 !important; margin:0 !important; box-sizing:border-box !important; position:relative !important; overflow:hidden !important; background:#f4f6f9 !important;">
    <div style="position:absolute !important; top:7mm !important; right:8mm !important; z-index:10 !important;">
      <img src="${OiaLogo}" style="width:18mm !important; display:block !important;" />
    </div>
    <div style="position:relative !important; z-index:5 !important; padding:10mm 12mm 16mm 12mm !important; box-sizing:border-box !important; height:100% !important; color:#1e293b !important; font-family:Arial, sans-serif !important;">
      <div style="margin-bottom:4mm;">
        <div style="font-size:5mm;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;color:#0f1f3a;line-height:1.2;font-family:'Montserrat', Arial, sans-serif;">Payment details</div>
        <div style="width:14mm;height:1mm;background:#e85d1c;border-radius:1mm;margin-top:1.5mm;"></div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:2mm;margin-bottom:2.5mm;">
        <div style="background:linear-gradient(160deg,#132043 0%,#0f1f3a 100%);color:#fff;border-bottom:1mm solid #e85d1c;border-radius:3mm;padding:2.5mm 2.5mm 2.8mm;">
          <div style="font-size:2.4mm;opacity:0.88;margin-bottom:1mm;">Selling price</div>
          <div style="font-size:3.5mm;font-weight:700;">${fmtAed(sellingPrice)}</div>
        </div>
        <div style="background:#e8ecf2;color:#0f1f3a;border-radius:3mm;padding:2.5mm 2.5mm 2.8mm;">
          <div style="font-size:2.4mm;margin-bottom:1mm;">Original price (OP)</div>
          <div style="font-size:3.5mm;font-weight:700;">${fmtAed(originalPrice)}</div>
        </div>
        <div style="background:#e8ecf2;color:#0f1f3a;border-radius:3mm;padding:2.5mm 2.5mm 2.8mm;">
          <div style="font-size:2.4mm;margin-bottom:1mm;">Payment plan</div>
          <div style="font-size:3.5mm;font-weight:700;">${planLabel || '—'}</div>
        </div>
        <div style="background:#e8ecf2;color:#0f1f3a;border-radius:3mm;padding:2.5mm 2.5mm 2.8mm;">
          <div style="font-size:2.4mm;margin-bottom:1mm;">Premium</div>
          <div style="font-size:3.5mm;font-weight:700;${premium < 0 ? 'color:#b91c1c;' : ''}">${fmtAed(premium)}</div>
        </div>
      </div>

      ${nocStrip}
      ${installmentTable}
      ${expensesBlock}
    </div>
    ${createFooter()}
  </div>
  `;
};

const createFooter = () => {
  return `
  <div style="position:absolute !important; bottom:0 !important; left:0 !important; width:100% !important; height:10% !important; background:#0B0736 !important; display:flex !important; align-items:center !important; padding:0 5mm !important; box-sizing:border-box !important; z-index:100 !important;">
    <p style="color:#fff !important; font-size:2.8mm !important; font-family:Arial, sans-serif !important; font-weight:400 !important; margin:0 !important; opacity:0.9 !important;">Powered By Oia Properties</p>
  </div>
  `;
};

const createFooter2 = () => {
  return `
  <div style="position:absolute !important; bottom:0 !important; left:0 !important; width:100% !important; height:10% !important; background:transparent !important; display:flex !important; align-items:center !important; padding:0 5mm !important; box-sizing:border-box !important; z-index:100 !important;">
    <p style="color:#fff !important; font-size:2.8mm !important; font-family:Arial, sans-serif !important; font-weight:400 !important; margin:0 !important; opacity:0.9 !important;">Powered By Oia Properties</p>
  </div>
  `;
};

const getMainImage = () => {
  if (property.value?.main_image) {
    return getImageUrl(property.value.main_image);
  } else if (property.value?.gallery_images && property.value.gallery_images.length > 0) {
    return getImageUrl(property.value.gallery_images[0].image_url);
  }
  return 'placeholder.png';
};

const getLocationIcon = () => {
  const svgString = `<svg width="328" height="44" viewBox="0 0 328 44" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M34.68 17V5.80001H39.048C40.0293 5.80001 40.8667 5.95468 41.56 6.26401C42.2533 6.57335 42.7867 7.02135 43.16 7.60801C43.5333 8.19468 43.72 8.89335 43.72 9.70401C43.72 10.5147 43.5333 11.2133 43.16 11.8C42.7867 12.376 42.2533 12.824 41.56 13.144C40.8667 13.4533 40.0293 13.608 39.048 13.608H35.56L36.28 12.856V17H34.68ZM36.28 13.016L35.56 12.216H39C40.024 12.216 40.7973 11.9973 41.32 11.56C41.8533 11.1227 42.12 10.504 42.12 9.70401C42.12 8.90401 41.8533 8.28535 41.32 7.84801C40.7973 7.41068 40.024 7.19201 39 7.19201H35.56L36.28 6.39201V13.016ZM51.0325 17V15.208L50.9525 14.872V11.816C50.9525 11.1653 50.7605 10.664 50.3765 10.312C50.0032 9.94935 49.4378 9.76801 48.6805 9.76801C48.1792 9.76801 47.6885 9.85335 47.2085 10.024C46.7285 10.184 46.3232 10.4027 45.9925 10.68L45.3525 9.52801C45.7898 9.17601 46.3125 8.90935 46.9205 8.72801C47.5392 8.53601 48.1845 8.44001 48.8565 8.44001C50.0192 8.44001 50.9152 8.72268 51.5445 9.28801C52.1738 9.85335 52.4885 10.7173 52.4885 11.88V17H51.0325ZM48.2485 17.096C47.6192 17.096 47.0645 16.9893 46.5845 16.776C46.1152 16.5627 45.7525 16.2693 45.4965 15.896C45.2405 15.512 45.1125 15.08 45.1125 14.6C45.1125 14.1413 45.2192 13.7253 45.4325 13.352C45.6565 12.9787 46.0138 12.68 46.5045 12.456C47.0058 12.232 47.6778 12.12 48.5205 12.12H51.2085V13.224H48.5845C47.8165 13.224 47.2992 13.352 47.0325 13.608C46.7658 13.864 46.6325 14.1733 46.6325 14.536C46.6325 14.952 46.7978 15.288 47.1285 15.544C47.4592 15.7893 47.9178 15.912 48.5045 15.912C49.0805 15.912 49.5818 15.784 50.0085 15.528C50.4458 15.272 50.7605 14.8987 50.9525 14.408L51.2565 15.464C51.0538 15.9653 50.6965 16.3653 50.1845 16.664C49.6725 16.952 49.0272 17.096 48.2485 17.096ZM55.331 17V8.52001H56.803V10.824L56.659 10.248C56.8937 9.66135 57.2883 9.21335 57.843 8.90401C58.3977 8.59468 59.0803 8.44001 59.891 8.44001V9.92801C59.827 9.91735 59.763 9.91201 59.699 9.91201C59.6457 9.91201 59.5923 9.91201 59.539 9.91201C58.7177 9.91201 58.067 10.1573 57.587 10.648C57.107 11.1387 56.867 11.848 56.867 12.776V17H55.331ZM63.0321 14.952L63.0641 12.984L67.9441 8.52001H69.8001L66.0561 12.2L65.2241 12.904L63.0321 14.952ZM61.7841 17V5.12801H63.3201V17H61.7841ZM68.2641 17L64.9361 12.872L65.9281 11.64L70.1521 17H68.2641ZM79.3964 17L74.4684 5.80001H76.1964L80.7244 16.136H79.7324L84.2924 5.80001H85.8924L80.9804 17H79.3964ZM91.72 17V15.208L91.64 14.872V11.816C91.64 11.1653 91.448 10.664 91.064 10.312C90.6907 9.94935 90.1253 9.76801 89.368 9.76801C88.8667 9.76801 88.376 9.85335 87.896 10.024C87.416 10.184 87.0107 10.4027 86.68 10.68L86.04 9.52801C86.4773 9.17601 87 8.90935 87.608 8.72801C88.2267 8.53601 88.872 8.44001 89.544 8.44001C90.7067 8.44001 91.6027 8.72268 92.232 9.28801C92.8613 9.85335 93.176 10.7173 93.176 11.88V17H91.72ZM88.936 17.096C88.3067 17.096 87.752 16.9893 87.272 16.776C86.8027 16.5627 86.44 16.2693 86.184 15.896C85.928 15.512 85.8 15.08 85.8 14.6C85.8 14.1413 85.9067 13.7253 86.12 13.352C86.344 12.9787 86.7013 12.68 87.192 12.456C87.6933 12.232 88.3653 12.12 89.208 12.12H91.896V13.224H89.272C88.504 13.224 87.9867 13.352 87.72 13.608C87.4533 13.864 87.32 14.1733 87.32 14.536C87.32 14.952 87.4853 15.288 87.816 15.544C88.1467 15.7893 88.6053 15.912 89.192 15.912C89.768 15.912 90.2693 15.784 90.696 15.528C91.1333 15.272 91.448 14.8987 91.64 14.408L91.944 15.464C91.7413 15.9653 91.384 16.3653 90.872 16.664C90.36 16.952 89.7147 17.096 88.936 17.096ZM96.0185 17V5.12801H97.5545V17H96.0185ZM100.487 17V5.12801H102.023V17H100.487ZM108.732 17.096C107.825 17.096 107.025 16.9093 106.332 16.536C105.649 16.1627 105.116 15.6507 104.732 15C104.359 14.3493 104.172 13.6027 104.172 12.76C104.172 11.9173 104.353 11.1707 104.716 10.52C105.089 9.86935 105.596 9.36268 106.236 9.00001C106.887 8.62668 107.617 8.44001 108.428 8.44001C109.249 8.44001 109.975 8.62135 110.604 8.98401C111.233 9.34668 111.724 9.85868 112.076 10.52C112.439 11.1707 112.62 11.9333 112.62 12.808C112.62 12.872 112.615 12.9467 112.604 13.032C112.604 13.1173 112.599 13.1973 112.588 13.272H105.372V12.168H111.788L111.164 12.552C111.175 12.008 111.063 11.5227 110.828 11.096C110.593 10.6693 110.268 10.3387 109.852 10.104C109.447 9.85868 108.972 9.73601 108.428 9.73601C107.895 9.73601 107.42 9.85868 107.004 10.104C106.588 10.3387 106.263 10.6747 106.028 11.112C105.793 11.5387 105.676 12.0293 105.676 12.584V12.84C105.676 13.4053 105.804 13.912 106.06 14.36C106.327 14.7973 106.695 15.1387 107.164 15.384C107.633 15.6293 108.172 15.752 108.78 15.752C109.281 15.752 109.735 15.6667 110.14 15.496C110.556 15.3253 110.919 15.0693 111.228 14.728L112.076 15.72C111.692 16.168 111.212 16.5093 110.636 16.744C110.071 16.9787 109.436 17.096 108.732 17.096ZM114.965 20.2C114.559 20.2 114.165 20.1307 113.781 19.992C113.397 19.864 113.066 19.672 112.789 19.416L113.445 18.264C113.658 18.4667 113.893 18.6213 114.149 18.728C114.405 18.8347 114.677 18.888 114.965 18.888C115.338 18.888 115.647 18.792 115.893 18.6C116.138 18.408 116.367 18.0667 116.581 17.576L117.109 16.408L117.269 16.216L120.597 8.52001H122.101L117.989 17.848C117.743 18.4453 117.466 18.9147 117.157 19.256C116.858 19.5973 116.527 19.8373 116.165 19.976C115.802 20.1253 115.402 20.2 114.965 20.2ZM116.981 17.272L113.109 8.52001H114.709L118.005 16.072L116.981 17.272ZM122.395 19.368L123.195 16.136L123.403 17.064C123.094 17.064 122.832 16.968 122.619 16.776C122.416 16.584 122.315 16.3227 122.315 15.992C122.315 15.672 122.416 15.4107 122.619 15.208C122.832 15.0053 123.088 14.904 123.387 14.904C123.696 14.904 123.947 15.0107 124.139 15.224C124.331 15.4267 124.427 15.6827 124.427 15.992C124.427 16.0987 124.416 16.2053 124.395 16.312C124.384 16.408 124.358 16.5253 124.315 16.664C124.283 16.792 124.23 16.952 124.155 17.144L123.403 19.368H122.395ZM131.149 17V5.80001H135.517C136.498 5.80001 137.335 5.95468 138.029 6.26401C138.722 6.57335 139.255 7.02135 139.629 7.60801C140.002 8.19468 140.189 8.89335 140.189 9.70401C140.189 10.5147 140.002 11.2133 139.629 11.8C139.255 12.376 138.722 12.8187 138.029 13.128C137.335 13.4373 136.498 13.592 135.517 13.592H132.029L132.749 12.856V17H131.149ZM138.637 17L135.789 12.936H137.501L140.381 17H138.637ZM132.749 13.016L132.029 12.232H135.469C136.493 12.232 137.266 12.0133 137.789 11.576C138.322 11.128 138.589 10.504 138.589 9.70401C138.589 8.90401 138.322 8.28535 137.789 7.84801C137.266 7.41068 136.493 7.19201 135.469 7.19201H132.029L132.749 6.39201V13.016ZM146.326 17.096C145.419 17.096 144.619 16.9093 143.926 16.536C143.243 16.1627 142.71 15.6507 142.326 15C141.952 14.3493 141.766 13.6027 141.766 12.76C141.766 11.9173 141.947 11.1707 142.31 10.52C142.683 9.86935 143.19 9.36268 143.83 9.00001C144.48 8.62668 145.211 8.44001 146.022 8.44001C146.843 8.44001 147.568 8.62135 148.198 8.98401C148.827 9.34668 149.318 9.85868 149.67 10.52C150.032 11.1707 150.214 11.9333 150.214 12.808C150.214 12.872 150.208 12.9467 150.198 13.032C150.198 13.1173 150.192 13.1973 150.182 13.272H142.966V12.168H149.382L148.758 12.552C148.768 12.008 148.656 11.5227 148.422 11.096C148.187 10.6693 147.862 10.3387 147.446 10.104C147.04 9.85868 146.566 9.73601 146.022 9.73601C145.488 9.73601 145.014 9.85868 144.598 10.104C144.182 10.3387 143.856 10.6747 143.622 11.112C143.387 11.5387 143.27 12.0293 143.27 12.584V12.84C143.27 13.4053 143.398 13.912 143.654 14.36C143.92 14.7973 144.288 15.1387 144.758 15.384C145.227 15.6293 145.766 15.752 146.374 15.752C146.875 15.752 147.328 15.6667 147.734 15.496C148.15 15.3253 148.512 15.0693 148.822 14.728L149.67 15.72C149.286 16.168 148.806 16.5093 148.23 16.744C147.664 16.9787 147.03 17.096 146.326 17.096ZM156.123 17.096C155.216 17.096 154.416 16.9093 153.723 16.536C153.04 16.1627 152.507 15.6507 152.123 15C151.749 14.3493 151.563 13.6027 151.563 12.76C151.563 11.9173 151.744 11.1707 152.107 10.52C152.48 9.86935 152.987 9.36268 153.627 9.00001C154.277 8.62668 155.008 8.44001 155.819 8.44001C156.64 8.44001 157.365 8.62135 157.995 8.98401C158.624 9.34668 159.115 9.85868 159.467 10.52C159.829 11.1707 160.011 11.9333 160.011 12.808C160.011 12.872 160.005 12.9467 159.995 13.032C159.995 13.1173 159.989 13.1973 159.979 13.272H152.763V12.168H159.179L158.555 12.552C158.565 12.008 158.453 11.5227 158.219 11.096C157.984 10.6693 157.659 10.3387 157.243 10.104C156.837 9.85868 156.363 9.73601 155.819 9.73601C155.285 9.73601 154.811 9.85868 154.395 10.104C153.979 10.3387 153.653 10.6747 153.419 11.112C153.184 11.5387 153.067 12.0293 153.067 12.584V12.84C153.067 13.4053 153.195 13.912 153.451 14.36C153.717 14.7973 154.085 15.1387 154.555 15.384C155.024 15.6293 155.563 15.752 156.171 15.752C156.672 15.752 157.125 15.6667 157.531 15.496C157.947 15.3253 158.309 15.0693 158.619 14.728L159.467 15.72C159.083 16.168 158.603 16.5093 158.027 16.744C157.461 16.9787 156.827 17.096 156.123 17.096ZM172.752 8.44001C173.434 8.44001 174.037 8.57335 174.56 8.84001C175.082 9.10668 175.488 9.51201 175.776 10.056C176.074 10.6 176.224 11.288 176.224 12.12V17H174.688V12.296C174.688 11.4747 174.496 10.856 174.112 10.44C173.728 10.024 173.189 9.81601 172.496 9.81601C171.984 9.81601 171.536 9.92268 171.152 10.136C170.768 10.3493 170.469 10.664 170.256 11.08C170.053 11.496 169.952 12.0133 169.952 12.632V17H168.416V12.296C168.416 11.4747 168.224 10.856 167.84 10.44C167.466 10.024 166.928 9.81601 166.224 9.81601C165.722 9.81601 165.28 9.92268 164.896 10.136C164.512 10.3493 164.213 10.664 164 11.08C163.786 11.496 163.68 12.0133 163.68 12.632V17H162.144V8.52001H163.616V10.776L163.376 10.2C163.642 9.64535 164.053 9.21335 164.608 8.90401C165.162 8.59468 165.808 8.44001 166.544 8.44001C167.354 8.44001 168.053 8.64268 168.64 9.04801C169.226 9.44268 169.61 10.0453 169.792 10.856L169.168 10.6C169.424 9.94935 169.872 9.42668 170.512 9.03201C171.152 8.63735 171.898 8.44001 172.752 8.44001ZM191.603 5.80001H193.203V17H191.603V5.80001ZM185.171 17H183.571V5.80001H185.171V17ZM191.747 12.008H185.011V10.616H191.747V12.008ZM196.331 17V8.52001H197.867V17H196.331ZM197.099 6.88801C196.8 6.88801 196.55 6.79201 196.347 6.60001C196.155 6.40801 196.059 6.17335 196.059 5.89601C196.059 5.60801 196.155 5.36801 196.347 5.17601C196.55 4.98401 196.8 4.88801 197.099 4.88801C197.398 4.88801 197.643 4.98401 197.835 5.17601C198.038 5.35735 198.139 5.58668 198.139 5.86401C198.139 6.15201 198.043 6.39735 197.851 6.60001C197.659 6.79201 197.408 6.88801 197.099 6.88801ZM200.8 17V5.12801H202.336V17H200.8ZM205.269 17V5.12801H206.805V17H205.269ZM212.169 17.096C211.465 17.096 210.793 17 210.153 16.808C209.524 16.616 209.028 16.3813 208.665 16.104L209.305 14.888C209.668 15.1333 210.116 15.3413 210.649 15.512C211.183 15.6827 211.727 15.768 212.281 15.768C212.996 15.768 213.508 15.6667 213.817 15.464C214.137 15.2613 214.297 14.9787 214.297 14.616C214.297 14.3493 214.201 14.1413 214.009 13.992C213.817 13.8427 213.561 13.7307 213.241 13.656C212.932 13.5813 212.585 13.5173 212.201 13.464C211.817 13.4 211.433 13.3253 211.049 13.24C210.665 13.144 210.313 13.016 209.993 12.856C209.673 12.6853 209.417 12.456 209.225 12.168C209.033 11.8693 208.937 11.4747 208.937 10.984C208.937 10.472 209.081 10.024 209.369 9.64001C209.657 9.25601 210.063 8.96268 210.585 8.76001C211.119 8.54668 211.748 8.44001 212.473 8.44001C213.028 8.44001 213.588 8.50935 214.153 8.64801C214.729 8.77601 215.199 8.96268 215.561 9.20801L214.905 10.424C214.521 10.168 214.121 9.99201 213.705 9.89601C213.289 9.80001 212.873 9.75201 212.457 9.75201C211.785 9.75201 211.284 9.86401 210.953 10.088C210.623 10.3013 210.457 10.5787 210.457 10.92C210.457 11.208 210.553 11.432 210.745 11.592C210.948 11.7413 211.204 11.8587 211.513 11.944C211.833 12.0293 212.185 12.104 212.569 12.168C212.953 12.2213 213.337 12.296 213.721 12.392C214.105 12.4773 214.452 12.6 214.761 12.76C215.081 12.92 215.337 13.144 215.529 13.432C215.732 13.72 215.833 14.104 215.833 14.584C215.833 15.096 215.684 15.5387 215.385 15.912C215.087 16.2853 214.665 16.5787 214.121 16.792C213.577 16.9947 212.927 17.096 212.169 17.096ZM217.145 19.368L217.945 16.136L218.153 17.064C217.844 17.064 217.582 16.968 217.369 16.776C217.166 16.584 217.065 16.3227 217.065 15.992C217.065 15.672 217.166 15.4107 217.369 15.208C217.582 15.0053 217.838 14.904 218.137 14.904C218.446 14.904 218.697 15.0107 218.889 15.224C219.081 15.4267 219.177 15.6827 219.177 15.992C219.177 16.0987 219.166 16.2053 219.145 16.312C219.134 16.408 219.108 16.5253 219.065 16.664C219.033 16.792 218.98 16.952 218.905 17.144L218.153 19.368H217.145ZM224.203 17L229.275 5.80001H230.859L235.947 17H234.267L229.739 6.69601H230.379L225.851 17H224.203ZM226.363 14.2L226.795 12.92H233.099L233.563 14.2H226.363ZM237.394 17V5.12801H238.93V17H237.394ZM246.383 17V5.80001H250.751C251.732 5.80001 252.57 5.95468 253.263 6.26401C253.956 6.57335 254.49 7.02135 254.863 7.60801C255.236 8.19468 255.423 8.89335 255.423 9.70401C255.423 10.5147 255.236 11.2133 254.863 11.8C254.49 12.376 253.956 12.8187 253.263 13.128C252.57 13.4373 251.732 13.592 250.751 13.592H247.263L247.983 12.856V17H246.383ZM253.871 17L251.023 12.936H252.735L255.615 17H253.871ZM247.983 13.016L247.263 12.232H250.703C251.727 12.232 252.5 12.0133 253.023 11.576C253.556 11.128 253.823 10.504 253.823 9.70401C253.823 8.90401 253.556 8.28535 253.023 7.84801C252.5 7.41068 251.727 7.19201 250.703 7.19201H247.263L247.983 6.39201V13.016ZM261.56 17.096C260.653 17.096 259.853 16.9093 259.16 16.536C258.477 16.1627 257.944 15.6507 257.56 15C257.187 14.3493 257 13.6027 257 12.76C257 11.9173 257.181 11.1707 257.544 10.52C257.917 9.86935 258.424 9.36268 259.064 9.00001C259.715 8.62668 260.445 8.44001 261.256 8.44001C262.077 8.44001 262.803 8.62135 263.432 8.98401C264.061 9.34668 264.552 9.85868 264.904 10.52C265.267 11.1707 265.448 11.9333 265.448 12.808C265.448 12.872 265.443 12.9467 265.432 13.032C265.432 13.1173 265.427 13.1973 265.416 13.272H258.2V12.168H264.616L263.992 12.552C264.003 12.008 263.891 11.5227 263.656 11.096C263.421 10.6693 263.096 10.3387 262.68 10.104C262.275 9.85868 261.8 9.73601 261.256 9.73601C260.723 9.73601 260.248 9.85868 259.832 10.104C259.416 10.3387 259.091 10.6747 258.856 11.112C258.621 11.5387 258.504 12.0293 258.504 12.584V12.84C258.504 13.4053 258.632 13.912 258.888 14.36C259.155 14.7973 259.523 15.1387 259.992 15.384C260.461 15.6293 261 15.752 261.608 15.752C262.109 15.752 262.563 15.6667 262.968 15.496C263.384 15.3253 263.747 15.0693 264.056 14.728L264.904 15.72C264.52 16.168 264.04 16.5093 263.464 16.744C262.899 16.9787 262.264 17.096 261.56 17.096ZM271.357 17.096C270.45 17.096 269.65 16.9093 268.957 16.536C268.274 16.1627 267.741 15.6507 267.357 15C266.984 14.3493 266.797 13.6027 266.797 12.76C266.797 11.9173 266.978 11.1707 267.341 10.52C267.714 9.86935 268.221 9.36268 268.861 9.00001C269.512 8.62668 270.242 8.44001 271.053 8.44001C271.874 8.44001 272.6 8.62135 273.229 8.98401C273.858 9.34668 274.349 9.85868 274.701 10.52C275.064 11.1707 275.245 11.9333 275.245 12.808C275.245 12.872 275.24 12.9467 275.229 13.032C275.229 13.1173 275.224 13.1973 275.213 13.272H267.997V12.168H274.413L273.789 12.552C273.8 12.008 273.688 11.5227 273.453 11.096C273.218 10.6693 272.893 10.3387 272.477 10.104C272.072 9.85868 271.597 9.73601 271.053 9.73601C270.52 9.73601 270.045 9.85868 269.629 10.104C269.213 10.3387 268.888 10.6747 268.653 11.112C268.418 11.5387 268.301 12.0293 268.301 12.584V12.84C268.301 13.4053 268.429 13.912 268.685 14.36C268.952 14.7973 269.32 15.1387 269.789 15.384C270.258 15.6293 270.797 15.752 271.405 15.752C271.906 15.752 272.36 15.6667 272.765 15.496C273.181 15.3253 273.544 15.0693 273.853 14.728L274.701 15.72C274.317 16.168 273.837 16.5093 273.261 16.744C272.696 16.9787 272.061 17.096 271.357 17.096ZM287.986 8.44001C288.669 8.44001 289.271 8.57335 289.794 8.84001C290.317 9.10668 290.722 9.51201 291.01 10.056C291.309 10.6 291.458 11.288 291.458 12.12V17H289.922V12.296C289.922 11.4747 289.73 10.856 289.346 10.44C288.962 10.024 288.423 9.81601 287.73 9.81601C287.218 9.81601 286.77 9.92268 286.386 10.136C286.002 10.3493 285.703 10.664 285.49 11.08C285.287 11.496 285.186 12.0133 285.186 12.632V17H283.65V12.296C283.65 11.4747 283.458 10.856 283.074 10.44C282.701 10.024 282.162 9.81601 281.458 9.81601C280.957 9.81601 280.514 9.92268 280.13 10.136C279.746 10.3493 279.447 10.664 279.234 11.08C279.021 11.496 278.914 12.0133 278.914 12.632V17H277.378V8.52001H278.85V10.776L278.61 10.2C278.877 9.64535 279.287 9.21335 279.842 8.90401C280.397 8.59468 281.042 8.44001 281.778 8.44001C282.589 8.44001 283.287 8.64268 283.874 9.04801C284.461 9.44268 284.845 10.0453 285.026 10.856L284.402 10.6C284.658 9.94935 285.106 9.42668 285.746 9.03201C286.386 8.63735 287.133 8.44001 287.986 8.44001ZM34.68 39V27.8H36.28V39H34.68ZM41.8411 39.096C41.1371 39.096 40.4651 39 39.8251 38.808C39.1958 38.616 38.6998 38.3813 38.3371 38.104L38.9771 36.888C39.3398 37.1333 39.7878 37.3413 40.3211 37.512C40.8545 37.6827 41.3985 37.768 41.9531 37.768C42.6678 37.768 43.1798 37.6667 43.4891 37.464C43.8091 37.2613 43.9691 36.9787 43.9691 36.616C43.9691 36.3493 43.8731 36.1413 43.6811 35.992C43.4891 35.8427 43.2331 35.7307 42.9131 35.656C42.6038 35.5813 42.2571 35.5173 41.8731 35.464C41.4891 35.4 41.1051 35.3253 40.7211 35.24C40.3371 35.144 39.9851 35.016 39.6651 34.856C39.3451 34.6853 39.0891 34.456 38.8971 34.168C38.7051 33.8693 38.6091 33.4747 38.6091 32.984C38.6091 32.472 38.7531 32.024 39.0411 31.64C39.3291 31.256 39.7345 30.9627 40.2571 30.76C40.7905 30.5467 41.4198 30.44 42.1451 30.44C42.6998 30.44 43.2598 30.5093 43.8251 30.648C44.4011 30.776 44.8705 30.9627 45.2331 31.208L44.5771 32.424C44.1931 32.168 43.7931 31.992 43.3771 31.896C42.9611 31.8 42.5451 31.752 42.1291 31.752C41.4571 31.752 40.9558 31.864 40.6251 32.088C40.2945 32.3013 40.1291 32.5787 40.1291 32.92C40.1291 33.208 40.2251 33.432 40.4171 33.592C40.6198 33.7413 40.8758 33.8587 41.1851 33.944C41.5051 34.0293 41.8571 34.104 42.2411 34.168C42.6251 34.2213 43.0091 34.296 43.3931 34.392C43.7771 34.4773 44.1238 34.6 44.4331 34.76C44.7531 34.92 45.0091 35.144 45.2011 35.432C45.4038 35.72 45.5051 36.104 45.5051 36.584C45.5051 37.096 45.3558 37.5387 45.0571 37.912C44.7585 38.2853 44.3371 38.5787 43.7931 38.792C43.2491 38.9947 42.5985 39.096 41.8411 39.096ZM47.4248 39V27.128H48.9608V39H47.4248ZM57.1575 39V37.208L57.0775 36.872V33.816C57.0775 33.1653 56.8855 32.664 56.5015 32.312C56.1282 31.9493 55.5628 31.768 54.8055 31.768C54.3042 31.768 53.8135 31.8533 53.3335 32.024C52.8535 32.184 52.4482 32.4027 52.1175 32.68L51.4775 31.528C51.9148 31.176 52.4375 30.9093 53.0455 30.728C53.6642 30.536 54.3095 30.44 54.9815 30.44C56.1442 30.44 57.0402 30.7227 57.6695 31.288C58.2988 31.8533 58.6135 32.7173 58.6135 33.88V39H57.1575ZM54.3735 39.096C53.7442 39.096 53.1895 38.9893 52.7095 38.776C52.2402 38.5627 51.8775 38.2693 51.6215 37.896C51.3655 37.512 51.2375 37.08 51.2375 36.6C51.2375 36.1413 51.3442 35.7253 51.5575 35.352C51.7815 34.9787 52.1388 34.68 52.6295 34.456C53.1308 34.232 53.8028 34.12 54.6455 34.12H57.3335V35.224H54.7095C53.9415 35.224 53.4242 35.352 53.1575 35.608C52.8908 35.864 52.7575 36.1733 52.7575 36.536C52.7575 36.952 52.9228 37.288 53.2535 37.544C53.5842 37.7893 54.0428 37.912 54.6295 37.912C55.2055 37.912 55.7068 37.784 56.1335 37.528C56.5708 37.272 56.8855 36.8987 57.0775 36.408L57.3815 37.464C57.1788 37.9653 56.8215 38.3653 56.3095 38.664C55.7975 38.952 55.1522 39.096 54.3735 39.096ZM65.984 30.44C66.6773 30.44 67.2853 30.5733 67.808 30.84C68.3413 31.1067 68.7573 31.512 69.056 32.056C69.3547 32.6 69.504 33.288 69.504 34.12V39H67.968V34.296C67.968 33.4747 67.7653 32.856 67.36 32.44C66.9653 32.024 66.4053 31.816 65.68 31.816C65.136 31.816 64.6613 31.9227 64.256 32.136C63.8507 32.3493 63.536 32.664 63.312 33.08C63.0987 33.496 62.992 34.0133 62.992 34.632V39H61.456V30.52H62.928V32.808L62.688 32.2C62.9653 31.6453 63.392 31.2133 63.968 30.904C64.544 30.5947 65.216 30.44 65.984 30.44ZM75.8506 39.096C75.0293 39.096 74.2933 38.9147 73.6426 38.552C73.0026 38.1893 72.496 37.6827 72.1226 37.032C71.7493 36.3813 71.5626 35.624 71.5626 34.76C71.5626 33.896 71.7493 33.144 72.1226 32.504C72.496 31.8533 73.0026 31.3467 73.6426 30.984C74.2933 30.6213 75.0293 30.44 75.8506 30.44C76.5653 30.44 77.2106 30.6 77.7866 30.92C78.3626 31.24 78.8213 31.72 79.1626 32.36C79.5146 33 79.6906 33.8 79.6906 34.76C79.6906 35.72 79.52 36.52 79.1786 37.16C78.848 37.8 78.3946 38.2853 77.8186 38.616C77.2426 38.936 76.5866 39.096 75.8506 39.096ZM75.9786 37.752C76.512 37.752 76.992 37.6293 77.4186 37.384C77.856 37.1387 78.1973 36.792 78.4426 36.344C78.6986 35.8853 78.8266 35.3573 78.8266 34.76C78.8266 34.152 78.6986 33.6293 78.4426 33.192C78.1973 32.744 77.856 32.3973 77.4186 32.152C76.992 31.9067 76.512 31.784 75.9786 31.784C75.4346 31.784 74.9493 31.9067 74.5226 32.152C74.096 32.3973 73.7546 32.744 73.4986 33.192C73.2426 33.6293 73.1146 34.152 73.1146 34.76C73.1146 35.3573 73.2426 35.8853 73.4986 36.344C73.7546 36.792 74.096 37.1387 74.5226 37.384C74.9493 37.6293 75.4346 37.752 75.9786 37.752ZM78.8746 39V36.712L78.9706 34.744L78.8106 32.776V27.128H80.3466V39H78.8746ZM82.6449 41.368L83.4449 38.136L83.6529 39.064C83.3435 39.064 83.0822 38.968 82.8689 38.776C82.6662 38.584 82.5649 38.3227 82.5649 37.992C82.5649 37.672 82.6662 37.4107 82.8689 37.208C83.0822 37.0053 83.3382 36.904 83.6369 36.904C83.9462 36.904 84.1969 37.0107 84.3889 37.224C84.5809 37.4267 84.6769 37.6827 84.6769 37.992C84.6769 38.0987 84.6662 38.2053 84.6449 38.312C84.6342 38.408 84.6075 38.5253 84.5649 38.664C84.5329 38.792 84.4795 38.952 84.4049 39.144L83.6529 41.368H82.6449ZM93.9996 39L99.0716 27.8H100.656L105.744 39H104.064L99.5356 28.696H100.176L95.6476 39H93.9996ZM96.1596 36.2L96.5916 34.92H102.896L103.36 36.2H96.1596ZM111.686 39.096C110.961 39.096 110.305 38.936 109.718 38.616C109.142 38.2853 108.684 37.8 108.342 37.16C108.012 36.52 107.846 35.72 107.846 34.76C107.846 33.8 108.017 33 108.358 32.36C108.71 31.72 109.174 31.24 109.75 30.92C110.337 30.6 110.982 30.44 111.686 30.44C112.518 30.44 113.254 30.6213 113.894 30.984C114.534 31.3467 115.041 31.8533 115.414 32.504C115.788 33.144 115.974 33.896 115.974 34.76C115.974 35.624 115.788 36.3813 115.414 37.032C115.041 37.6827 114.534 38.1893 113.894 38.552C113.254 38.9147 112.518 39.096 111.686 39.096ZM107.19 39V27.128H108.726V32.776L108.566 34.744L108.662 36.712V39H107.19ZM111.558 37.752C112.102 37.752 112.588 37.6293 113.014 37.384C113.452 37.1387 113.793 36.792 114.038 36.344C114.294 35.8853 114.422 35.3573 114.422 34.76C114.422 34.152 114.294 33.6293 114.038 33.192C113.793 32.744 113.452 32.3973 113.014 32.152C112.588 31.9067 112.102 31.784 111.558 31.784C111.025 31.784 110.54 31.9067 110.102 32.152C109.676 32.3973 109.334 32.744 109.078 33.192C108.833 33.6293 108.71 34.152 108.71 34.76C108.71 35.3573 108.833 35.8853 109.078 36.344C109.334 36.792 109.676 37.1387 110.102 37.384C110.54 37.6293 111.025 37.752 111.558 37.752ZM121.665 39.096C120.939 39.096 120.299 38.9627 119.745 38.696C119.201 38.4293 118.774 38.024 118.465 37.48C118.166 36.9253 118.017 36.232 118.017 35.4V30.52H119.553V35.224C119.553 36.056 119.75 36.68 120.145 37.096C120.55 37.512 121.115 37.72 121.841 37.72C122.374 37.72 122.838 37.6133 123.233 37.4C123.627 37.176 123.931 36.856 124.145 36.44C124.358 36.0133 124.465 35.5013 124.465 34.904V30.52H126.001V39H124.545V36.712L124.785 37.32C124.507 37.8853 124.091 38.3227 123.537 38.632C122.982 38.9413 122.358 39.096 121.665 39.096ZM133.446 39V27.8H138.166C139.36 27.8 140.411 28.0347 141.318 28.504C142.235 28.9733 142.944 29.6293 143.446 30.472C143.958 31.3147 144.214 32.2907 144.214 33.4C144.214 34.5093 143.958 35.4853 143.446 36.328C142.944 37.1707 142.235 37.8267 141.318 38.296C140.411 38.7653 139.36 39 138.166 39H133.446ZM135.046 37.608H138.07C138.998 37.608 139.798 37.432 140.47 37.08C141.152 36.728 141.68 36.2373 142.054 35.608C142.427 34.968 142.614 34.232 142.614 33.4C142.614 32.5573 142.427 31.8213 142.054 31.192C141.68 30.5627 141.152 30.072 140.47 29.72C139.798 29.368 138.998 29.192 138.07 29.192H135.046V37.608ZM150.968 30.44C151.662 30.44 152.27 30.5733 152.792 30.84C153.326 31.1067 153.742 31.512 154.04 32.056C154.339 32.6 154.488 33.288 154.488 34.12V39H152.952V34.296C152.952 33.4747 152.75 32.856 152.344 32.44C151.95 32.024 151.39 31.816 150.664 31.816C150.12 31.816 149.646 31.9227 149.24 32.136C148.835 32.3493 148.52 32.664 148.296 33.08C148.083 33.496 147.976 34.0133 147.976 34.632V39H146.44V27.128H147.976V32.808L147.672 32.2C147.95 31.6453 148.376 31.2133 148.952 30.904C149.528 30.5947 150.2 30.44 150.968 30.44ZM162.595 39V37.208L162.515 36.872V33.816C162.515 33.1653 162.323 32.664 161.939 32.312C161.566 31.9493 161 31.768 160.243 31.768C159.742 31.768 159.251 31.8533 158.771 32.024C158.291 32.184 157.886 32.4027 157.555 32.68L156.915 31.528C157.352 31.176 157.875 30.9093 158.483 30.728C159.102 30.536 159.747 30.44 160.419 30.44C161.582 30.44 162.478 30.7227 163.107 31.288C163.736 31.8533 164.051 32.7173 164.051 33.88V39H162.595ZM159.811 39.096C159.182 39.096 158.627 38.9893 158.147 38.776C157.678 38.5627 157.315 38.2693 157.059 37.896C156.803 37.512 156.675 37.08 156.675 36.6C156.675 36.1413 156.782 35.7253 156.995 35.352C157.219 34.9787 157.576 34.68 158.067 34.456C158.568 34.232 159.24 34.12 160.083 34.12H162.771V35.224H160.147C159.379 35.224 158.862 35.352 158.595 35.608C158.328 35.864 158.195 36.1733 158.195 36.536C158.195 36.952 158.36 37.288 158.691 37.544C159.022 37.7893 159.48 37.912 160.067 37.912C160.643 37.912 161.144 37.784 161.571 37.528C162.008 37.272 162.323 36.8987 162.515 36.408L162.819 37.464C162.616 37.9653 162.259 38.3653 161.747 38.664C161.235 38.952 160.59 39.096 159.811 39.096ZM171.39 39.096C170.664 39.096 170.008 38.936 169.422 38.616C168.846 38.2853 168.387 37.8 168.046 37.16C167.715 36.52 167.55 35.72 167.55 34.76C167.55 33.8 167.72 33 168.062 32.36C168.414 31.72 168.878 31.24 169.454 30.92C170.04 30.6 170.686 30.44 171.39 30.44C172.222 30.44 172.958 30.6213 173.598 30.984C174.238 31.3467 174.744 31.8533 175.118 32.504C175.491 33.144 175.678 33.896 175.678 34.76C175.678 35.624 175.491 36.3813 175.118 37.032C174.744 37.6827 174.238 38.1893 173.598 38.552C172.958 38.9147 172.222 39.096 171.39 39.096ZM166.894 39V27.128H168.43V32.776L168.27 34.744L168.366 36.712V39H166.894ZM171.262 37.752C171.806 37.752 172.291 37.6293 172.718 37.384C173.155 37.1387 173.496 36.792 173.742 36.344C173.998 35.8853 174.126 35.3573 174.126 34.76C174.126 34.152 173.998 33.6293 173.742 33.192C173.496 32.744 173.155 32.3973 172.718 32.152C172.291 31.9067 171.806 31.784 171.262 31.784C170.728 31.784 170.243 31.9067 169.806 32.152C169.379 32.3973 169.038 32.744 168.782 33.192C168.536 33.6293 168.414 34.152 168.414 34.76C168.414 35.3573 168.536 35.8853 168.782 36.344C169.038 36.792 169.379 37.1387 169.806 37.384C170.243 37.6293 170.728 37.752 171.262 37.752ZM177.8 39V30.52H179.336V39H177.8ZM178.568 28.888C178.269 28.888 178.018 28.792 177.816 28.6C177.624 28.408 177.528 28.1733 177.528 27.896C177.528 27.608 177.624 27.368 177.816 27.176C178.018 26.984 178.269 26.888 178.568 26.888C178.866 26.888 179.112 26.984 179.304 27.176C179.506 27.3573 179.608 27.5867 179.608 27.864C179.608 28.152 179.512 28.3973 179.32 28.6C179.128 28.792 178.877 28.888 178.568 28.888ZM181.661 41.368L182.461 38.136L182.669 39.064C182.359 39.064 182.098 38.968 181.885 38.776C181.682 38.584 181.581 38.3227 181.581 37.992C181.581 37.672 181.682 37.4107 181.885 37.208C182.098 37.0053 182.354 36.904 182.653 36.904C182.962 36.904 183.213 37.0107 183.405 37.224C183.597 37.4267 183.693 37.6827 183.693 37.992C183.693 38.0987 183.682 38.2053 183.661 38.312C183.65 38.408 183.623 38.5253 183.581 38.664C183.549 38.792 183.495 38.952 183.421 39.144L182.669 41.368H181.661ZM195.054 39.128C193.582 39.128 192.425 38.7067 191.582 37.864C190.74 37.0213 190.318 35.7893 190.318 34.168V27.8H191.918V34.104C191.918 35.352 192.19 36.264 192.734 36.84C193.289 37.416 194.068 37.704 195.07 37.704C196.084 37.704 196.862 37.416 197.406 36.84C197.961 36.264 198.238 35.352 198.238 34.104V27.8H199.79V34.168C199.79 35.7893 199.369 37.0213 198.526 37.864C197.694 38.7067 196.537 39.128 195.054 39.128ZM201.14 39L206.212 27.8H207.796L212.884 39H211.204L206.676 28.696H207.316L202.788 39H201.14ZM203.3 36.2L203.732 34.92H210.036L210.5 36.2H203.3ZM216.011 32.632H221.771V33.992H216.011V32.632ZM216.155 37.608H222.683V39H214.555V27.8H222.459V29.192H216.155V37.608Z" fill="black"/>
<path d="M17.5 11.75C17.5 17.4375 10.5 22.25 10.5 22.25C10.5 22.25 3.5 17.4375 3.5 11.75C3.5 9.8935 4.2375 8.11302 5.55025 6.80027C6.86301 5.48751 8.64349 4.75001 10.5 4.75001C12.3565 4.75001 14.137 5.48751 15.4497 6.80027C16.7625 8.11302 17.5 9.8935 17.5 11.75Z" stroke="#0B0736" stroke-width="1.5"/>
<path d="M13.125 11.75C13.125 12.4462 12.8484 13.1139 12.3562 13.6062C11.8639 14.0985 11.1962 14.375 10.5 14.375C9.80381 14.375 9.13613 14.0985 8.64385 13.6062C8.15156 13.1139 7.875 12.4462 7.875 11.75C7.875 11.0538 8.15156 10.3861 8.64385 9.89386C9.13613 9.40158 9.80381 9.12501 10.5 9.12501C11.1962 9.12501 11.8639 9.40158 12.3562 9.89386C12.8484 10.3861 13.125 11.0538 13.125 11.75Z" stroke="#0B0736" stroke-width="1.5"/>
</svg>`;
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svgString)}`;
};

const windowWidth = ref(window.innerWidth);

    onMounted(() => {
          console.log('Current User:', getCurrentUser());
  console.log('Roles:', getCurrentUser()?.roles);
  console.log('is_listing_team:', getCurrentUser()?.is_listing_team);
  console.log('canApproveListings:', canApproveListings.value);
        window.addEventListener('resize', () => {
          windowWidth.value = window.innerWidth;
          schedulePropertySidebarSync();
        });
      fetchProperty();
      fetchComments();
      fetchCommentsStats();
      document.addEventListener('keydown', handleKeydown);
      
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.property-actions-dropdown')) {
          closeActionsDropdown();
        }
      });
      
      setTimeout(() => {
        listenForAccessRequestUpdates();
      }, 1000);

      bindPropertySidebarScroll();
      nextTick(() => {
        schedulePropertySidebarSync();
        setTimeout(schedulePropertySidebarSync, 100);
        setTimeout(schedulePropertySidebarSync, 500);
      });
    });

    onUnmounted(() => {
      unbindPropertySidebarScroll();
      if (propertySidebarSyncRaf) {
        cancelAnimationFrame(propertySidebarSyncRaf);
      }
    });

    watch(loading, (isLoading) => {
      if (!isLoading) {
        nextTick(() => {
          schedulePropertySidebarSync();
          setTimeout(schedulePropertySidebarSync, 150);
        });
      }
    });

    // Cleanup listeners
    const cleanup = () => {
      document.removeEventListener('keydown', handleKeydown);
      cleanupEchoListeners();
    };
    
    // Payment Plan Functions
const parsePaymentPlans = (property) => {
  const paymentPlan = property.payment_plan_json || property.payment_plan;
  
  if (!paymentPlan) return [];
  
  try {
    if (typeof paymentPlan === 'string' && paymentPlan.trim().startsWith('[')) {
      const parsed = JSON.parse(paymentPlan);
      return Array.isArray(parsed) ? parsed : [parsed];
    }
    
    if (typeof paymentPlan === 'string') {
      return [paymentPlan];
    }
    
    if (Array.isArray(paymentPlan)) {
      return paymentPlan;
    }
    
    return [];
  } catch (e) {
    console.error('Error parsing payment plans:', e);
    return [paymentPlan];
  }
};

const hasPaymentPlans = (property) => {
  const plans = parsePaymentPlans(property);
  return plans.length > 0;
};

const isArrayPaymentPlan = (paymentPlan) => {
  if (!paymentPlan) return false;
  
  try {
    if (typeof paymentPlan === 'string' && paymentPlan.trim().startsWith('[')) {
      const parsed = JSON.parse(paymentPlan);
      return Array.isArray(parsed);
    }
    return Array.isArray(paymentPlan);
  } catch (e) {
    return false;
  }
};

const formatPaymentPlan = (paymentPlan) => {
  if (!paymentPlan) return '';
  
  const plans = parsePaymentPlans({ payment_plan: paymentPlan });
  return plans.join(', ');
};

// Add to computed properties
const getPaymentPlans = computed(() => {
  if (!property.value) return [];
  return parsePaymentPlans(property.value);
});
const handleCancelViewingClick = (event) => {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
  }
  
  closeActionsDropdown();
  
  Swal.fire({
    title: 'Cancel Viewing Request?',
    html: `
      <div class="text-start">
        <p><strong>Viewing Details:</strong></p>
        <p>Date: ${requestStatus.value?.viewing_details?.date ? formatDate(requestStatus.value.viewing_details.date) : 'N/A'}</p>
        <p>Time: ${requestStatus.value?.viewing_details?.time ? formatTime(requestStatus.value.viewing_details.time) : 'N/A'}</p>
        <div class="mb-3">
          <label class="form-label"><strong>Cancellation Reason *</strong></label>
          <textarea 
            id="cancelReasonInput" 
            class="form-control" 
            rows="4" 
            placeholder="Please provide a reason for cancelling this viewing request..."
            style="font-size: 14px;"
            maxlength="255"
            required
          ></textarea>
          <div class="text-end mt-1">
            <small class="text-muted" id="charCount">0/255</small>
          </div>
          <small class="text-danger mt-1" id="errorMsg" style="display: none;">Reason is required</small>
        </div>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, Cancel Request',
    cancelButtonText: 'Keep Request',
    reverseButtons: true,
    allowOutsideClick: false,
    preConfirm: () => {
      const reasonInput = document.getElementById('cancelReasonInput');
      const errorMsg = document.getElementById('errorMsg');
      
      if (!reasonInput.value.trim()) {
        errorMsg.style.display = 'block';
        reasonInput.focus();
        return false;
      }
      
      return { reason: reasonInput.value.trim() };
    },
    didOpen: () => {
      const textarea = document.getElementById('cancelReasonInput');
      const charCount = document.getElementById('charCount');
      const errorMsg = document.getElementById('errorMsg');
      
      textarea.addEventListener('input', () => {
        charCount.textContent = `${textarea.value.length}/255`;
        if (textarea.value.trim()) {
          errorMsg.style.display = 'none';
        }
      });
      
      setTimeout(() => {
        textarea.focus();
      }, 100);
    }
  }).then(async (result) => {
    if (result.isConfirmed && result.value) {
      try {
        cancellingSpecificRequest.value = true;
        
        const response = await api.post(`/listings/access-requests/${property.value.id}/cancel`, {
          request_type: 'viewing',
          cancellation_reason: result.value.reason
        });
        
        if (response.data.status) {
          proxy.$showNotification('Viewing request cancelled successfully!', 'success');
          requestStatus.value.viewing_status = null;
          requestStatus.value.viewing_details = null;
          await fetchRequestStatus();
        }
      } catch (err) {
        handleApiError(err, 'Failed to cancel viewing request');
      } finally {
        cancellingSpecificRequest.value = false;
      }
    }
  });
};
const openDriveLink = () => {
  if (!property?.value.drive_link) return;

  // Open the link in a new window
  window.open(property.value.drive_link, '_blank', 'noopener,noreferrer');
}

    return {
      propertySidebarColRef,
      propertySidebarStickyRef,
      propertySidebarSpacerRef,
      property,
      logo,
      propertyIcon,
      bedIcon,
      bathIcon,
      sqftIcon,
      loading,
      error,
      currentMainImage,
      chatMessage,
      showLightbox,
      currentImageIndex,
      loadingRequest,
      cancellingRequest,
      requestStatus,
      showOwnerDetailsModal,
      isPropertyOwner,
      canEditProperty,
      canDeleteProperty,
      canEditOrDelete,
      canShowOffers,
      canGenerateOffer,
      hasMortgageInfo,
      hasPaymentDetails,
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
      openCancelModal,
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
      // Area Hierarchy
      areaHierarchy,
      hasAreaHierarchy,
      hasAreaChildren,
      getAreaType,
      getLevelType,
      // New method
      goToAgentDetails,
      openChatWithAgent,
      showChatPopup,
      chatAgent,
      chatListingId,
      //pdf
      generatePDF,
      showOfferHistory,
        // Dropdown and Modal
        showActionsDropdown,
        showAssignAgentModal,
        selectedAgentId,
        assignmentNotes,
        assigningAgent,
        availableAgents,
        canAssignAgent,
        canUsePropertyChat,
        onlyShow,
        canMarkAsConverted,
        toggleActionsDropdown,
        closeActionsDropdown,
        toggleArchive,
        toggleActive,
        openAssignAgentModal,
        assignToAgent,
        markAsSold,
        revertFromConverted,
        openSoldOutModal,
        showSoldOutModal, 
 soldByChoice,
        showAddOwnerModal,
        newOwner,
        isSubmittingOwner,
        ownerNationalities,
        ownerLocations,
        selectSoldBy,
        closeSoldOutModal,
        openAddOwnerModal,
        submitNewOwnerAndMarkSold,
        resetNewOwnerForm,
        onlyLettersOwner,
        onlyNumbersOwner,
        handleOwnerNationalityChange,
        getOwnerLocationLabel,
        getOwnerLocationPlaceholder,
        fetchOwnerLocations,
        handleOwnerFileUpload,
        handleOwnerAdditionalDocumentsUpload,
        removeOwnerAdditionalDocument, 

          openFloorPlanSlider,
    closeFloorPlanSlider,
    nextFloorPlan,
    prevFloorPlan,
    setCurrentFloorPlan,
    handleFloorPlanError,
    showFloorPlanSlider,
    currentFloorPlanIndex,
      // Cleanup
      cleanup,
        showViewingModal,
  submittingViewing,
  viewingRequest,
  timeSlots,
  today,
  openViewingModal,
  submitViewingRequest,
  formatTime,
  canRequestViewing,
  cancelViewingRequest,
  getPaymentPlans,
  hasPaymentPlans,
  isArrayPaymentPlan ,
  formatPaymentPlan,
  parsePaymentPlans ,
  handleCancelViewingClick ,
   openDriveLink,
   hasAdditionalFeatures,
   additionalFeaturesList,
   
       selectedSoldByAgent,
    availableAgentsForSoldBy,
    loadingAgentsForSoldBy,
    
      showRentedModal,
    rentedByChoice,
    selectedRentedByAgent,
    rentedDate,
    availableAgentsForRentedBy,
    loadingAgentsForRentedBy,
    openRentedModal,
    closeRentedModal,
    selectRentedBy,
    markAsRented,
        addPersonType,
   revertFromRented,


    showOtherCompanyModal,
  otherCompanyData,
  submittingOtherCompany,
  otherCompanyType,
  openOtherCompanyModal,
  closeOtherCompanyModal,
  submitOtherCompany,
  submittedOtherCompany,
   canApproveListings,
  approveListing,
  openRejectFromDetailsModal,
  confirmRejectListing,
  showRejectDetailsModal,
  rejectReasonDetails,
  rejectingListing,

  showOIAgentSelection,
    showAToAModal,
    aToAData,
    submittingATO,
    aToAType,
    
    // متغيرات الإيجار
    showOIAgentSelectionRent,
    selectedRentedByAgent,
    availableAgentsForRentedBy,
    
    // دوال البيع
    openOIAgentSelection,
    backToOIAOptions,
    markAsSoldByOIAgent,
    openAToAModalForSale,
    submitAToA,
    closeAToAModal,
    
   
    openOIAgentSelectionRent,
    backToOIAOptionsRent,
    markAsRentedByOIAgent,
    openAToAModalForRent,
    submitAToAForRent,
   hasRejectionReason,formatRejectionDate ,rejectionDetails
    };
  },

  methods: {
    handleChatWithAgentClick() {
      this.openChatWithAgentFromStorage();
      this.closeActionsDropdown();
    },
    openChatWithAgentFromStorage() {
      let parsedUser = null;
      try {
        const userData = localStorage.getItem('user');
        parsedUser = userData ? JSON.parse(userData) : null;
      } catch (_) {
        parsedUser = null;
      }
      if (!parsedUser) {
        Swal.fire({ title: 'Please log in', text: 'You need to be logged in to start a chat.', icon: 'info' });
        return;
      }
      if (!window.__openPropertyChat) {
        Swal.fire({ title: 'Error', text: 'Chat is not available.', icon: 'info' });
        return;
      }
      try {
        const p = this.property?.value ?? this.property;
        const raw = sessionStorage.getItem('propertyChatAgent');
        const listingId = sessionStorage.getItem('propertyChatListingId');
        const contextRaw = sessionStorage.getItem('propertyChatContext');
        if (raw) {
          const agent = JSON.parse(raw);
          const storedContext = contextRaw ? JSON.parse(contextRaw) : {};
          const liveContext = p ? {
            propertyId: p.id ?? null,
            title: p.title || p.name || p.reference_number || p.reference || `Property #${p.id ?? ''}`,
            reference: p.reference_number || p.reference || p.ref_no || p.unit_ref || '',
            location: [p?.project?.name, p?.area?.title || p?.area?.name].filter(Boolean).join(' - '),
            price: p?.price !== undefined && p?.price !== null && p?.price !== ''
              ? `${Number(String(p.price).replace(/,/g, '')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${p?.currency || 'AED'}`
              : '',
          } : {};
          const mergedContext = { ...storedContext, ...liveContext };
          window.__openPropertyChat(agent, listingId ? parseInt(listingId, 10) : (p?.id ?? null), mergedContext);
          return;
        }
        if (!p?.agent) {
          Swal.fire({ title: 'No agent', text: 'This property has no agent assigned.', icon: 'info' });
          return;
        }
        window.__openPropertyChat(
          { id: p.agent.id, name: p.agent.name || p.agent.email, email: p.agent.email, avatar: p.agent.avatar_url || p.agent.avatar || null },
          p.id ?? null,
          {
            propertyId: p.id ?? null,
            title: p.title || p.name || p.reference_number || `Property #${p.id ?? ''}`,
            reference: p.reference_number || p.reference || '',
            location: [p?.project?.name, p?.area?.title || p?.area?.name].filter(Boolean).join(' - '),
            price: p?.price !== undefined && p?.price !== null && p?.price !== ''
              ? `${Number(String(p.price).replace(/,/g, '')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${p?.currency || 'AED'}`
              : '',
          }
        );
      } catch (_) {
        Swal.fire({ title: 'Error', text: 'Could not open chat.', icon: 'info' });
      }
    },
  },
  beforeUnmount() {
    this.cleanup();
  }
};


</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

.property-actions-dropdown {
  position: relative;
  margin-bottom: 16px;
  padding:8px;
}

.dropdown-toggle {
  width: 100%;
  /* display: flex; */
  align-items: center;
  justify-content: space-between;
  padding: 8px;
  background: #733E87;
  color: white;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: center;
}

.dropdown-toggle:hover {
  /* background: #001a57; */
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(11, 7, 54, 0.3);
}

.dropdown-arrow {
  transition: transform 0.3s ease;
}

.dropdown-arrow.rotated {
  transform: rotate(180deg);
}

.dropdown-menu {
  position: relative;
  top: auto;
  left: auto;
  right: auto;
  opacity: 0;
  visibility: hidden;
  max-height: 0;
  overflow: hidden;
  transition: all 0.3s ease;
  margin: 0;
  border: none;
  box-shadow: none;
  transform: none;
}

.dropdown-menu.show {
  opacity: 1;
  visibility: visible;
  max-height: 400px;
  margin-top: 8px;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  transform: none;
  overflow-y:auto;
}

.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: none;
  border: none;
  text-align: left;
  font-size: 13px;
  font-weight: 500;
  color: #333;
  cursor: pointer;
  transition: all 0.2s ease;
  border-bottom: 1px solid #f8f9fa;
}

.dropdown-item:last-child {
  border-bottom: none;
}

.dropdown-item:hover {
  background: #f8f9fa;
  color: #0B0736;
}

.dropdown-item i {
  font-size: 16px;
  width: 20px;
  text-align: center;
}
.dropdown-item:hover{
      background-color: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}


.modal-content {
  max-width: 500px;
  width: 90%;
}

.form-group {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: #333;
  font-size: 13px;
}

.form-select,
.form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  font-size: 13px;
  transition: border-color 0.3s ease;
}

.form-select:focus,
.form-control:focus {
  outline: none;
  border-color: #0B0736;
  box-shadow: 0 0 0 3px rgba(11, 7, 54, 0.1);
}

@media (max-width: 768px) {
  .dropdown-menu[style*="position: fixed"] {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    transform: none !important;
    width: 100% !important;
    max-width: none !important;
    max-height: 400px !important;
    margin-top: 8px !important;
  }
  
  .dropdown-backdrop-mobile {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
  }
  .property-actions-dropdown-wrapper {
  position: relative;
  z-index: 1000;
  top: 0px;
}
}

.agent-sidebar-card {
  position: relative;
  overflow: visible !important;
  top:0px
}

.sidebar-section {
  position: relative;
  overflow: visible !important;
}

@media (max-width: 768px) {
.property-content {
padding:0px !important;
}
}

.agent-sidebar-card {
  /* position: relative; */
  transition: all 0.3s ease;
}

.agent-sidebar-card.expanding {
  min-height: 400px; 
}
.property-actions-dropdown-wrapper {
  position: relative;
  margin-bottom: 0;
}

.property-actions-dropdown {
  position: relative;
  display: block;
}

.dropdown-item-btn {
  padding: 8px 0;
  border-bottom: 1px solid #f0f0f0;
}

.dropdown-item-btn:last-child {
  border-bottom: none;
}

.dropdown-item {
  padding: 10px 12px;
  width: 100%;
  border: none;
  background: none;
  cursor: pointer;
  transition: all 0.2s ease;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.dropdown-item:hover:not(.approved-info):not(.pending-info) {
  background-color: #f8f9fa;
}

.dropdown-item.approved-info {
  background: #e8f5e9;
  color: #2e7d32;
  cursor: default;
}
.dropdown-item.approved-info.viewing {
align-items: flex-start;
    flex-direction: column;
    margin-inline-start: 5px;

}
.dropdown-item.approved-info.viewing div::first{
 display:flex;
 gap:8px;
}
.dropdown-item.pending-info {
  background: #fff3e0;
  color: #f57c00;
  cursor: default;
}

.text-success {
  color: #28a745 !important;
}

.text-warning {
  color: #ffc107 !important;
}

.text-muted {
  color: #6c757d !important;
}

.request-time {
  font-size: 11px;
  opacity: 0.7;
  margin-top: 2px;
}

.dropdown-item:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-cancel-small {
  opacity: 0.7;
  transition: opacity 0.2s ease;
}

.btn-cancel-small:hover {
  opacity: 1;
  transform: scale(1.1);
}

.btn-cancel-small:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.request-status-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

.request-status-badge.pending {
  background: rgba(255, 193, 7, 0.1);
  color: #ffc107;
}

.request-status-badge.approved {
  background: rgba(40, 167, 69, 0.1);
  color: #28a745;
}

.request-status-badge.rejected {
  background: rgba(220, 53, 69, 0.1);
  color: #dc3545;
}
.loading-spinner-small {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid #f3f3f3;
  border-top: 2px solid #0B0736;
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
  /* height: 490px; */
margin-bottom: 20px;
}

.gallery-container {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 12px;
  height: 400px;
}

/* Only one image → main section takes the full width and the empty
   side-panel column is collapsed. */
.gallery-container.is-single {
  grid-template-columns: 1fr;
}
.gallery-container.is-single .side-images {
  display: none;
}
.gallery-container.is-single .main-image {
  object-fit: cover;
}

.main-image-section {
  border-radius: 12px ;
  overflow: hidden;
  position: relative;
  cursor: pointer;
}

.main-image {
  width: 100%;
  height: 100%;
  /* object-fit: cover; */
  transition: transform 0.3s ease;
      max-height: 400px;
}

.main-image-section:hover .main-image {
  transform: scale(1.05);
}

.image-overlay {
  position: absolute;
  left: 16px; 
  bottom: 16px; 
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: flex-start;
  color: #0B0736;
  opacity: 1;
  transform: translateY(0px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 15px ;
  gap: 12px;
  min-width: 214px;
  height: 50px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(11, 7, 54, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
}



.image-overlay i {
  font-size: 28px;
  transition: transform 0.3s ease;
}

.main-image-section:hover .image-overlay i {
  transform: scale(1.1);
}

.image-overlay span {
  font-size: 18px;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.main-image-section:hover .image-overlay {
  opacity: 1;
}




.side-images {
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: 100%;
      max-height: 400px;
}

.side-image {
  height: calc(50% - 4px);
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.3s ease;
  position: relative;
}

.side-image.active {
  border-color: #0B0736;
}

.side-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.side-image:hover {
  transform: scale(1.02);
}

.side-image.has-view-all {
  cursor: pointer;
}

.view-all-overlay {
  position: absolute;
  inset: 0;
  /* background: rgba(11, 7, 54, 0.65);
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px); */
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  transition: background 0.3s ease;
}

.side-image.has-view-all:hover .view-all-overlay {
  background: rgba(11, 7, 54, 0.78);
}

.view-all-content {
  text-align: center;
  padding: 10px;
  background: #0b0736a6;
  backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
  border-radius: 10px;
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
  font-size: 11px;
  opacity: 0.85;
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
  background: none;
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
  /* justify-content: space-between; */
  justify-content: end;
  align-items: center;
  padding: 16px 24px;
  background: none;
  /* border-bottom: 1px solid #e9ecef; */
}

.lightbox-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #ffffff;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.3s ease;
}

.lightbox-close:hover {
  background: #e9ecef;
  color: #0B0736;
}

.lightbox-counter {
  font-weight: 600;
  color: #0B0736;
}

.lightbox-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px;
  position: relative;
  height: 400px;
}

.lightbox-nav {
  /* background: rgba(255, 255, 255, 0.9); */
  color: white;
  border: none;
  width: 60px;
  height: 60px;
  /* border-radius: 50%; */
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  cursor: pointer;
  transition: all 0.3s ease;
  z-index: 2;
  font-weight: 700;
}

.lightbox-nav:hover:not(:disabled) {
  background: #0B0736;
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
  /* object-fit: contain; */
  border-radius: 8px;
}

.lightbox-thumbnails {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  background: rgba(0, 0, 0, 0.35);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.45) transparent;
  flex-shrink: 0;
  justify-content: center;
}

.lightbox-thumbnails::-webkit-scrollbar {
  height: 6px;
}

.lightbox-thumbnails::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.4);
  border-radius: 4px;
}

.lightbox-thumbnails::-webkit-scrollbar-track {
  background: transparent;
}

.lightbox-thumbnail {
  width: 72px;
  height: 54px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: border-color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
  flex-shrink: 0;
  opacity: 0.65;
}

.lightbox-thumbnail.active {
  border-color: #ffffff;
  opacity: 1;
}

.lightbox-thumbnail:hover {
  transform: scale(1.05);
  opacity: 1;
}

.lightbox-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* Property Content Styles */
.property-content {
  /* background: white; */
}

.location-section {
  padding-bottom: 20px;
  border-bottom: 1px solid #e9ecef;
}

.p-24{
  padding: 24px !important;
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
  background: #0B0736;
  color: white;
}

.property-main-info:first {
margin-top: 20px;
}
.property-main-info {
  padding: 30px;
  margin-bottom: 20px;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  background-color: #ffffff;
}

.price-main {
  margin-bottom: 8px;
}

.property-price {
  font-size: 24px !important;
  font-weight: 800;
  color: #0B0736;
  margin: 0 0 15px 0 !important;
  line-height: 1;
}

.property-title {
  font-size: 16px !important;
  font-weight: 600;
  color: #6c757d;
  margin: 0 !important;
  line-height: 1.2;
}

.specs-grid-main {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  margin-top: 12px;
}

.spec-main-item {
  display: flex;
  align-items: center;
  /* background: #f8f9fa; */
  /* border-radius: 8px; */
  padding: 8px 12px;
  /* border: 1px solid #e9ecef; */
  min-width: 100px;
}

.spec-main-info {
  display: flex;
  flex-direction: column;
  text-align: center;
  gap: 2px;
}

.spec-main-value {
  font-size: 16px;
  font-weight: 700;
  color: #0B0736;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.spec-main-value i {
  font-size: 14px;
  opacity: 0.8;
}

.spec-main-label {
  font-size: 11px;
  color: #6c757d;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detailed-info-section {
  padding: 16px;
  margin-bottom: 16px;
  border-radius: 12px;
  border: 1px solid #e9ecef;
  background-color: #ffffff;
}

.info-section {
  margin-bottom: 20px;
}

.section-title {
  font-size: 20px !important;
  font-weight: 600;
  color: #0B0736;
  /* margin-bottom: 12px; */
  padding: 10px 12px;
  /* background: #0B0736; */
  /* border-radius: 8px; */
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px 24px;
  margin-bottom: 16px;
}
@media (min-width: 992px) {
    .info-grid[data-v-2967ddc5] {
        grid-template-columns: repeat(3, 1fr);
    }
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

/* When .flex-column is added, neutralise the row-mode justify/align so the
   stacked layout actually looks vertical (label on top, value/pills below). */
.info-item.flex-column {
  flex-direction: column !important;
  align-items: flex-start !important;
  justify-content: flex-start !important;
  gap: 8px;
}
.info-item.flex-column .info-value {
  width: 100%;
}

.info-label {
  font-weight: 600;
  color: #555;
  font-size: 13px;
}

.info-value {
  font-weight: 800;
  color: #0B0736;
  font-size: 13px;
}

.description-content {
  background: #f8f9fa;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.description-text {
  line-height: 1.6;
  color: #555;
  font-size: 14px;
  margin: 0;
}
.property-show-row {
  align-items: stretch;
}

.property-show-sidebar-col {
  align-self: stretch;
  display: flex;
  flex-direction: column;
}

.property-sidebar-spacer {
  width: 100%;
  height: 0;
  flex-shrink: 0;
  pointer-events: none;
}

.property-show-sidebar-col {
  position: relative;
}

.sidebar-sticky-container {
  position: sticky;
  top: calc(var(--app-topbar-height, 2.75rem) + var(--app-header-below-gap, 0.5rem) + 0.75rem);
  z-index: 40;
  width: 100%;
  flex: 0 0 auto;
  align-self: flex-start;
  height: fit-content;
  border-radius: 20px;
}

.sidebar-sticky-container.is-sidebar-fixed {
  position: fixed !important;
  z-index: 45;
}

.sidebar-sticky-container.is-sidebar-fixed.is-sidebar-at-bottom {
  position: absolute !important;
  top: auto !important;
  left: 0 !important;
  width: 100% !important;
}

.sidebar-sticky-container.is-sidebar-fixed .agent-sidebar-card {
  box-shadow: 0 8px 28px rgba(11, 7, 54, 0.18);
}

/* Agent Sidebar Card - Improved Styles */
.agent-sidebar-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(40px);
  border-radius: 20px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #e9ecef;
  position: relative;
  max-height: calc(100dvh - var(--app-topbar-height, 2.75rem) - var(--app-header-below-gap, 0.5rem) - 1.5rem);
  overflow-x: hidden;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #c1c1c1 transparent;
}
.agent-sidebar-card::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(180deg, rgb(255 255 255) 0%, rgb(20 30 80 / 79%) 0%, rgba(5, 10, 40, 0.95) 100%);
  border-radius: 20px;

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
  align-items: start;
  gap: 12px;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e9ecef;
  text-align: left;
  position: relative;
  z-index: 1;
  pointer-events: auto;
}

.agent-sidebar-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #0B0736;
}

.agent-sidebar-info {
  width: 100%;
  position: relative;
  z-index: 1;
}

.agent-sidebar-name {
  font-size: 16px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 6px 0;
  line-height: 1.3;
}

.agent-sidebar-company {
  font-size: 13px;
  color: #ffffff;
  margin: 0 0 10px 0;
  line-height: 1.4;
}

.btn-show-agent-details {
  width: 100%;
  background: none;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  gap: 6px;
  padding: 0;
}

.btn-show-agent-details:hover {
  text-decoration: underline;
}

/* Sidebar Sections - Improved Responsive */
.sidebar-section {
  margin-bottom: 16px;
  padding-bottom: 12px;
  /* border-bottom: 1px solid #e9ecef; */
}

.sidebar-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
}

.sidebar-title {
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 10px;
  padding-bottom: 6px;
  /* border-bottom: 2px solid #0B0736; */
}

/* Unit Display Styles */
.owner-unit-info,
.approved-unit-info {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.unit-display {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.unit-display i {
  font-size: 20px;
  color: #0B0736;
}

.unit-value {
  font-size: 18px;
  font-weight: 700;
  color: #0B0736;
}

.unit-note {
  font-size: 11px;
  color: #28a745;
  margin: 0;
}

.approval-info {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #28a745;
  font-size: 11px;
  font-weight: 600;
}

/* Pending Request Styles */
.pending-request-info {
  background: #fff3cd;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #ffeaa7;
}

.request-status {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
}

.request-status i {
  color: #856404;
}

.status-text {
  font-weight: 600;
  color: #856404;
  font-size: 13px;
}

.request-details {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.request-date {
  color: #856404;
  font-size: 11px;
}

.btn-cancel-small {
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 10px;
}

/* Owner Info Display */
.owner-info-direct,
.approved-owner-info {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.info-display {
  margin-bottom: 10px;
}

.info-item-sidebar {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 13px;
}

.info-item-sidebar i {
  color: #0B0736;
  width: 14px;
}

.btn-show-owner-modal {
  width: 100%;
  padding: 8px;
  background: #0B0736;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
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
  gap: 6px;
  padding: 10px 12px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 100%;
}

.btn-request-unit {
  background: #0B0736;
  color: white;
}

.btn-request-owner {
  background: #733E87;
  color: white;
}

.btn-sidebar:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Cannot Request Styles */
.cannot-request-info {
  background: #f8d7da;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
}

.cannot-request-status {
  display: flex;
  align-items: center;
  gap: 6px;
}

.cannot-request-status i {
  color: #721c24;
}

.cannot-request-status .status-text {
  color: #721c24;
  font-weight: 600;
  font-size: 13px;
}

/* Owner Details Modal - Clean Design */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999 !important;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.owner-details-modal {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideUp 0.4s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Header */
.modal-header {
  padding: 24px 32px;
  background:linear-gradient(180deg,#fff,#141e50c9 0%,#050a28f2);
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  font-size: 28px;
  opacity: 0.9;
}

.modal-title {
  margin: 0;
  font-size: 24px;
  font-weight: 600;
  color:white !important;
}

.modal-subtitle {
  margin: 4px 0 0;
  opacity: 0.9;
  font-size: 14px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  color: white;
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

/* Body */
.modal-body {
  padding: 32px;
  flex: 1;
  overflow-y: auto;
}

/* Owner Summary */
.owner-summary-card {
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid #eaeaea;
}

.owner-name {
  margin: 0 0 12px 0;
  font-size: 28px;
  font-weight: 600;
  color: #2c3e50;
}

.owner-identifiers {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.owner-id,
.owner-nationality,
.owner-status {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.owner-id {
  background: #e3f2fd;
  color: #1976d2;
}

.owner-nationality {
  background: #e8f5e9;
  color: #388e3c;
}

.owner-status.resident {
  background: #f1f8e9;
  color: #689f38;
}

.owner-status.non-resident {
  background: #fff3e0;
  color: #f57c00;
}

/* Sections */
.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 20px 0;
  font-size: 18px;
  font-weight: 600;
  color: #2c3e50;
  padding-bottom: 12px;
  border-bottom: 2px solid #f0f0f0;
}

.section-title i {
  font-size: 20px;
}

/* Contact Section */
.contact-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.contact-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.contact-label {
  min-width: 140px;
  font-size: 14px;
  color: #666;
  font-weight: 500;
}

.contact-value {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 16px;
  color: #333;
  font-weight: 500;
}

.contact-actions {
  display: flex;
  gap: 8px;
}

.contact-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s;
  font-size: 16px;
}

.contact-btn.call {
  background: #e3f2fd;
  color: #1976d2;
}

.contact-btn.call:hover {
  background: #1976d2;
  color: white;
}

.contact-btn.whatsapp {
  background: #e8f5e9;
  color: #25d366;
}

.contact-btn.whatsapp:hover {
  background: #25d366;
  color: white;
}

.contact-btn.email {
  background: #fff3e0;
  color: #ff9800;
}

.contact-btn.email:hover {
  background: #ff9800;
  color: white;
}

/* Personal Information */
.personal-section {
  margin-top: 32px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 16px;
}

/*.info-item {*/
/*  display: flex;*/
  /*flex-direction: column;*/
/*  gap: 4px;*/
/*  padding: 12px;*/
/*  background: #f8f9fa;*/
/*  border-radius: 8px;*/
/*  border: 1px solid #eaeaea;*/
/*}*/

.info-label {
  font-size: 12px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 14px;
  color: #333;
  font-weight: 500;
}

/* Documents Section */
.documents-section {
  margin-top: 32px;
}

.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.document-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
}

.document-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
}

.document-icon {
  font-size: 32px;
  color: #667eea;
}

.document-name {
  font-size: 13px;
  font-weight: 500;
  color: #2c3e50;
  text-align: center;
}

.document-action {
  font-size: 16px;
  color: #999;
}

/* Footer */
.modal-footer {
  padding: 24px 32px;
  border-top: 1px solid #eaeaea;
  display: flex;
  justify-content: flex-end;
}

.btn-close-modal {
  padding: 12px 32px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-close-modal:hover {
  background: #5a6fd8;
  transform: translateY(-1px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

/* Responsive */
@media (max-width: 768px) {
  .owner-details-modal {
    max-height: 95vh;
  }
  
  .modal-header {
    padding: 20px;
  }
  
  .modal-body {
    padding: 20px;
  }
  
  .modal-title {
    font-size: 20px;
  }
  
  .contact-item {
    flex-direction: column;
    gap: 8px;
  }
  
  .contact-label {
    min-width: auto;
    width: 100%;
  }
  
  .contact-value {
    width: 100%;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .documents-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
.badge {
  padding: 4px 8px;
  border-radius: 16px;
  font-size: 11px;
  font-weight: 600;
}

.badge-primary {
  background: #0B0736;
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
  padding: 16px;
  margin-bottom: 12px;
}

.modal-section-title {
  font-size: 16px;
  font-weight: 700;
  color: #0B0736;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}

.modal-info-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.modal-info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

.modal-info-item:hover {
  border-color: #0B0736;
  background: white;
}

.modal-info-item.full-width {
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.modal-info-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  color: #555;
  font-size: 13px;
}

.modal-info-label i {
  color: #0B0736;
  font-size: 14px;
}

.modal-info-value {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
  color: #0B0736;
  font-size: 13px;
}

.address-text {
  line-height: 1.5;
  color: #555;
  font-size: 13px;
}

/* Action Buttons */
.btn-action-small {
  background: none;
  border: none;
  padding: 4px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 12px;
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
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  transition: all 0.3s ease;
}

.document-item:hover {
  border-color: #0B0736;
  transform: translateY(-1px);
}

.document-icon {
  width: 36px;
  height: 36px;
  background: #0B0736;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
}

.document-info {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.document-name {
  font-weight: 600;
  color: #0B0736;
  font-size: 13px;
}

.btn-view-document {
  background: #0B0736;
  color: white;
  border: none;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-view-document:hover {
  background: #0056b3;
}

/* No Documents */
.no-documents {
  background: #f8f9fa;
  border-radius: 12px;
  border: 2px dashed #dee2e6;
  padding: 30px 16px;
  text-align: center;
}

.no-documents-icon {
  font-size: 36px;
  color: #6c757d;
  margin-bottom: 8px;
}

.no-documents-text {
  color: #6c757d;
  margin: 0;
  font-size: 14px;
}

/* Modal Buttons */
.btn-modal {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  flex: 1;
  font-size: 13px;
}

.btn-modal-secondary {
  background: #6c757d;
  color: white;
}

.btn-modal-secondary:hover {
  background: #5a6268;
}

.btn-modal-primary {
  background: #0B0736;
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
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #e9ecef;
  background: #f8f9fa;
}

/* Property Actions */
.property-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  justify-content: space-between;
  
}

.btn-action {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-edit {
  background: #0B0736;
  color: white;
}

.btn-delete {
  background: #dc3545;
  color: white;
}

.btn-action:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* General Styles */
.property-title {
  font-size: 18px !important;
  font-weight: 600;
  color: #0B0736;
  margin-bottom: 12px;
  line-height: 1.2;
}

.text-center {
  text-align: center;
}

.spinner-border {
  width: 2rem;
  height: 2rem;
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
  flex-direction: column !important;
  align-items: left;
}

/* Comments Section Styles */
.comments-section {
  margin-top: 0;
}

.add-comment-form {
  background: white;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  overflow: hidden;
  margin-top: 5px;
}

.form-header {
  padding: 12px 16px;
}

.form-header h5 {
  margin: 0;
  color: #0B0736;
  font-size: 16px;
}

.form-body {
  padding: 16px;
}

.comment-input {
  position: relative;
}

.char-counter {
  position: absolute;
  bottom: 6px;
  right: 6px;
  font-size: 11px;
  color: #6c757d;
  background: white;
  padding: 2px 4px;
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
  font-size: 36px;
  color: #e9ecef;
  margin-bottom: 12px;
}

.comment-item {
  border: 1px solid #e9ecef;
  border-radius: 12px;
  margin-bottom: 12px;
  overflow: hidden;
}

.comment-item.has-replies {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}

.comment-main {
  padding: 16px;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
}

.user-avatar.small {
  width: 28px;
  height: 28px;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #0B0736;
  margin: 0;
  font-size: 13px;
}

.comment-date {
  font-size: 11px;
  color: #6c757d;
}

.comment-body {
  margin-bottom: 10px;
}

.comment-text {
  margin: 0;
  line-height: 1.5;
  color: #555;
  font-size: 13px;
}

.comment-actions {
  display: flex;
  gap: 8px;
}

.btn-action {
  background: none;
  border: none;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 3px;
}

.btn-reply {
  color: #0B0736;
  background: #f8f9fa;
}

.btn-reply:hover {
  background: #0B0736;
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
  margin-top: 10px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 8px;
}

.reply-actions {
  display: flex;
  gap: 6px;
  margin-top: 6px;
}

/* Replies Container */
.replies-container {
  background: #f8f9fa;
  border-top: 1px solid #e9ecef;
  padding: 12px 16px 12px 48px;
}

.reply-item {
  border: 1px solid #e9ecef;
  border-radius: 8px;
  background: white;
  margin-bottom: 8px;
}

.reply-item:last-child {
  margin-bottom: 0;
}

.reply-main {
  padding: 12px;
}

.reply-header {
  margin-bottom: 6px;
}

/* Login Prompt */
.login-prompt .alert {
  margin: 0;
  border-radius: 8px;
  font-size: 13px;
}

.comment-container{
  background: #f8f9fa;
  border-radius: 12px;
  padding: 16px;
  margin-top: 16px;
}

@media (max-width: 768px) {
  .specs-grid-main {
    gap: 6px;
    margin-top: 10px;
  }
  
  .spec-main-item {
    padding: 6px 8px;
    min-width: 80px;
  }
  
  .spec-main-value {
    font-size: 14px;
  }
  
  .spec-main-value i {
    font-size: 12px;
  }
  
  .spec-main-label {
    font-size: 10px;
  }
  
  .property-price {
    font-size: 20px !important;
  }
  
  .property-title {
    font-size: 14px !important;
  }
}

@media (max-width: 480px) {
  .specs-grid-main {
    gap: 4px;
  }
  
  .spec-main-item {
    padding: 4px 6px;
    min-width: 70px;
  }
  
  .spec-main-value {
    font-size: 12px;
  }
  
  .spec-main-label {
    font-size: 9px;
  }
}

/* Improved Responsive Design for Mobile */
@media (max-width: 768px) {
  .property-gallery {
  position: relative;
  height: auto;

}
  .agent-sidebar-card {
    position: relative;
    top: 0;
    margin-bottom: 16px;
    max-height: none;
  }
  
  .agent-profile {
    flex-direction: row;
    text-align: left;
    gap: 10px;
  }
  
  .agent-sidebar-avatar {
    width: 50px;
    height: 50px;
  }
  
  .agent-sidebar-info {
    flex: 1;
  }
  
  .agent-sidebar-name {
    font-size: 14px;
    margin-bottom: 4px;
  }
  
  .agent-sidebar-company {
    font-size: 12px;
    margin-bottom: 6px;
  }
  
  .btn-show-agent-details {
    font-size: 12px;
  }
  
  .sidebar-title {
    font-size: 14px;
  }

  .gallery-container {
    grid-template-columns: 1fr;
    grid-template-rows: 250px 70px;
    height: auto;
  }
  
  .side-images {
    flex-direction: row;
  }
  
  .side-image {
    height: 70px;
    flex: 1;
  }
  
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .lightbox-content {
    height: 95vh;
    margin: 8px;
  }
  
  .lightbox-main {
    padding: 8px;
  }
  
  .lightbox-nav {
    width: 36px;
    height: 36px;
    font-size: 18px;
  }
  
  .lightbox-thumbnails {
    padding: 10px 12px;
  }
  
  .lightbox-thumbnail {
    width: 50px;
    height: 40px;
  }
  
  .owner-details-modal {
    margin: 8px;
    max-height: 95vh;
  }
  
  .owner-profile-section {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }
  
  .documents-grid {
    grid-template-columns: 1fr;
  }
  
  .modal-info-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
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
    gap: 6px;
  }
  
  .comment-actions {
    flex-wrap: wrap;
  }
  
  .replies-container {
    padding-left: 16px;
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
    padding: 12px;
  }
  
  .agent-profile {
    gap: 8px;
  }
  
  .agent-sidebar-name {
    font-size: 13px;
  }
  
  .agent-sidebar-company {
    font-size: 11px;
  }
  
  .sidebar-title {
    font-size: 13px;
  }
  
  .unit-value,
  .info-item-sidebar span {
    font-size: 12px;
  }
  
  .unit-note,
  .approval-info,
  .status-text,
  .request-date,
  .cannot-request-status .status-text {
    font-size: 10px;
  }
  
  .btn-sidebar,
  .btn-show-owner-modal {
    font-size: 12px;
    padding: 8px 10px;
  }

  .gallery-container {
    grid-template-rows: 200px 60px;
  }
  
  .side-image {
    height: 60px;
  }
  
  .spec-main-item {
    padding: 8px;
  }
  
  .lightbox-header {
    padding: 10px 12px;
  }
  
  .lightbox-main {
    padding: 4px;
  }
  
  .owner-avatar {
    width: 60px;
    height: 60px;
  }
  
  .owner-name {
    font-size: 18px;
  }
  
  .modal-info-section {
    padding: 12px;
  }
  
  .modal-section-title {
    font-size: 14px;
  }
}

/* Additional Mobile Optimizations */
@media (max-width: 480px) {
  .agent-sidebar-card {
    padding: 10px;
  }
  
  .agent-sidebar-name {
    font-size: 12px;
  }
  
  .agent-sidebar-company {
    font-size: 10px;
  }
  
  .btn-show-agent-details {
    font-size: 11px;
  }
  
  .sidebar-title {
    font-size: 12px;
    margin-bottom: 8px;
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

@media (max-width: 768px) {
  .property-main-info {
    margin-bottom: 12px;
  }
  
  .info-section {
    margin-bottom: 16px;
  }
  
  .location-section {
    padding-bottom: 12px;
    margin-bottom: 12px;
  }
  
  .property-categories {
    gap: 4px;
    margin-top: 10px;
  }
  
  .category-tag {
    padding: 4px 8px;
    font-size: 10px;
  }
  
  .lightbox-content {
    margin: 4px;
    height: 95vh;
  }
  
  .lightbox-header {
    padding: 10px 12px;
  }
  
  .lightbox-main {
    padding: 6px;
  }
  
  .lightbox-thumbnails {
    padding: 8px 10px;
  }
}

@media (max-width: 768px) and (orientation: landscape) {
  .gallery-container {
    grid-template-rows: 150px 50px;
  }
  
  .agent-sidebar-card {
    max-height: 80vh;
  }
}
.btn-pdf {
  background: #dc3545;
  color: white;
}

.btn-pdf:hover {
  background: #c82333;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}
.btn-sidebar.btn-pdf {
  background: #dc3545;
  color: white;
  border: none;
  width: 100%;
  padding: 12px;
  font-size: 14px;
  font-weight: 600;
}

.btn-sidebar.btn-pdf:hover {
  background: #c82333;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

@media print {
  .pdf-header {
    background: #0B0736 !important;
    color: white !important;
    -webkit-print-color-adjust: exact;
  }
  
  .pdf-section {
    page-break-inside: avoid;
  }
  
  .pdf-image {
    max-width: 100% !important;
    height: auto !important;
  }
}
/* Sold Out Modal Styles */
.sold-out-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.option-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  background: white;
}

.option-card:hover {
  border-color: #0B0736;
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.option-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: #0B0736;
  flex-shrink: 0;
}

.option-card:nth-child(1) .option-icon {
  /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
}

.option-card:nth-child(2) .option-icon {
  /* background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); */
}

.option-content {
  flex: 1;
}

.option-content h5 {
  margin: 0 0 4px 0;
  font-weight: 600;
  color: #0B0736;
}

.option-content p {
  margin: 0;
  font-size: 13px;
  color: #6c757d;
}

.option-arrow {
  color: #6c757d;
  font-size: 20px;
}

.option-card:hover .option-arrow {
  color: #0B0736;
  transform: translateX(4px);
}
.property-actions-dropdown-wrapper {
  position: relative;
  /* z-index: 1000; */
  /* top: 10px; */

}

.property-actions-dropdown {
  position: relative;
}
.request-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.request-action-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.btn-request-compact {
display: flex;
    flex-direction: row;
    align-items: center;
    gap: 6px;
    padding: 8px;
    background: #0B0736;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s 
ease;
    width: 100%;
    /* min-height: 60px; */
    text-align: center;
    justify-content: center;
}

.btn-request-compact:hover:not(:disabled) {
  background: #001a57;
  transform: translateY(-2px);
}

.btn-request-compact:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-request-compact i {
  font-size: 16px;
}

.approved-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 8px;
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid #e9ecef;
  text-align: center;
  color: #000000;
}
.dropdown-menu .approved-info{
  background: none;
  border:none;
      flex-direction: row;


}
.request-actions-grid .info-display {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 0px !important;
}

.info-value {
  font-size: 11px;
  font-weight: 600;
  color: #0B0736;
}

.approval-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #28a745;
  font-size: 11px;
  font-weight: 600;
}

.pending-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  background: #fff3cd;
  /* border-radius: 8px; */
  border: 1px solid #ffeaa7;
  gap: 5px;
}
.dropdown-menu .pending-info{
    background: none;
  border: none;
}
.pending-status {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #856404;
  font-size: 11px;
}

.btn-cancel-small {
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 4px;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 10px;
}

.btn-view-small {
  background: #0B0736;
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

.cannot-request-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px;
  background: #f8d7da;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
  color: #721c24;
  font-size: 11px;
  text-align: center;
}

@media (max-width: 768px) {
  .request-actions-grid {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .btn-request-compact {
    min-height: 50px;
    padding: 10px 6px;
    font-size: 11px;
  }
}
.floor-plan-slider-image{
  max-height: 300px;
}
.lightbox-header-right{
  float: right;
}
.btn-success{
  background-color: #0B0736;
  border-color: #0B0736;
}
.btn-success:hover,.btn-success:active,.btn-success:focus{
  background-color: #733E87 !important;
  border-color: #733E87 !important;
}
.btn-success svg{
      margin-bottom: 4px;

}
.card-main{
  background: none !important;
}
.property-actions .btn {
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  font-size: 13px;
  border-radius: 8px;
}

.btn-chat-agent-inline {
  color: #fff;
  background: #0d6efd;
  border: none;
  cursor: pointer;
}
.btn-chat-agent-inline:hover {
  background: #0b5ed7;
  color: #fff;
}
.btn-chat-agent-inline i {
  font-size: 15px;
}
.dropdown-item-btn{
  display: flex;
  gap: 10px;
}

/* viewing */
.viewing-details {
  background: #e8f4fd;
  border-left: 4px solid #2196f3;
  padding: 12px;
  border-radius: 6px;
  margin-top: 8px;
}

.viewing-details-item {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  font-size: 12px;
}

.viewing-details-item i {
  color: #2196f3;
  width: 16px;
}

.request-time.viewing {
  display: block;
  font-size: 10px;
  color: #666;
  margin-top: 2px;
}

@media (max-width: 768px) {
  .modal-content {
    width: 95%;
    margin: 10px;
  }
  
  .time-slots-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
.bg-primary{
    background-color:#0B0736 !important;
}

@media (max-width: 580px) {

.property-main-info.mb-16 .specs-grid-main .spec-main-item{
    padding: 10px !important;
}
}
.image-overlay-right{
    right:16px;
   left:auto;
}
.sold-out-modal-title{
    color:#fff !important;
}
@media (max-width: 768px) {
  .card.card-main.p-0.radius-12.overflow-hidden .property-content .spec-main-value {
    font-size: 15px !important;
  }
}

@media (max-width: 480px) {
  .card.card-main.p-0.radius-12.overflow-hidden .property-content .spec-main-value {
    font-size: 14px !important;
  }
}
@media (min-width: 991px) and (max-width: 1226px) {
    .info-value{
        font-size: 10px;
    }
    .approved-info{
        padding: 5px;
    }
}

@media (min-width: 1227px) and (max-width: 1303px) {
    .info-value{
        font-size: 11px;
    }
    .approved-info{
        padding: 6px;
    }
}
.additional-field span{
margin: 0 2px;
}
.modal-header h4{
    font-size:20px !important;
}
.option-content h6{
        font-size:20px !important;
}
/* Rejection Alert Styles */
/* Rejection Alert Styles */
.rejection-alert-container {
  /*padding: 0 16px;*/
  margin-bottom: 16px;
}

.rejection-alert {
  background: #fff;
  border-left: 4px solid #dc3545;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  display:flex;
}

.rejection-alert-header {
  font-size: 16px;
  color: #dc3545;
  display: inline-flex;
  align-items: center;
  border-right: 2px solid #ffe0e0;
  padding-right: 16px;
  margin-right: 16px;
}

.rejection-alert-header i {
  font-size: 18px;
}

.rejection-alert-header strong {
  font-weight: 600;
}

.rejection-alert-body {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.rejection-reason-wrapper {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.rejection-reason-label {
  font-weight: 600;
  color: #721c24;
  margin: 0;
  font-size: 14px;
}

.rejection-reason-text {
  color: #721c24;
  font-size: 14px;
  margin: 0;
  font-weight: 500;
}

.rejection-meta {
  display: inline-flex;
  align-items: center;
}

.rejection-date {
  color: #6c757d;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.rejection-date i {
  font-size: 14px;
}

@media (max-width: 768px) {
  .rejection-alert {
    padding: 12px 16px;
  }
  
  .rejection-alert-header {
    display: flex;
    border-right: none;
    border-bottom: 1px solid #ffe0e0;
    padding-bottom: 8px;
    margin-bottom: 8px;
    padding-right: 0;
    margin-right: 0;
    width: 100%;
  }
  
  .rejection-alert-body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    width: 100%;
  }
  
  .rejection-reason-wrapper {
    flex-wrap: wrap;
  }
}

/* ── Floor Plans section grid (added in Property Details) ── */
.floor-plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}

.floor-plan-card {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
}
.floor-plan-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
}
.floor-plan-card-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  background: #f1f5f9;
  overflow: hidden;
}
.floor-plan-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.floor-plan-card-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 28px;
}
.floor-plan-card-overlay {
  position: absolute;
  inset: 0;
  background: rgba(11, 7, 54, 0);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 24px;
  opacity: 0;
  transition: background 0.2s ease, opacity 0.2s ease;
}
.floor-plan-card:hover .floor-plan-card-overlay {
  background: rgba(11, 7, 54, 0.45);
  opacity: 1;
}
.floor-plan-card-name {
  padding: 10px 12px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #0B0736;
  border-top: 1px solid rgba(15, 23, 42, 0.06);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Icon used next to each Additional Feature row label */
.feature-row-icon {
  color: #16a34a;
  font-size: 0.9rem;
}

/* Features section: the "Features" title runs left, and selected amenity
   pills wrap to fill the same line, dropping to extra rows as needed. */
.features-section-title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.features-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-left: 4px;
}
.feature-pill {
  display: inline-flex;
  align-items: center;
  background: #eef2ff;
  color: #1e3a8a;
  border: 1px solid #c7d2fe;
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1;
  white-space: nowrap;
}
.feature-pill i {
  color: #16a34a;
  font-size: 0.8rem;
}
.info-item .full-width{
  flex-direction: column !important;
}
:deep(.flex-column){
  flex-direction: column !important;

}
</style>
<style>
.property-show-inner{
  padding:0 1rem 1rem 0 !important;
}
.flex-column{
  flex-direction: column !important;

}
</style>