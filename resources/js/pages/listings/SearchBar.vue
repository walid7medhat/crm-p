<template>
  <div class="search-container listing-search-transparent">
    <div class="listing-search-shell">
      <div class="listing-headline">
        <h2>{{ dynamicHeadline }}</h2>
        <span>{{ formattedResultCount }} listed</span>
      </div>

      <div class="listing-main-search">
        <i class="ri-search-line listing-main-search-icon"></i>
        <v-select
          v-model="selectedArea"
          :options="areas"
          :disabled="isLoadingAreas"
          :multiple="true"
          :close-on-select="false"
          :clear-search-on-select="false"
          :append-to-body="false"
          label="name"
          placeholder="City, community or building"
          class="custom-select listing-main-location"
          @update:modelValue="handleFilterChange"
        >
           <template #open-indicator="{ attributes }">
              <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
              </span>
          </template>
          <template #clear-indicator="{ attributes }">
            <span v-bind="attributes">
               <i class="ri-close-line custom-clear"></i>       
             </span>
          </template>
          <template #option="option">
            <div class="location-option">
              <i class="ri-map-pin-line location-option-icon"></i>
              <div class="location-option-text">
                <span class="location-option-name">{{ locationFirstLine(option) }}</span>
                <span class="location-option-subtitle">{{ locationSecondLine(option) }}</span>
              </div>
            </div>
          </template>
          <template #selected-option="option">
            <div v-if="option" class="location-selected">
              <span class="location-selected-name">{{ locationFirstLine(option) }}</span>
              <span class="location-selected-subtitle">{{ locationSecondLine(option) }}</span>
            </div>
          </template>
        <template #selected-option-container="{ option, deselect, disabled }">
          <span class="vs__selected location-chip">
            {{ locationFirstLine(option) }}

            <button
              v-if="!disabled"
              class="vs__deselect location-chip-close"
              type="button"
              @click.stop="deselect(option)"
            >
              <i class="ri-close-line custom-clear"></i>
            </button>
          </span>
        </template>
        </v-select>
      </div>

      <div class="listing-pill-row">
        <v-select
          v-model="selectedSaleRent"
          :options="typeOptions"
          label="label"
          :reduce="option => option.value"
          :searchable="false"
          :append-to-body="false"
          placeholder="Rent"
          class="custom-select listing-pill-select listing-pill-select-sm"
          @update:modelValue="handleFilterChange"
        >
           <template #open-indicator="{ attributes }">
                <span v-bind="attributes">
                    <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                </span>
            </template>
            <template #clear-indicator="{ attributes }">
            <span v-bind="attributes">
               <i class="ri-close-line custom-clear"></i>            </span>
          </template>
        </v-select>

        <v-select
          v-model="selectedPropertyType"
          :options="propertyTypes"
          :disabled="isLoadingPropertyTypes"
          label="name"
          :searchable="false"
          :append-to-body="false"
          placeholder="Property type"
          class="custom-select listing-pill-select"
          @update:modelValue="handleFilterChange"
        >
           <template #open-indicator="{ attributes }">
              <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
              </span>
          </template>
          <template #clear-indicator="{ attributes }">
            <span v-bind="attributes">
               <i class="ri-close-line custom-clear"></i>            </span>
          </template>
        </v-select>

        <div class="listing-beds-wrap">
          <button type="button" class="listing-pill-btn" @click.stop="toggleBedsDropdown">
           <span>
                {{
                  selectedBeds || selectedBaths
                    ? `${selectedBeds || ''} Beds / ${selectedBaths || ''} Baths`
                    : 'Beds & Baths'
                }}
              </span>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div v-if="showBedsDropdown" class="listing-beds-popover" @click.stop>
            <div class="listing-pop-label">Bedrooms</div>
            <div class="listing-chip-grid">
              <button
                v-for="bed in bedsOptions"
                :key="bed"
                type="button"
                class="listing-chip-btn"
                :class="{ active: selectedBeds === bed }"
                @click="selectBedsOption(bed)"
              >
                {{ bed }}
              </button>
            </div>
            <div class="listing-pop-label mt-2">Bathrooms</div>
              <div class="listing-chip-grid">
                <button
                  v-for="bath in bathsOptions"
                  :key="bath"
                  type="button"
                  class="listing-chip-btn"
                  :class="{ active: selectedBaths === bath }"
                  @click="selectBathOption(bath)"
                >
                  {{ bath }}
                </button>
              </div>
          </div>
        </div>

        <div class="listing-price-wrap">
          <button type="button" class="listing-pill-btn" @click.stop="togglePriceDropdown">
            <span>Price</span>
            <i class="ri-arrow-down-s-line"></i>
          </button>

          <div v-if="showPriceDropdown" class="listing-price-popover" @click.stop>
            <div class="listing-pop-grid">
              <div>
                <label>Minimum</label>
                <input
                  type="text"
                  :value="formatThousandsDisplay(priceFrom)"
                  class="range-input-side"
                  @input="onPriceFromInput"
                  @blur="handlePriceChange"
                  placeholder="0"
                >
              </div>
              <div>
                <label>Maximum</label>
                <input
                  type="text"
                  :value="formatThousandsDisplay(priceTo)"
                  class="range-input-side"
                  @input="onPriceToInput"
                  @blur="handlePriceChange"
                  placeholder="Any"
                >
              </div>
            </div>
          </div>
        </div>

        <div class="listing-filter-wrap">
          <button type="button" class="listing-pill-btn listing-filter-btn" @click.stop="toggleMoreFilters">
            <span>Filters</span>
            <i class="ri-equalizer-line"></i>
          </button>
          <div v-if="showMoreFilters" class="listing-more-filter-popover" @click.stop>
            <div class="listing-pop-title">More Filters</div>

            <div class="listing-filter-section">
              <label class="listing-pop-label">Area (sqft)</label>
              <div class="listing-pop-grid listing-pop-grid--range">
                <div>
                  <label>Minimum</label>
                  <input
                    type="text"
                    :value="formatThousandsDisplay(sizeFrom)"
                    class="range-input-side"
                    @input="onSizeFromInput"
                    @blur="handleSizeChange"
                    placeholder="0"
                  >
                </div>
                <div>
                  <label>Maximum</label>
                  <input
                    type="text"
                    :value="formatThousandsDisplay(sizeTo)"
                    class="range-input-side"
                    @input="onSizeToInput"
                    @blur="handleSizeChange"
                    placeholder="Any"
                  >
                </div>
              </div>
            </div>

            <div class="listing-pop-grid listing-pop-grid--two">
              <div>
                <label>Project Status</label>
                <v-select
                  v-model="selectedCompletionStatus"
                  :options="completionStatusOptions"
                  :searchable="false"
                  :append-to-body="false"
                  placeholder="Select status"
                  class="custom-select listing-pop-select"
                  @update:modelValue="handleFilterChange"
                >
                 <template #open-indicator="{ attributes }">
                      <span v-bind="attributes">
                          <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                      </span>
                  </template>
                  <template #clear-indicator="{ attributes }">
                    <span v-bind="attributes">
                       <i class="ri-close-line custom-clear"></i>                    </span>
                  </template>
              </v-select>
              </div>
              <div>
                <label>Sort By</label>
                <v-select
                  v-model="selectedSort"
                  :options="sortOptions"
                  label="label"
                  :reduce="option => option.value"
                  :searchable="false"
                  :append-to-body="false"
                  placeholder="Most Recent"
                  class="custom-select listing-pop-select"
                  @update:modelValue="handleFilterChange"
                >
               <template #open-indicator="{ attributes }">
                    <span v-bind="attributes">
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                    </span>
                </template>
                <template #clear-indicator="{ attributes }">
                  <span v-bind="attributes">
                     <i class="ri-close-line custom-clear"></i>                  </span>
                </template>
              </v-select>
              </div>
              <div class="listing-pop-field--full"   v-if="!isMyListingPage">
                <label>Agent</label>
                <v-select
                  v-model="selectedAgent"
                  :options="agents"
                  :disabled="isLoadingAgents"
                  label="name"
                  :searchable="true"
                  :append-to-body="false"
                  placeholder="Select agent"
                  class="custom-select listing-pop-select"
                  @update:modelValue="handleFilterChange"
                >
               <template #open-indicator="{ attributes }">
                    <span v-bind="attributes">
                        <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
                    </span>
                </template>
                <template #clear-indicator="{ attributes }">
                    <span v-bind="attributes">
                       <i class="ri-close-line custom-clear"></i>                    </span>
                  </template>
              </v-select>
              </div>
            </div>

            <div class="listing-pop-actions">
              <button type="button" class="btn btn-outline-secondary" @click="resetFilters">Reset</button>
              <button type="button" class="btn btn-primary" @click="showMoreFilters = false; applyFilters()">Done</button>
            </div>
          </div>
        </div>

        <router-link
          to="/notify-me"
          class="listing-icon-circle listing-notify-btn"
          title="Notify me"
        >
          <i class="ri-notification-2-line"></i>
        </router-link>

        <button class="btn unified-search-btn" @click="applyFilters" :disabled="isLoadingAreas || isLoadingPropertyTypes">
          <i class="ri-search-line"></i>
          Search
        </button>
        <div  class="listing-status-row">
          <button class="status-btn" v-if="showStatusTabs" :class="{ active: activeStatus === 'all' }" @click="emitStatusChange('all')">
            <i class="ri-list-check"></i> All
          </button>
          <button class="status-btn" v-if="showStatusTabs" :class="{ active: activeStatus === 'active' }" @click="emitStatusChange('active')">
            <i class="ri-checkbox-circle-line"></i> Active
          </button>
          <button class="status-btn" v-if="showStatusTabs" :class="{ active: activeStatus === 'inactive' }" @click="emitStatusChange('inactive')">
            <i class="ri-close-circle-line"></i> Inactive
          </button>
          <button class="status-btn"  v-if="showStatusTabs" :class="{ active: activeStatus === 'sold' }" @click="emitStatusChange('sold')">
            <i class="ri-checkbox-circle-fill"></i> Sold Out
          </button>
          <button class="status-btn" v-if="showStatusTabs" :class="{ active: activeStatus === 'draft' }" @click="emitStatusChange('draft')">
            <i class="fa fa-pencil-alt"></i> Draft
          </button>
          <span class="status-sort-separator" v-if="showStatusTabs"></span>
          <button
            type="button"
            class="status-btn hot-deal-btn"
            :class="{ active: selectedSort === 'hot_deal' }"
            @click="setQuickSort('hot_deal')"
          >
            <i class="ri-fire-line" aria-hidden="true"></i>
            Hot Deal
          </button>
          <v-select
            :modelValue="quickSortForDropdown"
            :options="quickSortSelectOptions"
            label="label"
            :reduce="(option) => option.value"
            :searchable="false"
            :append-to-body="false"
            placeholder="Price & date sort"
            class="custom-select listing-status-row-sort-select"
            @update:modelValue="setQuickSort"
          >
         <template #open-indicator="{ attributes }">
            <span v-bind="attributes">
                <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
            </span>
        </template>
        <template #clear-indicator="{ attributes }">
          <span v-bind="attributes">
             <i class="ri-close-line custom-clear"></i>          </span>
        </template>
          </v-select>
        </div>
      </div>
    </div>

    <div v-if="showPriceDropdown || showMoreFilters || showBedsDropdown" class="dropdown-overlay" @click="closeAllDropdowns"></div>
  </div>
