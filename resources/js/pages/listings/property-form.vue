<template>
  <div class="row gy-4 mt-2">

    <!-- 🏡 Property Details -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Details</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-md-4">
              <label class="form-label">Sale or Rent</label>
              <v-select 
                v-model="form.saleOrRent" 
                :options="saleRentOptions" 
                placeholder="Select Sale or Rent"
              />
            </div>
            <div class="col-md-4">
              <label class="form-label">Hot Deal</label>
              <v-select 
                v-model="form.is_hot_deal" 
                :options="hotDealOptions" 
                placeholder="Select Hot Deal"
              />
            </div>
            
            <!-- Rented Status -->
            <!--<div class="col-md-4">-->
            <!--  <label class="form-label">Rented Status</label>-->
            <!--  <v-select -->
            <!--    v-model="form.rented_status" -->
            <!--    :options="rentedStatusOptions" -->
            <!--    placeholder="Select Rented Status"-->
            <!--  />-->
             
            <!--</div>-->

            <!-- Rented Until -->
            <!--<div class="col-md-4" v-if="form.rented_status === 'Rented' ">-->
            <!--  <label class="form-label">Rented Until</label>-->
            <!--  <input -->
            <!--    v-model="form.rented_until" -->
            <!--    type="date" -->
            <!--    class="form-control" -->
            <!--    placeholder="Select date"-->
            <!--  />-->
            <!--</div>-->

            <div class="col-md-4">
              <label class="form-label">Property Type</label>
              <v-select 
                v-model="form.property_type" 
                :options="propertyTypes" 
                label="name" 
                placeholder="Select Property Type"
                :disabled="isLoadingPropertyTypes"
              />
              <div v-if="isLoadingPropertyTypes" class="text-muted small mt-1">Loading property types...</div>
            </div>
            
            <div class="col-md-4">
              <label class="form-label">Completion Status</label>
              <v-select 
                v-model="form.completionStatus" 
                :options="completionStatusOptions" 
                placeholder="Select Completion Status"
              />
            </div>
            
            <!-- Payment Plan -->
              <div class="col-md-4">
            <label class="form-label">Payment Plans</label>
            <v-select 
              v-model="form.payment_plans" 
              :options="paymentPlanOptions"
              multiple
              placeholder="Select Payment Plans"
              :clearable="true"
              :close-on-select="false"
              :searchable="true"
            >
              <template #selected-option-container="{ option, deselect }">
                <div class="selected-tag">
                  {{ option.label || option }}
                  <button @click="deselect(option)" class="tag-close">
                    ×
                  </button>
                </div>
              </template>
            </v-select>
            <div class="text-muted small mt-1">
              <small>You can select multiple payment plans</small>
            </div>
          </div>
            
            <div class="col-md-4">
              <label class="form-label">Project 
                <span v-if="!selectedProject" class="text-danger">*</span></label>
              <v-select 
                v-model="selectedProject" 
                :options="projects" 
                label="name" 
                placeholder="Select Project"
                :disabled="isLoadingProjects"
                :clearable="true"
              >
                <template #option="{ name, area }">
                  <div class="d-flex flex-column">
                    <strong>{{ name }}</strong>
                    <small class="text-muted" v-if="area">
                      {{ area.name || area.area_parents_title }}
                    </small>
                  </div>
                </template>
              </v-select>
              <div v-if="isLoadingProjects" class="text-muted small mt-1">Loading projects...</div>
            </div>
           
            <!-- Area -->
            <div class="col-md-4">
              <label class="form-label">Address 
                <span v-if="!selectedProject" class="text-danger">*</span>
              </label>
              <v-select
                v-model="form.area"
                :options="filteredAreas"
                label="name"
                :placeholder="getAreaPlaceholder()"
                :disabled="isLoadingAreas"
                :class="{ 'project-areas': selectedProject }"
              />
              <div v-if="isLoadingAreas" class="text-muted small mt-1">Loading areas...</div>
              
              <div v-if="selectedProject && form.projectAreas.length === 0" class="text-warning small mt-1">
                <i class="fas fa-exclamation-circle"></i>
                No specific areas found for this project. Please select from general areas.
              </div>
            </div>
        
            <!--<div class="col-md-4">-->
            <!--  <label class="form-label">Unit Number</label>-->
            <!--  <input v-model="form.unit_number" type="text" class="form-control" placeholder="Enter unit number" />-->
            <!--</div>-->
            <div class="col-md-4">
              <label class="form-label">Unit Number</label>
              <input 
                v-model="form.unit_number" 
                type="text" 
                class="form-control" 
                :class="{ 'is-invalid': unitNumberError }"
                placeholder="Enter unit number" 
                @blur="validateUnitNumber"
                @input="clearUnitNumberError"
              />
              <div v-if="isLoadingUnitNumber" class="text-muted small mt-1">
                <i class="fas fa-spinner fa-spin me-1"></i>
                Checking unit number...
              </div>
              <div v-if="unitNumberError" class="invalid-feedback d-block">
                <i class="fas fa-exclamation-circle me-1"></i>
                {{ unitNumberError }}
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Price</label>
             <input
              v-model="form.price"
              @input="form.price = form.price.replace(/[^0-9]/g, '')"
              type="text"
              inputmode="numeric"
              class="form-control"
              placeholder="Enter price"
            />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 🏠 Unit Specifications -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Unit Specifications</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-md-3">
              <label class="form-label">Number of Bedrooms</label>
              <v-select
                v-model="form.number_of_bedrooms"
                :options="bedroomOptions"
                placeholder="Select bedrooms"
                :reduce="option => option.value"
                label="label"
              />
            </div>

            <div class="col-md-3">
              <label class="form-label">Number of Bathrooms</label>
              <v-select
                v-model="form.number_of_bathrooms"
                :options="bathroomOptions"
                placeholder="Select bathrooms"
                :reduce="option => option.value"
                label="label"
              />
            </div>
           
            <div class="col-md-3">
              <label class="form-label">Size (sqm)</label>
              <input
                v-model.number="form.size_sqmt" placeholder="Enter Size (sqm)"
                type="number"
                class="form-control"
                @blur="convertSqmToSqft"
              />
            </div>

            <div class="col-md-3">
              <label class="form-label">Size (sqft)</label>
              <input
                v-model.number="form.size_sqft"
                type="number"
                class="form-control"
                @blur="convertSqftToSqm" placeholder="Enter Size (sqft)"
              />
            </div>

            <div class="col-md-12">
              <label class="form-label">Note</label>
              <textarea v-model="form.comment" rows="3" class="form-control" placeholder="Write notes..."></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 💰 Mortgage & Rent Info -->
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header">
              <h6 class="card-title mb-0">Mortgage & Rent Info</h6>
            </div>
            <div class="card-body">
              <div class="row gy-3">
                <div class="col-md-4">
                  <label class="form-label">Mortgage Status</label>
                  <v-select 
                    v-model="form.mortgageStatus" 
                    :options="mortgageStatusOptions" 
                    placeholder="Select Mortgage Status"
                  />
                </div>
    
                <div class="col-md-4">
                  <label class="form-label">Mortgage Amount</label>
                  <input v-model="form.mortgageAmount" type="number" class="form-control" placeholder="Enter Mortgage Amount"/>
                </div>
    
                <!-- Force new row on desktop so first line only has Mortgage fields -->
                <div class="w-100 d-none d-md-block"></div>
    
                <div class="col-md-4">
                  <label class="form-label">Occupancy Status</label>
                  <v-select 
                    v-model="form.occupancyStatus" 
                    :options="occupancyStatusOptions" 
                    placeholder="Select Occupancy Status"
                  />
                </div>
    
                <div class="col-md-4">
                  <label class="form-label">Rent Expiry Date</label>
                  <input v-model="form.rentExpiryDate" type="date" class="form-control" placeholder="Enter Rent Expiry Date" />
                </div>
    
                <div class="col-md-4">
                  <label class="form-label">Rent Amount</label>
                  <input v-model="form.rentAmount" type="number" class="form-control" placeholder="Enter Rent Amount" />
                </div>
    
                <div class="col-md-12">
                  <label class="form-label">Comment</label>
                  <textarea v-model="form.mortgageComment" rows="3" class="form-control" placeholder="Enter Mortage Comment" ></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

    <!-- 🖼️ Gallery Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Gallery</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
           <!--<div class="col-12">-->
           <!--   <label class="form-label">Google Drive Link (Optional)</label>-->
           <!--   <div class="input-group">-->
           <!--     <span class="input-group-text">-->
           <!--       <i class="fab fa-google-drive"></i>-->
           <!--     </span>-->
           <!--     <input -->
           <!--       v-model="form.driveLink" -->
           <!--       type="url" -->
           <!--       class="form-control" -->
           <!--       placeholder="https://drive.google.com/drive/folders/..."-->
           <!--     />-->
           <!--   </div>-->
           <!--   <div class="text-muted small mt-1">-->
           <!--         <small>You can add a Google Drive link containing additional property images</small>-->
           <!--     </div>-->
    
           <!-- </div>-->
            <div class="col-12">
              <label class="form-label">Upload Property Images</label>
              <input 
                type="file" 
                class="form-control" 
                multiple 
                @change="handleGalleryUpload" 
                accept="image/*"
                ref="galleryInput"
              />
              <div class="text-muted small mt-1">
                You can choose multiple images (PNG, JPG, JPEG, SVG, WebP). Max 10MB per image.
                <strong>First image will be set as the hero hero image.</strong>
              </div>
            </div>

            <!-- Gallery Preview -->
            <div class="col-12" v-if="form.gallery.length > 0">
              <label class="form-label mb-3">Gallery Preview</label>
              <div class="row g-3">
                <div 
                  v-for="(item, index) in form.gallery" 
                  :key="index"
                  class="col-xl-3 col-lg-4 col-md-6"
                >
                  <div class="gallery-item position-relative" :class="{ 'hero-image': index === 0 }">
                    <div class="card h-100 border-primary" v-if="index === 0">
                      <div class="card-header bg-primary text-white py-1 text-center">
                        <small><i class="fas fa-star me-1"></i> Hero Image</small>
                      </div>
                      <img 
                        :src="item.preview || getImagePreview(item.file || item)" 
                        class="card-img-top gallery-image" 
                        alt="Gallery image"
                        style="height: 200px; object-fit: cover;"
                        @error="handleImageError"
                      />
                      <div class="card-body p-3">
                        <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                        <p class="card-text small text-muted">{{ formatFileSize(item.size || item.file?.size) }}</p>
                      </div>
                      <button 
                        type="button" 
                        class="btn-close position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                        @click="removeGalleryImage(index)"
                        style="--bs-bg-opacity: 0.8;"
                      ></button>
                    </div>
                    
                    <div class="card h-100" v-else>
                      <img 
                        :src="item.preview || getImagePreview(item.file || item)" 
                        class="card-img-top gallery-image" 
                        alt="Gallery image"
                        style="height: 200px; object-fit: cover;"
                        @error="handleImageError"
                      />
                      <div class="card-body p-3">
                        <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                        <p class="card-text small text-muted">{{ formatFileSize(item.size || item.file?.size) }}</p>
                        <div class="d-flex gap-1 mt-2">
                          <button 
                            type="button" 
                            class="btn btn-sm btn-outline-primary"
                            @click="setAsHeroImage(index)"
                            title="Set as hero image"
                          >
                            Set as hero image
                          </button>
                        </div>
                      </div>
                      <button 
                        type="button" 
                        class="btn-close position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                        @click="removeGalleryImage(index)"
                        style="--bs-bg-opacity: 0.8;"
                      ></button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div class="col-12" v-else>
              <div class="text-center py-5 border rounded bg-light">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No images uploaded yet. Add some photos to showcase your property!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Floor Plans Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Floor Plans</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-12">
              <label class="form-label">Upload Floor Plans</label>
              <input 
                type="file" 
                class="form-control" 
                multiple 
                @change="handleFloorPlanUpload" 
                accept="image/*"
                ref="floorPlanInput"
              />
              <div class="text-muted small mt-1">
                You can choose multiple floor plan images (PNG, JPG, JPEG, SVG, WebP). Max 10MB per image.
              </div>
            </div>

            <!-- Floor Plans Preview -->
            <div class="col-12" v-if="form.floorPlans.length > 0">
              <label class="form-label mb-3">Floor Plans Preview</label>
              <div class="row g-3">
                <div 
                  v-for="(item, index) in form.floorPlans" 
                  :key="index"
                  class="col-xl-3 col-lg-4 col-md-6"
                >
                  <div class="floor-plan-item position-relative">
                    <div class="card h-100">
                      <img 
                        :src="item.preview || getImagePreview(item.file || item)" 
                        class="card-img-top floor-plan-image" 
                        alt="Floor plan image"
                        style="height: 200px; object-fit: cover;"
                        @error="handleImageError"
                      />
                      <div class="card-body p-3">
                        <div class="mb-2">
                          <label class="form-label small">Plan Name</label>
                          <input 
                            v-model="item.customName" 
                            type="text" 
                            class="form-control form-control-sm" 
                            placeholder="Enter plan name"
                            @change="updateFloorPlanName(index, $event)"
                          />
                        </div>
                        <p class="card-text small text-truncate mb-1">{{ item.name || item.file?.name }}</p>
                        <p class="card-text small text-muted">{{ formatFileSize(item.size || item.file?.size) }}</p>
                      </div>
                      <button 
                        type="button" 
                        class="btn-close position-absolute top-0 end-0 m-2 bg-danger rounded-circle p-1"
                        @click="removeFloorPlan(index)"
                        style="--bs-bg-opacity: 0.8;"
                      ></button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div class="col-12" v-else>
              <div class="text-center py-5 border rounded bg-light">
                <i class="fas fa-blueprint fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No floor plans uploaded yet. Add floor plans to showcase your property layout!</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 📄 Property Documents Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Property Documents</h6>
        </div>
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-md-4">
              <label class="form-label">SPA Document</label>
              <input
                type="file"
                class="form-control"
                accept=".pdf,.jpg,.jpeg,.png,.svg"
                @change="handlePropertyDocumentUpload($event, 'spa_document')"
              />
              <div v-if="form.spa_document" class="small text-muted mt-1 d-flex align-items-center justify-content-between gap-2">
                <span class="text-truncate">{{ form.spa_document.name }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="removePropertyDocument('spa_document')">
                  Remove
                </button>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Other Document</label>
              <input
                type="file"
                class="form-control"
                accept=".pdf,.jpg,.jpeg,.png,.svg"
                @change="handlePropertyDocumentUpload($event, 'other_document')"
              />
              <div v-if="form.other_document" class="small text-muted mt-1 d-flex align-items-center justify-content-between gap-2">
                <span class="text-truncate">{{ form.other_document.name }}</span>
                <button type="button" class="btn btn-sm btn-outline-danger" @click="removePropertyDocument('other_document')">
                  Remove
                </button>
              </div>
            </div>

            <div class="col-12">
              <div class="text-muted small">
                Allowed types: PDF, JPG, JPEG, PNG, SVG. Max 10MB per file.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Owner Section -->
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header">
          <h6 class="card-title mb-0">Owner Details</h6>
        </div>
        <div class="card-body">
          <div class="row align-items-end gy-3">
            <div class="col-md-6 col-sm-8">
              <label class="form-label">Select Owner</label>
              <v-select
                v-model="selectedOwner"
                :options="owners"
                label="full_name"
                placeholder="Search by phone or email"
                :filterable="true"
                :filter-by="customOwnerFilter"
                :disabled="isLoadingOwners"
                :reduce="owner => owner"
              >
                <template #option="{ full_name, email, phone_number, whatsapp_number, second_phone_number }">
                  <div class="d-flex flex-column">
                    <strong>{{ full_name }}</strong>
                    <small class="text-muted">
                      {{ email }}
                      <span v-if="phone_number || whatsapp_number || second_phone_number">
                        | {{ phone_number || whatsapp_number || second_phone_number }}
                      </span>
                    </small>
                  </div>
                </template>
                
                <template #selected-option="{ full_name, email, phone_number }">
                  <div>
                    {{ full_name }}
                    <small class="text-muted ms-2">
                      {{ email || phone_number }}
                    </small>
                  </div>
                </template>
              </v-select>
              <div v-if="isLoadingOwners" class="text-muted small mt-1">Loading owners...</div>
            </div>
            <div class="col-md-3 col-sm-4">
              <button v-if="proxy.$hasPermission('owners-create')" class="btn btn-primary w-100 mt-3 mt-md-0" @click="showAddOwner = true">
                + Add New Owner
              </button>
            </div>
          </div>
        </div>
        
        <div class="card-footer text-center footer-pt">
          <div class="d-flex gap-2 justify-content-center">
            <button
              type="button"
              class="btn btn-outline-secondary"
              @click="handleSubmit('draft')"
              :disabled="isSubmitting"
            >
              <i class="fas fa-save me-1"></i>
              Save as Draft
            </button>
            <button
              type="button"
              class="btn btn-outline-primary"
              @click="handleSubmit('preview')"
              :disabled="isSubmitting"
            >
              <i class="fas fa-eye me-1"></i>
              Preview
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="handleSubmit('publish')"
              :disabled="isSubmitting"
            >
              <i class="fas fa-paper-plane me-1"></i>
              Publish Listing
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Add Owner Modal -->
    <div v-if="showAddOwner" class="modal-backdrop">
      <div class="modal-container">
        <!-- Modal Header -->
        <div class="modal-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
          </div>
          <button class="btn-close" @click="showAddOwner = false">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body">
          
          <!-- Section 1: Personal Information -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-id-card"></i>
              <span>Personal Information</span>
              <span class="badge badge-primary ms-2">Required</span>
            </div>
            
            <div class="row">
              <!-- Salutation -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">Salutation</label>
                <v-select 
                  v-model="newOwner.salutation" 
                  :options="salutationOptions" 
                  placeholder="Select Salutation"
                  :clearable="true"
                />
              </div>

              <!-- First Name -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">First Name</label>
                <input 
                  v-model="newOwner.first_name" 
                  type="text" 
                  class="form-control" 
                  placeholder="Enter First Name"
                  required
                  @input="filterNameInput('first_name')"
                />
              </div>

              <!-- Last Name -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label required">Last Name</label>
                <input 
                  v-model="newOwner.last_name" 
                  type="text" 
                  class="form-control" 
                  placeholder="Enter Last Name"
                  required
                  @input="filterNameInput('last_name')"
                />
              </div>

              <!-- Email -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label ">Email Address</label>
                <input 
                  v-model="newOwner.email" 
                  type="email" 
                  class="form-control" 
                  placeholder="Enter Email"
                />
              </div>
            </div>
          </div>

          <!-- Section 2: Contact Information -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-phone-alt"></i>
              <span>Contact Information</span>
              <span class="badge badge-primary ms-2">Required</span>
            </div>
            
            <div class="row">
              <!-- Primary Phone -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label required">Primary Phone</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.phone_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter Phone Number"
                    required
                    @input="filterNumberInput('phone_number')"
                  />
                </div>
              </div>

              <!-- WhatsApp -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label">WhatsApp Number</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.whatsapp_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter WhatsApp"
                    @input="filterNumberInput('whatsapp_number')"
                  />
                </div>
              </div>

              <!-- Secondary Phone -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label">Secondary Phone</label>
                <div class="input-group">
                  <input 
                    v-model="newOwner.second_phone_number" 
                    type="tel" 
                    class="form-control" 
                    placeholder="Enter Second Phone"
                    @input="filterNumberInput('second_phone_number')"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Nationality & Residence -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-globe"></i>
              <span>Nationality & Residence</span>
            </div>
            
            <div class="row">
              <!-- Nationality -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">Nationality</label>
                <v-select 
                  v-model="newOwner.nationality" 
                  :options="nationalities" 
                  placeholder="Select Nationality" 
                  @update:modelValue="handleNationalityChange"
                  :clearable="true"
                />
              </div>

              <!-- Residency Status -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">Residency Status</label>
                <v-select
                  v-model="newOwner.residency_status" 
                  :options="residencyStatusOptions"
                  :reduce="option => option.value"
                  placeholder="Select Residency Status"
                  :disabled="newOwner.nationality === 'UAE'"
                  :clearable="true"
                />
              </div>

              <!-- Location -->
              <div class="col-md-4 col-sm-6">
                <label class="form-label ">{{ getLocationLabel() }}</label>
                <v-select
                  v-model="newOwner.location_id" 
                  :options="locations"
                  :reduce="location => location.id"
                  :label="'name'"
                  :placeholder="getLocationPlaceholder()"
                  :disabled="!newOwner.residency_status"
                  :loading="isLoadingLocations"
                  :clearable="true"
                >
                  <template #option="location">
                    <div class="d-flex align-items-center gap-2">
                      <i class="fas fa-map-marker-alt text-primary"></i>
                      <span>{{ location.name }}</span>
                    </div>
                  </template>
                </v-select>
              </div>
            </div>
          </div>

          <!-- Section 4: Document Upload -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-file-upload"></i>
              <span>Document Upload</span>
              <span class="badge badge-warning ms-2">Optional</span>
            </div>
            
            <div class="row">
              <!-- ID Front -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">ID Front Copy</label>
                <div class="file-upload-area" @click="$refs.idFront.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-id-card"></i>
                  </div>
                  <div class="file-upload-text">Upload ID Front</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="idFront"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'id_front')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.id_front" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.id_front.name }}
                  </span>
                </div>
              </div>

              <!-- ID Back -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">ID Back Copy</label>
                <div class="file-upload-area" @click="$refs.idBack.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-id-card"></i>
                  </div>
                  <div class="file-upload-text">Upload ID Back</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="idBack"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'id_back')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.id_back" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.id_back.name }}
                  </span>
                </div>
              </div>

              <!-- Visa Copy -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">Visa Copy</label>
                <div class="file-upload-area" @click="$refs.visaCopy.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-passport"></i>
                  </div>
                  <div class="file-upload-text">Upload Visa</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="visaCopy"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'visa_copy')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.visa_copy" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.visa_copy.name }}
                  </span>
                </div>
              </div>

              <!-- Passport Copy -->
              <div class="col-md-3 col-sm-6">
                <label class="form-label">Passport Copy</label>
                <div class="file-upload-area" @click="$refs.passportCopy.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-passport"></i>
                  </div>
                  <div class="file-upload-text">Upload Passport</div>
                  <div class="file-upload-hint">Max 10MB • JPG, PNG, PDF</div>
                  <input 
                    ref="passportCopy"
                    type="file" 
                    class="d-none" 
                    @change="handleNewOwnerFile($event, 'passport_copy')" 
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.passport_copy" class="mt-2">
                  <span class="badge badge-success">
                    <i class="fas fa-check me-1"></i>
                    {{ newOwner.passport_copy.name }}
                  </span>
                </div>
              </div>

              <!-- Additional Documents -->
              <div class="col-12 mt-3">
                <label class="form-label">Additional Documents</label>
                <div class="file-upload-area" @click="$refs.additionalOwnerDocs.click()">
                  <div class="file-upload-icon">
                    <i class="fas fa-file-alt"></i>
                  </div>
                  <div class="file-upload-text">Upload Additional Documents</div>
                  <div class="file-upload-hint">Max 5MB each • JPG, PNG, PDF • Multiple files allowed</div>
                  <input
                    ref="additionalOwnerDocs"
                    type="file"
                    class="d-none"
                    multiple
                    @change="handleNewOwnerAdditionalFiles"
                    accept=".jpg,.jpeg,.png,.pdf"
                  />
                </div>
                <div v-if="newOwner.additional_documents && newOwner.additional_documents.length" class="mt-2">
                  <span
                    v-for="(file, index) in newOwner.additional_documents"
                    :key="index"
                    class="badge badge-info me-1 mb-1 d-inline-flex align-items-center"
                  >
                    <i class="fas fa-file me-1"></i>
                    <span class="text-truncate" style="max-width: 180px;">{{ file.name }}</span>
                    <button
                      type="button"
                      class="btn-close btn-close-white btn-sm ms-2"
                      @click.stop="removeNewOwnerAdditionalFile(index)"
                    ></button>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 5: Additional Notes -->
          <div class="section">
            <div class="section-title">
              <i class="fas fa-sticky-note"></i>
              <span>Additional Notes</span>
              <span class="badge badge-warning ms-2">Optional</span>
            </div>
            
            <div class="col-md-12">
              <label class="form-label">Owner Notes</label>
              <textarea 
                v-model="newOwner.notes" 
                rows="4" 
                class="form-control" 
                placeholder="Add any additional notes about the owner..."
              ></textarea>
              <div class="text-muted mt-2">
                <small>Add any important information that might be useful for future reference.</small>
              </div>
            </div>
          </div>

        </div>

        <!-- Modal Footer -->
        <div class="modal-footer">
          <div class="d-flex justify-content-between w-100">
            <div>
              <button class="btn btn-outline-primary" @click="resetNewOwnerForm">
                <i class="fas fa-redo me-2"></i>
                Reset Form
              </button>
            </div>
            <div class="d-flex gap-3">
              <button class="btn btn-secondary" @click="showAddOwner = false">
                <i class="fas fa-times me-2"></i>
                Cancel
              </button>
              <button 
                class="btn btn-primary" 
                @click="submitNewOwner"
                :disabled="isSubmitting"
              >
                <i class="fas fa-save me-2"></i>
                <span v-if="isSubmitting">
                  <span class="spinner-border spinner-border-sm me-2"></span>
                  Saving...
                </span>
                <span v-else>Save Owner</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed, getCurrentInstance } from "vue";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
