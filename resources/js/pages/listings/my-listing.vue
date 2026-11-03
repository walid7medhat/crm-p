<template>
  <div class="container-fluid" :class="{ 'all-listings-mobile': isMobileViewport, 'mt-4': !isMobileViewport }">

    <div class="top-search-toolbar mb-3">
      <div class="top-search-col">
        <SearchBar
          @filters-changed="handleFiltersChanged"
          @status-changed="setStatus"
          :initial-filters="initialFilters"
          :result-count="pagination?.total || filteredProperties.length"
          :show-status-tabs="true"
          :show-rejected-tab="true"
          :active-status="activeStatus"
        />
      </div>
      <div class="top-export-col">
        <button type="button" class="export-active-btn" :disabled="isExporting" @click="exportActiveListingsToExcel">
          <i class="ri-file-excel-2-line me-1"></i>
          {{ isExporting ? 'Exporting...' : 'Export Listings' }}
        </button>
      </div>
    </div>
    
    <!-- Properties Grid -->
    <div class="row gx-4 gy-4 p-4">
      <!-- Loading State -->
      <div v-if="isLoading" class="col-12">
        <div class="crm-page-loader" role="status" aria-live="polite" aria-busy="true">
          <div class="crm-page-loader__ring" aria-hidden="true">
            <span class="crm-page-loader__arc" />
          </div>
          <p class="crm-page-loader__title">Loading properties</p>
          <p class="crm-page-loader__sub">Fetching the latest listings…</p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredProperties.length === 0" class="col-12 text-center py-5">
        <i class="ri-home-4-line display-1 text-muted d-flex justify-content-center"></i>
        <h6 class="mt-3 text-muted">No properties found</h6>
        <p class="text-muted">Try adjusting your search filters or status</p>
      </div>

      <!-- Properties Grid -->
      <div
        v-else
        v-for="(property, index) in filteredProperties"
        :key="property.id || index"
        class="col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600"
      >
        <MobileListingCard
          v-if="isMobileViewport"
          :property="property"
          :fallback-image="defaultImages[0]"
        />
        <PropertyListingCard
          v-else
          :property="property"
          :compare-mode="compareMode"
          :compare-selected="isCompareSelected(property.id)"
          :is-rejected="isRejectedListing"
          dim-inactive
          breakdown-action
          @open="onListingClick"
          @breakdown="openBreakdownModal"
        />
      </div>
    </div>

    <ListingPaymentBreakdownQuickModal
      v-model="breakdownModalOpen"
      :listing-id="breakdownModalListingId"
      :listing-preview="breakdownModalPreview"
      @saved="onBreakdownSaved"
    />
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
                <i class="ri-arrow-left-line"></i> <span class="pagination-label">Previous</span>
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
                <span class="pagination-label">Next</span> <i class="ri-arrow-right-line"></i>
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
import { ref, onMounted, computed ,watch } from 'vue';
import { markKanbanReady } from '@/composables/useKanbanReady.js'
import { useRoute, useRouter } from 'vue-router';
import SearchBar from "./SearchBar.vue";
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import ListingPaymentBreakdownQuickModal from '@/components/listings/ListingPaymentBreakdownQuickModal.vue';
import { openPropertyDetailsFromClick } from '@/composables/usePropertyDetailsModal';
import { usePropertyCompareSelection } from '@/composables/propertyCompareSelection';
import { hidesBedsBaths } from '@/composables/listingPropertyType';
import { useMobileNavigation } from '@/composables/useMobileNavigation';
import MobileListingCard from '@/components/listings/MobileListingCard.vue';
import PropertyListingCard from '@/components/listings/PropertyListingCard.vue';
import {
  listingNeedsPaymentBreakdownHighlight,
  canQuickEditPaymentBreakdown,
} from '@/utils/listingPaymentBreakdownStatus';

// Default images
// import property1 from "/assets/images/a.jpeg";
// import property2 from "/assets/images/b.jpeg";
// import property3 from "/assets/images/c.jpeg";
// import property4 from "/assets/images/a.jpeg";

