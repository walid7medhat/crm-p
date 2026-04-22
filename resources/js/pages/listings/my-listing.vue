<template>
  <div class="container-fluid mt-4">

    <div class="top-search-toolbar mb-3">
      <div class="top-search-col">
        <SearchBar
          @filters-changed="handleFiltersChanged"
          @status-changed="setStatus"
          :initial-filters="initialFilters"
          :result-count="pagination?.total || filteredProperties.length"
          :show-status-tabs="true"
          :active-status="activeStatus"
        />
      </div>
      <div class="top-export-col">
        <button type="button" class="export-active-btn" :disabled="isExporting" @click="exportActiveListingsToExcel">
          <i class="ri-file-excel-2-line me-1"></i>
          {{ isExporting ? 'Exporting...' : 'Export Active Listings' }}
        </button>
      </div>
    </div>
    
    <!-- Properties Grid -->
    <div class="row gx-4 gy-4 p-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status">
          
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading properties...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredProperties.length === 0" class="col-12 text-center py-5">
        <i class="ri-home-4-line display-1 text-muted"></i>
        <h4 class="mt-3 text-muted">No properties found</h4>
        <p class="text-muted">Try adjusting your search filters or status</p>
      </div>

      <!-- Properties Grid -->
      <div
        v-else
        v-for="(property, index) in filteredProperties"
        :key="property.id || index"
        class="col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600"
      >
       <router-link
            :to="`/property-details/${property.id}`"
            class="property-card-link"
            :class="{ 'converted-card': property.status === 'converted' }"
          >
          <div class="property-card" :class="{ 
            // 'converted-card': property.status === 'converted',
            'inactive-card': !property.is_active 
          }">
            <!-- Image -->
            <div class="property-image position-relative">
              <img 
                :src="getPropertyImage(property)" 
                :alt="property.title"
                class="w-100 object-fit-cover" 
                :class="{ 
                  // 'converted-image': property.status === 'converted',
                  'inactive-image': !property.is_active 
                }"
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
                  <span v-if="property.is_hot_deal== 'Yes'" class="badge-off_plan">
                    Hot Deal
                  </span>
                  <span v-if="property.occupancy_status && property.completion_status != 'Under Construction'" class="badge-occupancy_status">
                    {{property.occupancy_status}}
                  </span>
                </div>
              <!-- Images Count Badge Only -->
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
              <p class="property-location mb-3" :title="property.area">
                <i class="ri-map-pin-line me-1"></i>{{ property.area }}
              </p>

               <div class="property-details d-flex justify-content-between text-muted small mb-3">
                  <span class="d-flex justify-content-between icons">
                      <img :src="propertyIcon" class="imgicon"/>
                      <!--<i class="ri-building-4-line me-1"></i>-->
                      <span>
                      {{ getPropertyType(property) }}
                      </span>
                      
                </span>
                 <span class="d-flex justify-content-between icons" v-if="!property.property_type.toLowerCase().includes('plot') && !property.property_type.toLowerCase().includes('land') && property.number_of_bedrooms !== null && property.number_of_bedrooms !== undefined">
                      <img :src="bedIcon" class="imgicon"/>
                      <span>
                        {{ property.number_of_bedrooms == 0 ? 'Studio' : property.number_of_bedrooms }}
                      </span>
                    </span>

                  <span class="d-flex justify-content-between icons" v-if="!property.property_type.toLowerCase().includes('plot') && !property.property_type.toLowerCase().includes('land') && property.number_of_bathrooms !== null && property.number_of_bathrooms !== undefined && property.number_of_bathrooms!=0">
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
              
             <div class="d-flex align-items-end justify-between">
                <p class="property-agent text-muted small mb-2" v-if="property.agent">
                      Listed by: {{ (getAgentName(property.agent) || '').length > 20
                                        ? (getAgentName(property.agent) || '').slice(0, 20) + '...'
                                        : getAgentName(property.agent)
                                    }}

                </p>
                <div class="property-listed-date mb-2">
                    <i class="ri-calendar-line me-1"></i>
                    <small class="text-muted">Listed at: {{ formatDate(property.created_at) }}</small>
                  </div>
                </div>

              <!-- Action Buttons -->
              <div class="d-flex gap-2">
                <router-link
              
                  :to="`/property-details/${property.id}`"
                  class="view-more-btn flex-grow-1"
                >
                  View Details
                </router-link>
                
                <!-- Status Toggle Button -->
                <!-- <button
                  v-if="property.status !== 'converted'"
                  class="status-toggle-btn"
                  :class="property.is_active ? 'btn-active' : 'btn-inactive'"
                  @click="togglePropertyStatus(property)"
                  :title="property.is_active ? 'Deactivate Property' : 'Activate Property'"
                >
                  <i :class="property.is_active ? 'ri-eye-line' : 'ri-eye-off-line'"></i>
                </button>
                -->
                <!-- Disabled Button for converted properties -->
                
              </div>
            </div>
          </div>
       </router-link>
      </div>
    </div>

    <!-- Pagination -->
       <div v-if="pagination && pagination.total > pagination.per_page" class="row mt-4">
      <div class="col-12">
        <nav aria-label="Properties pagination">
          <ul class="pagination justify-content-center">
            <!-- Previous Button -->
            <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
              <button 
                class="page-link" 
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
              >
                <i class="ri-arrow-left-line"></i> Previous
              </button>
            </li>

            <!-- Page Numbers -->
            <li 
              v-for="page in displayedPages" 
              :key="page"
              class="page-item" 
              :class="{ active: page === pagination.current_page }"
            >
              <button 
                class="page-link" 
                @click="changePage(page)"
                :disabled="page === '...'"
              >
                {{ page }}
              </button>
            </li>

            <!-- Next Button -->
            <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
              <button 
                class="page-link" 
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
              >
                Next <i class="ri-arrow-right-line"></i>
              </button>
            </li>
          </ul>
        </nav>

        <!-- Pagination Info -->
        <div class="text-center text-white small mt-2">
          Showing {{ showingFrom }}-{{ showingTo }} of {{ pagination.total }} properties
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SearchBar from "./SearchBar.vue";
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