</template>

<script>
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { ref, onMounted, computed, getCurrentInstance, onUnmounted, watch } from 'vue';
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import { useRoute } from 'vue-router';
import api from '@/plugins/axios';

export default {
  name: "FixedSearchBar",
  components: { vSelect },
  props: {
    initialFilters: {
      type: Object,
      default: null
    },
    resultCount: {
      type: Number,
      default: null
    },
    showStatusTabs: {
      type: Boolean,
      default: false
    },
    activeStatus: {
      type: String,
      default: "all"
    }
  },
  setup(props, { emit }) {
    
    // Reactive data
    const areas = ref([]);
      const projects = ref([]);
    const propertyTypes = ref([]);
    const agents = ref([]);
    const isLoadingAreas = ref(false);
    const isLoadingPropertyTypes = ref(false);
    const isLoadingAgents = ref(false);
     const isLoadingProjects = ref(false);
      const selectedProject = ref(null); 
      const selectedCompletionStatus = ref(null); 
    const selectedSaleRent = ref("");
    const selectedStatus = ref("All");
    const selectedArea = ref([]);
    const selectedPropertyType = ref(null);
    const selectedAgent = ref(null);
    const selectedBeds = ref("");
    const selectedSort = ref("");

    const priceFrom = ref(0);
    const priceTo = ref(10000000);
    const sizeFrom = ref(0);
    const sizeTo = ref(10000);

    const showPriceDropdown = ref(false);
    const showSizeDropdown = ref(false);
    const showMoreFilters = ref(false);
    const showBedsDropdown = ref(false);
const searchReferenceNumber = ref("");

    // Debounce timer
    const searchTimer = ref(null);
const allAreas = ref([]);


const route = useRoute();

const isMyListingPage = computed(() => {
  return route.path === '/my-listing';
});


    // Static options
    const saleRentOptions = ["All", "Sale", "Rent"];
    const statusOptions = ["All", "Ready", "Offplan"];
    const bedsOptions = ["Studio", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10+"];
    const selectedBaths = ref("");
const bathsOptions = ["1", "2", "3", "4", "5", "6+"];
     const completionStatusOptions = [
      { label: "All", value: null },
      { label: "Completed", value: "Completed" },
      { label: "Under Construction", value: "Under Construction" }
    ];
const typeOptions = [
  { label: "All", value: "All" },
  { label: "Sale", value: "Sale" },
  { label: "Rent", value: "Rent" }
];

const sortOptions = [
  { label: "Hot Deal", value: "hot_deal" },
  { label: "Latest Listings", value: "created_at_desc" },
  { label: "Price: Low to High", value: "price_asc" },
  { label: "Price: High to Low", value: "price_desc" }
];

    /** Status row: dropdown for price/date only (Hot Deal stays a separate button). */
    const quickSortSelectOptions = [
      { label: "Latest Listings", value: "created_at_desc" },
      { label: "Price: Low to High", value: "price_asc" },
      { label: "Price: High to Low", value: "price_desc" }
    ];
    // Fetch areas from API
     const fetchProjects = async () => {
      try {
        isLoadingProjects.value = true;
        const response = await api.get("/listings/projects");
        
        const projectsData = response.data.data || response.data;
        
        projects.value = projectsData.map(project => ({
          id: project.id,
          name: project.name || project.project_name || project.title || `Project ${project.id}`
        }));
        
        console.log("✅ Projects loaded:", projects.value.length);
        
      } catch (error) {
        console.error("❌ Error fetching projects:", error.response || error);
      } finally {
        isLoadingProjects.value = false;
      }
    };
    const fetchAreas = async () => {
  try {
    isLoadingAreas.value = true;
    const response = await api.get("/listings/areas/?has_listings=true");
    
    const areasData = response.data.data || response.data;
    
    const mappedAreas = areasData.map(area => ({
      id: area.id,
      name: area.area_parents_title || area.name || area.title,
      subtitle: area.region || area.city || area.country || 'UAE'
    }));
    
    areas.value = mappedAreas;
    allAreas.value = mappedAreas; // حفظ نسخة كاملة
    
    console.log("✅ Areas loaded:", areas.value.length);
    
  } catch (error) {
    console.error("❌ Error fetching areas:", error.response || error);
  } finally {
    isLoadingAreas.value = false;
  }
};

    // Fetch property types from API
    const fetchPropertyTypes = async () => {
      try {
        isLoadingPropertyTypes.value = true;
        const response = await api.get("/listings/property-types");
        
        const propertyTypesData = response.data.data || response.data;
        
        propertyTypes.value = propertyTypesData.map(type => ({
          id: type.id,
          name: type.name || type.type_name || type.title
        }));
        
        console.log("✅ Property types loaded:", propertyTypes.value.length);
        
      } catch (error) {
        console.error("❌ Error fetching property types:", error.response || error);
      } finally {
        isLoadingPropertyTypes.value = false;
      }
    };
const selectBathOption = (bath) => {
  selectedBaths.value = selectedBaths.value === bath ? "" : bath;
  handleFilterChange();
};
    const fetchAgents = async () => {
      try {
        isLoadingAgents.value = true;
        const response = await api.get("/listings/agents");
        const agentsData = response.data.data || response.data;
        agents.value = agentsData.map(agent => ({
          id: agent.id,
          name: agent.name || `${agent.first_name || ''} ${agent.last_name || ''}`.trim() || `Agent ${agent.id}`,
        }));
      } catch (error) {
        console.error("Error fetching agents:", error.response || error);
      } finally {
        isLoadingAgents.value = false;
      }
    };

    // Computed properties
    const priceProgressStyle = computed(() => {
      const min = 0;
      const max = 10000000;
      const from = ((priceFrom.value - min) / (max - min)) * 100;
      const to = ((priceTo.value - min) / (max - min)) * 100;
      return {
        left: `${from}%`,
        right: `${100 - to}%`
      };
    });

    const sizeProgressStyle = computed(() => {
      const min = 0;
      const max = 10000;
      const from = ((sizeFrom.value - min) / (max - min)) * 100;
      const to = ((sizeTo.value - min) / (max - min)) * 100;
      return {
        left: `${from}%`,
        right: `${100 - to}%`
      };
    });

    const hasActiveFilters = computed(() => {
      const hasSelectedAreas = Array.isArray(selectedArea.value)
        ? selectedArea.value.length > 0
        : !!selectedArea.value;
      return selectedSaleRent.value !== "All" || 
             selectedStatus.value !== "All" || 
             hasSelectedAreas || 
             selectedPropertyType.value || 
             selectedBeds.value ||
             selectedBaths.value || 
             priceFrom.value > 0 || 
             priceTo.value < 10000000 || 
             sizeFrom.value > 0 || 
             sizeTo.value < 10000 || searchReferenceNumber.value.trim() !== ""
             ||
             selectedCompletionStatus.value !== null;
    });

    const dynamicHeadline = computed(() => {
      const hasMode = selectedSaleRent.value && selectedSaleRent.value !== 'All';
      const selectedAreas = Array.isArray(selectedArea.value) ? selectedArea.value : (selectedArea.value ? [selectedArea.value] : []);
      const hasLocation = selectedAreas.length > 0;
      const locationLabel = hasLocation
        ? (selectedAreas.length === 1
            ? locationFirstLine(selectedAreas[0])
            : `${locationFirstLine(selectedAreas[0])} +${selectedAreas.length - 1}`)
        : 'UAE';
      if (!hasMode && !hasLocation) return 'Properties in UAE';
      if (hasMode) return `Properties for ${selectedSaleRent.value.toLowerCase()} in ${locationLabel}`;
      return `Properties in ${locationLabel}`;
    });

    const formattedResultCount = computed(() => {
      const count = props.resultCount ?? 116323;
      return Number(count || 0).toLocaleString();
    });

    // Convert filters to API format
    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};
      if (filters.completionStatus && filters.completionStatus.value) {
        apiFilters.completion_status = filters.completionStatus.value;
      }
      // Sale/Rent Filter
      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }

      // Status Filter
      if (filters.status && filters.status !== 'All') {
        apiFilters.completion_status = filters.status;
      }

      // Area Filter
      if (Array.isArray(filters.area) && filters.area.length) {
        apiFilters.area_ids = filters.area.map(a => a?.id).filter(Boolean);
      } else if (filters.area && filters.area.id) {
        apiFilters.area_id = filters.area.id;
      }
     if (filters.project) {
        apiFilters.project_id = filters.project.id;
      }
      // Property Type Filter
      if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
      if (filters.agent) {
        apiFilters.agent_id = filters.agent.id;
      }

      // Bedrooms Filter
      if (filters.beds) {
        if (filters.beds == 'Studio') {
          apiFilters.number_of_bedrooms = 'Studio';
        } else {
          apiFilters.number_of_bedrooms = parseInt(filters.beds);
        }
      }
      if (filters.baths) {
          apiFilters.number_of_bathrooms = parseInt(filters.baths);
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
      if (filters.referenceNumber && filters.referenceNumber.trim() !== "") {
        apiFilters.reference_number = filters.referenceNumber.trim();
      }

      // Sort
     if (filters.sort) {
          apiFilters.sort = filters.sort; 
        }

      return apiFilters;
    };

    const performSearch = () => {
      if (searchTimer.value) {
        clearTimeout(searchTimer.value);
      }

      searchTimer.value = setTimeout(() => {
        const filters = {
          saleRent: selectedSaleRent.value,
              completionStatus: selectedCompletionStatus.value,
          status: selectedStatus.value,
          area: selectedArea.value,
          project: selectedProject.value,
          propertyType: selectedPropertyType.value,
          agent: selectedAgent.value,
          beds: selectedBeds.value,
          priceFrom: priceFrom.value,
          priceTo: priceTo.value,
          sizeFrom: sizeFrom.value,
          sizeTo: sizeTo.value,
          sort: selectedSort.value,
           referenceNumber: searchReferenceNumber.value,
           baths: selectedBaths.value,
        };
        
        console.log("🔍 Auto-search with filters:", filters);
        
        // Emit event to parent component
        emit('filters-changed', filters);
      }, 500);
    };

    const handleFilterChange = () => {
       
      performSearch();
    };

    const emitStatusChange = (status) => {
      emit('status-changed', status);
    };

    const setQuickSort = (sortValue) => {
      selectedSort.value = sortValue;
      handleFilterChange();
    };

    /** When Hot Deal is selected, dropdown shows placeholder (no price/date value). */
    const quickSortForDropdown = computed(() => {
      const priceDateSorts = new Set(["created_at_desc", "price_asc", "price_desc"]);
      return priceDateSorts.has(selectedSort.value) ? selectedSort.value : null;
    });

    const handleSaleRentChange = (type) => {
      selectedSaleRent.value = type;
      performSearch();
    };

    const handleStatusChange = (status) => {
      selectedStatus.value = status;
      performSearch();
    };

    const handlePriceChange = () => {
      validatePriceFrom();
      validatePriceTo();
      performSearch();
    };

    const handlePriceSliderChange = () => {
      updatePriceFrom();
      updatePriceTo();
      performSearch();
    };

    const handleSizeChange = () => {
      validateSizeFrom();
      validateSizeTo();
      performSearch();
    };

    const handleSizeSliderChange = () => {
      updateSizeFrom();
      updateSizeTo();
      performSearch();
    };

    const applyFilters = () => {
       if (!selectedSort.value) {
        selectedSort.value = "created_at_desc";
      }
      const filters = {
        saleRent: selectedSaleRent.value,
         completionStatus: selectedCompletionStatus.value,
        status: selectedStatus.value,
        area: selectedArea.value,
         project: selectedProject.value,
        propertyType: selectedPropertyType.value,
        agent: selectedAgent.value,
        beds: selectedBeds.value,
        baths: selectedBaths.value,
        priceFrom: priceFrom.value,
        priceTo: priceTo.value,
        sizeFrom: sizeFrom.value,
        sizeTo: sizeTo.value,
        sort: selectedSort.value,
         referenceNumber: searchReferenceNumber.value ,
           
      };
      
      console.log("🔍 Manual search with filters:", filters);
      emit('filters-changed', filters);
    };

    // When coming back with saved filters (e.g. via Back button), restore them
    // Restore form values from URL/parent when coming back (do not emit — parent already fetches)
    const resolveCompletionForSelect = (cs) => {
      if (cs == null || cs === '') return null;
      const val = typeof cs === 'object' && cs !== null && 'value' in cs ? cs.value : cs;
      const found = completionStatusOptions.find((o) => o.value === val);
      return found !== undefined ? found : (typeof cs === 'object' ? cs : null);
    };

    /** vue-select matches option objects by reference; after URL restore we only have { id }. Re-bind to loaded options so the label shows (Villa, etc.). */
    const syncPropertyTypeAndAgentFromLoadedOptions = () => {
      const filters = props.initialFilters;
      if (!filters) return;
      if (filters.propertyType?.id != null && propertyTypes.value.length) {
        const found = propertyTypes.value.find(
          (p) => Number(p.id) === Number(filters.propertyType.id)
        );
        if (found) selectedPropertyType.value = found;
      }
      if (filters.agent?.id != null && agents.value.length) {
        const found = agents.value.find(
          (a) => Number(a.id) === Number(filters.agent.id)
        );
        if (found) selectedAgent.value = found;
      }
    };

    watch(
      () => props.initialFilters,
      (filters) => {
        if (!filters) return;
        selectedSaleRent.value = filters.saleRent ?? "All";
        selectedStatus.value = filters.status ?? "All";
      
          let areasWithNames = [];
        if (filters.area && filters.area.length > 0) {
          if (allAreas.value.length > 0) {
            // البحث عن المناطق في القائمة المحملة
            areasWithNames = filters.area.map(area => {
              const foundArea = allAreas.value.find(a => a.id === area.id);
              return foundArea || { id: area.id, name: `Area ${area.id}`, subtitle: '' };
            });
          } else {
            // إذا لم تكن المناطق محملة بعد، استخدم البيانات الموجودة أو انتظر التحميل
            areasWithNames = filters.area;
          }
        }
            selectedArea.value = areasWithNames;
        selectedProject.value = filters.project || null;
        selectedPropertyType.value = filters.propertyType || null;
        selectedAgent.value = filters.agent || null;
        selectedBeds.value = filters.beds || "";
        selectedBaths.value = filters.baths ?? "";
        selectedSort.value = filters.sort || "created_at_desc";
        priceFrom.value = filters.priceFrom ?? 0;
        priceTo.value = filters.priceTo ?? 10000000;
        sizeFrom.value = filters.sizeFrom ?? 0;
        sizeTo.value = filters.sizeTo ?? 10000;
        searchReferenceNumber.value = filters.referenceNumber || "";
        selectedCompletionStatus.value = resolveCompletionForSelect(filters.completionStatus);
        syncPropertyTypeAndAgentFromLoadedOptions();
      },
      { immediate: true, deep: false }
    );

    watch([propertyTypes, agents], () => {
      syncPropertyTypeAndAgentFromLoadedOptions();
    });

    const resetFilters = () => {
      selectedSaleRent.value = "All";
      selectedStatus.value = "All";
        selectedCompletionStatus.value = null;
      selectedArea.value = [];
      selectedProject.value = null;
      selectedPropertyType.value = null;
      selectedAgent.value = null;
      selectedBeds.value = "";
      selectedSort.value = "created_at_desc";
      priceFrom.value = 0;
      priceTo.value = 5000000;
      sizeFrom.value = 0;
      sizeTo.value = 5000;
      performSearch(); 
      searchReferenceNumber.value = "";
    };

    const resetPriceRange = () => {
      priceFrom.value = 0;
      priceTo.value = 5000000;
      performSearch();
    };

    const resetSizeRange = () => {
      sizeFrom.value = 0;
      sizeTo.value = 5000;
      performSearch();
    };

    const togglePriceDropdown = () => {
      showPriceDropdown.value = !showPriceDropdown.value;
      if (showPriceDropdown.value) {
        showMoreFilters.value = false;
        showSizeDropdown.value = false;
        showBedsDropdown.value = false;
      }
    };

    const toggleBedsDropdown = () => {
      showBedsDropdown.value = !showBedsDropdown.value;
      if (showBedsDropdown.value) {
        showPriceDropdown.value = false;
        showMoreFilters.value = false;
        showSizeDropdown.value = false;
      }
    };

    const selectBedsOption = (bed) => {
      selectedBeds.value = selectedBeds.value === bed ? "" : bed;
      handleFilterChange();
    };

    const toggleSizeDropdown = () => {
      showSizeDropdown.value = !showSizeDropdown.value;
    };

    const closeAllDropdowns = () => {
      showPriceDropdown.value = false;
      showSizeDropdown.value = false;
      showMoreFilters.value = false;
      showBedsDropdown.value = false;
    };

    const toggleMoreFilters = () => {
      showMoreFilters.value = !showMoreFilters.value;
      if (showMoreFilters.value) {
        showPriceDropdown.value = false;
        showSizeDropdown.value = false;
        showBedsDropdown.value = false;
      }
    };

    const updatePriceFrom = () => {
      if (priceFrom.value > priceTo.value) priceTo.value = priceFrom.value;
    };

    const updatePriceTo = () => {
      if (priceTo.value < priceFrom.value) priceFrom.value = priceTo.value;
    };

    const updateSizeFrom = () => {
      if (sizeFrom.value > sizeTo.value) sizeTo.value = sizeFrom.value;
    };

    const updateSizeTo = () => {
      if (sizeTo.value < sizeFrom.value) sizeFrom.value = sizeTo.value;
    };

    const validatePriceFrom = () => {
      priceFrom.value = parseInt(priceFrom.value) || 0;
      if (priceFrom.value < 0) priceFrom.value = 0;
      if (priceFrom.value > 10000000) priceFrom.value = 10000000;
      updatePriceFrom();
    };

    const validatePriceTo = () => {
      priceTo.value = parseInt(priceTo.value) || 5000000;
      if (priceTo.value < 0) priceTo.value = 0;
      if (priceTo.value > 10000000) priceTo.value = 10000000;
      updatePriceTo();
    };

    const validateSizeFrom = () => {
      sizeFrom.value = parseInt(sizeFrom.value) || 0;
      if (sizeFrom.value < 0) sizeFrom.value = 0;
      if (sizeFrom.value > 10000) sizeFrom.value = 10000;
      updateSizeFrom();
    };

    const validateSizeTo = () => {
      sizeTo.value = parseInt(sizeTo.value) || 5000;
      if (sizeTo.value < 0) sizeTo.value = 0;
      if (sizeTo.value > 10000) sizeTo.value = 10000;
      updateSizeTo();
    };

    const formatNumber = (num) => {
      return num.toLocaleString();
    };

    const parseThousandsInput = (value) => {
      const digits = String(value ?? '').replace(/[^\d]/g, '');
      return digits ? parseInt(digits, 10) : 0;
    };

    const formatThousandsDisplay = (value) => {
      const parsed = Number(value);
      if (!Number.isFinite(parsed) || parsed <= 0) return '';
      return parsed.toLocaleString();
    };

    const onPriceFromInput = (event) => {
      priceFrom.value = parseThousandsInput(event.target.value);
      event.target.value = formatThousandsDisplay(priceFrom.value);
    };

    const onPriceToInput = (event) => {
      priceTo.value = parseThousandsInput(event.target.value);
      event.target.value = formatThousandsDisplay(priceTo.value);
    };

    const onSizeFromInput = (event) => {
      sizeFrom.value = parseThousandsInput(event.target.value);
      event.target.value = formatThousandsDisplay(sizeFrom.value);
    };

    const onSizeToInput = (event) => {
      sizeTo.value = parseThousandsInput(event.target.value);
      event.target.value = formatThousandsDisplay(sizeTo.value);
    };

    // Location: first line = first part of name, second line = full remainder (rest + subtitle)
    const locationFirstLine = (option) => {
      if (!option || !option.name) return '';
      const idx = option.name.indexOf(',');
      return idx > 0 ? option.name.slice(0, idx).trim() : option.name;
    };
    const locationSecondLine = (option) => {
      if (!option) return '';
      const name = option.name || '';
      const subtitle = option.subtitle || 'UAE';
      const idx = name.indexOf(',');
      const rest = idx > 0 ? name.slice(idx + 1).trim() : '';
      if (rest) return subtitle ? `${rest}, ${subtitle}` : rest;
      return subtitle;
    };

    const isFirstSelectedArea = (option) => {
      const selectedAreas = Array.isArray(selectedArea.value) ? selectedArea.value : [];
      return selectedAreas.length > 0 && selectedAreas[0]?.id === option?.id;
    };

    const isSecondSelectedArea = (option) => {
      const selectedAreas = Array.isArray(selectedArea.value) ? selectedArea.value : [];
      return selectedAreas.length > 1 && selectedAreas[1]?.id === option?.id;
    };

    const remainingSelectedAreasCount = computed(() => {
      const selectedAreas = Array.isArray(selectedArea.value) ? selectedArea.value : [];
      return Math.max(selectedAreas.length - 1, 0);
    });

    onMounted(() => {
      fetchAreas();
      fetchPropertyTypes();
      fetchAgents();
fetchProjects()
      document.addEventListener('click', (e) => {
        const searchContainer = document.querySelector('.search-container');
        if (searchContainer && !searchContainer.contains(e.target)) {
          closeAllDropdowns();
        }
      });
    });

    onUnmounted(() => {
      if (searchTimer.value) {
        clearTimeout(searchTimer.value);
      }
    });

    return {
      // Data
      areas,
      projects,
      propertyTypes,
      agents,
      isLoadingAreas,
      isLoadingPropertyTypes,
      isLoadingAgents,
      isLoadingProjects, 
      selectedSaleRent,
      selectedStatus,
       selectedCompletionStatus, 
      selectedArea,
      selectedProject, 
      selectedPropertyType,
      selectedAgent,
      selectedBeds,
      selectedBaths,
      selectedSort,
      priceFrom,
      priceTo,
      sizeFrom,
      sizeTo,
      showPriceDropdown,
      showSizeDropdown,
      showMoreFilters,
      showBedsDropdown,
      
      // Static options
      saleRentOptions,
      typeOptions,
      statusOptions,
       completionStatusOptions,
      bedsOptions,
      bathsOptions,
      sortOptions,
      quickSortSelectOptions,
      quickSortForDropdown,
      isMyListingPage,
      // Computed
      priceProgressStyle,
      sizeProgressStyle,
      hasActiveFilters,
      dynamicHeadline,
      formattedResultCount,
      
      // Methods
      applyFilters,
      resetFilters,
      resetPriceRange,
      resetSizeRange,
      togglePriceDropdown,
      toggleBedsDropdown,
      toggleSizeDropdown,
      toggleMoreFilters,
      closeAllDropdowns,
      selectBedsOption,
      selectBathOption,
      updatePriceFrom,
      updatePriceTo,
      updateSizeFrom,
      updateSizeTo,
      validatePriceFrom,
      validatePriceTo,
      validateSizeFrom,
      validateSizeTo,
      formatNumber,
      formatThousandsDisplay,
      handleFilterChange,
      emitStatusChange,
      setQuickSort,
      handleSaleRentChange,
      handleStatusChange,
      handlePriceChange,
      handlePriceSliderChange,
      handleSizeChange,
      handleSizeSliderChange,
      onPriceFromInput,
      onPriceToInput,
      onSizeFromInput,
      onSizeToInput,
      searchReferenceNumber,
      locationFirstLine,
      locationSecondLine,
      isFirstSelectedArea,
      isSecondSelectedArea,
      remainingSelectedAreasCount,
    };
  }
};
</script>