export default {
  name: 'AllListings',
  components: { SearchBar, Breadcrumb, ListingPaymentBreakdownQuickModal, MobileListingCard, PropertyListingCard },
  setup() {
    const { isMobileViewport } = useMobileNavigation();
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
    let skipNextQueryWatch = false;
    const breakdownModalOpen = ref(false);
    const breakdownModalListingId = ref(null);
    const breakdownModalPreview = ref(null);
   const propertyIcon = '/assets/icons/property-icon.svg';
  const bedIcon = '/assets/icons/bedroom-icon.svg';
  const bathIcon = '/assets/icons/bathroom-icon.svg';
  const sqftIcon = '/assets/icons/area-size.svg';
    // Default images array for fallback
    const defaultImages = [property1, property2, property3, property4];

    /** Rejected by an approver and still active (same rule as the API `rejected` filter). */
    const isRejectedListing = (property) =>
      !property?.approved && !!String(property?.rejection_reason || '').trim() && property?.status === 'draft'
      && !!property?.is_active;

    // Computed property for filtered properties based on status
  const filteredProperties = computed(() => {
      switch (activeStatus.value) {
        case 'all':
          return properties.value;
        case 'active':
          return properties.value.filter(property => property.is_active && !property.is_archived && property.status !== 'converted' &&
              property.status !== 'rented' && property.approved  &&
              property.status !== 'converted' && property.status !== 'draft');
        case 'inactive':
          return properties.value.filter(property => !property.is_active
          //  && !property.is_archived && property.status !== 'converted' &&
          //     property.status !== 'rented' &&
          //     property.status !== 'draft'
            );
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
        case 'rejected':
          return properties.value.filter(property => isRejectedListing(property));

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
     if (page === null && route.query.page) {
        page = parseInt(route.query.page) || 1;
      }
      if (page === null) page = 1;
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
      case 'rejected':
          params.rejected = true;
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
    markKanbanReady();
  }
};
const pruneEmptyQueryValues = (obj) => {
  const out = {};
  Object.keys(obj).forEach((k) => {
    const v = obj[k];
    if (v === undefined || v === null || v === '' || v === 0) return;
    out[k] = v;
  });
  return out;
};
const isSameQuery = (a, b) => {
  const aKeys = Object.keys(a);
  const bKeys = Object.keys(b);
  return aKeys.length === bKeys.length && aKeys.every((k) => String(a[k]) === String(b[k]));
};
    // Change page
    const changePage = (page) => {
      if (page < 1 || page > pagination.value.last_page || page === '...') return;
      
      // حفظ رقم الصفحة في الـ URL
      const query = { ...route.query, page: page };
      router.replace({ query: pruneEmptyQueryValues(query) });
      
      const apiFilters = convertFiltersToAPI(currentFilters.value);
      fetchProperties(apiFilters, page);
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };
const LISTING_QUERY_KEYS = [
  'sale_rent', 'area_ids', 'area_id', 'project_id', 'type_id', 'beds', 'baths',
  'type_ids', 'beds_list', 'baths_list',
  'price_from', 'price_to', 'size_from', 'size_to', 'sort', 'ref',
  'completion_status', 'occupancy_status', 'agent_id', 'agent_name', 'additional_features',
  'page'
];

    const encodeFiltersToQuery = (filters) => {
        const activeFeatures = filters.selectedFeatures 
        ? Object.keys(filters.selectedFeatures).filter(key => filters.selectedFeatures[key] === true)
        : [];
      return {
        // "All"/0/max-bound/default-sort all mean "no filter" — omit them so a
        // reset clears the URL back to a clean path instead of writing back the
        // defaults as if they were real filter selections.
        sale_rent: filters.saleRent && filters.saleRent !== 'All' ? filters.saleRent : undefined,
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
        price_from: filters.priceFrom > 0 ? filters.priceFrom : undefined,
        price_to: filters.priceTo > 0 && filters.priceTo < 10000000 ? filters.priceTo : undefined,
        size_from: filters.sizeFrom > 0 ? filters.sizeFrom : undefined,
        size_to: filters.sizeTo > 0 && filters.sizeTo < 10000 ? filters.sizeTo : undefined,
        sort: filters.sort && filters.sort !== 'created_at_desc' ? filters.sort : undefined,
        ref: filters.referenceNumber || undefined,
        completion_status: filters.completionStatus?.value || undefined,
        occupancy_status: filters.occupancyStatus?.value || undefined,
        agent_id: filters.agent?.id || undefined,
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
        occupancyStatus: query.occupancy_status
          ? { label: query.occupancy_status, value: query.occupancy_status }
          : null,
        agent: query.agent_id ? { id: Number(query.agent_id) } : null,
          selectedFeatures: selectedFeatures,
           page: query.page ? parseInt(query.page) : 1,
      };
    };

    // Handle filters from SearchBar
    const handleFiltersChanged = (filters, meta = {}) => {
      console.log("🎯 Filters received:", filters);

      // Reset = behave like a first visit: no filters, no sort, "All" tab.
      if (meta.reset) {
        currentFilters.value = {};
        initialFilters.value = null;
        activeStatus.value = 'all';
        fetchProperties({}, 1);

        const base = { ...route.query };
        LISTING_QUERY_KEYS.forEach((k) => { delete base[k]; });
        const nextQuery = pruneEmptyQueryValues(base);
        if (!isSameQuery(nextQuery, route.query)) {
          // Our own URL cleanup — don't let the query watcher refetch with defaults.
          skipNextQueryWatch = true;
          router.replace({ query: nextQuery });
        }
        return;
      }

      currentFilters.value = filters;
      initialFilters.value = filters;
      
      // Convert filters to API format
      const apiFilters = convertFiltersToAPI(filters);
      
      // Fetch properties with new filters (reset to page 1)
      fetchProperties(apiFilters, 1);

      // Persist filters in URL so Back restores them — drop stale keys first so a
      // cleared/reset filter doesn't linger from the previous query string.
      const base = { ...route.query };
      LISTING_QUERY_KEYS.forEach((k) => { delete base[k]; });
      router.replace({
        query: pruneEmptyQueryValues({
          ...base,
          ...encodeFiltersToQuery(filters),
        }),
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
      // Agent Filter (matches AllLsting.vue behavior)
      if (filters.agent && filters.agent.id) {
        apiFilters.agent_id = filters.agent.id;
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
      if (filters.occupancyStatus && filters.occupancyStatus.value) {
        apiFilters.occupancy_status = filters.occupancyStatus.value;
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

    /** Force Excel to treat the value as text (preserves leading zeros, avoids scientific notation on long phone numbers). */
    const toTextCell = (value) => {
      const safe = String(value ?? '').replace(/"/g, '""');
      return `="${safe}"`;
    };

    const canSeeSensitiveData = computed(() => {
      try {
        const userStr = localStorage.getItem('user');
        if (!userStr) return false;
        const user = JSON.parse(userStr);
        const privilegedRoles = ['Team Lead', 'Admin'];
        return !privilegedRoles.includes(user.role_name);
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

        const headers = canSeeSensitiveData.value
          ? [
              'Area',
              'Project name',
              'Unit number',
              'Purpose',
              'Size per sqft',
              'Selling price',
              'Status',
              'Owner name',
              'Owner mobile number',
              'Bedroom',
              'Type',
              'Created at',
            ]
          : [
              'Area',
              'Project name',
              'Agent',
              'Purpose',
              'Size per sqft',
              'Selling price',
              'Status',
              'Bedroom',
              'Type',
              'Created at',
            ];

        const lines = await Promise.all(rows.map(async (property) => {
          const title =property?.area || '-';
          const projectName = property?.project?.name || property?.project_name || property?.title || '-';
          const unitNumber = property?.unit_number || property?.reference_number || '-';
          const sizePerSqft = property?.size_sqft || '-';
          const sellingPrice = property?.price || 0;
          const status = property?.is_active ? 'Active' : 'Inactive';
          const { ownerName, ownerMobileNumber } = await resolveOwnerFieldsForExport(property);
          const createdAt = property?.created_at ? formatDate(property.created_at) : '-';
          const bedroom = getBedroomLabel(property);
          const type = getPropertyType(property);
          const listing_status = property?.listing_status || '-';
          const agent=property?.agent?.name;
          if (canSeeSensitiveData.value) {
            return [
              toCell(title),
              toCell(projectName),
              toTextCell(unitNumber),
              toCell(listing_status),
              toCell(sizePerSqft),
              toCell(sellingPrice),
              toCell(status),
              toCell(ownerName),
              toTextCell(ownerMobileNumber),
              toCell(bedroom),
              toCell(type),
              toCell(createdAt),
            ].join(',');
          }

          return [
            toCell(title),
            toCell(projectName),
            toCell(agent),
            toCell(listing_status),
            toCell(sizePerSqft),
            toCell(sellingPrice),
            toCell(status),
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

    const openBreakdownModal = (property) => {
      breakdownModalListingId.value = property.id;
      breakdownModalPreview.value = property;
      breakdownModalOpen.value = true;
    };

    const onBreakdownSaved = (updated) => {
      if (!updated?.id) return;
      const idx = properties.value.findIndex((p) => p.id === updated.id);
      if (idx !== -1) {
        properties.value[idx] = { ...properties.value[idx], ...updated };
      }
    };

    const { compareMode, isSelected: isCompareSelected, toggleListing } = usePropertyCompareSelection();

    const openDetailsModal = (event, property) => {
      openPropertyDetailsFromClick(event, property.id, {
        onDeleted: () => fetchProperties(convertFiltersToAPI(currentFilters.value || {}), pagination.value?.current_page || 1),
      });
    };

    const onListingClick = (event, property) => {
      if (compareMode.value) {
        event.preventDefault();
        toggleListing(property);
        return;
      }
      openDetailsModal(event, property);
    };

    // Fetch initial properties on component mount
    onMounted(() => {
      const hasQuery = Object.keys(route.query).length > 0;
        const pageFromUrl = route.query.page ? parseInt(route.query.page) : 1;

      if (hasQuery) {
        const filters = decodeFiltersFromQuery(route.query);
        currentFilters.value = filters;
        initialFilters.value = filters;
        const apiFilters = convertFiltersToAPI(filters);
        fetchProperties(apiFilters, pageFromUrl);

        // Drop default values (sale_rent=All, sort=created_at_desc, …) from the URL.
        const base = { ...route.query };
        LISTING_QUERY_KEYS.forEach((k) => { delete base[k]; });
        const cleanQuery = pruneEmptyQueryValues({
          ...base,
          ...encodeFiltersToQuery(filters),
          page: route.query.page,
        });
        if (!isSameQuery(cleanQuery, route.query)) {
          skipNextQueryWatch = true;
          router.replace({ query: cleanQuery });
        }
      } else {
        fetchProperties({},pageFromUrl);
      }
    });
watch(() => route.query.page, (newPage, oldPage) => {
  if (skipNextQueryWatch) return;
  if (newPage && newPage !== oldPage) {
    const page = parseInt(newPage);
    if (page !== pagination.value?.current_page) {
      const apiFilters = convertFiltersToAPI(currentFilters.value);
      fetchProperties(apiFilters, page);
    }
  }
});

// أضف watch للاستجابة لتغييرات route.query بالكامل (للتحديث عند الضغط على Back)
watch(() => route.query, (newQuery, oldQuery) => {
  if (skipNextQueryWatch) {
    skipNextQueryWatch = false;
    return;
  }
  // تجاهل التغييرات الناتجة عن تحديث page فقط
  if (newQuery.page !== oldQuery.page) {
    const page = newQuery.page ? parseInt(newQuery.page) : 1;
    if (page !== pagination.value?.current_page) {
      const apiFilters = convertFiltersToAPI(currentFilters.value);
      fetchProperties(apiFilters, page);
    }
    return;
  }
  
  // إذا تغيرت الفلاتر الأخرى، قم بتحديث البيانات
  const hasFilterChange = LISTING_QUERY_KEYS.some(key => {
    if (key === 'page') return false;
    return newQuery[key] !== oldQuery[key];
  });
  
  if (hasFilterChange) {
    const filters = decodeFiltersFromQuery(newQuery);
    currentFilters.value = filters;
    initialFilters.value = filters;
    const apiFilters = convertFiltersToAPI(filters);
    const page = newQuery.page ? parseInt(newQuery.page) : 1;
    fetchProperties(apiFilters, page);
  }
}, { deep: true });
    return {
      properties,
      filteredProperties,
      isMobileViewport,
      defaultImages,
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
      handleImageError,
      breakdownModalOpen,
      breakdownModalListingId,
      breakdownModalPreview,
      openBreakdownModal,
      onBreakdownSaved,
      openDetailsModal,
      compareMode,
      isCompareSelected,
      onListingClick,
      listingNeedsPaymentBreakdownHighlight,
      hidesBedsBaths,
      isRejectedListing,
      canQuickEditPaymentBreakdown,
    };
  }
};
</script>

<style scoped>

.top-export-col {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}

.export-active-btn {
  border: 1px solid var(--color-primary);
  background: var(--gradient-primary);
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
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: #fff9ef;
}

.status-btn.active {
  background: linear-gradient(135deg, var(--color-primary) 0%, #ffb224 100%);
  border-color: #f1a10a;
  color: white;
  box-shadow: 0 4px 10px rgba(250, 163, 0, 0.22);
}

/* Images Badge Only */








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







/* Pagination Styles */
.pagination {
  margin-bottom: 0;
}

.page-item.active .page-link {
  background: var(--gradient-primary);
  border-color: var(--color-primary);
  color: #fff;
}

.page-link {
  color: var(--color-primary);
  border: 1px solid #dee2e6;
  padding: 8px 16px;
  margin: 0 4px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.page-link:hover {
  background-color: var(--color-primary-soft);
  border-color: var(--color-primary-border);
  color: var(--color-primary);
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

  /* Compact mobile pagination (keep Previous/Next on one line) */
  .pagination {
    flex-wrap: nowrap;
    overflow-x: auto;
    justify-content: center;
    -webkit-overflow-scrolling: touch;
  }

  .pagination-label {
    display: none;
  }

  .page-link {
    padding: 6px 10px;
    margin: 0 2px;
    font-size: 12px;
    border-radius: 8px;
    white-space: nowrap;
  }
}




/* Card States */













/*============*/







</style>