<template>
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-end mb-3 mt-3" v-if="$hasPermission('listings-create')">
      <router-link to="/property-form" class="btn btn-primary create-property-btn">
        <i class="ri-add-line"></i>
        Create Property
      </router-link>
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
        <h6 class="ui-h-mini mt-3 text-muted">No properties found</h6>
        <p class="text-muted">Try adjusting your search filters or status</p>
      </div>

      <!-- Properties Grid -->
      <div
        v-else
        v-for="(property, index) in filteredProperties"
        :key="property.id || index"
        class="col-xxl-3 col-lg-4 col-md-6"
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

            <h6 class="ui-h-mini property-title mb-2">{{ property.title || 'No Title' }}</h6>
            <p class="property-location mb-3">
              <i class="ri-map-pin-line me-1"></i>{{ getLocation(property) }}
            </p>

            <div class="property-details d-flex justify-content-between text-muted small mb-3">
              <span><i class="ri-building-4-line me-1"></i>{{ getPropertyType(property) }}</span>
              <span><i class="ri-hotel-bed-line me-1"></i>{{ property.number_of_bedrooms || 0 }}</span>
              <span><i class="ri-water-flash-line me-1"></i>{{ property.number_of_bathrooms || 0 }}</span>
              <span><i class="ri-ruler-line me-1"></i>{{ property.size_sqft || property.size_sqmt || 0 }} {{ getAreaUnit(property) }}</span>
            </div>
            
            <p class="property-agent text-muted small mb-2" v-if="property.agent">
              Listed by: {{ getAgentName(property.agent) }}
            </p>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
              <router-link
                v-if="property.status !== 'converted'"
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
              <button
                v-else
                class="view-more-btn converted-btn flex-grow-1"
                disabled
              >
                Sold Out
              </button>
            </div>
          </div>
        </div>
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
import property1 from "@/assets/images/a.jpeg";
import property2 from "@/assets/images/b.jpeg";
import property3 from "@/assets/images/c.jpeg";
import property4 from "@/assets/images/a.jpeg";

export default {
  name: 'AllListings',
  components: { SearchBar, Breadcrumb },
  setup() {
    const properties = ref([]);
    const isLoading = ref(false);
    const currentFilters = ref({});
    const pagination = ref(null);
    const activeStatus = ref('all'); 

    const defaultImages = [property1, property2, property3, property4];

    // Computed property for filtered properties based on status
    const filteredProperties = computed(() => {
      if (activeStatus.value === 'all') {
        return properties.value;
      } else if (activeStatus.value === 'active') {
        return properties.value.filter(property => property.is_active);
      } else {
        return properties.value.filter(property => !property.is_active);
      }
    });

    // Status toggle functions
    const setStatus = (status) => {
      activeStatus.value = status;
    };

  const togglePropertyStatus = async (property) => {
  try {
    const newStatus = !property.is_active;
    
    property.is_active = newStatus;
    
    await api.patch(`/listings/properties/${property.id}/toggle-status`);
    
    console.log(`✅ Property ${newStatus ? 'activated' : 'deactivated'} successfully`);
    
  } catch (error) {
    console.error("❌ Error updating property status:", error.response || error);
    
    property.is_active = !property.is_active;
    
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
    const fetchProperties = async (filters = {}, page = 1) => {
      try {
        isLoading.value = true;
        
        // Build query parameters
        const params = {
          ...filters,
          page: page,
          per_page: 12
        };

        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === null || params[key] === undefined || params[key] === '') {
            delete params[key];
          }
        });

        console.log("📤 Fetching properties with params:", params);

        const response = await api.get("/listings/properties?my_listings=true&is_archived=true", { params });
        
        // Handle API response
        const responseData = response.data.data;
        const responseMeta = response.data.meta.pagination;
        
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

    const changePage = (page) => {
      if (page < 1 || page > pagination.value.last_page || page === '...') return;
      fetchProperties(currentFilters.value, page);
    };

    const handleFiltersChanged = (filters) => {
      console.log("🎯 Filters received:", filters);
      currentFilters.value = filters;
      
      const apiFilters = convertFiltersToAPI(filters);
      
      fetchProperties(apiFilters, 1);
    };

    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};

      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }

      if (Array.isArray(filters.area) && filters.area.length) {
        apiFilters.area_ids = filters.area.map((a) => a?.id).filter(Boolean);
      } else if (filters.area) {
        apiFilters.area_id = filters.area.id;
      }

      if (Array.isArray(filters.propertyTypes) && filters.propertyTypes.length) {
        const ids = filters.propertyTypes.map((p) => p?.id).filter(Boolean);
        apiFilters.property_type_ids = ids;
        if (ids.length === 1) {
          apiFilters.property_type_id = ids[0];
        }
      } else if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }

      const bedsList = Array.isArray(filters.bedsList) ? filters.bedsList : (filters.beds ? [filters.beds] : []);
      if (bedsList.length) {
        apiFilters.number_of_bedrooms_in = bedsList;
        if (bedsList.length === 1) {
          const firstBed = bedsList[0];
          apiFilters.number_of_bedrooms = firstBed === 'Studio' ? 0 : parseInt(firstBed);
        }
      }
      const bathsList = Array.isArray(filters.bathsList) ? filters.bathsList : (filters.baths ? [filters.baths] : []);
      if (bathsList.length) {
        apiFilters.number_of_bathrooms_in = bathsList;
        if (bathsList.length === 1) {
          apiFilters.number_of_bathrooms = parseInt(bathsList[0]);
        }
      }

      if (filters.priceFrom > 0 || filters.priceTo < 10000000) {
        apiFilters.min_price = filters.priceFrom;
        apiFilters.max_price = filters.priceTo;
      }

      if (filters.sizeFrom > 0 || filters.sizeTo < 10000) {
        apiFilters.min_size = filters.sizeFrom;
        apiFilters.max_size = filters.sizeTo;
      }

      if (filters.sort) {
        apiFilters.sort = mapSortToBackend(filters.sort);
      }

      return apiFilters;
    };

    const mapSortToBackend = (sortOption) => {
      const sortMap = {
        'Most Recent': 'created_at_desc',
        'Price: Low to High': 'price_asc',
        'Price: High to Low': 'price_desc',
        'Size: Small to Large': 'size_asc', 
        'Size: Large to Small': 'size_desc'
      };
      return sortMap[sortOption] || 'created_at_desc';
    };

    const formatPrice = (price) => {
      if (!price) return '0';
      return new Intl.NumberFormat().format(price);
    };

    const getLocation = (property) => {
      if (property.area && property.area.area_title) return property.area.area_title;
      if (property.location) return property.location;
      return 'Location not specified';
    };

    const getPropertyType = (property) => {
      if (property.property_type && property.property_type.name) return property.property_type.name;
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
  border-color: #007bff;
  color: #007bff;
}

.status-btn.active {
  background: #007bff;
  border-color: #007bff;
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
  background: #f6f6f6;
  color: #333;
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
</style>