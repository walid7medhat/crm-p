<template>
  <div class="container-fluid" :class="{ 'all-listings-mobile': isMobileViewport, 'mt-4': !isMobileViewport }">
   
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
      <div v-else-if="properties.length === 0" class="col-12 text-center py-5">
        <i class="ri-home-4-line display-1 text-muted d-flex justify-content-center"></i>
        <h6 class="mt-3 text-dark">No properties found</h6>
        <button @click="notifyMe" class="btn btn-primary">
        Get notified when matching properties become available
        </button>
      </div>

      <!-- Properties Grid -->
      <template v-else>
        <div
          v-for="(property, index) in properties"
          :key="property.id || index"
          :class="isMobileViewport ? 'col-12' : 'col-12 col-md-6 col-xl-4 col-xxl-4 custom-1600'"
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
            breakdown-action
            @open="onListingClick"
            @breakdown="openBreakdownModal"
          />
        </div>
      </template>
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
                <i class="ri-arrow-left-line"></i><span class="page-link__label"> Previous</span>
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
                <span class="page-link__label">Next </span><i class="ri-arrow-right-line"></i>
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
import { ref, onMounted, computed, watch, nextTick } from 'vue'; // ✅ إضافة nextTick و watch
import { markKanbanReady } from '@/composables/useKanbanReady.js'
import { useRoute, useRouter } from 'vue-router'; // ✅ إضافة useRoute/useRouter
import SearchBar from "./SearchBar.vue";
import MobileListingCard from '@/components/listings/MobileListingCard.vue';
import PropertyListingCard from '@/components/listings/PropertyListingCard.vue';
import { useMobileNavigation } from '@/composables/useMobileNavigation.js';
import api from "@/plugins/axios";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import ListingPaymentBreakdownQuickModal from '@/components/listings/ListingPaymentBreakdownQuickModal.vue';
import { openPropertyDetailsFromClick } from '@/composables/usePropertyDetailsModal';
import { listingPurpose, usePropertyCompareSelection } from '@/composables/propertyCompareSelection';
import { hidesBedsBaths } from '@/composables/listingPropertyType';
import {
  listingNeedsPaymentBreakdownHighlight,
  canQuickEditPaymentBreakdown,
} from '@/utils/listingPaymentBreakdownStatus';