<style scoped>
:deep(.vs__dropdown-toggle) {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}

:deep(.vs__dropdown-toggle:focus) {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}

:deep(.vs__dropdown-toggle:hover) {
    border: none !important;
}

.search-container {
  width: 100%;
  margin: 0;
}

.search-bar {
  width: 100%;
  padding: 12px 16px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border: 1px solid #eaeaea;
  margin: 0;
}

/* Main Search Row */
.main-search-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
  align-items: flex-end;
}

/* Single line: all filters in one row, full width */
.main-search-row-single {
  display: flex;
  flex-wrap: nowrap;
  gap: 10px;
  margin-bottom: 10px;
  width: 100%;
  align-items: flex-end;
}

.form-group-inline {
  flex: 1 1 0;
  min-width: 0;
  width: 100%;
}

.form-group-inline :deep(.custom-select),
.form-group-inline :deep(.vs__dropdown-toggle),
.form-group-inline .range-dropdown,
.form-group-inline .range-dropdown-btn {
  width: 100%;
}

/* Location: wider than other fields */
.form-group-inline.form-group-location {
  flex: 1.6 1 0;
  min-width: 0;
}

.form-group-inline.form-group-range {
  flex: 1 1 0;
  min-width: 0;
}

/* Same gap between label and input for all (match Price/Size range) */
.form-label-inline {
  margin-bottom: 2px;
  font-size: 0.7rem;
}