const { proxy } = getCurrentInstance();

const saleRentOptions = ['Sale', 'Rent'];
const rentedStatusOptions = ['Available', 'Rented']; 
const paymentPlanOptions = [
  '50/50', '40/60', '80/20', '15/85', '65/35', '60/40',
  '20/80', '35/65', '10/90', '55/45', '45/55', '70/30',
  '30/70', '25/75', '75/25', '10/1% Monthly', '20/1% Monthly',
  '30/1% Monthly', '85/15', '15/85', '90/10',
  '10% down payment, 8-year installments'
];
const completionStatusOptions = ['Completed', 'Under Construction'];
const furnishedStatusOptions = ['Furnished', 'Unfurnished'];
const ownershipTypeOptions = ['freehold', 'leasehold'];
const mortgageStatusOptions = ['Mortgage', 'Non Mortgage'];
const occupancyStatusOptions = ['Occupied', 'Vacant'];
const salutationOptions = ['Mr', 'Mrs', 'Ms'];
const residencyStatusOptions = [
  { value: 'resident', label: 'Resident' },
  { value: 'non_resident', label: 'Non Resident' }
];

const projects = ref([]);
const isLoadingProjects = ref(false);
const selectedProject = ref(null);
const owners = ref([]);
const selectedOwner = ref(null);
const showAddOwner = ref(false);
const isLoadingOwners = ref(false);
const unitViews = ref([]);
const isLoadingUnitViews = ref(false);
const layoutTypes = ref([]);
const isLoadingLayoutTypes = ref(false);
const propertyTypes = ref([]);
const isLoadingPropertyTypes = ref(false);
const developers = ref([]);
const isLoadingDevelopers = ref(false);
const areas = ref([]);
const isLoadingAreas = ref(false);
const newOwner = ref({
  salutation: "", first_name: "", last_name: "", email: "",
  phone_number: "", whatsapp_number: "", second_phone_number: "",
  nationality: "", residency_status: "", location_id: "",
  id_front: null, id_back: null, visa_copy: null, passport_copy: null, notes: "",
  additional_documents: [],
});
const locations = ref([]);
const isLoadingLocations = ref(false);
const isSubmitting = ref(false);
const hotDealOptions = ['Yes', 'No'];

