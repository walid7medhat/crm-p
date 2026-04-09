<template>
  <div class="container-fluid mt-4">
   
    <div class="top-search-toolbar mb-3">
      <div class="top-search-col">
        <SearchBar
          ref="searchBarRef"
          @filters-changed="handleFiltersChanged"
          @status-changed="setStatus"
          :initial-filters="initialFilters"
          :result-count="pagination?.total || properties.length"
          :show-status-tabs="isAdmin"
          :active-status="activeStatus"
        />
      </div>
    </div>
    <!-- Properties Grid -->
    <div class="row gx-4  p-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-white">Loading properties...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="properties.length === 0" class="col-12 text-center py-5">
        <i class="ri-home-4-line display-1 text-white"></i>
        <h4 class="mt-3 text-white">No properties found</h4>
        <button @click="notifyMe" class="btn btn-primary">
        Get notified when matching properties become available
        </button>
        <!--<p class="text-white">Try adjusting your search filters</p>-->
      </div>

      <!-- Properties Grid -->
      <div
        v-else
        v-for="(property, index) in properties"
        :key="property.id || index"
        class="col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600 "
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
                  <span class="d-flex justify-content-between icons" >
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
                <span class="view-more-btn">
                  View Details
                </span>
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
import { ref, onMounted, computed, watch, nextTick } from 'vue'; // ✅ إضافة nextTick و watch
import { useRoute, useRouter } from 'vue-router'; // ✅ إضافة useRoute/useRouter
import SearchBar from "./SearchBar.vue";
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