/* No space between title and input for Project Status, Property Type, Bedrooms */
.form-label-tight {
  margin-bottom: 0;
}

.form-label-location {
  margin-bottom: 2px;
}

/* Location: same height & padding as Price/Size range */
:deep(.location-select .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 0px 8px !important;
  align-items: center !important;
  font-size: 0.65rem !important;
}

:deep(.location-select .vs__selected) {
  padding: 0 !important;
  margin: 0 !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.location-select .vs__placeholder) {
  margin: 0 !important;
  position: static !important;
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected) {
  width: 100%;
  text-align: center;
}

:deep(.location-select .vs__selected .location-selected) {
  text-align: left;
}

/* Location selected value in 2 lines (when using selected-option slot) */
.location-selected {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.2;
}

.location-selected-name {
  font-weight: 600;
  font-size: 0.75rem;
  color: #01062d;
}

.location-selected-subtitle {
  font-size: 0.7rem;
  color: #64748b;
}

.location-chip {
  display: inline-flex !important;
  align-items: center;
  gap: 6px;
  border: 1px solid #cfd7e6 !important;
  border-radius: 12px !important;
  background: #f8fafc !important;
  padding: 3px 8px !important;
  margin: 2px 6px 2px 0 !important;
  font-size: 12px !important;
  color: #334155 !important;
  line-height: 1.1;
  height: 28px;
  box-sizing: border-box;
}