const form = ref({
  title: "", unit_number: "", ownership_type: null, saleOrRent: "",
  completionStatus: "", area: null, developer: null, property_type: null,
  price: "", number_of_bedrooms: "", number_of_bathrooms: "",
  layout_type: null, unit_view: null, furnished_status: "",
  size_sqmt: "", size_sqft: "", floorPlans: [], gallery: [],
  comment: "", mortgageStatus: "", occupancyStatus: "",
  mortgageAmount: "", rentExpiryDate: "", rentAmount: "",
  mortgageComment: "", projectAreas: [], rented_status: "",      
  rented_until: "", payment_plan: "", payment_plans: [] ,driveLink: "", is_hot_deal: "",
  spa_document: null, desk_document: null, other_document: null,
});

const isLoadingUnitNumber = ref(false);
const unitNumberError = ref("");

watch(() => [form.value.unit_number, selectedProject.value, form.value.saleOrRent], 
  ([newUnitNumber, newProject, newStatus], [oldUnitNumber, oldProject, oldStatus]) => {
  if ((newProject !== oldProject || newStatus !== oldStatus) && newUnitNumber) {
    setTimeout(() => {
      validateUnitNumber();
    }, 500);
  }
}, { deep: true });
watch(() => form.value.payment_plans, (newValue) => {
  if (newValue && newValue.length > 0) {
    form.value.payment_plan = JSON.stringify(newValue.map(item => item.value || item));
  } else {
    form.value.payment_plan = null;
  }
}, { deep: true });