// Default images
// import property1 from "@/assets/images/a.jpeg";
// import property2 from "@/assets/images/b.jpeg";
// import property3 from "@/assets/images/c.jpeg";
// import property4 from "@/assets/images/a.jpeg";

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
    const activeStatus = ref('all'); 
    const userRole = ref(''); 
    const propertyIcon = '/assets/icons/property-icon.svg';
    const bedIcon = '/assets/icons/bedroom-icon.svg';
    const bathIcon = '/assets/icons/bathroom-icon.svg';
    const sqftIcon = '/assets/icons/area-size.svg';
    const searchBarRef = ref(null);
    const initialFilters = ref(null);
    
    const isAdmin = computed(() => {
      return userRole.value === 'super_admin' || userRole.value === 'admin';
    });

    const defaultImages = [property1, property2, property3, property4];

    const applyAgentFilterFromQuery = async (agentId, agentName) => {
      await nextTick(); 
      
      if (!searchBarRef.value) {
        console.log('⏳ Waiting for SearchBar to load...');
        setTimeout(() => applyAgentFilterFromQuery(agentId, agentName), 500);
        return;
      }
      
      try {
        const searchBar = searchBarRef.value;
            const agents = searchBar.agents || [];
        const foundAgent = agents.find(a => a.id == agentId);
        
        if (foundAgent) {
          console.log('✅ Found agent in list:', foundAgent.name);
          
          searchBar.selectedAgent = foundAgent;
          
          setTimeout(() => {
            searchBar.applyFilters();
          }, 300);
        } else {
          console.log('⚠️ Agent not found in list, creating custom filter');
          
          const customAgent = {
            id: parseInt(agentId),
            name: agentName || `Agent ${agentId}`
          };
          
          searchBar.selectedAgent = customAgent;
          
          setTimeout(() => {
            searchBar.applyFilters();
          }, 300);
        }
      } catch (error) {
        console.error('❌ Error applying agent filter:', error);
      }
    };

    const handleAgentFromQuery = (query) => {
      if (!query.agent_id) return;
      
      try {
        const agentFilter = {
          agent_id: parseInt(query.agent_id)
        };
        
        console.log('🔍 Applying agent filter directly:', agentFilter);
        
        currentFilters.value = { 
          ...currentFilters.value, 
          ...agentFilter 
        };
        
        fetchProperties({}, 1);
        
      } catch (error) {
        console.error('❌ Error handling agent from query:', error);
      }
    };

    watch(() => route.query, (newQuery) => {
      if (newQuery.agent_id) {
        console.log('🎯 Agent filter detected in URL:', newQuery);
        
        setTimeout(() => {
          applyAgentFilterFromQuery(newQuery.agent_id, newQuery.agent_name);
        }, 1000);
        
        setTimeout(() => {
          handleAgentFromQuery(newQuery);
        }, 1500);
      }
    }, { immediate: true });

    // Status toggle functions
  
    const setStatus = (status) => {
      activeStatus.value = status;
      fetchProperties(currentFilters.value, 1); 
    };

    // Computed property for filtered properties based on status
    const filteredProperties = computed(() => {
      if (!isAdmin.value) {
        return properties.value;
      }

      switch (activeStatus.value) {
        case 'all':
          return properties.value;
        case 'active':
          return properties.value.filter(property => 
            property.is_active && 
            !property.is_archived && 
            property.status !== 'converted' &&  
            property.status !== 'draft'
          );
        case 'inactive':
          return properties.value.filter(property => 
            !property.is_active && 
            !property.is_archived && 
            property.status !== 'converted'
          );
        case 'archived':
          return properties.value.filter(property => property.is_archived);
        case 'sold':
          return properties.value.filter(property => property.status === 'converted');
        case 'draft':
          return properties.value.filter(property => property.status === 'draft');
        case 'off_plan':
          return properties.value.filter(property => property.is_off_plan && property.is_off_plan === 'Yes');
        default:
          return properties.value;
      }
    });

    const fetchUserInfo = () => {
      try {
        const userData = localStorage.getItem('user');
        
        if (userData) {
          const user = JSON.parse(userData);
          
          const userRoles = user.roles || [];
          const roleName = user.role_name || '';
          
          if (userRoles.includes('super_admin') || roleName === 'super_admin') {
            userRole.value = 'super_admin';
          } else if (userRoles.includes('admin') || roleName === 'admin') {
            userRole.value = 'admin';
          } else {
            userRole.value = 'user';
          }
          
          console.log("👤 User role:", userRole.value);
        } else {
          userRole.value = 'user';
        }
      } catch (error) {
        console.error("❌ Error:", error);
        userRole.value = 'user';
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
        
        if (Object.keys(filters).length > 0) {
          currentFilters.value = { ...currentFilters.value, ...filters };
        }
        
        const params = {
          ...currentFilters.value,
          page: page,
          per_page: 12
        };

        if (isAdmin.value && activeStatus.value !== 'all') {
          switch (activeStatus.value) {
            case 'archived':
              params.is_archived = true;
              break;
            case 'sold':
              params.status = 'converted';
              break;
            case 'active':
              params.is_active = true;
              params.is_archived = false;
              break;
            case 'inactive':
              params.is_active = false;
              params.is_archived = false;
              break;
            case 'draft':
              params.status = 'draft';
              break;
            case 'off_plan':
              params.status = 'off_plan';
              break;
          }
        }

        // Remove empty filters
        Object.keys(params).forEach(key => {
          if (params[key] === null || params[key] === undefined || params[key] === '' || params[key] === 0) {
            delete params[key];
          }
        });

        console.log("📤 Final API request params:", params);
        console.log("🔍 Current active status:", activeStatus.value);
        console.log("💾 Current saved filters:", currentFilters.value);

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
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };

    const encodeFiltersToQuery = (filters) => {
      return {
        sale_rent: filters.saleRent || undefined,
        area_id: filters.area?.id || undefined,
        project_id: filters.project?.id || undefined,
        type_id: filters.propertyType?.id || undefined,
        beds: filters.beds || undefined,
        price_from: filters.priceFrom,
        price_to: filters.priceTo,
        size_from: filters.sizeFrom,
        size_to: filters.sizeTo,
        sort: filters.sort || undefined,
        ref: filters.referenceNumber || undefined,
        completion_status: filters.completionStatus?.value || undefined,
        agent_id: filters.agent?.id || undefined,
      };
    };

    const decodeFiltersFromQuery = (query) => {
      return {
        saleRent: query.sale_rent || 'All',
        area: query.area_id ? { id: Number(query.area_id) } : null,
        project: query.project_id ? { id: Number(query.project_id) } : null,
        propertyType: query.type_id ? { id: Number(query.type_id) } : null,
        beds: query.beds || '',
        priceFrom: query.price_from ? Number(query.price_from) : 0,
        priceTo: query.price_to ? Number(query.price_to) : 10000000,
        sizeFrom: query.size_from ? Number(query.size_from) : 0,
        sizeTo: query.size_to ? Number(query.size_to) : 10000,
        sort: query.sort || 'created_at_desc',
        referenceNumber: query.ref || '',
        completionStatus: query.completion_status
          ? { label: query.completion_status, value: query.completion_status }
          : null,
        agent: query.agent_id ? { id: Number(query.agent_id) } : null,
      };
    };

    // Handle filters from SearchBar
    const handleFiltersChanged = (filters) => {
      console.log("🎯 Filters received from SearchBar:", filters);
      
      currentFilters.value = convertFiltersToAPI(filters);
      initialFilters.value = filters;
      
      activeStatus.value = 'all';
      
      // Fetch properties with new filters (reset to page 1)
      fetchProperties({}, 1); 

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
      if (filters.area && filters.area.id) {
        apiFilters.area_id = filters.area.id;
      }
     if (filters.completionStatus && filters.completionStatus.value) {
        apiFilters.completion_status = filters.completionStatus.value;
      }
      // Property Type Filter
      if (filters.propertyType && filters.propertyType.id) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
      
      if (filters.referenceNumber && filters.referenceNumber.trim() !== "") {
        apiFilters.reference_number = filters.referenceNumber.trim();
      }
      
      // Agent Filter
      if (filters.agent && filters.agent.id) {
        apiFilters.agent_id = filters.agent.id;
      }

      // Bedrooms Filter
      if (filters.beds && filters.beds !== 'All') {
        if (filters.beds === 'Studio') {
          apiFilters.number_of_bedrooms = 'Studio';
        } else {
          apiFilters.number_of_bedrooms = parseInt(filters.beds);
        }
      }

      // Price Range Filter
      if (filters.priceFrom > 0 || filters.priceTo < 10000000) {
        apiFilters.min_price = filters.priceFrom > 0 ? filters.priceFrom : undefined;
        apiFilters.max_price = filters.priceTo < 10000000 ? filters.priceTo : undefined;
      }
      if (filters.project && filters.project.id) {
        apiFilters.project_id = filters.project.id;
      }
      // Size Range Filter
      if (filters.sizeFrom > 0 || filters.sizeTo < 10000) {
        apiFilters.min_size = filters.sizeFrom > 0 ? filters.sizeFrom : undefined;
        apiFilters.max_size = filters.sizeTo < 10000 ? filters.sizeTo : undefined;
      }

      // Sort
      if (filters.sort && filters.sort !== 'Most Recent') {
        apiFilters.sort = mapSortToBackend(filters.sort);
      }

      Object.keys(apiFilters).forEach(key => {
        if (apiFilters[key] === undefined || apiFilters[key] === null || apiFilters[key] === '') {
          delete apiFilters[key];
        }
      });

      console.log("🔍 Converted API filters:", apiFilters);
      return apiFilters;
    };

    // Map frontend sort options to backend
    const mapSortToBackend = (sortOption) => {
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
      if (property.property_type) return property.property_type;
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
    
    const notifyMe = async () => {

      await api.post('/search-alerts', currentFilters.value)
    
      $showNotification("You'll be notified when a matching property is added")
    }
    // Fetch initial properties on component mount
    onMounted(async () => {
      await fetchUserInfo(); 

      const hasQuery = Object.keys(route.query).length > 0;
      if (hasQuery) {
        const filters = decodeFiltersFromQuery(route.query);
        currentFilters.value = convertFiltersToAPI(filters);
        initialFilters.value = filters;
        fetchProperties({}, 1);
      } else {
        fetchProperties();
      }
    });

    return {
      properties: filteredProperties, 
      propertyIcon,
      bedIcon,
      bathIcon,
      sqftIcon,
      isLoading,
      pagination,
      activeStatus,
      isAdmin,
      showingFrom,
      showingTo,
      displayedPages,
      setStatus,
      handleFiltersChanged,
      changePage,
      getPropertyImage,
      formatPrice,
      formatDate,
      getLocation,
      getPropertyType,
      getAreaUnit,
      getAgentName,
      handleImageLoad,
      handleImageError,
      searchBarRef,
      initialFilters,
      notifyMe
    };
  }
};
</script>
<style scoped>
.property-image img {
  height: 230px;
  transition: transform 0.4s ease;
  opacity: 0;
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
    overflow: hidden;
  text-overflow: ellipsis;
    white-space: nowrap;

}

.view-more-btn {
  display: inline-block;
  text-align: center;
  width: 100%;
  background: #FAA300;
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
/* Toolbar layout; status + Hot Deal row come from ListingsSearchBar (scoped there). */
.top-search-toolbar {
  display: block;
}

.top-search-col {
  flex: 1 1 auto;
  min-width: 0;
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
.badge-off_plan {
  background: #faa300;
  color: white;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.7rem;
  font-weight: 500;
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