// Default images
// import property1 from "/assets/images/a.jpeg";
// import property2 from "/assets/images/b.jpeg";
// import property3 from "/assets/images/c.jpeg";
// import property4 from "/assets/images/a.jpeg";

export default {
  name: 'AllListings',
  components: { SearchBar, Breadcrumb },
  setup() {
      const property1 = "/assets/images/a.jpeg";
    const property2 = "/assets/images/b.jpeg";
    const property3 = "/assets/images/c.jpeg";
    const property4 = "/assets/images/a.jpeg";
    const route = useRoute();
    const router = useRouter();

    const properties = ref([]);
    const isLoading = ref(false);
    const currentFilters = ref({});
    const pagination = ref(null);
    const isExporting = ref(false);
    const activeStatus = ref('all'); // 'all', 'active', 'inactive'
    const initialFilters = ref(null);
   const propertyIcon = '/assets/icons/property-icon.svg';
  const bedIcon = '/assets/icons/bedroom-icon.svg';
  const bathIcon = '/assets/icons/bathroom-icon.svg';
  const sqftIcon = '/assets/icons/area-size.svg';
    // Default images array for fallback
    const defaultImages = [property1, property2, property3, property4];

    // Computed property for filtered properties based on status
  const filteredProperties = computed(() => {
      switch (activeStatus.value) {
        case 'all':
          return properties.value;
        case 'active':
          return properties.value.filter(property => property.is_active && !property.is_archived && property.status !== 'converted' &&
              property.status !== 'rented' &&
              property.status !== 'converted' && property.status !== 'draft');
        case 'inactive':
          return properties.value.filter(property => !property.is_active && !property.is_archived && property.status !== 'converted' &&
              property.status !== 'rented' &&
              property.status !== 'draft');
        case 'archived':
          return properties.value.filter(property => property.is_archived);
        case 'off_plan':
          return properties.value.filter(property => property.is_off_plan && property.is_off_plan=='Yes' );
        case 'draft':
          return properties.value.filter(property => property.status === 'draft');
       case 'sold':
          return properties.value.filter(p => p.status === 'converted');
        case 'rented': 
           return properties.value.filter(property => property.status === 'rented');

        case 'draft':
          return properties.value.filter(
            p => !p.is_active && !p.is_archived
          );
            default:
              return properties.value;
          }
        });

    // Status toggle functions
    const setStatus = (status) => {
      activeStatus.value = status;
        
      const apiFilters = convertFiltersToAPI(currentFilters.value); 
      fetchProperties(apiFilters, 1);
    };

  const togglePropertyStatus = async (property) => {
  try {
    const newStatus = !property.is_active;
    
    // Update locally immediately for better UX
    property.is_active = newStatus;
    
    // Send API request to update status - USE THE SPECIFIC ENDPOINT
    await api.patch(`/listings/properties/${property.id}/toggle-status`);
    
    console.log(`✅ Property ${newStatus ? 'activated' : 'deactivated'} successfully`);
    
  } catch (error) {
    console.error("❌ Error updating property status:", error.response || error);
    
    // Revert local change if API call fails
    property.is_active = !property.is_active;
    
    // Show error message
    alert('Failed to update property status. Please try again.');
  }
};

    const handleImageLoad = (event) => {
      event.target.classList.add('loaded');
    };

    const handleImageError = (event) => {
      event.target.src = defaultImages[0]; 
    };

    const getPropertyImage = (property) => {
      if (property.main_image) {
        return property.main_image.image_url || property.main_image;
      }
      
      if (property.gallery_images && property.gallery_images.length > 0) {
        const firstImage = property.gallery_images[0];
        return firstImage.image_url || firstImage.url || firstImage;
      }
      
      return defaultImages[Math.floor(Math.random() * defaultImages.length)];
    };

    // Computed properties for pagination display
    const showingFrom = computed(() => {
      if (!pagination.value) return 0;
      return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1;
    });

    const showingTo = computed(() => {
      if (!pagination.value) return 0;
      return Math.min(pagination.value.current_page * pagination.value.per_page, pagination.value.total);
    });

    const displayedPages = computed(() => {
      if (!pagination.value) return [];
      
      const current = pagination.value.current_page;
      const last = pagination.value.last_page;
      const delta = 2;
      const range = [];
      const rangeWithDots = [];

      for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
          range.push(i);
        }
      }

      let prev;
      for (let i of range) {
        if (prev) {
          if (i - prev === 2) {
            rangeWithDots.push(prev + 1);
          } else if (i - prev !== 1) {
            rangeWithDots.push('...');
          }
        }
        rangeWithDots.push(i);
        prev = i;
      }

      return rangeWithDots;
    });

    // Fetch properties from API
  // Fetch properties from API