watch(() => form.value.saleOrRent, (newValue) => {
  if (newValue === 'Sale') {
    form.value.rented_status = "";
    form.value.rented_until = "";
  }
});

watch(() => newOwner.value.residency_status, async (newStatus, oldStatus) => {
  if (newOwner.value.nationality === 'UAE') return;
  if (newStatus !== oldStatus) {
    newOwner.value.location_id = "";
    if (newStatus) await fetchLocations(newStatus);
    else locations.value = [];
  }
});

watch(() => selectedProject.value, async (newProject, oldProject) => {
  if (newProject) {
    try {
      const response = await api.get(`/listings/projects/${newProject.id}/areas`);
      const projectAreasData = response.data.data || response.data;
      form.value.projectAreas = projectAreasData.map(area => ({
        id: area.id,
        name: area.area_parents_title || area.name || area.title,
        project_id: newProject.id
      }));
      form.value.area = null;
      console.log(`✅ Loaded ${form.value.projectAreas.length} areas for project:`, newProject.name);
    } catch (error) {
      console.error('❌ Error fetching project areas:', error);
      form.value.projectAreas = [];
      proxy.$showNotification("⚠️ Could not load project areas. Using general areas.", "warning");
    }
  } else {
    form.value.projectAreas = [];
    form.value.area = null;
  }
});

// 6. computed properties
const agentId = computed(() => {
  try {
    const userData = localStorage.getItem('user');
    return userData ? JSON.parse(userData).id : null;
  } catch (error) {
    console.error('❌ Error parsing user data:', error);
    return null;
  }
});

const number_of_bedrooms = computed({
  get: () => form.value.number_of_bedrooms,
  set: (val) => {
    if (!val) form.value.number_of_bedrooms = '';
    else if (typeof val === 'object' && 'value' in val) form.value.number_of_bedrooms = parseInt(val.value) || '';
    else form.value.number_of_bedrooms = parseInt(val) || '';
  }
});

const number_of_bathrooms = computed({
  get: () => form.value.number_of_bathrooms,
  set: (val) => {
    if (!val) form.value.number_of_bathrooms = '';
    else if (typeof val === 'object' && 'value' in val) form.value.number_of_bathrooms = parseInt(val.value) || '';
    else form.value.number_of_bathrooms = parseInt(val) || '';
  }
});

const filteredAreas = computed(() => {
  if (selectedProject.value && form.value.projectAreas.length > 0) {
    return form.value.projectAreas;
  }
  return areas.value.filter(area => area.children_count === 0);
});

const fetchProjects = async () => {
  try {
    isLoadingProjects.value = true;
    const response = await api.get("/listings/projects");
    const projectsData = response.data.data || response.data;
    projects.value = projectsData.map(project => ({
      id: project.id,
      name: project.title || project.name,
      area: project.area,
      area_id: project.area_id
    }));
    console.log('✅ Projects loaded:', projects.value);
  } catch (error) {
    console.error("❌ Error fetching projects:", error);
    proxy.$showNotification("❌ Failed to load projects.", "error");
  } finally {
    isLoadingProjects.value = false;
  }
};

const fetchLayoutTypes = async () => {
  try {
    isLoadingLayoutTypes.value = true;
    const response = await api.get("/listings/layout_types");
    const layoutTypesData = response.data.data || response.data;
    layoutTypes.value = layoutTypesData.map(layout => ({
      id: layout.id,
      name: layout.name || layout.layout_name || layout.title
    }));
    console.log('✅ Layout types loaded:', layoutTypes.value);
  } catch (error) {
    console.error("❌ Error fetching layout types:", error.response || error);
    proxy.$showNotification("❌ Failed to load layout types.", "error");
  } finally {
    isLoadingLayoutTypes.value = false;
  }
};

const fetchUnitViews = async () => {
  try {
    isLoadingUnitViews.value = true;
    const response = await api.get("/listings/unit_views");
    const unitViewsData = response.data.data || response.data;
    unitViews.value = unitViewsData.map(view => ({
      id: view.id,
      name: view.name || view.view_name || view.title
    }));
    console.log('✅ Unit views loaded:', unitViews.value);
  } catch (error) {
    console.error("❌ Error fetching unit views:", error.response || error);
    proxy.$showNotification("❌ Failed to load unit views.", "error");
  } finally {
    isLoadingUnitViews.value = false;
  }
};

const filterNameInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field].replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '');
};

const filterNumberInput = (field) => {
  if (!newOwner.value[field]) return;
  newOwner.value[field] = newOwner.value[field].replace(/[^0-9]/g, '');
};

const fetchOwners = async () => {
  try {
    isLoadingOwners.value = true;
    const response = await api.get("/listings/owners");
    const ownersData = response.data.data || response.data;
    owners.value = ownersData.map(owner => ({
      id: owner.id,
      full_name: owner.full_name || `${owner.first_name} ${owner.last_name}`,
      first_name: owner.first_name,
      last_name: owner.last_name,
      email: owner.email,
      phone_number: owner.phone_number
    }));
  } catch (error) {
    console.error("❌ Error fetching owners:", error);
    proxy.$showNotification("❌ Failed to load owners.", "error");
  } finally {
    isLoadingOwners.value = false;
  }
};