.location-chip-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: #64748b;
  padding: 0;
  width: 16px;
  height: 16px;
  cursor: pointer;
  font-size: 12px;
  line-height: 1;
}

/* Location dropdown options: 2 lines with icon (like image) */
.location-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px 0;
  min-height: 40px;
}

.location-option-icon {
  font-size: 1.1rem;
  color: #64748b;
  flex-shrink: 0;
  margin-top: 2px;
}

.location-option-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.location-option-name {
  font-weight: 600;
  font-size: 0.75rem;
  color: #01062d;
  line-height: 1.2;
}

.location-option-subtitle {
  font-size: 0.65rem;
  color: #64748b;
  line-height: 1.2;
}

/* Location dropdown list: wider */
:deep(.location-select + .vs__dropdown-menu),
:deep(.location-select .vs__dropdown-menu) {
  min-width: 320px !important;
  width: 100% !important;
  max-width: 400px;
}

/* All top-row inputs: same height, padding, font as Price/Size range */
.unified-input-inline {
     min-height: 30px !important;
    padding: 0px !important;
    font-size: .65rem !important;
}

:deep(.unified-input-inline.vs--single .vs__dropdown-toggle),
:deep(.form-group-inline .custom-select.vs--single .vs__dropdown-toggle) {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}

.main-search-row-single :deep(.vs__selected),
.main-search-row-single :deep(.vs__search),
.main-search-row-single :deep(.vs__placeholder) {
  font-size: 0.65rem !important;
}

.unified-btn-inline {
  min-height: 30px !important;
  padding: 2px 8px !important;
  font-size: 0.65rem !important;
}