const fetchProperties = async (filters = {}, page = 1) => {
  try {
    isLoading.value = true;
    
    // Build query parameters
    const params = {
      ...filters,
      page: page,
      per_page: 12,
      my_listings: true
    };

    switch (activeStatus.value) {
      case 'archived':
        params.is_archived = true;
        break;
      case 'sold':
        params.converted = true;
        break;
      case 'rented': 
        params.status = 'rented';
        break;
      case 'active':
        params.is_active = true;
        break;
      case 'inactive':
        params.is_active = false;
        break;
      case 'off_plan':
          params.status = 'off_plan';
          break;
      case 'draft':
          params.status = 'draft';
          break;
      default:
        break;
    }

    // Remove empty filters
    Object.keys(params).forEach(key => {
      if (params[key] === null || params[key] === undefined || params[key] === '') {
        delete params[key];
      }
    });

    console.log("📤 Fetching properties with params:", params);

    const response = await api.get("/listings/properties", { params });
    
    // Handle API response
    const responseData = response.data.data;
    const responseMeta = response.data.meta;
    
    if (Array.isArray(responseData)) {
      properties.value = responseData;
      pagination.value = responseMeta || {
        current_page: 1,
        last_page: 1,
        per_page: 12,
        total: responseData.length
      };
    } else {
      properties.value = [];
      pagination.value = null;
      console.warn("⚠️ Unexpected API response structure:", responseData);
    }

    console.log("✅ Properties loaded:", properties.value.length);
    console.log("📄 Pagination info:", pagination.value);
    
  } catch (error) {
    console.error("❌ Error fetching properties:", error.response || error);
    properties.value = [];
    pagination.value = null;
  } finally {
    isLoading.value = false;
  }
};

    // Change page
    const changePage = (page) => {
      if (page < 1 || page > pagination.value.last_page || page === '...') return;
        const apiFilters = convertFiltersToAPI(currentFilters.value); 
     fetchProperties(apiFilters, page);
    };

    const encodeFiltersToQuery = (filters) => {
        const activeFeatures = filters.selectedFeatures 
        ? Object.keys(filters.selectedFeatures).filter(key => filters.selectedFeatures[key] === true)
        : [];
      return {
        sale_rent: filters.saleRent || undefined,
        area_id: filters.area?.id || undefined,
        area_ids: Array.isArray(filters.area) && filters.area.length
          ? filters.area.map((a) => a?.id).filter(Boolean).join(',')
          : undefined,
        project_id: filters.project?.id || undefined,
        type_id: filters.propertyType?.id || undefined,
        type_ids: Array.isArray(filters.propertyTypes) && filters.propertyTypes.length
          ? filters.propertyTypes.map((p) => p?.id).filter(Boolean).join(',')
          : undefined,
        beds: filters.beds || undefined,
        beds_list: Array.isArray(filters.bedsList) && filters.bedsList.length ? filters.bedsList.join(',') : undefined,
        baths: filters.baths || undefined,
        baths_list: Array.isArray(filters.bathsList) && filters.bathsList.length ? filters.bathsList.join(',') : undefined,
        price_from: filters.priceFrom,
        price_to: filters.priceTo,
        size_from: filters.sizeFrom,
        size_to: filters.sizeTo,
        sort: filters.sort || undefined,
        ref: filters.referenceNumber || undefined,
        completion_status: filters.completionStatus?.value || undefined,
         additional_features: activeFeatures.length > 0 ? activeFeatures.join(',') : undefined,
      };
    };

    const decodeFiltersFromQuery = (query) => {
      const areaIds = query.area_ids
        ? query.area_ids.split(',').map((id) => Number(id)).filter((id) => !Number.isNaN(id))
        : (query.area_id ? [Number(query.area_id)] : []);
      const areas = areaIds.map((id) => ({ id }));
      const typeIds = query.type_ids
        ? query.type_ids.split(',').map((id) => Number(id)).filter((id) => !Number.isNaN(id))
        : (query.type_id ? [Number(query.type_id)] : []);
      const propertyTypes = typeIds.map((id) => ({ id }));
      const bedsList = query.beds_list ? query.beds_list.split(',').filter(Boolean) : (query.beds ? [query.beds] : []);
      const bathsList = query.baths_list ? query.baths_list.split(',').filter(Boolean) : (query.baths ? [query.baths] : []);
       const selectedFeatures = {};
      if (query.additional_features) {
        const features = query.additional_features.split(',');
        features.forEach(feature => {
          selectedFeatures[feature] = true;
        });
      }
      return {
        saleRent: query.sale_rent || 'All',
        area: areas.length ? areas : null,
        project: query.project_id ? { id: Number(query.project_id) } : null,
        propertyType: propertyTypes[0] || null,
        propertyTypes,
        beds: bedsList[0] || '',
        bedsList,
        baths: bathsList[0] || '',
        bathsList,
        priceFrom: query.price_from ? Number(query.price_from) : 0,
        priceTo: query.price_to ? Number(query.price_to) : 10000000,
        sizeFrom: query.size_from ? Number(query.size_from) : 0,
        sizeTo: query.size_to ? Number(query.size_to) : 10000,
        sort: query.sort || 'created_at_desc',
        referenceNumber: query.ref || '',
        completionStatus: query.completion_status
          ? { label: query.completion_status, value: query.completion_status }
          : null,
          selectedFeatures: selectedFeatures, 
      };
    };

    // Handle filters from SearchBar
    const handleFiltersChanged = (filters) => {
      console.log("🎯 Filters received:", filters);
      currentFilters.value = filters;
      initialFilters.value = filters;
      
      // Convert filters to API format
      const apiFilters = convertFiltersToAPI(filters);
      
      // Fetch properties with new filters (reset to page 1)
      fetchProperties(apiFilters, 1);

      // Persist filters in URL so Back restores them
      router.replace({
        query: {
          ...route.query,
          ...encodeFiltersToQuery(filters),
        },
      });
    };

    // Convert frontend filters to backend API format
    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};

      // Sale/Rent Filter
      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }

      // Area Filter
      if (Array.isArray(filters.area) && filters.area.length) {
        apiFilters.area_ids = filters.area.map((a) => a?.id).filter(Boolean);
      } else if (filters.area) {
        apiFilters.area_id = filters.area.id;
      }

      // Property Type Filter
      if (Array.isArray(filters.propertyTypes) && filters.propertyTypes.length) {
        const typeIds = filters.propertyTypes.map((p) => p?.id).filter(Boolean);
        apiFilters.property_type_ids = typeIds;
        if (typeIds.length === 1) {
          apiFilters.property_type_id = typeIds[0];
        }
      } else if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
     if (filters.referenceNumber && filters.referenceNumber.trim() !== "") {
        apiFilters.reference_number = filters.referenceNumber.trim();
      }
        if (filters.project) {
        apiFilters.project_id = filters.project.id;
      }
      // Bedrooms Filter
      const bedsList = Array.isArray(filters.bedsList) ? filters.bedsList : (filters.beds ? [filters.beds] : []);
      if (bedsList.length) {
        apiFilters.number_of_bedrooms_in = bedsList;
        if (bedsList.length === 1) {
          const firstBed = bedsList[0];
          apiFilters.number_of_bedrooms = firstBed === 'Studio' ? 'Studio' : parseInt(firstBed);
        }
      }
      const bathsList = Array.isArray(filters.bathsList) ? filters.bathsList : (filters.baths ? [filters.baths] : []);
      if (bathsList.length) {
        apiFilters.number_of_bathrooms_in = bathsList;
        if (bathsList.length === 1) {
          apiFilters.number_of_bathrooms = parseInt(bathsList[0]);
        }
      }
     if (filters.completionStatus && filters.completionStatus.value) {
        apiFilters.completion_status = filters.completionStatus.value;
      }
      // Price Range Filter
      if (filters.priceFrom > 0 || filters.priceTo < 10000000) {
        apiFilters.min_price = filters.priceFrom;
        apiFilters.max_price = filters.priceTo;
      }

      // Size Range Filter
      if (filters.sizeFrom > 0 || filters.sizeTo < 10000) {
        apiFilters.min_size = filters.sizeFrom;
        apiFilters.max_size = filters.sizeTo;
      }
       if (filters.selectedFeatures && Object.keys(filters.selectedFeatures).length > 0) {
            const activeFeatures = Object.keys(filters.selectedFeatures).filter(
              key => filters.selectedFeatures[key] === true
            );
            if (activeFeatures.length > 0) {
              apiFilters.additional_features = activeFeatures;
            }
          }

      // Sort
      if (filters.sort) {
        apiFilters.sort = mapSortToBackend(filters.sort);
      }

      return apiFilters;
    };

    // Map frontend sort options to backend
    const mapSortToBackend = (sortOption) => {
      // const sortMap = {
      //   'Most Recent': 'created_at_desc',
      //   'Price: Low to High': 'price_asc',
      //   'Price: High to Low': 'price_desc',
      //   'Size: Small to Large': 'size_asc', 
      //   'Size: Large to Small': 'size_desc'
      // };
      // return sortMap[sortOption] || 'created_at_desc';
      return sortOption;
    };

    // Helper functions for property display
    const formatPrice = (price) => {
      if (!price) return '0';
      return new Intl.NumberFormat().format(price);
    };
     const formatDate = (dateString) => {
      if (!dateString) return 'N/A';
      
      try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        });
      } catch (error) {
        return 'Invalid Date';
      }
    };

     const getLocation = (property) => {
      let loc = property.area || property.location;
      if (!loc) return 'Location not specified';
    
      const parts = loc.split(',').map(p => p.trim());
      parts.shift(); 
      return parts.join(', ');
    };
    

    const getPropertyType = (property) => {
      if (property.property_type ) return property.property_type;
      if (property.type) return property.type;
      return 'Property';
    };

    const getAreaUnit = (property) => {
      return property.size_sqft ? 'Sqft' : 'Sqm';
    };

    const getAgentName = (agent) => {
      if (!agent) return 'Unknown Agent';
      if (agent.name) return agent.name;
      if (agent.first_name && agent.last_name) return `${agent.first_name} ${agent.last_name}`;
      if (agent.first_name) return agent.first_name;
      return 'Unknown Agent';
    };

    const getValueByFlexibleKeys = (source, keys) => {
      if (!source || typeof source !== 'object') return '';
      const entries = Object.entries(source);
      const normalize = (value) => String(value || '').toLowerCase().replace(/[\s_-]/g, '');
      for (const key of keys) {
        const direct = source[key];
        if (direct !== undefined && direct !== null && String(direct).trim() !== '') return direct;
        const normalizedKey = normalize(key);
        const found = entries.find(([existingKey]) => normalize(existingKey) === normalizedKey);
        if (found && found[1] !== undefined && found[1] !== null && String(found[1]).trim() !== '') return found[1];
      }
      return '';
    };

    const parseOwnerInfo = (ownerInfo) => {
      if (!ownerInfo) return {};
      if (typeof ownerInfo === 'string') {
        try {
          const parsed = JSON.parse(ownerInfo);
          return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
          return {};
        }
      }
      return typeof ownerInfo === 'object' ? ownerInfo : {};
    };

    const deepFindByKey = (source, candidateKeys) => {
      const normalize = (value) => String(value || '').toLowerCase().replace(/[\s_-]/g, '');
      const wanted = candidateKeys.map((k) => normalize(k));
      const queue = [source];
      const seen = new Set();

      while (queue.length) {
        const node = queue.shift();
        if (!node || typeof node !== 'object' || seen.has(node)) continue;
        seen.add(node);

        for (const [k, v] of Object.entries(node)) {
          const normalizedKey = normalize(k);
          if (wanted.includes(normalizedKey) && v !== null && v !== undefined && String(v).trim() !== '') {
            return v;
          }
          if (v && typeof v === 'object') queue.push(v);
        }
      }

      return '';
    };

    const getOwnerName = (property) => {
      const ownerInfo = parseOwnerInfo(property?.ownerinformation || property?.owner_information || property?.ownerInfo);
      const ownerName =
        getValueByFlexibleKeys(ownerInfo, ['name', 'full_name', 'owner_name', 'owner-name']) ||
        deepFindByKey(ownerInfo, ['owner_name', 'owner-name', 'name', 'full_name']) ||
        getValueByFlexibleKeys(property?.owner || {}, ['name', 'full_name']) ||
        deepFindByKey(property?.owner || {}, ['name', 'full_name', 'owner_name']) ||
        getValueByFlexibleKeys(property || {}, ['owner_name', 'owner-name', 'landlord_name']);
      return ownerName || '-';
    };

    const getOwnerMobileNumber = (property) => {
      const ownerInfo = parseOwnerInfo(property?.ownerinformation || property?.owner_information || property?.ownerInfo);
      const ownerMobile =
        getValueByFlexibleKeys(ownerInfo, [
          'mobile',
          'mobile_number',
          'owner_mobile',
          'phone',
          'phone_number',
          'primary_phone',
          'Primary Phone',
        ]) ||
        deepFindByKey(ownerInfo, [
          'mobile',
          'mobile_number',
          'owner_mobile',
          'phone',
          'phone_number',
          'primary_phone',
          'primaryphone',
        ]) ||
        getValueByFlexibleKeys(property?.owner || {}, ['mobile', 'phone', 'phone_number', 'primary_phone']) ||
        deepFindByKey(property?.owner || {}, ['mobile', 'phone', 'phone_number', 'primary_phone']) ||
        getValueByFlexibleKeys(property || {}, ['owner_mobile', 'owner_phone', 'primary_phone', 'Primary Phone']);
      return ownerMobile || '-';
    };

    const getOwnerFieldsFromProperty = (property) => ({
      ownerName: getOwnerName(property),
      ownerMobileNumber: getOwnerMobileNumber(property),
    });

    const resolveOwnerFieldsForExport = async (property) => {
      const initial = getOwnerFieldsFromProperty(property);
      const hasOwnerName = initial.ownerName && initial.ownerName !== '-';
      const hasOwnerMobile = initial.ownerMobileNumber && initial.ownerMobileNumber !== '-';
      if ((hasOwnerName && hasOwnerMobile) || !property?.id) return initial;

      const endpointCandidates = [
        `/listings/properties/${property.id}`,
        `/listings/properties/show/${property.id}`,
      ];

      for (const endpoint of endpointCandidates) {
        try {
          const response = await api.get(endpoint);
          const payload =
            response?.data?.data?.property ||
            response?.data?.data ||
            response?.data?.property ||
            response?.data ||
            {};
          const merged = { ...property, ...payload };
          const resolved = getOwnerFieldsFromProperty(merged);
          const resolvedName = resolved.ownerName && resolved.ownerName !== '-';
          const resolvedMobile = resolved.ownerMobileNumber && resolved.ownerMobileNumber !== '-';
          if (resolvedName || resolvedMobile) return resolved;
        } catch (error) {
          // Try the next endpoint shape.
        }
      }

      return initial;
    };

    const getBedroomLabel = (property) => {
      const value = property?.number_of_bedrooms;
      if (value === null || value === undefined || value === '') return '-';
      return Number(value) === 0 ? 'Studio' : String(value);
    };

    const toCell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