const customOwnerFilter = (option, label, search) => {
  if (!search || search.trim() === '') return true;
  const searchTerm = search.toLowerCase().trim();
  const cleanPhoneNumber = (phone) => phone ? phone.replace(/[\s+()-]/g, '').toLowerCase() : '';
  
  if (option.email && option.email.toLowerCase().includes(searchTerm)) return true;
  if (option.phone_number && cleanPhoneNumber(option.phone_number).includes(cleanPhoneNumber(searchTerm))) return true;
  if (option.whatsapp_number && cleanPhoneNumber(option.whatsapp_number).includes(cleanPhoneNumber(searchTerm))) return true;
  if (option.second_phone_number && cleanPhoneNumber(option.second_phone_number).includes(cleanPhoneNumber(searchTerm))) return true;
  return false;
};

const fetchPropertyTypes = async () => {
  try {
    isLoadingPropertyTypes.value = true;
    const response = await api.get("/listings/property-types");
    const propertyTypesData = response.data.data || response.data;
    propertyTypes.value = propertyTypesData.map(type => ({
      id: type.id,
      name: type.name || type.type_name || type.title
    }));
  } catch (error) {
    console.error("❌ Error fetching property types:", error);
    proxy.$showNotification("❌ Failed to load property types.", "error");
  } finally {
    isLoadingPropertyTypes.value = false;
  }
};

const fetchDevelopers = async () => {
  try {
    isLoadingDevelopers.value = true;
    const response = await api.get("/listings/developers");
    const developersData = response.data.data || response.data;
    developers.value = developersData.map(developer => ({
      id: developer.id,
      name: developer.name || developer.developer_name || developer.title
    }));
  } catch (error) {
    console.error("❌ Error fetching developers:", error);
    proxy.$showNotification("❌ Failed to load developers.", "error");
  } finally {
    isLoadingDevelopers.value = false;
  }
};

const fetchAreas = async () => {
  try {
    isLoadingAreas.value = true;
    const response = await api.get("/listings/areas");
    const areasData = response.data.data || response.data;
    areas.value = areasData.map(area => ({
      id: area.id,
      name: area.area_parents_title || area.name || area.title,
      children_count: area.children_count ?? 0
    }));
  } catch (error) {
    console.error("❌ Error fetching areas:", error);
    proxy.$showNotification("❌ Failed to load areas.", "error");
  } finally {
    isLoadingAreas.value = false;
  }
};

const getAreaPlaceholder = () => {
  if (selectedProject.value && form.value.projectAreas.length > 0) {
    return `Select area in ${selectedProject.value.name}`;
  }
  return "Select area";
};

const handleNationalityChange = (newNationality) => {
  if (newNationality === 'UAE') {
    newOwner.value.residency_status = 'resident';
    fetchLocations('resident');
  } else {
    newOwner.value.residency_status = 'non_resident';
    newOwner.value.location_id = "";
    fetchLocations('non_resident');
  }
};

const getLocationLabel = () => {
  if (newOwner.value.nationality === 'UAE') return 'City';
  else if (newOwner.value.residency_status === 'resident') return 'Emirate';
  else if (newOwner.value.residency_status === 'non_resident') return 'Country';
  return 'Emirate or Country';
};

const getLocationPlaceholder = () => {
  if (newOwner.value.nationality === 'UAE') return 'Select City';
  else if (newOwner.value.residency_status === 'resident') return 'Select Emirate';
  else if (newOwner.value.residency_status === 'non_resident') return 'Select Country';
  return 'Select location';
};

const fetchLocations = async (residencyStatus) => {
  try {
    isLoadingLocations.value = true;
    locations.value = [];
    const response = await api.get(
      `/listings/owners/locations/available?residency_status=${residencyStatus}`
    );
    locations.value = response.data.data || response.data;
  } catch (error) {
    locations.value = [];
  } finally {
    isLoadingLocations.value = false;
  }
};

const submitNewOwner = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    proxy.$showNotification("❌ You are not logged in!", "error");
    return;
  }

  try {
    const formData = new FormData();
    for (const key in newOwner.value) {
      if (key === 'additional_documents') continue;
      const value = newOwner.value[key];
      if (value instanceof File) formData.append(key, value);
      else if (value !== null && value !== "") formData.append(key, value);
    }

    if (Array.isArray(newOwner.value.additional_documents) && newOwner.value.additional_documents.length) {
      newOwner.value.additional_documents.forEach((file, index) => {
        if (file instanceof File) {
          formData.append(`additional_documents[${index}]`, file);
        }
      });
    }

    const response = await api.post("/listings/owners", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const createdOwner = response.data?.data || response.data;
    await fetchOwners();
    
    const newOwnerInList = owners.value.find(owner => owner.id === createdOwner.id);
    if (newOwnerInList) selectedOwner.value = newOwnerInList;

    newOwner.value = {
      salutation: "", first_name: "", last_name: "", email: "",
      phone_number: "", whatsapp_number: "", second_phone_number: "",
      nationality: "", residency_status: "", location_id: "",
      id_front: null, id_back: null, visa_copy: null, passport_copy: null, notes: "",
      additional_documents: [],
    };
    locations.value = [];
    showAddOwner.value = false;
    proxy.$showNotification("✅ Owner added successfully!", "success");
  } catch (error) {
    console.error("❌ Error adding owner:", error);
    if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else {
      proxy.$showNotification("❌ Failed to add owner.", "error");
    }
  }
};

const handleNewOwnerFile = (e, field) => {
  const file = e.target.files[0];
  if (file) newOwner.value[field] = file;
};

const handleNewOwnerAdditionalFiles = (e) => {
  const files = Array.from(e.target.files || []);
  if (!files.length) return;

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
  const validFiles = files.filter(file => {
    if (!validTypes.includes(file.type)) {
      proxy.$showNotification(`❌ File "${file.name}" is not a valid type.`, "error");
      return false;
    }
    if (file.size > 5 * 1024 * 1024) {
      proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 5MB.`, "error");
      return false;
    }
    return true;
  });

  if (validFiles.length) {
    newOwner.value.additional_documents = [
      ...(newOwner.value.additional_documents || []),
      ...validFiles,
    ];
    proxy.$showNotification(`✅ Added ${validFiles.length} additional document(s)`, "success");
  }

  e.target.value = '';
};

const removeNewOwnerAdditionalFile = (index) => {
  if (!Array.isArray(newOwner.value.additional_documents)) return;
  newOwner.value.additional_documents.splice(index, 1);
};

const handleFloorPlanUpload = (e) => {
  const files = Array.from(e.target.files);
  if (files.length > 0) {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
    const validFiles = files.filter(file => {
      if (!validTypes.includes(file.type)) {
        proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
        return false;
      }
      if (file.size > 10 * 1024 * 1024) {
        proxy.$showNotification(`❌ Floor plan "${file.name}" is too large. Max size is 10MB.`, "error");
        return false;
      }
      return true;
    });

    if (validFiles.length > 0) {
      const filesWithNames = validFiles.map(file => ({
        file: file, name: file.name, size: file.size, type: file.type,
        customName: file.name.replace(/\.[^/.]+$/, ""), preview: URL.createObjectURL(file)
      }));
      form.value.floorPlans = [...form.value.floorPlans, ...filesWithNames];
      e.target.value = '';
      proxy.$showNotification(`✅ Added ${validFiles.length} floor plan(s)`, "success");
    }
  }
};

const updateFloorPlanName = (index, event) => {
  form.value.floorPlans[index].customName = event.target.value;
};

const handleGalleryUpload = (e) => {
  const files = Array.from(e.target.files);
  if (files.length > 0) {
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml', 'image/webp'];
    const validFiles = files.filter(file => {
      if (!validTypes.includes(file.type)) {
        proxy.$showNotification(`❌ File "${file.name}" is not a valid image type.`, "error");
        return false;
      }
      if (file.size > 10 * 1024 * 1024) {
        proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 10MB.`, "error");
        return false;
      }
      return true;
    });

    if (validFiles.length > 0) {
      const filesWithPreview = validFiles.map(file => ({
        file: file, name: file.name, size: file.size, type: file.type,
        preview: URL.createObjectURL(file)
      }));
      form.value.gallery = [...form.value.gallery, ...filesWithPreview];
      e.target.value = '';
      proxy.$showNotification(`✅ Added ${validFiles.length} image(s) to gallery`, "success");
    }
  }
};

