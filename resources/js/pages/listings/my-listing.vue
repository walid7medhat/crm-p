<template>
  <div class="container-fluid mt-4">

    
    <!-- Search Bar -->
    <SearchBar @filters-changed="handleFiltersChanged" />
    
    <!-- Status Toggle Buttons -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="status-toggle-buttons">
          <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'all' }"
            @click="setStatus('all')"
          >
            <i class="ri-list-check"></i>
            All My Listings
          </button>
          <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'active' }"
            @click="setStatus('active')"
          >
            <i class="ri-checkbox-circle-line"></i>
            Active
          </button>
          <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'inactive' }"
            @click="setStatus('inactive')"
          >
            <i class="ri-close-circle-line"></i>
            Inactive
          </button>
           <!-- <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'archived' }"
            @click="setStatus('archived')"
          >
            <i class="ri-archive-line"></i>
            Archived
          </button> -->
          <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'sold' }"
            @click="setStatus('sold')"
          >
            <i class="ri-checkbox-circle-fill"></i>
            Sold Out
          </button>
          
          <button 
            class="status-btn" 
            :class="{ active: activeStatus === 'draft' }"
            @click="setStatus('draft')"
          >
            <i class="fa fa-pencil-alt"></i>

            Draft
          </button>
         
          
        </div>
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
                <span v-else-if="property.status === 'draft'" class="badge-sold">
                  <i class="ri-checkbox-circle-fill me-1"></i>draft
                </span>
                <span v-if="property.is_archived" class="badge-archived">
                  <i class="ri-archive-fill me-1"></i>Archived
                </span>
                <span v-if="!property.is_active" class="badge-inactive">
                  <i class="ri-eye-off-fill me-1"></i>Inactive
                </span>
                 <span  v-if="property.is_hot_deal== 'Yes'" class="badge-off_plan">
                 Hot Deal
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
                      {{ property.number_of_bedrooms == 0 ?'Studio':property.number_of_bedrooms}}
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
        <div class="text-center text-muted small mt-2">
          Showing {{ showingFrom }}-{{ showingTo }} of {{ pagination.total }} properties
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
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
    const properties = ref([]);
    const isLoading = ref(false);
    const currentFilters = ref({});
    const pagination = ref(null);
    const activeStatus = ref('all'); // 'all', 'active', 'inactive'
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
          return properties.value.filter(property => property.is_active && !property.is_archived && property.status !== 'converted' && property.status !== 'converted' && property.status !== 'draft');
        case 'inactive':
          return properties.value.filter(property => !property.is_active && !property.is_archived && property.status !== 'converted' && property.status !== 'draft');
        case 'archived':
          return properties.value.filter(property => property.is_archived);
        case 'off_plan':
          return properties.value.filter(property => property.is_off_plan && property.is_off_plan=='Yes' );
        case 'draft':
          return properties.value.filter(property => property.status === 'draft');
       case 'sold':
          return properties.value.filter(p => p.status === 'converted');

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
        fetchProperties(currentFilters.value, 1); 
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
      fetchProperties(currentFilters.value, page);
    };

    // Handle filters from SearchBar
    const handleFiltersChanged = (filters) => {
      console.log("🎯 Filters received:", filters);
      currentFilters.value = filters;
      
      // Convert filters to API format
      const apiFilters = convertFiltersToAPI(filters);
      
      // Fetch properties with new filters (reset to page 1)
      fetchProperties(apiFilters, 1);
    };

    // Convert frontend filters to backend API format
    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};

      // Sale/Rent Filter
      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }

      // Area Filter
      if (filters.area) {
        apiFilters.area_id = filters.area.id;
      }

      // Property Type Filter
      if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
     if (filters.referenceNumber && filters.referenceNumber.trim() !== "") {
        apiFilters.reference_number = filters.referenceNumber.trim();
      }

      // Bedrooms Filter
      if (filters.beds) {
        if (filters.beds === 'Studio') {
          apiFilters.number_of_bedrooms = 'Studio';
        } else {
          apiFilters.number_of_bedrooms = parseInt(filters.beds);
        }
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

    // Fetch initial properties on component mount
    onMounted(() => {
      fetchProperties();
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
      activeStatus,
      showingFrom,
      showingTo,
      displayedPages,
      setStatus,
      togglePropertyStatus,
      handleFiltersChanged,
      changePage,
      getPropertyImage,
      formatPrice,
      getLocation,
      getPropertyType,
      getAreaUnit,
      getAgentName,
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
.status-toggle-buttons {
  display: flex;
  gap: 12px;
  padding: 0 16px;
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
}

.status-btn:hover {
  border-color: #FAA300;
  color: #FAA300;
}

.status-btn.active {
  background: #FAA300;
  border-color: #FAA300;
  color: white;
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
  .status-toggle-buttons {
    flex-direction: column;
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
</style>