.range-preview-inline {
  font-size: 0.6rem !important;
  line-height: 1.2;
  white-space: normal;
  word-break: break-word;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.form-group.compact {
  flex: 1;
  min-width: 150px;
}

/* Double Group for Price & Size */
.double-group {
  flex: 2;
  min-width: 300px;
}

.double-row {
  display: flex;
  gap: 12px;
  width: 100%;
}

.range-group {
  flex: 1;
  min-width: 140px;
}

.form-label {
  display: block;
  margin-left: 8px;
  font-weight: 600;
  color: #333;
  font-size: 0.8rem;
}

/* Clean Input Styles */
.unified-input {
  width: 100%;
  min-height: 44px;
  border-radius: 8px;
  border: 1px solid #ddd;
  padding: 8px 12px;
  background: #fff;
  font-size: 0.85rem;
  font-family: inherit;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.unified-input:focus {
  border-color: #01062d;
  box-shadow: 0 0 0 2px rgba(1, 6, 45, 0.1);
  outline: none;
}

/* Clean Select Styles */
.unified-select {
  width: 100%;
}

.unified-select .vs__dropdown-toggle {
  min-height: 44px !important;
  border-radius: 8px !important;
  border: 1px solid #ddd !important;
  padding: 8px 12px !important;
  background: #fff !important;
  font-size: 0.85rem !important;
  box-sizing: border-box !important;
}

.unified-select .vs__dropdown-toggle:focus-within {
  border-color: #01062d !important;
  box-shadow: 0 0 0 2px rgba(1, 6, 45, 0.1) !important;
}

/* Clean Button Styles */
.unified-btn {
  width: 100%;
  min-height: 44px;
  border-radius: 8px;
  border: 1px solid #ddd;
  padding: 8px 12px;
  background: #fff;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: #333;
  transition: all 0.2s ease;
  font-family: inherit;
  box-sizing: border-box;
}

.unified-btn:hover {
  border-color: #01062d;
}

.range-preview {
  font-weight: 500;
  color: #01062d;
  font-size: 0.8rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dropdown-icon {
  font-size: 1rem;
  color: #666;
  transition: transform 0.2s;
}

.unified-btn:focus .dropdown-icon {
  transform: rotate(180deg);
}

/* Clean Dropdown */
.compact-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 0;
  margin-top: 4px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  min-width: 260px;
  max-height: 220px;
  overflow: hidden;
}

.range-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  font-weight: 600;
  color: #01062d;
  font-size: 0.85rem;
}

.close-dropdown {
  background: none;
  border: none;
  font-size: 1rem;
  color: #666;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
}

.range-slider-container {
  padding: 16px;
}

/* Inputs side by side */
.range-inputs-side {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
  align-items: center;
}

.input-group-side {
  flex: 1;
}

.range-input-side {
  width: 100%;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 0.85rem;
  text-align: center;
  box-sizing: border-box;
  background: #fff;
}

.range-input-side:focus {
  border-color: #01062d;
  outline: none;
  box-shadow: 0 0 0 2px rgba(1, 6, 45, 0.1);
}

.range-track {
  position: relative;
  height: 4px;
  background: #e0e0e0;
  border-radius: 2px;
  margin: 20px 0;
}

.range-progress {
  position: absolute;
  height: 100%;
  background: #01062d;
  border-radius: 2px;
  top: 0;
  z-index: 1;
}

.range-slider {
  position: absolute;
      top: 76%;
  left: 0;
  right: 0;
  height: 4px;
  margin-top: 0;
}

.slider {
  position: absolute;
  width: 100%;
  pointer-events: none;
  -webkit-appearance: none;
  height: 16px;
  background: transparent;
  margin: 0;
  top: -6px; 
  z-index: 3;
}

.slider::-webkit-slider-thumb {
  pointer-events: all;
  -webkit-appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #01062d;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 2;
}

.slider::-moz-range-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #01062d;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.slider::-ms-thumb {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #01062d;
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

/* Search Button */
.search-btn-group {
  min-width: 120px;
}

.unified-search-btn {
  padding: 5px 25px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #FAA300;
  color: white;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.unified-search-btn:hover {
  background: #020a47;
  transform: translateY(-1px);
}

/* Secondary Filters */
.secondary-filters {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 0;
  padding-top: 8px;
  border-top: 1px solid #f0f0f0;
}

.filter-section {
  display: flex;
  align-items: center;
  gap: 6px;
}

.filter-label {
  font-weight: 600;
  color: #333;
  font-size: 0.8rem;
  white-space: nowrap;
  margin-bottom:0px !important;
}

.secondary-filters .filter-section .unified-select {
  min-width: 120px;
}

:deep(.unified-select-secondary.vs--single .vs__dropdown-toggle) {
  min-height: 36px !important;
  padding: 4px 8px !important;
  font-size: 0.75rem !important;
}

.status-tabs {
  display: flex;
  gap: 6px;
}

.status-tab {
  padding: 8px 16px;
  border-radius: 6px;
  border: 1px solid #ddd;
  background: #f8f9fa;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.2s;
  color: #666;
  min-height: 36px;
  display: flex;
  align-items: center;
}

.status-tab:hover {
  border-color: #01062d;
  color: #01062d;
}

.status-tab.active {
  background: #01062d;
  color: #fff;
  border-color: #01062d;
}

/* Active Filters */
.active-filters {
  padding: 12px 16px;
  background: #f8f9fa;
  border-radius: 8px;
}

.active-filters-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #01062d;
}

.clear-all {
  background: none;
  border: none;
  color: #666;
  cursor: pointer;
  font-size: 0.8rem;
  text-decoration: underline;
}

.filter-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-tag {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 14px;
  padding: 4px 10px;
  font-size: 0.75rem;
  color: #333;
}

.filter-tag i {
  cursor: pointer;
  color: #666;
  font-size: 0.8rem;
}

.filter-tag i:hover {
  color: #01062d;
}

/* Dropdown Overlay */
.dropdown-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: transparent;
  z-index: 999;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-search-row-single {
    flex-wrap: wrap;
  }
  .form-group-inline {
    min-width: 100px;
  }
  .form-group-inline.form-group-location {
    max-width: none;
  }
}

@media (max-width: 768px) {
  .search-bar {
    padding: 16px;
  }
  
  .main-search-row {
    flex-direction: column;
  }
  
  .main-search-row-single {
    flex-direction: column;
  }
  
  .form-group-inline,
  .form-group-inline.form-group-location,
  .form-group-inline.form-group-range {
    min-width: 100%;
    max-width: none;
  }
  
  .form-group.compact {
    min-width: 100%;
  }
  
  .double-group {
    min-width: 100%;
  }
  
  .double-row {
    flex-direction: column;
    gap: 8px;
  }
  
  .secondary-filters {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  
  .filter-section {
    justify-content: space-between;
  }
  
  .status-tabs {
    justify-content: center;
  }
  
  .compact-dropdown {
    position: fixed;
    top: 50%;
    left: 10%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 300px;
    z-index: 10000;
  }
  
  .range-inputs-side {
    flex-direction: column;
    gap: 8px;
  }
}

:deep(.vs__selected) {
  margin: 2px 4px 2px 0 !important;
  padding: 4px 8px !important;
  background: #f8f9fa !important;
  border: none !important;
  border-radius: 4px !important;
  font-size: 0.8rem !important;
  box-shadow: none !important;
}

:deep(.vs__search) {
  font-size: 0.85rem !important;
  margin: 0 !important;
  padding: 0 !important;
  border: none !important;
  box-shadow: none !important;
}

:deep(.vs__actions) {
  padding: 0 !important;
}

:deep(.vs__clear) {
  margin-right: 4px !important;
  border: none !important;
}

:deep(.vs__dropdown-option) {
  font-size: 0.85rem !important;
  padding: 8px 12px !important;
  border: none !important;
}

:deep(.vs__dropdown-menu) {
  border-radius: 0 0 8px 8px !important;
  border: 1px solid #ddd !important;
  border-top: none !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
  margin-top: 0 !important;
}

:deep(.vs__open-indicator) {
  fill: #666 !important;
}

:deep(.vs__clear-indicator) {
  stroke: #666;
  stroke-width: 1;
  width: 12px;
  height: 12px;
  opacity: 0.7;
}
:deep(.vs__deselect) {
  border: none !important;
  box-shadow: none !important;
}

.range-dropdown {
  position: relative;
  display: block;
  margin:0px 8px;
}

.range-dropdown-content {
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
  transform: translateY(0) !important;
}

/* Placement + label visibility fixes */
.search-container {
  position: relative;
  overflow: visible !important;
}

.listing-more-filter-popover,
.listing-price-popover {
  top: calc(100% + 8px) !important;
  right: 0 !important;
  left: auto !important;
}

:deep(.listing-pill-select .vs__selected-options) {
  flex-wrap: nowrap !important;
  overflow: hidden !important;
}

:deep(.listing-pill-select .vs__selected),
:deep(.listing-pill-select .vs__placeholder) {
  display: inline-block !important;
  white-space: nowrap !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  max-width: 100% !important;
}

/* Screenshot-like all listing search style */
.listing-search-transparent {
  background: transparent !important;
}

.listing-search-shell {
  background: #fff;
  border-radius: 30px;
  padding: 18px 22px 20px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}

.listing-headline {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 14px;
}

.listing-headline h2 {
  margin: 0;
  font-size: 24px;
  line-height: 1.1;
  font-weight: 800;
  color: #1f2937;
}

.listing-headline span {
  font-size: 14px;
  color: #6b7280;
}

.listing-main-search {
  position: relative;
  margin-bottom: 12px;
  max-width: 710px;
}

.listing-main-search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
  font-size: 16px;
  color: #94a3b8;
}