const handlePropertyDocumentUpload = (e, field) => {
  const file = e.target.files?.[0];
  if (!file) return;

  const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];
  if (!validTypes.includes(file.type)) {
    proxy.$showNotification(`❌ File "${file.name}" is not a valid type.`, "error");
    e.target.value = '';
    return;
  }

  if (file.size > 10 * 1024 * 1024) {
    proxy.$showNotification(`❌ File "${file.name}" is too large. Max size is 10MB.`, "error");
    e.target.value = '';
    return;
  }

  form.value[field] = file;
  e.target.value = '';
  proxy.$showNotification(`✅ Added document: ${file.name}`, "success");
};

const removePropertyDocument = (field) => {
  form.value[field] = null;
  proxy.$showNotification("🗑️ Document removed", "info");
};

const getImagePreview = (file) => {
  if (file instanceof File) return URL.createObjectURL(file);
  if (file && file.image_url) return file.image_url;
  if (file && file.path) return file.path;
  return '';
};

const handleImageError = (event) => {
  console.error('❌ Image failed to load:', event);
  event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMTgiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgZm91bmQ8L3RleHQ+PC9zdmc+';
};

const cleanupObjectURLs = () => {
  if (form.value.hero_image && form.value.hero_image.preview) {
    URL.revokeObjectURL(form.value.hero_image.preview);
  }
  form.value.floorPlans.forEach(item => { if (item.preview) URL.revokeObjectURL(item.preview); });
  form.value.gallery.forEach(item => { if (item.preview) URL.revokeObjectURL(item.preview); });
};

const removeHeroImage = () => {
  if (form.value.hero_image && form.value.hero_image.preview) {
    URL.revokeObjectURL(form.value.hero_image.preview);
  }
  form.value.hero_image = null;
  proxy.$showNotification("🗑️ Hero image removed", "info");
};

const removeFloorPlan = (index) => {
  if (form.value.floorPlans[index] && form.value.floorPlans[index].preview) {
    URL.revokeObjectURL(form.value.floorPlans[index].preview);
  }
  form.value.floorPlans.splice(index, 1);
  proxy.$showNotification("🗑️ Floor plan removed", "info");
};

const removeGalleryImage = (index) => {
  if (form.value.gallery[index] && form.value.gallery[index].preview) {
    URL.revokeObjectURL(form.value.gallery[index].preview);
  }
  form.value.gallery.splice(index, 1);
  proxy.$showNotification("🗑️ Image removed from gallery", "info");
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const setAsHeroImage = (index) => {
  if (index === 0) return;
  const selectedImage = form.value.gallery[index];
  form.value.gallery.splice(index, 1);
  form.value.gallery.unshift(selectedImage);
  proxy.$showNotification("✅ Image set as hero property image", "success");
};

const handleSubmit = async (action = 'draft') => {
  try {
    isSubmitting.value = true;
       const plotTypes = ['Plot', 'Land', 'Residential Plot', 'Commercial Plot']; // Adjust based on your data
    const isPlot= form.value.property_type && 
           plotTypes.some(type => 
               form.value.property_type.name.toLowerCase().includes(type.toLowerCase())
           );
    if (!selectedOwner.value) {
      proxy.$showNotification("❌ Please select an owner first!", "error");
      isSubmitting.value = false;
      return;
    }
     if (form.value.unit_number && selectedProject.value) {
      if (unitNumberError.value) {
        proxy.$showNotification(`❌ ${unitNumberError.value}`, "error");
        isSubmitting.value = false;
        return;
      }
    }
    if (form.value.saleOrRent === 'Rent' && form.value.rented_status === 'Rented' && !form.value.rented_until) {
      proxy.$showNotification("❌ Please select rental end date!", "error");
      isSubmitting.value = false;
      return;
    }

    const currentAgentId = agentId.value;
    if (!currentAgentId) {
      proxy.$showNotification("❌ Agent ID not found. Please login again.", "error");
      isSubmitting.value = false;
      return;
    }

    const requiredFields = [
      { value: form.value.property_type, message: "❌ Please select a property type!" },
      { value: form.value.area, message: "❌ Please select an area!" },
    ];

    for (const field of requiredFields) {
      if (!field.value) {
        proxy.$showNotification(field.message, "error");
        isSubmitting.value = false;
        return;
      }
    }

    if (action !== 'draft') {
      if (!form.value.completionStatus) {
        proxy.$showNotification("❌ Please select completion status!", "error");
        isSubmitting.value = false;
        return;
      }
      
      if (form.value.gallery.length === 0) {
        proxy.$showNotification("❌ At least one gallery image is required!", "error");
        isSubmitting.value = false;
        return;
      }
      
      if (form.value.gallery.length < 10 && !isPlot) {
        proxy.$showNotification(`❌ At least 10 gallery images are required! Currently you have ${form.value.gallery.length}.`, "error");
        isSubmitting.value = false;
        return;
      }else if (form.value.gallery.length < 1 && isPlot){
          proxy.$showNotification(`❌ At least 1 gallery images are required! Currently you have ${form.value.gallery.length}.`, "error");
        isSubmitting.value = false;
        return;
      }
    if (form.value.gallery.length > 15) {
          proxy.$showNotification(
            `❌ Maximum 15 gallery images are allowed. Currently you have ${form.value.gallery.length}.`,
            "error"
          );
          isSubmitting.value = false;
          return;
        }

    }
    
 
    const formData = new FormData();
    formData.append('action', action);
    formData.append('owner_id', selectedOwner.value.id);
    formData.append('agent_id', currentAgentId);
    formData.append('property_type_id', form.value.property_type.id);
    formData.append('area_id', form.value.area.id);
    formData.append('unit_view_id', form.value.unit_view?.id ?? "");
    formData.append('layout_type_id', form.value.layout_type?.id ?? "");
    
    if (form.value.rented_status) formData.append('rented_status', form.value.rented_status);
    if (form.value.rented_until) formData.append('rented_until', form.value.rented_until);
    if (form.value.payment_plan) formData.append('payment_plan', form.value.payment_plan);

    if (form.value.gallery.length > 0) {
      const firstImage = form.value.gallery[0].file || form.value.gallery[0];
      if (firstImage instanceof File) formData.append('hero_image', firstImage);
    }
    if (form.value.driveLink) {
      formData.append('drive_link', form.value.driveLink);
    }
     
    formData.append('is_hot_deal', form.value.is_hot_deal || 'No');
    
    const textFields = {
      'unit_number': form.value.unit_number, 'ownership_type': form.value.ownership_type,
      'listing_status': form.value.saleOrRent, 'completion_status': form.value.completionStatus,
      'price': form.value.price, 'number_of_bedrooms': form.value.number_of_bedrooms,
      'number_of_bathrooms': form.value.number_of_bathrooms, 'size_sqmt': form.value.size_sqmt,
      'size_sqft': form.value.size_sqft, 'furnished_status': form.value.furnished_status,
      'comment': form.value.comment, 'mortgage_status': form.value.mortgageStatus,
      'occupancy_status': form.value.occupancyStatus, 'mortgage_amount': form.value.mortgageAmount,
      'rent_expiry_date': form.value.rentExpiryDate, 'rent_amount': form.value.rentAmount,
      'mortgage_comment': form.value.mortgageComment,
    };

    Object.entries(textFields).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') formData.append(key, value);
    });

    if (form.value.developer) formData.append('developer_id', form.value.developer.id);
    if (selectedProject.value) formData.append('project_id', selectedProject.value.id);

    if (form.value.floorPlans.length > 0) {
      form.value.floorPlans.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) {
          formData.append(`floor_plans[${index}]`, file);
          formData.append(`floor_plan_names[${index}]`, item.customName || file.name.replace(/\.[^/.]+$/, ""));
        }
      });
    }

    if (form.value.gallery.length > 0) {
      form.value.gallery.forEach((item, index) => {
        const file = item.file || item;
        if (file instanceof File) formData.append(`gallery[${index}]`, file);
      });
    }

    if (form.value.spa_document instanceof File) formData.append('spa_document', form.value.spa_document);
    if (form.value.desk_document instanceof File) formData.append('desk_document', form.value.desk_document);
    if (form.value.other_document instanceof File) formData.append('other_document', form.value.other_document);

    console.log('📤 Sending form data with action:', action);
    console.log('📋 Payment plan JSON:', form.value.payment_plan);

    const response = await api.post("/listings/properties", formData, {
      headers: { "Content-Type": "multipart/form-data" },
      timeout: 30000,
    });

    console.log("✅ Success Response:", response.data);
    const propertyId = response.data.data?.id || response.data.id;
    
    let successMessage;
    if (action === 'draft') successMessage = "✅ Property saved as draft successfully!";
    else if (action === 'preview') successMessage = "✅ Property saved for preview!";
    else successMessage = "✅ Property published successfully!";
    
    proxy.$showNotification(successMessage, "success");
    
    if (action === 'preview') proxy.$router.push(`/property-details/${propertyId}`);
    else proxy.$router.push(`/property-details/${propertyId}`);
    
  } catch (error) {
    console.error("❌ Full Error:", error);
    if (error.code === 'ECONNABORTED') proxy.$showNotification("❌ Request timeout. Please try again.", "error");
    else if (error.response?.status === 413) proxy.$showNotification("❌ File too large. Please reduce image sizes.", "error");
    else if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(', ');
      proxy.$showNotification(`❌ Validation Error: ${errorMessages}`, "error");
    } else if (error.response?.data?.message) proxy.$showNotification(`❌ Server Error: ${error.response.data.message}`, "error");
    else if (error.message) proxy.$showNotification(`❌ Network Error: ${error.message}`, "error");
    else proxy.$showNotification("❌ Unexpected error occurred.", "error");
  } finally {
    isSubmitting.value = false;
  }
};