<<<<<<< HEAD
// Add this inside setup() function, after your refs
const canSeeSensitiveData = computed(() => {
  try {
    const userStr = localStorage.getItem('user');
    if (!userStr) return false;
    const user = JSON.parse(userStr);
    
    // Check if user is Team Lead (admin_parent_name exists OR role_name is team_lead)
    return  user.role_name != 'Team Lead' && user.role_name != 'Manager' && user.role_name != 'Admin';
  } catch (error) {
    console.error('Error checking user permissions:', error);
    return false;
  }
});
const exportActiveListingsToExcel = async () => {
  try {
    isExporting.value = true;

    const baseFilters = convertFiltersToAPI(currentFilters.value || {});
    const rows = [];
    let page = 1;
    let lastPage = 1;

    do {
      const params = {
        ...baseFilters,
        page,
        per_page: 200,
        my_listings: true,
        is_active: true,
      };

      delete params.is_archived;
      delete params.converted;

      const response = await api.get('/listings/properties', { params });
      const data = Array.isArray(response?.data?.data) ? response.data.data : [];
      const meta = response?.data?.meta || {};
      lastPage = Number(meta.last_page || 1);

      const onlyActiveRows = data.filter((property) =>
        property?.is_active &&
        !property?.is_archived &&
        property?.status !== 'converted' &&
        property?.status !== 'rented' &&
        property?.status !== 'draft'
      );

      rows.push(...onlyActiveRows);
      page += 1;
    } while (page <= lastPage);

    // Define headers based on user permission
    const headers = canSeeSensitiveData.value
      ? [
=======

    const exportActiveListingsToExcel = async () => {
      try {
        isExporting.value = true;

        const baseFilters = convertFiltersToAPI(currentFilters.value || {});
        const rows = [];
        let page = 1;
        let lastPage = 1;

        do {
          const params = {
            ...baseFilters,
            page,
            per_page: 200,
            my_listings: true,
            is_active: true,
          };

          delete params.is_archived;
          delete params.converted;

          const response = await api.get('/listings/properties', { params });
          const data = Array.isArray(response?.data?.data) ? response.data.data : [];
          const meta = response?.data?.meta || {};
          lastPage = Number(meta.last_page || 1);

          const onlyActiveRows = data.filter((property) =>
            property?.is_active &&
            !property?.is_archived &&
            property?.status !== 'converted' &&
            property?.status !== 'rented' &&
            property?.status !== 'draft'
          );

          rows.push(...onlyActiveRows);
          page += 1;
        } while (page <= lastPage);

        const headers = [
>>>>>>> 809e6aa7 (fix my-listing export owner fields)
          'Project name',
          'Unit number',
          'Size per sqft',
          'Selling price',
          'Status',
<<<<<<< HEAD
          'Owner name',
          'Owner mobile number',
          'Bedroom',
          'Type',
          'Created at',
        ]
      : [
          'Project name',
          'Size per sqft',
          'Selling price',
          'Status',
          'Bedroom',
          'Type',
          'Created at',
        ];

    const lines = await Promise.all(rows.map(async (property) => {
      const projectName = property?.project?.name || property?.project_name || property?.title || '-';
      const unitNumber = property?.unit_number || property?.reference_number || '-';
      const sizePerSqft = property?.size_sqft || '-';
      const sellingPrice = property?.price || 0;
      const status = property?.is_active ? 'Active' : 'Inactive';
      const { ownerName, ownerMobileNumber } = await resolveOwnerFieldsForExport(property);
      const createdAt = property?.created_at ? formatDate(property.created_at) : '-';
      const bedroom = getBedroomLabel(property);
      const type = getPropertyType(property);

      if (canSeeSensitiveData.value) {
        //  - sees all data including sensitive
        return [
          toCell(projectName),
          toCell(unitNumber),
          toCell(sizePerSqft),
          toCell(sellingPrice),
          toCell(status),
          toCell(ownerName),
          toCell(ownerMobileNumber),
          toCell(bedroom),
          toCell(type),
          toCell(createdAt),
        ].join(',');
      } else {
        // Regular user - NO unit number, NO owner details
        return [
          toCell(projectName),
          toCell(sizePerSqft),
          toCell(sellingPrice),
          toCell(status),
          toCell(bedroom),
          toCell(type),
          toCell(createdAt),
        ].join(',');
      }
    }));

    const csv = [headers.map(toCell).join(','), ...lines].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `my-listings-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Error exporting listings:', error?.response || error);
    alert('Failed to export listings. Please try again.');
  } finally {
    isExporting.value = false;
  }
};
=======
          'owner name',
          'owner mobile number',
          'bedroom',
          'type',
          'created at',
        ];

        const lines = await Promise.all(rows.map(async (property) => {
          const projectName = property?.project?.name || property?.project_name || property?.title || '-';
          const unitNumber = property?.unit_number || property?.reference_number || '-';
          const sizePerSqft = property?.size_sqft || '-';
          const sellingPrice = property?.price || 0;
          const status = property?.is_active ? 'Active' : 'Inactive';
          const { ownerName, ownerMobileNumber } = await resolveOwnerFieldsForExport(property);
          const createdAt = property?.created_at ? formatDate(property.created_at) : '-';
          const bedroom = getBedroomLabel(property);
          const type = getPropertyType(property);

          return [
            toCell(projectName),
            toCell(unitNumber),
            toCell(sizePerSqft),
            toCell(sellingPrice),
            toCell(status),
            toCell(ownerName),
            toCell(ownerMobileNumber),
            toCell(bedroom),
            toCell(type),
            toCell(createdAt),
          ].join(',');
        }));

        const csv = [headers.map(toCell).join(','), ...lines].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `my-active-listings-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
      } catch (error) {
        console.error('❌ Error exporting active listings:', error?.response || error);
        alert('Failed to export active listings. Please try again.');
      } finally {
        isExporting.value = false;
      }
    };
>>>>>>> 809e6aa7 (fix my-listing export owner fields)

    // Fetch initial properties on component mount
    onMounted(() => {
      const hasQuery = Object.keys(route.query).length > 0;
      if (hasQuery) {
        const filters = decodeFiltersFromQuery(route.query);
        currentFilters.value = filters;
        initialFilters.value = filters;
        const apiFilters = convertFiltersToAPI(filters);
        fetchProperties(apiFilters, 1);
      } else {
        fetchProperties();
      }
    });

    return {
      properties,
      filteredProperties,
        propertyIcon,
      bedIcon,
      bathIcon,
      sqftIcon,
      isLoading,
      pagination,
      isExporting,
      activeStatus,
      initialFilters,
      showingFrom,
      showingTo,
      displayedPages,
      setStatus,
      togglePropertyStatus,
      handleFiltersChanged,
      changePage,
      getPropertyImage,
      formatPrice,
      formatDate,
      getLocation,
      getPropertyType,
      getAreaUnit,
      getAgentName,
      exportActiveListingsToExcel,
      handleImageLoad,
      handleImageError
    };
  }
};
</script>

<style scoped>
.property-image img {
  height: 230px;
  transition: transform 0.4s ease;
  /* opacity: 0; */
  transition: opacity 0.3s ease;
}

.top-export-col {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}

.export-active-btn {
  border: 1px solid #0d6efd;
  background: #0d6efd;
  color: #fff;
  border-radius: 10px;
  height: 40px;
  padding: 0 14px;
  font-size: 13px;
  font-weight: 600;
}

.export-active-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
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

/* Status Toggle Buttons */
.top-search-toolbar {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  align-items: stretch;
}

.top-search-col {
  flex: 1 1 auto;
  min-width: 0;
}

.top-status-col {
  flex: 0 0 auto;
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
  display: flex;
  align-items: stretch;
  margin-top: 6px;
}

.status-toggle-buttons {
  display: flex;
  gap: 12px;
  padding: 0 16px;
  margin-bottom: 20px;
}

.status-toggle-buttons-compact {
  display: grid;
  grid-template-columns: repeat(2, minmax(120px, 1fr));
  gap: 8px;
  padding: 8px;
  margin-bottom: 0;
  width: 290px;
  background: #fff;
  border: 1px solid #e6ebf3;
  border-radius: 14px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
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
}

.status-toggle-buttons-compact .status-btn {
  padding: 8px 10px;
  border-radius: 10px;
  border-width: 1px;
  border-color: #dbe2ee;
  background: #f8fafc;
  font-size: 0.72rem;
  font-weight: 600;
  color: #475569;
  gap: 4px;
  line-height: 1;
  min-height: 34px;
  letter-spacing: 0.01em;
  justify-content: center;
}

.status-btn:hover {
  border-color: #faa300;
  color: #faa300;
  background: #fff9ef;
}

.status-btn.active {
  background: linear-gradient(135deg, #faa300 0%, #ffb224 100%);
  border-color: #f1a10a;
  color: white;
  box-shadow: 0 4px 10px rgba(250, 163, 0, 0.22);
}

/* Images Badge Only */
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
  background: #FAA300;
  color: #ffffff;
  font-weight: 500;
  border-radius: 10px;
  padding: 8px 0;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.3s;
  border: none;
  flex-grow: 1;
}

.view-more-btn:hover {
  background: #111;
  color: #fff;
}

/* Status Toggle Button */
.status-toggle-btn {
  padding: 8px 12px;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
}

.btn-active {
  background: #28a745;
  color: white;
}

.btn-active:hover {
  background: #218838;
}

.btn-inactive {
  background: #6c757d;
  color: white;
}

.btn-inactive:hover {
  background: #545b62;
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

.inactive-card {
  opacity: 0.8;
}

.converted-image {
  filter: grayscale(20%);
}

.inactive-image {
  filter: grayscale(40%);
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

/* Responsive Design */
@media (max-width: 768px) {
  .top-search-toolbar {
    flex-direction: column;
    gap: 10px;
  }

  .top-status-col {
    width: 100%;
  }

  .status-toggle-buttons {
    flex-direction: column;
  }

  .status-toggle-buttons-compact {
    width: 100%;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  
  .status-btn {
    justify-content: center;
  }
  
  .property-content .d-flex.gap-2 {
    flex-direction: column;
  }
  
  .status-toggle-btn {
    width: 100%;
    margin-top: 8px;
  }
}
.status-badges {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-direction: row;
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
.badge-off_plan {
  background: #faa300;
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
  box-shadow: none; 
}

.property-card-link .view-more-btn {
  display: inline-block;
  pointer-events: none; 
}
.icons{
    gap:2px
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
.badge-occupancy_status{
    background: #EDEBEB !important;
    color: #01062C !important;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;  
}
/*============*/


.status-badge {
   background: #01062d ;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
}



.property-listed-date {
  font-size: 0.75rem;
  /*color: #666;*/
  margin-bottom:0 !important;
}

.property-listed-date i {
  font-size: 0.8rem;
  color: #FAA300;
}
.justify-between{
    justify-content:space-between;
}
.icons img{
    width: 17px;
    height: 17px;
}
</style>