<template>
    <div class="dashboard-main-body">
        <Breadcrumb :title="owner ? owner.name : 'Owner Details'" :breadcrumbs="[
            { name: 'Owners', path: '/owners' },
            { name: owner ? owner.name : 'Loading...' }
        ]" />

        <div class="card" v-if="owner">
            <div class="card-body">
                <!-- Main Info Tabs -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="nav nav-pills owner-tabs mb-3" id="ownerTab" role="tablist">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                                    data-bs-target="#personal" type="button" role="tab">
                                Personal Info
                            </button>
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" 
                                    data-bs-target="#documents" type="button" role="tab">
                                Documents
                                <span v-if="hasDocuments" class="badge bg-primary ms-2">{{ getDocumentCount() }}</span>
                            </button>
                            <!-- إضافة تاب Properties -->
                            <button class="nav-link" id="properties-tab" data-bs-toggle="tab" 
                                    data-bs-target="#properties" type="button" role="tab">
                                Properties
                                <span v-if="totalProperties > 0" class="badge bg-primary ms-2">{{ totalProperties }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="ownerTabContent">
                    <!-- Personal Info Tab -->
                    <div class="tab-pane fade show active" id="personal" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <iconify-icon icon="lucide:user" class="me-2 text-primary"></iconify-icon>
                                            Basic Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <span class="info-label">Salutation</span>
                                                <span class="info-value">{{ owner.salutation || '-' }}</span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">First Name</span>
                                                <span class="info-value fw-bold">{{ owner.first_name || '-' }}</span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Last Name</span>
                                                <span class="info-value fw-bold">{{ owner.last_name || '-' }}</span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Email</span>
                                                <a :href="`mailto:${owner.email}`" class="info-value text-primary">
                                                    {{ owner.email || '-' }}
                                                </a>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Phone</span>
                                                <a :href="`tel:${owner.phone_number}`" class="info-value">
                                                    {{ owner.phone_number || '-' }}
                                                </a>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Nationality</span>
                                                <span class="info-value badge bg-info">{{ owner.nationality || '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Residency -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <iconify-icon icon="lucide:map-pin" class="me-2 text-primary"></iconify-icon>
                                            Residency Status
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <span class="info-label">Residency Status</span>
                                                <span class="info-value">
                                                    <span v-if="owner.residency_status === 'resident'" 
                                                          class="badge bg-success">Resident</span>
                                                    <span v-else-if="owner.residency_status === 'non_resident'" 
                                                          class="badge bg-warning">Non Resident</span>
                                                    <span v-else class="badge bg-secondary">-</span>
                                                </span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">{{ getLocationLabel() }}</span>
                                                <span class="info-value">{{ owner.location?.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-4" v-if="owner.notes">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <iconify-icon icon="lucide:sticky-note" class="me-2 text-primary"></iconify-icon>
                                            Notes
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0">{{ owner.notes }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <iconify-icon icon="lucide:folder" class="me-2 text-primary"></iconify-icon>
                                            Owner Documents
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row" v-if="hasDocuments">
                                            <div class="col-md-4 col-sm-6 mb-4" 
                                                 v-for="(doc, index) in getDocumentList()" 
                                                 :key="index">
                                                <div class="document-item card h-100">
                                                    <div class="card-body text-center">
                                                        <div class="document-icon mb-3">
                                                            <iconify-icon :icon="getDocumentIcon(doc.type)" 
                                                                         width="40" 
                                                                         class="text-primary"></iconify-icon>
                                                        </div>
                                                        <h6 class="document-title mb-2">{{ doc.title }}</h6>
                                                        <div class="document-actions d-flex gap-2 justify-content-center">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    @click="viewDocument(doc.url)">
                                                                <iconify-icon icon="lucide:eye" class="me-1"></iconify-icon>
                                                                View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    @click="downloadDocument(doc.url, doc.title)">
                                                                <iconify-icon icon="lucide:download" class="me-1"></iconify-icon>
                                                                Download
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-center py-5">
                                            <iconify-icon icon="lucide:folder-x" width="64" class="text-muted mb-3"></iconify-icon>
                                            <h5 class="text-muted">No Documents Found</h5>
                                            <p class="text-muted">This owner has no uploaded documents.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Properties Tab -->
                    <div class="tab-pane fade" id="properties" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <!-- Properties Grid -->
                                <div v-if="propertiesLoading" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-2">Loading properties...</p>
                                </div>

                                <div v-else-if="filteredProperties.length === 0" class="text-center py-5">
                                    <div class="mb-3">
                                        <i class="ri-home-4-line display-1 text-muted"></i>
                                    </div>
                                    <h5 class="text-muted">No Properties Found</h5>
                                    <p class="text-muted">This owner doesn't have any properties yet.</p>
                                </div>

                                <div v-else class="row g-3">
                                    <div 
                                        v-for="property in filteredProperties" 
                                        :key="property.id" 
                                        class="col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600"
                                    >
                                           <router-link
                                        :to="`/property-details/${property.id}`"
                                        class="property-card-link"
                                        :class="{ 'converted-card': property.status === 'converted' }"
                                      >
                                        <div class="property-card">
                                          <!-- Image -->
                                          <div class="property-image position-relative">
                                            <img 
                                              :src="getPropertyImage(property)" 
                                              :alt="property.title"
                                              class="w-100 object-fit-cover" 
                                              :class="{ 'converted-image': property.status === 'converted' }"
                                              loading="lazy" 
                                              @load="handleImageLoad" 
                                              @error="handleImageError" 
                                            />
                                            
                                            <div class="status-badges">
                                              <span v-if="property.status === 'converted'" class="badge-sold">
                                                <i class="ri-checkbox-circle-fill me-1"></i>Sold Out
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
                                            </div>
                                            
                                            <span class="badge-images" v-if="property.gallery_images && property.gallery_images.length">
                                              <i class="ri-image-line me-1"></i>{{ property.gallery_images.length }}
                                            </span>
                                          </div>
                            
                                          <!-- Content -->
                                          <div class="property-content p-3">
                                            <div class="d-flex align-items-end mb-1">
                                              <h6 class="property-price mb-0 me-1">{{ formatPrice(property.price) }}</h6>
                                              <small class="text-muted price-unit">AED</small>
                                            </div>
                            
                                            <h5 class="property-title mb-2">{{ property.title || 'No Title' }}</h5>
                                            <p class="property-location mb-3">
                                              <i class="ri-map-pin-line me-1"></i>{{ getLocation(property) }}
                                            </p>
                            
                                            <div class="property-details d-flex justify-content-between text-muted small mb-3">
                                              <span class="d-flex justify-content-between icons">
                                                  <img :src="propertyIcon" class="imgicon"/>
                                                  <!--<i class="ri-building-4-line me-1"></i>-->
                                                  <span>
                                                  {{ getPropertyType(property) }}
                                                  </span>
                                                  
                                            </span>
                                              <span class="d-flex justify-content-between icons">
                                                  <!--<i class="ri-hotel-bed-line me-1"></i>-->
                                                  <img :src="bedIcon" class="imgicon"/>
                                                   <span>
                                                  {{ property.number_of_bedrooms || 0 }}
                                                  </span>
                                             </span>
                                              <span class="d-flex justify-content-between icons">
                                                  <!--<i class="ri-water-flash-line me-1"></i>-->
                                                  <img :src="bathIcon" class="imgicon"/>
                                                   <span>
                                                  {{ property.number_of_bathrooms || 0 }}
                                                  </span>
                                            </span>
                                              <span class="d-flex justify-content-between icons">
                                                  <!--<i class="ri-ruler-line me-1"></i>-->
                                                  <img :src="sqftIcon" class="imgicon"/>
                                                   <span>
                                                  {{ property.size_sqft || property.size_sqmt || 0 }} {{ getAreaUnit(property) }}
                                                  </span>
                                            </span>
                                            </div>
                                            
                                            <p class="property-agent text-muted small mb-2" v-if="property.agent">
                                              Listed by: {{ getAgentName(property.agent) }}
                                            </p>
                            
                                            <!-- Button - يمكن إزالته أو إبقاؤه حسب الحاجة -->
                                            <span class="view-more-btn">
                                              View Details
                                            </span>
                                          </div>
                                        </div>
                                      </router-link>
                                    </div>
                                </div>

                                <!-- Properties Count -->
                                <div v-if="filteredProperties.length > 0" class="mt-4 text-end">
                                    <small class="text-muted">
                                        Showing {{ filteredProperties.length }} of {{ totalProperties }} properties
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p>Loading owner information...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="card">
            <div class="card-body text-center py-5">
                <iconify-icon icon="lucide:user-x" class="text-muted mb-3" width="48"></iconify-icon>
                <h5>Owner Not Found</h5>
                <p class="text-muted">The owner you're looking for doesn't exist.</p>
                <button class="btn btn-primary" @click="$router.push('/owners')">
                    Back to Owners List
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { API_ENDPOINTS } from '@/config/api';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '@/assets/images/avatar/avatar1.png';

export default {
    name: 'ViewOwner',
    components: {
        Breadcrumb
    },
    data() {
        return {
            
            loading: false,
            owner: null,
            error: null,
            userPlaceholder,
            properties: [],
            propertiesLoading: false,
            sortField: 'created_at',
            sortDirection: 'desc',
            propertyStatusFilter: 'all',
            propertyTypeFilter: 'all',
            showSoldProperties: false,
            propertyIcon : '/assets/icons/property-icon.svg',
            bedIcon : '/assets/icons/bedroom-icon.svg',
            bathIcon : '/assets/icons/bathroom-icon.svg',
            sqftIcon : '/assets/icons/area-size.svg',
            
        };
    },
    computed: {
        fullName() {
            if (!this.owner) return '';
            return `${this.owner.first_name || ''} ${this.owner.last_name || ''}`.trim() || 'Unnamed Owner';
        },
        hasDocuments() {
            if (!this.owner) return false;
            const hasStandard = this.owner.id_front_path || this.owner.id_back_path ||
                   this.owner.visa_copy_path || this.owner.passport_copy_path ||
                   this.owner.id_front_url || this.owner.id_back_url ||
                   this.owner.visa_copy_url || this.owner.passport_copy_url;
            const hasAdditional = this.owner.additional_documents && this.owner.additional_documents.length > 0;
            return hasStandard || hasAdditional;
        },
        totalProperties() {
            return this.properties && Array.isArray(this.properties) ? this.properties.length : 0;
        },
        filteredProperties() {
            if (!this.properties || !Array.isArray(this.properties)) return [];
            
            let filtered = this.properties;
            
            // تصفية حسب الحالة
            if (this.propertyStatusFilter !== 'all') {
                filtered = filtered.filter(p => {
                    switch (this.propertyStatusFilter) {
                        case 'active':
                            return p.is_active && !p.is_archived && p.status !== 'converted' && p.status !== 'draft';
                        case 'draft':
                            return p.status === 'draft';
                        case 'sold':
                            return p.status === 'converted';
                        default:
                            return true;
                    }
                });
            }
            
            // تصفية حسب النوع
            if (this.propertyTypeFilter !== 'all') {
                filtered = filtered.filter(p => {
                    if (this.propertyTypeFilter === 'sale') {
                        return p.listing_status === 'Sale' || p.listing_type === 'sale';
                    } else if (this.propertyTypeFilter === 'rent') {
                        return p.listing_status === 'Rent' || p.listing_type === 'rent';
                    }
                    return true;
                });
            }
            
            // إخفاء المباعة حسب التفضيلات
            if (!this.showSoldProperties) {
                filtered = filtered.filter(p => p.status !== 'converted');
            }
            
            // الترتيب
            return [...filtered].sort((a, b) => {
                let aValue = a[this.sortField] || '';
                let bValue = b[this.sortField] || '';
                
                if (this.sortField === 'created_at') {
                    aValue = new Date(aValue).getTime();
                    bValue = new Date(bValue).getTime();
                } else {
                    aValue = String(aValue).toLowerCase();
                    bValue = String(bValue).toLowerCase();
                }
                
                if (this.sortDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
        }
    },
    mounted() {
        console.log('🚀 ViewOwner component mounted');
        console.log('🔍 Owner ID from route:', this.$route.params.id);
        
        this.fetchOwner();
    },
    methods: {
        async fetchOwner() {
            console.log('🔄 [1] Starting fetchOwner...');
            try {
                this.loading = true;
                const token = localStorage.getItem('token');
                const ownerId = this.$route.params.id;
                
                console.log('🔑 [2] Token exists:', !!token);
                console.log('👤 [3] Owner ID:', ownerId);
                
                if (!token) {
                    console.error('❌ No token found');
                    this.showNotification('Please login first', 'error');
                    this.$router.push('/login');
                    return;
                }
                
                console.log('🌐 [4] Calling API:', `/listings/owners/${ownerId}`);
                
                const response = await fetch(API_ENDPOINTS.OWNER_BY_ID(ownerId), {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    console.log('✅ [5] API Response:', data);
                    
                    this.owner = data.data || data;
                    console.log('👤 [6] Owner data loaded:', this.owner);
                    
                    // جلب البروبيرتيز الخاصة بهذا المالك فقط
                    console.log('🏠 [7] Fetching properties for this owner...');
                    await this.fetchOwnerProperties();
                    
                } else {
                    console.error('❌ [8] Failed to fetch owner:', response.status);
                    throw new Error(`Failed to fetch owner: ${response.status}`);
                }
                
            } catch (error) {
                console.error('❌ [9] Error in fetchOwner:', error);
                this.error = error.message;
                this.showNotification('Failed to load owner data', 'error');
            } finally {
                this.loading = false;
                console.log('🏁 [10] fetchOwner completed');
            }
        },

        async fetchOwnerProperties() {
            console.log('🔄 [11] Starting fetchOwnerProperties...');
            try {
                this.propertiesLoading = true;
                const token = localStorage.getItem('token');
                const ownerId = this.$route.params.id;
                
                console.log('📝 [12] Parameters:', { 
                    token: !!token, 
                    ownerId 
                });
                
                // ✅ **استخدام الـ endpoint الرئيسي**
                const endpoint = `/api/listings/properties?owner_id=${ownerId}`;
                console.log('🌐 [13] Using endpoint:', endpoint);
                
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });

                console.log('📡 [14] Response status:', response.status);

                if (response.ok) {
                    const data = await response.json();
                    console.log('✅ [15] API Response:', data);
                    
                    // ✅ **معالجة جميع الأشكال المحتملة للرد**
                    if (data.data && Array.isArray(data.data)) {
                        // الحالة 1: {data: [], ...}
                        this.properties = data.data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from data.data`);
                    } else if (Array.isArray(data)) {
                        // الحالة 2: []
                        this.properties = data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties (direct array)`);
                    } else if (data.status && data.data && Array.isArray(data.data)) {
                        // الحالة 3: {status: true, data: [], ...}
                        this.properties = data.data;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from status.data`);
                    } else if (data.results && Array.isArray(data.results)) {
                        // الحالة 4: {results: [], ...}
                        this.properties = data.results;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from results`);
                    } else if (data.listings && Array.isArray(data.listings)) {
                        // الحالة 5: {listings: [], ...}
                        this.properties = data.listings;
                        console.log(`✅ [16] Loaded ${this.properties.length} properties from listings`);
                    } else {
                        console.warn('⚠️ [17] No properties found or unexpected structure:', data);
                        this.properties = [];
                    }
                    
                } else if (response.status === 403) {
                    console.error('❌ [18] Access forbidden (403)');
                    await this.fetchAllPropertiesAndFilter(ownerId, token);
                    
                } else {
                    console.error('❌ [19] Failed with status:', response.status);
                    const errorText = await response.text();
                    console.error('📄 [20] Error response:', errorText);
                }
                
            } catch (error) {
                console.error('❌ [21] Error in fetchOwnerProperties:', error);
                this.properties = [];
                this.showNotification('Failed to load owner properties', 'error');
            } finally {
                this.propertiesLoading = false;
                console.log('✅ [22] propertiesLoading set to false');
                console.log('📊 [23] Final properties count:', this.properties.length);
            }
        },

        async fetchAllPropertiesAndFilter(ownerId, token) {
            try {
                console.log('🔄 [24] Fetching all properties to filter...');
                
                const response = await fetch(`/api/listings/properties?per_page=100`, {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    let allProperties = [];
                    if (data.data && Array.isArray(data.data)) {
                        allProperties = data.data;
                    } else if (Array.isArray(data)) {
                        allProperties = data;
                    }
                    
                    this.properties = allProperties.filter(property => {
                        return (
                            (property.owner_id && property.owner_id.toString() === ownerId.toString()) ||
                            (property.owner && property.owner.id && property.owner.id.toString() === ownerId.toString()) ||
                            (property.owner_id === parseInt(ownerId))
                        );
                    });
                    
                    console.log(`✅ [25] Filtered ${this.properties.length} properties for owner ${ownerId}`);
                    
                } else {
                    console.error('❌ [26] Failed to fetch all properties:', response.status);
                }
                
            } catch (error) {
                console.error('❌ [27] Error in fetchAllPropertiesAndFilter:', error);
            }
        },

        // وظائف مساعدة للعرض
        getPropertyImage(property) {
            if (!property) return this.getDefaultPropertyImage();
            
            if (property.main_image) return property.main_image;
            if (property.thumbnail) return property.thumbnail;
            if (property.gallery_images && Array.isArray(property.gallery_images) && property.gallery_images.length > 0) {
                const firstImage = property.gallery_images[0];
                return firstImage.image_url || firstImage.url || firstImage;
            }
            if (property.images && Array.isArray(property.images) && property.images.length > 0) {
                const firstImage = property.images[0];
                return firstImage.url || firstImage;
            }
            
            return this.getDefaultPropertyImage();
        },

        getDefaultPropertyImage() {
            return 'https://via.placeholder.com/400x300/e9ecef/6c757d?text=Property';
        },
  getLocation(property)  {
      let loc = property.area || property.location;
      if (!loc) return 'Location not specified';
    
      const parts = loc.split(',').map(p => p.trim());
      parts.shift(); 
      return parts.join(', ');
    },
     getAreaUnit(property)  {
      return property.size_sqft ? 'Sqft' : 'Sqm';
    },
     getAgentName (agent) {
      if (!agent) return 'Unknown Agent';
      if (agent.name) return agent.name;
      if (agent.first_name && agent.last_name) return `${agent.first_name} ${agent.last_name}`;
      if (agent.first_name) return agent.first_name;
      return 'Unknown Agent';
    },
        getPropertyLocation(property) {
            if (!property) return 'Location not specified';
            
            if (property.area) return property.area;
            if (property.area_name) return property.area_name;
            if (property.location) {
                let loc = property.location;
                const parts = loc.split(',').map(p => p.trim());
                parts.shift(); 
                return parts.join(', ');
            }
            
            return 'Location not specified';
        },

        getPropertyType(property) {
            if (!property) return 'Property';
            
            if (property.property_type ) return property.property_type;
            if (property.property_type_name) return property.property_type_name;
            if (property.type) return property.type;
            
            return 'Property';
        },

        formatPrice(price) {
            if (!price || price === 0) return '0';
            return new Intl.NumberFormat().format(price);
        },

        handlePropertyImageLoad(event) {
            // يمكن إضافة تأثيرات تحميل الصور إذا لزم الأمر
        },

        handlePropertyImageError(event) {
            event.target.src = this.getDefaultPropertyImage();
        },

        getLocationLabel() {
            if (!this.owner) return 'Location';
            if (this.owner.nationality === 'UAE') {
                return 'Country';
            } else if (this.owner.residency_status === 'resident') {
                return 'Emirate';
            } else if (this.owner.residency_status === 'non_resident') {
                return 'Country';
            }
            return 'Location';
        },

        getDocumentList() {
            if (!this.owner) return [];
            
            const documents = [];
            
            if (this.owner.id_front_path || this.owner.id_front_url) {
                documents.push({
                    title: 'ID Front',
                    type: 'id_front',
                    url: this.owner.id_front_path || this.owner.id_front_url
                });
            }
            
            if (this.owner.id_back_path || this.owner.id_back_url) {
                documents.push({
                    title: 'ID Back',
                    type: 'id_back',
                    url: this.owner.id_back_path || this.owner.id_back_url
                });
            }
            
            if (this.owner.visa_copy_path || this.owner.visa_copy_url) {
                documents.push({
                    title: 'Visa Copy',
                    type: 'visa',
                    url: this.owner.visa_copy_path || this.owner.visa_copy_url
                });
            }
            
            if (this.owner.passport_copy_path || this.owner.passport_copy_url) {
                documents.push({
                    title: 'Passport Copy',
                    type: 'passport',
                    url: this.owner.passport_copy_path || this.owner.passport_copy_url
                });
            }
            // Additional documents
            if (this.owner.additional_documents && Array.isArray(this.owner.additional_documents)) {
                this.owner.additional_documents.forEach(doc => {
                    documents.push({
                        title: doc.name || 'Document',
                        type: 'additional',
                        url: doc.url
                    });
                });
            }
            return documents;
        },

        getDocumentCount() {
            return this.getDocumentList().length;
        },

        getDocumentIcon(type) {
            const icons = {
                'id_front': 'lucide:id-card',
                'id_back': 'lucide:id-card',
                'visa': 'lucide:file-text',
                'passport': 'mdi:passport',
                'additional': 'lucide:file-plus'
            };
            return icons[type] || 'lucide:file';
        },

        viewDocument(url) {
            if (url) {
                window.open(url, '_blank');
            } else {
                this.showNotification('Document not available', 'warning');
            }
        },

        downloadDocument(url, filename) {
            if (url) {
                const link = document.createElement('a');
                link.href = url;
                link.download = filename || 'document';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                this.showNotification('Document not available for download', 'warning');
            }
        },

        async downloadAllDocuments() {
            const documents = this.getDocumentList();
            if (documents.length === 0) {
                this.showNotification('No documents available for download', 'warning');
                return;
            }

            this.showNotification(`Downloading ${documents.length} document(s)...`, 'info');
            
            for (const doc of documents) {
                this.downloadDocument(doc.url, doc.title);
            }
        },

        showNotification(message, type = 'info') {
            console.log(`📢 Notification [${type}]: ${message}`);
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                alert(`${type.toUpperCase()}: ${message}`);
            }
        }
    }
};
</script>

<style scoped>
/* ============ TABS ============ */
.owner-tabs .nav-link {
    border-radius: 8px;
    padding: 10px 20px;
    margin-right: 10px;
    border: 1px solid #dee2e6;
    background-color: #f8f9fa;
    color: #6c757d;
}

.owner-tabs .nav-link.active {
    background-color: #0B0736;
    color: white;
    border-color: #0B0736;
}

/* ============ INFO GRID ============ */
.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f1f1;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-label {
    font-weight: 500;
    color: #6c757d;
    font-size: 13px;
}

.info-value {
    font-size: 14px;
    text-align: right;
}

/* ============ DOCUMENT ITEMS ============ */
.document-item {
    transition: all 0.3s ease;
    border: 1px solid #dee2e6;
}

.document-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border-color: #0B0736;
}

.document-icon {
    color: #0B0736;
}

.document-title {
    font-size: 14px;
    font-weight: 600;
    color: #343a40;
}

/* ============ PROPERTY CARDS ============ */
/* تحسينات الـ CSS للصور */
/*.property-image img {*/
/*  height: 230px;*/
/*  transition: transform 0.4s ease;*/
/*  opacity: 0;*/
/*  transition: opacity 0.3s ease;*/
/*}*/

/*.property-image img.loaded {*/
/*  opacity: 1;*/
/*}*/

/* تحسين الـ performance للـ animations */
.property-card {
  will-change: transform;
}

.property-image img {
  will-change: transform;
  height:100%;
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

.badge-status {
  position: absolute;
  top: 14px;
  left: 14px;
  background: #1e1e1e;
  color: #fff;
  font-size: 0.75rem;
  padding: 5px 12px;
  border-radius: 10px;
  letter-spacing: 0.3px;
}

/* Converted Badge */
.badge-converted {
  position: absolute;
  top: 14px;
  right: 14px;
  background: rgba(40, 167, 69, 0.9);
  color: #fff;
  font-size: 0.75rem;
  padding: 5px 12px;
  border-radius: 10px;
  letter-spacing: 0.3px;
  font-weight: 500;
  backdrop-filter: blur(4px);
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
}

.view-more-btn {
  display: inline-block;
  text-align: center;
  width: 100%;
  background: #733E87;
  color: #ffffff;
  font-weight: 500;
  border-radius: 10px;
  padding: 8px 0;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.3s;
}

.view-more-btn:hover {
  background: #111;
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

.converted-btn {
  background: #e9ecef !important;
  color: #6c757d !important;
  cursor: not-allowed;
  border: none;
}

.converted-btn:hover {
  background: #e9ecef !important;
  color: #6c757d !important;
}

/* Pagination Styles */
.pagination {
  margin-bottom: 0;
}

.page-item.active .page-link {
  background-color: #1e1e1e;
  border-color: #1e1e1e;
  color: white;
}

.page-link {
  color: #333;
  border: 1px solid #dee2e6;
  padding: 8px 16px;
  margin: 0 4px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.page-link:hover {
  background-color: #f8f9fa;
  border-color: #dee2e6;
  color: #333;
}

.page-item.disabled .page-link {
  color: #6c757d;
  background-color: #fff;
  border-color: #dee2e6;
}
/* Status Toggle Buttons */
.status-toggle-buttons {
  display: flex;
  gap: 12px;
  padding: 0 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.status-btn {
  padding: 10px 20px;
  border: 2px solid #e9ecef;
  background: white;
  border-radius: 12px;
  color: #6c757d;
  font-weight: 500;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.status-btn:hover {
  border-color: #733E87;
  color: #733E87;
}

.status-btn.active {
  background: #733E87;
  border-color: #733E87;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

/* تحسين التنسيق للشاشات الصغيرة */
@media (max-width: 768px) {
  .status-toggle-buttons {
    flex-direction: column;
  }
  
  .status-btn {
    justify-content: center;
  }
}

@media (max-width: 1200px) {
  .status-toggle-buttons {
    gap: 8px;
  }
  
  .status-btn {
    padding: 8px 16px;
    font-size: 0.9rem;
  }
}
.status-badges {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-direction: column;
  gap: 5px;
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

/* Card States */
.sold-card {
  opacity: 0.8;
  border: 2px solid #28a745;
}

.archived-card {
  opacity: 0.8;
  border: 2px solid #ffc107;
}

.sold-image {
  filter: grayscale(30%) sepia(30%);
}

.archived-image {
  filter: grayscale(20%);
}

.inactive-image {
  filter: grayscale(40%);
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
  box-shadow: none; /* إزالة الظل الداخلي إذا كان موجودًا */
}

.property-card-link .view-more-btn {
  display: inline-block;
  pointer-events: none; /* لمنع النقر على الزر نفسه بشكل منفصل */
}
.icons{
    gap:2px
}
/* ============ CARDS ============ */
.card {
    border-radius: 0.75rem;
    transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid #e9ecef;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-radius: 0.75rem 0.75rem 0 0 !important;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 1rem;
}

.card-body {
    padding: 1rem;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 375px) {
    .property-image {
        height: 8rem;
    }
    
    .property-content {
        padding: 0.75rem;
    }
    
    .property-price .price {
        font-size: 1rem;
    }
    
    .property-title {
        font-size: 0.8125rem;
        height: 2.25rem;
    }
    
    .detail-item i {
        font-size: 0.875rem;
    }
    
    .detail-item small {
        font-size: 0.5625rem;
    }
    
    .owner-tabs .nav-link {
        padding: 0.375rem 0.5rem;
        font-size: 0.75rem;
        margin-right: 5px;
    }
}

@media (min-width: 376px) and (max-width: 575px) {
    .property-image {
        height: 9rem;
    }
    
    .detail-item small {
        font-size: 0.6875rem;
    }
}

@media (min-width: 576px) and (max-width: 767px) {
    .owner-tabs .nav-link {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .property-image {
        height: 10rem;
    }
    
    .property-title {
        font-size: 0.875rem;
    }
}

@media (min-width: 768px) and (max-width: 991px) {
    .property-image {
        height: 11rem;
    }
    
    .property-title {
        font-size: 0.9375rem;
    }
}

@media (min-width: 992px) {
    .property-image {
        height: 12rem;
    }
}

@media (min-width: 1200px) {
    .property-image {
        height: 13rem;
    }
}
</style>