const resetForm = () => {
  cleanupObjectURLs();
  form.value = {
    title: "", unit_number: "", ownership_type: "", saleOrRent: "", completionStatus: "",
    area: null, developer: null, property_type: null, price: "", number_of_bedrooms: "",
    number_of_bathrooms: "", layout_type: null, unit_view: null, furnished_status: "",
    size_sqmt: "", size_sqft: "", hero_image: null, floorPlans: [], gallery: [],
    comment: "", mortgageStatus: "", occupancyStatus: "", mortgageAmount: "",
    rentExpiryDate: "", rentAmount: "", mortgageComment: "", projectAreas: [],
    rented_status: "", rented_until: "", payment_plan: "", payment_plans: []   , driveLink: ""  ,is_hot_deal:"",
    spa_document: null, desk_document: null, other_document: null,
  };
  selectedOwner.value = null;
  selectedProject.value = null;
  console.log('🔄 Form has been reset');
};

const loadPaymentPlansFromString = (paymentPlanString) => {
  if (!paymentPlanString) return [];
  try {
    const parsed = JSON.parse(paymentPlanString);
    if (Array.isArray(parsed)) return parsed.map(plan => ({ label: plan, value: plan }));
    else return [{ label: paymentPlanString, value: paymentPlanString }];
  } catch (e) {
    return [{ label: paymentPlanString, value: paymentPlanString }];
  }
};

const convertSqmToSqft = () => {
  if (form.value.size_sqmt && !isNaN(form.value.size_sqmt)) {
    form.value.size_sqft = (form.value.size_sqmt * 10.7639).toFixed(2);
  } else {
    form.value.size_sqft = "";
  }
};

const convertSqftToSqm = () => {
  if (form.value.size_sqft && !isNaN(form.value.size_sqft)) {
    form.value.size_sqmt = (form.value.size_sqft / 10.7639).toFixed(2);
  } else {
    form.value.size_sqmt = "";
  }
};