// Default images
// import property1 from "@/assets/images/a.jpeg";
// import property2 from "@/assets/images/b.jpeg";
// import property3 from "@/assets/images/c.jpeg";
// import property4 from "@/assets/images/a.jpeg";

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
    const { compareMode, isSelected: isCompareSelected, toggleListing } = usePropertyCompareSelection();
    const properties = ref([]);
    const isLoading = ref(false);
    const currentFilters = ref({});
    const pagination = ref(null);
    const activeStatus = ref('all'); 
    const userRole = ref(''); 
    let fetchRequestId = 0;
    let agentFilterApplied = false; 
    const propertyIcon = '/assets/icons/property-icon.svg';
    const bedIcon = '/assets/icons/bedroom-icon.svg';
    const bathIcon = '/assets/icons/bathroom-icon.svg';
    const sqftIcon = '/assets/icons/area-size.svg';
    const searchBarRef = ref(null);
    const initialFilters = ref(null);
    const breakdownModalOpen = ref(false);
    const breakdownModalListingId = ref(null);
    const breakdownModalPreview = ref(null);

    const isAdmin = computed(() => {
      return userRole.value === 'super_admin' || userRole.value === 'admin';
    });

    const defaultImages = [property1, property2, property3, property4];

    const applyAgentFilterFromQuery = async (agentId, agentName, { refetch = false } = {}) => {
      await nextTick(); 
      
      if (!searchBarRef.value) {
        console.log('⏳ Waiting for SearchBar to load...');
        setTimeout(() => applyAgentFilterFromQuery(agentId, agentName, { refetch }), 500);
        return;
      }
      
      try {
        const searchBar = searchBarRef.value;
            const agents = searchBar.agents || [];
        const foundAgent = agents.find(a => a.id == agentId);
        
        if (foundAgent) {
          console.log('✅ Found agent in list:', foundAgent.name);
          searchBar.selectedAgent = foundAgent;
        } else {
          console.log('⚠️ Agent not found in list, creating custom filter');
          searchBar.selectedAgent = {
            id: parseInt(agentId),
            name: agentName || `Agent ${agentId}`
          };
        }

        // Only refetch when agent_id changes after initial mount.
        // Initial page load already fetches via onMounted restore.
        if (refetch) {
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

    watch(() => route.query.agent_id, (agentId, previousAgentId) => {
      if (!agentId) {
        agentFilterApplied = false;
        return;
      }

      const isInitial = previousAgentId === undefined;
      const changed = !isInitial && String(previousAgentId) !== String(agentId);

      // Sync SearchBar UI; refetch only when agent changes after mount.
      // Initial page load already fetches via onMounted restore.
      setTimeout(() => {
        applyAgentFilterFromQuery(agentId, route.query.agent_name, { refetch: changed });
      }, isInitial ? 800 : 100);

      if (isInitial) {
        agentFilterApplied = true;
      }
    });

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
            property.approved  &&
            !property.is_archived && 
            property.status !== 'converted' &&  
            property.status !== 'rented' &&
            property.status !== 'draft'
          );
        case 'inactive':
          return properties.value.filter(property => 
            !property.is_active 
            // && !property.is_archived && 
            // property.status !== 'converted' &&
            // property.status !== 'rented' 
          );
        case 'archived':
          return properties.value.filter(property => property.is_archived);
        case 'sold':
          return properties.value.filter(property => property.status === 'converted');
        case 'rented': 
           return properties.value.filter(property => property.status === 'rented');
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
          } else if (userRoles.includes('branch_admin') || roleName === 'branch_admin') {
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
      const requestId = ++fetchRequestId;
      try {
        isLoading.value = true;
        if (!page && route.query.page) {
          page = parseInt(route.query.page) || 1;
        }
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
            case 'rented': 
                params.status = 'rented';
                break;
            case 'active':
              params.is_active = true;
              params.is_archived = false;
              break;
            case 'inactive':
              params.is_active = false;
              // params.is_archived = false;
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
 if (params.area_ids && !Array.isArray(params.area_ids)) {
      if (typeof params.area_ids === 'string') {
        params.area_ids = params.area_ids.split(',').map(id => Number(id));
      } else {
        params.area_ids = [params.area_ids];
      }
    }
        console.log("📤 Final API request params:", params);
        console.log("🔍 Current active status:", activeStatus.value);
        console.log("💾 Current saved filters:", currentFilters.value);

        const response = await api.get("/listings/properties", { params });
        if (requestId !== fetchRequestId) {
          return; // Stale response — a newer fetch is in flight
        }
        
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
        if (requestId !== fetchRequestId) return;
        console.error("❌ Error fetching properties:", error.response || error);
        properties.value = [];
        pagination.value = null;
      } finally {
        if (requestId === fetchRequestId) {
          isLoading.value = false;
          markKanbanReady();
        }
      }
    };

    // Change page
   const changePage = (page) => {
      if (page < 1 || page > pagination.value.last_page || page === '...') return;
      
      // Update the URL with the page parameter
      const query = { ...route.query, page: page };
      router.replace({ query: pruneEmptyQueryValues(query) });
      
      fetchProperties(currentFilters.value, page);
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };

    /** Query keys owned by listing search — strip before merge so cleared filters don’t linger. */
    const LISTING_QUERY_KEYS = [
      'sale_rent', 'area_ids', 'area_id', 'project_id', 'type_id', 'beds', 'baths',
      'type_ids', 'beds_list', 'baths_list',
      'price_from', 'price_to', 'size_from', 'size_to', 'sort', 'ref',
      'completion_status', 'occupancy_status', 'agent_id', 'agent_name','additional_features','page'
    ];

    // v2: v1 entries were saved with old defaults (priceTo 5000000 / sizeTo 5000)
    // that looked like real filters and leaked back into the URL on every visit.
    const LISTING_FILTERS_STORAGE_KEY = 'listingSearchFilters_v2';
    try {
      localStorage.removeItem('listingSearchFilters');
    } catch (error) {
      // storage unavailable — nothing to clean
    }

    const hasListingQueryParams = (query) =>
      LISTING_QUERY_KEYS.some((key) => {
        const value = query[key];
        return value !== undefined && value !== null && value !== '';
      });

    const saveListingFiltersToStorage = (filters) => {
      if (!filters) return;
      try {
        const payload = {
          saleRent: filters.saleRent || 'All',
          area: Array.isArray(filters.area)
            ? filters.area.map((area) => ({
              id: area.id,
              name: area.name || '',
              subtitle: area.subtitle || '',
            }))
            : [],
          project: filters.project?.id ? { id: filters.project.id } : null,
          propertyType: filters.propertyType?.id ? { id: filters.propertyType.id } : null,
          propertyTypes: Array.isArray(filters.propertyTypes)
            ? filters.propertyTypes.map((type) => ({ id: type.id }))
            : [],
          beds: filters.beds || '',
          bedsList: filters.bedsList || [],
          baths: filters.baths || '',
          bathsList: filters.bathsList || [],
          priceFrom: filters.priceFrom ?? 0,
          priceTo: filters.priceTo ?? 10000000,
          sizeFrom: filters.sizeFrom ?? 0,
          sizeTo: filters.sizeTo ?? 10000,
          sort: filters.sort || 'created_at_desc',
          referenceNumber: filters.referenceNumber || '',
          completionStatus: filters.completionStatus || null,
          occupancyStatus: filters.occupancyStatus || null,
          agent: filters.agent?.id ? { id: filters.agent.id } : null,
          selectedFeatures: filters.selectedFeatures || {},
        };
        localStorage.setItem(LISTING_FILTERS_STORAGE_KEY, JSON.stringify(payload));
      } catch (error) {
        console.warn('Failed to save listing filters:', error);
      }
    };

    const loadListingFiltersFromStorage = () => {
      try {
        const raw = localStorage.getItem(LISTING_FILTERS_STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        return parsed;
      } catch (error) {
        console.warn('Failed to load listing filters:', error);
        return null;
      }
    };

    const pruneEmptyQueryValues = (obj) => {
      const out = {};
      Object.keys(obj).forEach((k) => {
        const v = obj[k];
        if (v === undefined || v === null || v === '') return;
        out[k] = v;
      });
      return out;
    };

    const encodeFiltersToQuery = (filters) => {
        const activeFeatures = filters.selectedFeatures 
        ? Object.keys(filters.selectedFeatures).filter(key => filters.selectedFeatures[key] === true)
        : [];
      return {
        sale_rent: filters.saleRent && filters.saleRent !== 'All' ? filters.saleRent : undefined,
        // area_id: filters.area?.id || undefined,
        area_ids: filters.area && filters.area.length > 0 
      ? filters.area.map(a => a.id).join(',')  // تحويل المصفوفة إلى سلسلة مفصولة بفواصل
      : undefined,
        project_id: filters.project?.id || undefined,
        type_id: filters.propertyType?.id || undefined,
        type_ids: Array.isArray(filters.propertyTypes) && filters.propertyTypes.length
          ? filters.propertyTypes.map((p) => p?.id).filter(Boolean).join(',')
          : undefined,
        beds: filters.beds || undefined,
        baths: filters.baths || undefined,
        beds_list: Array.isArray(filters.bedsList) && filters.bedsList.length ? filters.bedsList.join(',') : undefined,
        baths_list: Array.isArray(filters.bathsList) && filters.bathsList.length ? filters.bathsList.join(',') : undefined,
        price_from: filters.priceFrom > 0 ? filters.priceFrom : undefined,
        price_to: filters.priceTo < 10000000 ? filters.priceTo : undefined,
        size_from: filters.sizeFrom > 0 ? filters.sizeFrom : undefined,
        size_to: filters.sizeTo < 10000 ? filters.sizeTo : undefined,
        sort: filters.sort && filters.sort !== 'created_at_desc' ? filters.sort : undefined,
        ref: filters.referenceNumber || undefined,
        completion_status: filters.completionStatus?.value || undefined,
        occupancy_status: filters.occupancyStatus?.value || undefined,
        agent_id: filters.agent?.id || undefined,
         additional_features: activeFeatures.length > 0 ? activeFeatures.join(',') : undefined,
      };
    };

    const replaceRouteWithListingFilters = (filters, { keepPage = false } = {}) => {
      const page = route.query.page;
      const base = { ...route.query };
      LISTING_QUERY_KEYS.forEach((k) => { delete base[k]; });
      const merged = { ...base, ...encodeFiltersToQuery(filters) };
      if (keepPage && page) merged.page = page;
      router.replace({ query: pruneEmptyQueryValues(merged) });
    };

const decodeFiltersFromQuery = async (query) => {
        let areaIds = [];
  if (query.area_ids) {
    areaIds = query.area_ids.split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
  } else if (query.area_id) {
    areaIds = [parseInt(query.area_id)];
  }
  
  let areasWithNames = [];
  if (areaIds.length > 0) {
    try {
      const response = await api.get("/listings/areas", { 
        params: { ids: areaIds.join(',') } 
      });
      const areasData = response.data.data || response.data;
      const rows = Array.isArray(areasData) ? areasData : [];
      const byId = new Map(rows.map((area) => [area.id, area]));
      // Only IDs from the URL (API historically ignored `ids` and returned all areas)
      areasWithNames = areaIds.map((id) => {
        const area = byId.get(id);
        if (!area) {
          return { id, name: `Area ${id}`, subtitle: '' };
        }
        return {
          id: area.id,
          name: area.area_parents_title || area.name || area.title,
          subtitle: area.region || area.city || area.country || 'UAE'
        };
      });
    } catch (error) {
      console.error("Error fetching areas:", error);
      // Fallback: استخدام id فقط
      areasWithNames = areaIds.map(id => ({ id, name: '', subtitle: '' }));
    }
  }
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
        // area: query.area_id ? { id: Number(query.area_id) } : null,
             area: areasWithNames,
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
      };
    };

    // Handle filters from SearchBar
    const handleFiltersChanged = (filters, meta = {}) => {
      console.log("🎯 Filters received from SearchBar:", filters);

      // Reset = behave like a first visit: no filters, no sort, no saved state.
      if (meta.reset) {
        currentFilters.value = {};
        initialFilters.value = null;
        activeStatus.value = 'all';
        try {
          localStorage.removeItem(LISTING_FILTERS_STORAGE_KEY);
        } catch (error) {
          console.warn('Failed to clear listing filters:', error);
        }
        const base = { ...route.query };
        LISTING_QUERY_KEYS.forEach((k) => { delete base[k]; });
        router.replace({ query: pruneEmptyQueryValues(base) });
        fetchProperties({}, 1);
        return;
      }

      currentFilters.value = convertFiltersToAPI(filters);
      initialFilters.value = filters;
      
      activeStatus.value = 'all';
      
      // Fetch properties with new filters (reset to page 1)
      fetchProperties({}, 1); 

      // Persist filters in URL so browser Back / refresh restores the same search state
      replaceRouteWithListingFilters(filters);
      saveListingFiltersToStorage(filters);
    };

    // Convert frontend filters to backend API format
    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};

      // Sale/Rent Filter
      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }

      // Area Filter
      // if (filters.area && filters.area.id) {
      //   apiFilters.area_id = filters.area.id;
      // }
       if (filters.area && filters.area.length > 0) {
          apiFilters.area_ids = filters.area.map(a => a.id);
        }

     if (filters.completionStatus && filters.completionStatus.value) {
        apiFilters.completion_status = filters.completionStatus.value;
      }
      if (filters.occupancyStatus && filters.occupancyStatus.value) {
        apiFilters.occupancy_status = filters.occupancyStatus.value;
      }
      // Property Type Filter
      if (Array.isArray(filters.propertyTypes) && filters.propertyTypes.length) {
        const typeIds = filters.propertyTypes.map((p) => p?.id).filter(Boolean);
        apiFilters.property_type_ids = typeIds;
        if (typeIds.length === 1) {
          apiFilters.property_type_id = typeIds[0];
        }
      } else if (filters.propertyType && filters.propertyType.id) {
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
      
    if (filters.selectedFeatures && Object.keys(filters.selectedFeatures).length > 0) {
        const activeFeatures = Object.keys(filters.selectedFeatures).filter(
          key => filters.selectedFeatures[key] === true
        );
        if (activeFeatures.length > 0) {
          apiFilters.additional_features = activeFeatures;
        }
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

    const openDetailsModal = (event, property) => {
      openPropertyDetailsFromClick(event, property.id, {
        onDeleted: () => fetchProperties({}, pagination.value?.current_page || 1),
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
    onMounted(async () => {
      await fetchUserInfo();
  const pageFromUrl = route.query.page ? parseInt(route.query.page) : 1;

      const restoreAndFetch = async (filters) => {
        currentFilters.value = convertFiltersToAPI(filters);
        initialFilters.value = filters;
        saveListingFiltersToStorage(filters);
        await fetchProperties({}, pageFromUrl);
      };

      if (hasListingQueryParams(route.query)) {
        try {
          const filters = await decodeFiltersFromQuery(route.query);
          await restoreAndFetch(filters);
          // Drop default values like sale_rent=All / sort=created_at_desc from the URL.
          replaceRouteWithListingFilters(filters, { keepPage: true });
        } catch (e) {
          console.error('Failed to restore listing filters from URL:', e);
          fetchProperties({},pageFromUrl);
        }
        return;
      }

      const storedFilters = loadListingFiltersFromStorage();
      if (storedFilters) {
        try {
          await restoreAndFetch(storedFilters);
          replaceRouteWithListingFilters(storedFilters);
        } catch (e) {
          console.error('Failed to restore listing filters from storage:', e);
          fetchProperties({},pageFromUrl);
        }
        return;
      }

      fetchProperties({},pageFromUrl);
    });

    return {
      isMobileViewport,
      defaultImages,
      properties: filteredProperties, 
      propertyIcon,
      bedIcon,
      bathIcon,
      sqftIcon,
      isLoading,
      compareMode,
      listingPurpose,
      isCompareSelected,
      onListingClick,
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
      notifyMe,
      breakdownModalOpen,
      breakdownModalListingId,
      breakdownModalPreview,
      openBreakdownModal,
      onBreakdownSaved,
      openDetailsModal,
      listingNeedsPaymentBreakdownHighlight,
      hidesBedsBaths,
      canQuickEditPaymentBreakdown,
    };
  }
};
</script>
<style scoped>
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
/* Toolbar layout; status + Hot Deal row come from ListingsSearchBar (scoped there). */
.top-search-toolbar {
  display: block;
}

.top-search-col {
  flex: 1 1 auto;
  min-width: 0;
}

</style>