:deep(.listing-main-location .vs__dropdown-toggle) {
  min-height: 42px !important;
  border-radius: 999px !important;
  border: 1px solid #e2e8f0 !important;
  padding-left: 38px !important;
  box-shadow: none !important;
}

:deep(.listing-main-location .vs__placeholder),
:deep(.listing-main-location .vs__selected),
:deep(.listing-main-location .vs__search) {
  font-size: 14px !important;
  color: #6b7280 !important;
}

.listing-pill-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

:deep(.listing-pill-select .vs__dropdown-toggle) {
  min-height: 52px !important;
  border-radius: 999px !important;
  border: 1px solid #dbe1eb !important;
  padding: 2px 18px !important;
}

:deep(.listing-pill-select .vs__selected),
:deep(.listing-pill-select .vs__placeholder),
:deep(.listing-pill-select .vs__search) {
  font-size: 22px !important;
}

.listing-pill-select-sm {
  width: 154px;
}

.listing-pill-btn {
  min-height: 52px;
  border: 1px solid #dbe1eb;
  border-radius: 999px;
  background: #fff;
  color: #334155;
  padding: 0 22px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 22px;
}
 .listing-pill-btn i{
  color: rgb(207, 219, 236);
 }
.listing-price-wrap {
  position: relative;
}

.listing-beds-wrap {
  position: relative;
}

.listing-filter-btn {
  font-weight: 600;
}

.listing-filter-wrap {
  position: relative;
  display: inline-flex;
}

.unified-search-btn {
  min-height: 52px;
  min-width: 52px;
  border-radius: 999px;
  border: none;
  background: #faa300;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
}

.listing-icon-circle {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  border: 1px solid #dbe1eb;
  background: #fff;
  color: #475569;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.listing-notify-btn {
  text-decoration: none;
}

.listing-map-btn {
  background: #24135f;
  color: #fff;
  gap: 8px;
  font-size: 22px;
  font-weight: 700;
}

.listing-price-popover,
.listing-more-filter-popover {
  position: absolute;
  z-index: 1200;
  right: 0;
  top: calc(100% + 10px);
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.15);
  padding: 14px;
}

.listing-beds-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 540px;
  max-width: min(540px, calc(100vw - 24px));
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.15);
  padding: 14px;
  z-index: 1200;
}

.listing-chip-grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 8px;
}

.listing-chip-btn {
  border: 1px solid #dbe2ee;
  border-radius: 999px;
  background: #fff;
  color: #334155;
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
  padding: 0 10px;
  text-align: center;
}

.listing-chip-btn.active {
    border-color: #01062c;
    color: #01062c;
  background: #f5f3ff;
}

.listing-price-popover {
  width: 360px;
  right: auto;
  left: 0;
}

.listing-more-filter-popover {
  width: min(460px, calc(100vw - 20px));
  border-radius: 16px;
  padding: 16px;
}

.listing-pop-title {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 12px;
}

.listing-filter-section {
  margin-bottom: 12px;
}

.listing-section-label {
  display: block;
  font-size: 16px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 8px;
}

.listing-pop-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.listing-pop-grid--two {
  margin-top: 2px;
}

.listing-pop-grid--range {
  gap: 10px;
}

.listing-pop-field--full {
  grid-column: 1 / -1;
}

.listing-pop-grid label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 6px;
  color: #334155;
}

.listing-more-filter-popover .range-input-side,
.listing-price-popover .range-input-side,
:deep(.listing-pop-select .vs__dropdown-toggle) {
  min-height: 52px !important;
  border-radius: 14px !important;
  font-size: 16px !important;
}

/* More Filters: visible border on each field/select */
.listing-more-filter-popover .range-input-side {
  border: 1px solid #d8dfe9 !important;
  background: #fff !important;
}

:deep(.listing-pop-select .vs__dropdown-toggle) {
  border: 1px solid #d8dfe9 !important;
  background: #fff !important;
  box-shadow: none !important;
}

.listing-pop-actions {
  margin-top: 14px;
  display: flex;
  gap: 12px;
}

.listing-pop-actions .btn {
  flex: 1;
  min-height: 30px;
  border-radius: 12px;
  font-weight: 500;
}