const bedroomOptions = [
  { label: "Studio", value: 0 }, { label: "1", value: 1 }, { label: "2", value: 2 }, { label: "3", value: 3 },
  { label: "4", value: 4 }, { label: "5", value: 5 }, { label: "6", value: 6 },
  { label: "7", value: 7 }, { label: "8", value: 8 }, { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

const bathroomOptions = [
  { label: "1", value: 1 }, { label: "2", value: 2 }, { label: "3", value: 3 },
  { label: "4", value: 4 }, { label: "5", value: 5 }, { label: "6", value: 6 },
  { label: "7", value: 7 }, { label: "8", value: 8 }, { label: "9", value: 9 },
  { label: "10+", value: 10 }
];

const nationalities = ref([
  "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda",
  "Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain",
  "Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia",
  "Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso",
  "Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic",
  "Chad","Chile","China","Colombia","Comoros","Congo (Congo-Brazzaville)",
  "Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark","Djibouti","Dominica",
  "Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
  "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia",
  "Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau",
  "Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq",
  "Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya",
  "Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia",
  "Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia",
  "Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico",
  "Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique",
  "Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua",
  "Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan",
  "Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines",
  "Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis",
  "Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino",
  "Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles",
  "Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia",
  "South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname",
  "Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand",
  "Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey",
  "Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom",
  "United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela",
  "Vietnam","Yemen","Zambia","Zimbabwe"
]);

const clearUnitNumberError = () => {
  unitNumberError.value = "";
};

const validateUnitNumber = async () => {
  if (!form.value.unit_number || 
      !form.value.saleOrRent || 
      !selectedProject.value) {
    return true;
  }

  try {
    isLoadingUnitNumber.value = true;
    unitNumberError.value = "";

    const response = await api.post("/listings/properties/validate-unit-number", {
      unit_number: form.value.unit_number,
      listing_status: form.value.saleOrRent,
      project_id: selectedProject.value.id
    });

    const data = response.data;

    if (data.exists) {
      unitNumberError.value = `❌ This unit number is already in use for ${form.value.saleOrRent} in this project`;
    } else {
      console.log("✅ Unit number is available");
    }

  } catch (error) {
    console.error("❌ Error validating unit number:", error);
    
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      if (errors.unit_number) {
        unitNumberError.value = errors.unit_number[0];
      }
    } else {
      unitNumberError.value = "⚠️ Could not validate unit number. Please try again.";
    }
     return false;
  } finally {
    isLoadingUnitNumber.value = false;
  }
};

onMounted(() => {
  console.log('🔧 Component mounted, fetching data...');
  console.log('👤 Current agent ID:', agentId.value);
  
  Promise.all([
    fetchOwners(),
    fetchPropertyTypes(),
    fetchAreas()
  ]).then(() => console.log('✅ Basic data loaded'));
   form.value.is_hot_deal = "No";
  fetchDevelopers();
  fetchUnitViews(); 
  fetchLayoutTypes();
  fetchProjects(); 
  cleanupObjectURLs();
  console.log('🚀 All data fetch requests initiated');
});
</script>

<style scoped>
/* 🔹 Global Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
}

/* 🔹 Dark Blue Gradient Colors */
:root {
  --dark-blue-gradient: linear-gradient(135deg, #0c2461 0%, #1e3799 100%);
  --dark-blue-light: #1e3799;
  --dark-blue-dark: #0c2461;
  --dark-blue-hover: #2a3db0;
}

/* 🔹 Modal Styles */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1050;
  padding: 20px;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-container {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 1200px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  position: relative;
  animation: slideUp 0.4s ease;
}

@keyframes slideUp {
  from { transform: translateY(30px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.modal-header {
  color: white;
  padding: 24px 30px;
  border-radius: 16px 16px 0 0;
  position: sticky;
  top: 0;
  z-index: 10;
}

.modal-header h5 {
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.modal-header .btn-close {
  border-radius: 50%;
  padding: 8px;
  opacity: 0.8;
  transition: all 0.3s ease;
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-header .btn-close:hover {
  opacity: 1;
  transform: rotate(90deg);
}

.modal-body {
  padding: 30px;
}

.modal-footer {
  background: #f8f9fa;
  padding: 20px 30px;
  border-radius: 0 0 16px 16px;
  border-top: 1px solid #eaeaea;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* 🔹 Section Styles */
.section {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  border: 1px solid #eaeaea;
  transition: all 0.3s ease;
}

.section:hover {
  border-color: var(--dark-blue-light);
  box-shadow: 0 8px 25px rgba(30, 55, 153, 0.1);
}

.section-title {
  color: #2d3748;
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--dark-blue-light);
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-title i {
  color: var(--dark-blue-light);
  font-size: 1.2rem;
}

/* 🔹 Form Styles */
.form-label {
  font-weight: 500;
  color: #4a5568;
  margin-bottom: 8px;
  display: block;
}

.form-control, .v-select {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: white;
}

.form-control:focus, .v-select:focus {
  border-color: var(--dark-blue-light);
  box-shadow: 0 0 0 3px rgba(30, 55, 153, 0.15);
  outline: none;
}

.v-select {
  --vs-border-radius: 10px;
  --vs-border-color: #e2e8f0;
  --vs-search-input-color: #4a5568;
}

.v-select .vs__dropdown-toggle {
  border-radius: 10px;
  padding: 8px;
}

.v-select .vs__search {
  padding: 10px;
}

.v-select.project-areas .vs__dropdown-toggle {
  background-color: #e8f4fd !important;
  border-color: #0d6efd !important;
}

.v-select.project-areas .vs__selected {
  color: #0d6efd !important;
  font-weight: 600;
}

.v-select.project-areas .vs__dropdown-menu {
  border-color: #0d6efd;
}

/* 🔹 Button Styles */
.btn {
  border-radius: 10px;
  padding: 12px 24px;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-primary {
  background: rgba(12, 36, 97, 0.9); /* Solid dark blue */
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-primary:hover {
  background: rgba(12, 36, 97, 1);
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(12, 36, 97, 0.3);
}


.btn-primary:hover {
  background: linear-gradient(135deg, #1a3db0 0%, #2540c7 100%);
}
.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
}

.btn-outline-primary {
  border: 2px solid rgba(5, 10, 40, 0.95);
  color: rgba(5, 10, 40, 0.95);
  background: transparent;
}

.btn-outline-primary:hover {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.25) 0%, rgba(20, 30, 80, 0.79) 0%, rgba(5, 10, 40, 0.95) 100%);
  color: white;
  transform: translateY(-2px);
}

.btn-danger {
  background: linear-gradient(135deg, #ef476f 0%, #d90429 100%);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(239, 71, 111, 0.3);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

/* 🔹 File Upload Styles */
.file-upload-area {
  border: 2px dashed #cbd5e0;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  background: #f8fafc;
  transition: all 0.3s ease;
  cursor: pointer;
}

.file-upload-area:hover {
  border-color: var(--dark-blue-light);
  background: #edf2f7;
}

.file-upload-area.dragover {
  border-color: var(--dark-blue-light);
  background: #e6eeff;
  transform: scale(1.02);
}

.file-upload-icon {
  font-size: 3rem;
  color: var(--dark-blue-light);
  margin-bottom: 15px;
}

.file-upload-text {
  color: #4a5568;
  margin-bottom: 10px;
}

.file-upload-hint {
  color: #718096;
  font-size: 0.875rem;
}

/* 🔹 Gallery & Floor Plan Styles */
.image-preview-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.image-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
  position: relative;
}

.image-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
  border-color: var(--dark-blue-light);
}

.image-card.hero {
  border: 3px solid var(--dark-blue-light);
  position: relative;
}

.image-card.hero::before {
  content: 'Main Image';
  position: absolute;
  top: 10px;
  left: 10px;
  background: var(--dark-blue-gradient);
  color: white;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  z-index: 2;
}

.image-preview {
  width: 100%;
  height: 140px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.image-card:hover .image-preview {
  transform: scale(1.05);
}

.image-info {
  padding: 12px;
  background: #f8fafc;
}

.image-name {
  font-weight: 500;
  color: #2d3748;
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 5px;
}

.image-size {
  color: #718096;
  font-size: 0.8rem;
}

.image-actions {
  position: absolute;
  top: 10px;
  right: 10px;
  display: flex;
  gap: 5px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.image-card:hover .image-actions {
  opacity: 1;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(0, 0, 0, 0.1);
  color: #4a5568;
  font-size: 0.875rem;
  transition: all 0.3s ease;
}

.btn-icon:hover {
  background: white;
  color: #ef476f;
  transform: scale(1.1);
}

.btn-icon.primary:hover {
  color: var(--dark-blue-light);
}

/* 🔹 Badge Styles */
.badge {
  font-size: 0.75rem;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.badge-primary {
  background: var(--dark-blue-gradient);
  color: white;
}

.badge-success {
  background: linear-gradient(135deg, #06d6a0 0%, #04966a 100%);
  color: white;
}

.badge-warning {
  background: linear-gradient(135deg, #ffd166 0%, #f4a261 100%);
  color: #2d3748;
}

.badge-danger {
  background: linear-gradient(135deg, #ef476f 0%, #d90429 100%);
  color: white;
}

/* 🔹 Loading States */
.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
  z-index: 10;
  backdrop-filter: blur(5px);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid var(--dark-blue-light);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .modal-container {
    max-height: 90vh;
    border-radius: 12px;
    margin: 10px;
  }
  
  .modal-body {
    padding: 20px;
  }
  
  .modal-header {
    padding: 18px 20px;
  }
  
  .section {
    padding: 18px;
  }
  
  .image-preview-container {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
  }
  
  .btn {
    padding: 10px 18px;
    font-size: 0.9rem;
  }
}

@media (max-width: 576px) {
  .modal-container {
    max-height: 95vh;
  }
  
  .modal-body {
    padding: 15px;
  }
  
  .image-preview-container {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .section-title {
    font-size: 1.1rem;
  }
}

.text-muted {
  color: #718096 !important;
}

.text-primary {
  color: var(--dark-blue-light) !important;
}

.text-success {
  color: #06d6a0 !important;
}

.text-danger {
  color: #ef476f !important;
}

.text-warning {
  color: #ffd166 !important;
}

.text-info {
  color: #0dcaf0 !important;
}

.mb-0 {
  margin-bottom: 0 !important;
}

.mt-1 {
  margin-top: 4px !important;
}

.mt-2 {
  margin-top: 8px !important;
}

.mt-3 {
  margin-top: 12px !important;
}

.mt-4 {
  margin-top: 16px !important;
}

.mb-1 {
  margin-bottom: 4px !important;
}

.mb-2 {
  margin-bottom: 8px !important;
}

.mb-3 {
  margin-bottom: 12px !important;
}

.mb-4 {
  margin-bottom: 16px !important;
}

.ms-auto {
  margin-left: auto !important;
}

.me-2 {
  margin-right: 8px !important;
}

.d-flex {
  display: flex !important;
}

.justify-content-between {
  justify-content: space-between !important;
}

.justify-content-end {
  justify-content: flex-end !important;
}

.align-items-center {
  align-items: center !important;
}

.flex-wrap {
  flex-wrap: wrap !important;
}

.gap-2 {
  gap: 8px !important;
}

.gap-3 {
  gap: 12px !important;
}

.gap-4 {
  gap: 16px !important;
}

.w-100 {
  width: 100% !important;
}

/* 🔹 Scrollbar Styling */
.modal-container::-webkit-scrollbar {
  width: 8px;
}

.modal-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb {
  background: var(--dark-blue-gradient);
  border-radius: 4px;
}

.modal-container::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #1a3db0 0%, #2540c7 100%);
}

/* 🔹 Form Grid System */
.row {
  display: flex;
  flex-wrap: wrap;
  margin: -10px;
}

.col {
  flex: 1;
  padding: 10px;
  min-width: 200px;
}

.col-md-6 {
  flex: 0 0 50%;
  max-width: 50%;
}

.col-md-4 {
  flex: 0 0 33.333%;
  max-width: 33.333%;
}

.col-md-3 {
  flex: 0 0 25%;
  max-width: 25%;
}

@media (max-width: 992px) {
  .col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
  }
  
  .col-md-4 {
    flex: 0 0 50%;
    max-width: 50%;
  }
  
  .col-md-3 {
    flex: 0 0 50%;
    max-width: 50%;
  }
}

@media (max-width: 576px) {
  .col-md-4,
  .col-md-3 {
    flex: 0 0 100%;
    max-width: 100%;
  }
}

.card-hover {
  transition: all 0.3s ease;
}

.card-hover:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.input-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.input-group .form-control {
  flex: 1;
}

.input-group-text {
  background: #f8f9fa;
  border: 1px solid #e2e8f0;
  padding: 12px 16px;
  border-radius: 10px;
  color: #4a5568;
  font-weight: 500;
  white-space: nowrap;
}

.divider {
  height: 1px;
  background: linear-gradient(to right, transparent, #e2e8f0, transparent);
  margin: 24px 0;
}

.required::after {
  content: " *";
  color: #ef476f;
  font-weight: bold;
}

[data-tooltip] {
  position: relative;
  cursor: help;
}

[data-tooltip]:hover::before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  background: #2d3748;
  color: white;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  white-space: nowrap;
  z-index: 1000;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

[data-tooltip]:hover::after {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #2d3748;
  margin-bottom: -5px;
}

.project-areas-label {
  color: #0d6efd;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 5px;
}

.project-areas-label i {
  font-size: 14px;
}
.vs--searchable .vs__dropdown-toggle {
    min-height: 2.75rem !important;
    height: auto !important;
    padding-bottom: 0;
}
.vs__selected-options{
     height: auto;
}
.selected-tag{
        margin: 2px;
    border: 1px solid #202645;
    border-radius: 10px;
    padding: 2px 5px;
    background: #202645;
    color: white;
}
body.swal2-toast-shown  {
    z-index: 10000 !important;
}
.is-invalid {
  border-color: #dc3545 !important;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e") !important;
  background-repeat: no-repeat;
  background-position: right calc(0.375em + 0.1875rem) center;
  background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #dc3545;
}

.valid-feedback {
  display: block;
  width: 100%;
  margin-top: 0.25rem;
  font-size: 0.875em;
  color: #198754;
}

</style>