@media (max-width: 991px) {
  .listing-pop-grid {
    grid-template-columns: 1fr;
  }

  .listing-beds-popover {
    width: min(540px, calc(100vw - 24px));
  }

  .listing-chip-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

/* Unified compact search bar (my-listing + alllisting parity) */
.listing-search-shell {
  max-width: 980px !important;
  padding: 12px 16px !important;
  border-radius: 24px !important;
  position: relative !important;
  overflow: visible !important;
  background: #fff !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12) !important;
}

.listing-headline {
  margin-bottom: 6px !important;
}

.listing-headline h2 {
  font-size: 18px !important;
  font-weight: 500 !important;
  letter-spacing: -0.1px;
}

.listing-headline span {
  font-size: 12px !important;
}

:deep(.listing-main-location .vs__dropdown-toggle) {
  min-height: 44px !important;
  border-radius: 999px !important;
  padding-left: 36px !important;
  border-color: #d5dbe6 !important;
}

:deep(.listing-main-location .vs__selected),
:deep(.listing-main-location .vs__search),
:deep(.listing-main-location .vs__placeholder) {
  font-size: 14px !important;
}

.listing-pill-row {
  gap: 6px !important;
  flex-wrap: wrap !important;
  align-items: center !important;
  margin-bottom: 0 !important;
  max-width: fit-content;
}

.listing-status-row {
  margin-top: 6px;
  margin-left: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.listing-status-row .status-btn {
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid #dbe2ee;
  background: #f8fafc;
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  line-height: 1;
  min-height: 32px;
}

.listing-status-row .status-btn.active {
  background: linear-gradient(135deg, #faa300 0%, #ffb224 100%);
  border-color: #f1a10a;
  color: #fff;
  box-shadow: 0 4px 10px rgba(250, 163, 0, 0.22);
}

.status-sort-separator {
  width: 1px;
  height: 24px;
  background: #dbe2ee;
  margin: 0 2px;
}

/* Hot Deal: distinct “fire” accent vs orange status tabs */
.listing-status-row .status-btn.hot-deal-btn {
  border: 1px solid #fdba74;
  background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
  color: #9a3412;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.listing-status-row .status-btn.hot-deal-btn i {
  font-size: 14px;
  color: #ea580c;
}

.listing-status-row .status-btn.hot-deal-btn.active {
  background: linear-gradient(135deg, #c2410c 0%, #ea580c 45%, #f97316 100%);
  border-color: #9a3412;
  color: #fff;
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.38);
}

.listing-status-row .status-btn.hot-deal-btn.active i {
  color: #fff;
}

.listing-status-row-sort-select {
  flex: 0 0 auto;
  min-width: 168px;
  max-width: 200px;
}

:deep(.listing-status-row-sort-select .vs__dropdown-toggle) {
  min-height: 32px !important;
  padding: 2px 8px 2px 10px !important;
  border-radius: 999px !important;
  border: 1px solid #dbe2ee !important;
  background: #fff !important;
}

:deep(.listing-status-row-sort-select .vs__selected),
:deep(.listing-status-row-sort-select .vs__search),
:deep(.listing-status-row-sort-select .vs__placeholder) {
  font-size: 11px !important;
  font-weight: 600 !important;
  color: #475569 !important;
  margin: 0 !important;
}

:deep(.listing-status-row-sort-select .vs__actions) {
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.listing-pill-select {
  width: 170px !important;
}

.listing-pill-select-sm {
  width: 140px !important;
}

:deep(.listing-pill-select .vs__dropdown-toggle) {
  min-height: 42px !important;
  padding: 0 12px !important;
  border-color: #d7deea !important;
  background: #fff !important;
  border-radius: 12px !important;
  display: flex !important;
  align-items: center !important;
}

:deep(.listing-pill-select .vs__selected),
:deep(.listing-pill-select .vs__search),
:deep(.listing-pill-select .vs__placeholder) {
  font-size: 13px !important;
  color: #334155 !important;
  line-height: 1.2 !important;
  margin: 0 !important;
  padding: 0 !important;
}

:deep(.listing-pill-select .vs__selected-options) {
  align-items: center !important;
  min-height: 100% !important;
}

.listing-pill-btn {
  min-height: 42px !important;
  font-size: 13px !important;
  padding: 0 14px !important;
  border-color: #d7deea !important;
  border-radius: 12px !important;
}

.unified-search-btn {
  min-height: 42px !important;
  font-size: 13px !important;
  padding: 0 16px !important;
  border-radius: 12px !important;
  background: #faa300 !important;
  color: #fff !important;
}

.listing-notify-btn.listing-icon-circle {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  font-size: 16px;
}

.listing-more-filter-popover,
.listing-price-popover {
  right: 0 !important;
}

.listing-filter-wrap .listing-more-filter-popover {
  left: 0 !important;
  right: auto !important;
  top: calc(100% + 8px) !important;
  transform: none !important;
  margin-top: 0 !important;
}

:deep(.listing-pill-select .vs__dropdown-menu),
:deep(.listing-main-location .vs__dropdown-menu),
:deep(.listing-pop-select .vs__dropdown-menu) {
  position: absolute !important;
  top: calc(100% + 8px) !important;
  left: 0 !important;
  right: auto !important;
  transform: none !important;
  z-index: 2000 !important;
  max-height: 360px !important;
  overflow-y: auto !important;
  border-radius: 14px !important;
  border: 1px solid #d4dbe7 !important;
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14) !important;
  padding: 8px 0 !important;
}

:deep(.listing-pill-select .vs__dropdown-option),
:deep(.listing-main-location .vs__dropdown-option),
:deep(.listing-pop-select .vs__dropdown-option) {
  padding: 12px 16px !important;
  font-size: 15px !important;
  line-height: 1.35 !important;
  white-space: normal !important;
}

:deep(.listing-pill-select .vs__dropdown-option--highlight),
:deep(.listing-main-location .vs__dropdown-option--highlight),
:deep(.listing-pop-select .vs__dropdown-option--highlight) {
  background: #f5f4ff !important;
  color: #312e81 !important;
}

.listing-more-filter-popover .listing-pop-title,
.listing-price-popover .listing-pop-title {
  font-size: 16px !important;
  margin-bottom: 8px !important;
}

.listing-more-filter-popover .listing-pop-grid label,
.listing-price-popover .listing-pop-grid label {
  font-size: 12px !important;
  margin-bottom: 4px !important;
}

.listing-more-filter-popover .range-input-side,
.listing-price-popover .range-input-side,
:deep(.listing-pop-select .vs__dropdown-toggle) {
  min-height: 36px !important;
  font-size: 13px !important;
}

/* More filters visual tuning (image-like compact card) */
.listing-more-filter-popover .listing-section-label {
  font-size: 15px !important;
  margin-bottom: 7px !important;
}

.listing-more-filter-popover .listing-pop-grid label {
  font-size: 12px !important;
  color: #4b5563 !important;
  margin-bottom: 5px !important;
}

.listing-more-filter-popover .listing-pop-label {
  display: block;
  font-size: 12px !important;
  font-weight: 600;
  color: #4b5563 !important;
  margin-bottom: 5px !important;
}

.listing-more-filter-popover .range-input-side,
:deep(.listing-more-filter-popover .listing-pop-select .vs__dropdown-toggle) {
  min-height: 44px !important;
  border-radius: 12px !important;
  border-color: #d6dde8 !important;
  font-size: 14px !important;
}

/* Keep Price popover attached to Price button */
.listing-price-wrap .listing-price-popover {
  left: 0 !important;
  right: auto !important;
  top: calc(100% + 8px) !important;
}
:deep(.custom-clear) {
  font-size: 14px;
  color: rgb(207, 219, 236);
}
.listing-chip-grid button{
  font-weight: 400;
    font-size: 13px;
}


.vs__open-indicator-icon {
  width: 16px !important;
  height: 16px !important;
  color: rgb(207, 219, 236) !important;
  display: block !important;
}

:deep(.custom-clear) {
  font-size: 16px !important;
  color: rgb(207, 219, 236) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  line-height: 1 !important;
}

:deep(.custom-clear:hover) {
  color: #ef4444 !important;
}

/* ضبط حاوية الأزرار لتكون الأيقونات في المنتصف */
:deep(.vs__actions) {
  display: flex !important;
  align-items: center !important;
  gap: 1px !important;
  padding: 0px !important;
}

:deep(.vs__clear),
:deep(.vs__open-indicator) {
  display: flex !important;
  align-items: center !important;
  padding: 0 !important;
}

/* إزالة الخلفية والحدود الافتراضية لزر الإغلاق */
:deep(.vs__clear) {
  background: transparent !important;
  border: none !important;
  opacity: 1 !important;
}

/* تعديل أيقونة السهم داخل الـ open-indicator */
:deep(.vs__open-indicator) {
  display: flex !important;
  align-items: center !important;
}

/* توحيد حجم أيقونة الإغلاق في الـ location-chip */
.location-chip-close .custom-clear {
  font-size: 12px !important;
}


:deep(.vs__deselect svg),
:deep(.vs__deselect .vs__deselect-icon),
:deep(.vs__clear svg),
:deep(.vs__clear-indicator) {
  display: none !important;
}

:deep(.vs__deselect) {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  margin: 0 0 0 6px !important;
  width: 18px !important;
  height: 18px !important;
  cursor: pointer !important;
  border-radius: 50% !important;
  transition: all 0.2s ease !important;
}

:deep(.vs__deselect):hover {
  background: #fee2e2 !important;
}

:deep(.vs__deselect)::before {
  content: "✕" !important;
  font-size: 11px !important;
  font-weight: bold !important;
  color: rgb(207, 219, 236) !important;
  font-family: monospace !important;
  line-height: 1 !important;
}

:deep(.vs__deselect):hover::before {
  color: #ef4444 !important;
}

.location-chip-close {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: transparent !important;
  border: none !important;
  padding: 0 !important;
  margin: 0 0 0 6px !important;
  width: 18px !important;
  height: 18px !important;
  cursor: pointer !important;
  border-radius: 50% !important;
  transition: all 0.2s ease !important;
}

.location-chip-close:hover {
  background: #fee2e2 !important;
}

.location-chip-close .custom-clear {
  display: none !important;
}

.location-chip-close::before {
  content: "✕" !important;
  font-size: 10px !important;
  font-weight: 500 !important;
  color: rgb(207, 219, 236) !important;
  font-family: monospace !important;
}

.location-chip-close:hover::before {
  color: #ef4444 !important;
}

:deep(.vs__clear) {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 20px !important;
  height: 20px !important;
  margin: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
  cursor: pointer !important;
  border-radius: 50% !important;
}

:deep(.vs__clear):hover {
  background: #fee2e2 !important;
}

:deep(.vs__clear)::before {
  content: "✕" !important;
  font-size: 11px !important;
  font-weight: 500 !important;
  color: rgb(207, 219, 236) !important;
  font-family: monospace !important;
}

:deep(.vs__clear):hover::before {
  color: #ef4444 !important;
}
</style>