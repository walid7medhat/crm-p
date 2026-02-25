<template>
  <div class="">
    <div class="search-bar">
      
      <!-- Main Search Row -->
      <div class="main-search-row main-search-row-single">
                <!-- Location -->
                   <!-- Location: wider, placeholder centered, options in 2 lines -->
                <div class="form-group form-group-inline form-group-location">
                  <label class="form-label form-label-inline form-label-location">Location</label>
                  <v-select
                    v-model="selectedArea"
                    :options="areas"
                    :disabled="isLoadingAreas"
                    label="name"
                    placeholder="Select area"
                    class="custom-select unified-input unified-input-inline unified-input-inline location-select"
                    @update:modelValue="handleFilterChange"
                  >
                       <template #open-indicator="{ attributes }">
                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
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
                    <template #no-options>
                      <div class="text-center p-2">
                        {{ isLoadingAreas ? 'Loading areas...' : 'No areas found' }}
                      </div>
                    </template>
                  </v-select>
                </div>
      <!-- Completion Status (Off Plan/Ready) -->
            <div class="form-group form-group-inline ">
              <label class="form-label form-label-inline form-label-location">Project Status</label>
              <v-select
                v-model="selectedCompletionStatus"
                :options="completionStatusOptions"
                placeholder="Select status"
                class="custom-select unified-input unified-input-inline"
                @update:modelValue="handleFilterChange"
              >
                  <template #open-indicator="{ attributes }">
                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                         </template>
              </v-select>
            </div>
        <!-- Property Type -->
        <div class="form-group form-group-inline ">
          <label class="form-label form-label-inline form-label-location">Property Type</label>
          <v-select
            v-model="selectedPropertyType"
            :options="propertyTypes"
            :disabled="isLoadingPropertyTypes"
            label="name"
            placeholder="Any Type"
            class="custom-select unified-input unified-input-inline"
            @update:modelValue="handleFilterChange"
          >
              <template #open-indicator="{ attributes }">
                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                         </template>
            <template #no-options>
              <div class="text-center p-2">
                {{ isLoadingPropertyTypes ? 'Loading property types...' : 'No property types found' }}
              </div>
            </template>
          </v-select>
        </div>

     
          <!-- Agent Filter -->
        <div class="form-group form-group-inline ">
          <label class="form-label form-label-inline form-label-location">Agent</label>
          <v-select
            v-model="selectedAgent"
            :options="agents"
            :disabled="isLoadingAgents"
            label="name"
            placeholder="Any Agent"
            class="custom-select unified-input unified-input-inline"
            @update:modelValue="handleFilterChange"
          >
              <template #open-indicator="{ attributes }">
                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                         </template>
            <template #no-options>
              <div class="text-center p-2">
                {{ isLoadingAgents ? 'Loading agents...' : 'No agents found' }}
              </div>
            </template>
          </v-select>
        </div>
         <!-- Bedrooms -->
        <div class="form-group form-group-inline ">
          <label class="form-label form-label-inline form-label-location">Bedrooms</label>
          <v-select
            v-model="selectedBeds"
            :options="bedsOptions"
            placeholder="Any"
            class="custom-select unified-input unified-input-inline"
            @update:modelValue="handleFilterChange"
          ><template #open-indicator="{ attributes }">
                            <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                         </template>
            </v-select>
        </div>
           <!-- Price & Size in Same Row -->
            <!-- Price Range -->
        <div class="form-group form-group-inline form-group-range">
          <label class="form-label form-label-inline">Price Range</label>
          <div class="range-dropdown">
            <button class="range-dropdown-btn unified-btn unified-btn-inline" @click="togglePriceDropdown">
              <span class="range-preview range-preview-inline">
                {{ formatNumber(priceFrom) }} - {{ formatNumber(priceTo) }} AED
              </span>
              <i class="ri-arrow-down-s-line dropdown-icon"></i>
            </button>
            <div v-if="showPriceDropdown" class="range-dropdown-content compact-dropdown">
              <div class="range-header">
                <span>Price (AED)</span>
                <button class="close-dropdown" @click="showPriceDropdown = false">
                  <i class="ri-close-line"></i>
                </button>
              </div>
              <div class="range-slider-container">
                <div class="range-inputs-side">
                  <div class="input-group-side">
                    <input type="text" v-model="priceFrom" class="range-input-side" @change="handlePriceChange" placeholder="Min">
                  </div>
                  <div class="input-group-side">
                    <input type="text" v-model="priceTo" class="range-input-side" @change="handlePriceChange" placeholder="Max">
                  </div>
                </div>
                <div class="range-track">
                  <div class="range-progress" :style="priceProgressStyle"></div>
                </div>
                <div class="range-slider">
                  <input type="range" min="0" max="10000000" step="100000" v-model="priceFrom" class="slider" @input="handlePriceSliderChange" />
                  <input type="range" min="0" max="10000000" step="100000" v-model="priceTo" class="slider" @input="handlePriceSliderChange" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Size Range -->
        <div class="form-group form-group-inline form-group-range">
          <label class="form-label form-label-inline">Size Range</label>
          <div class="range-dropdown">
            <button class="range-dropdown-btn unified-btn unified-btn-inline" @click="toggleSizeDropdown">
              <span class="range-preview range-preview-inline">
                {{ sizeFrom }} - {{ sizeTo }} sqft
              </span>
              <i class="ri-arrow-down-s-line dropdown-icon"></i>
            </button>
            <div v-if="showSizeDropdown" class="range-dropdown-content compact-dropdown">
              <div class="range-header">
                <span>Size (sqft)</span>
                <button class="close-dropdown" @click="showSizeDropdown = false">
                  <i class="ri-close-line"></i>
                </button>
              </div>
              <div class="range-slider-container">
                <div class="range-inputs-side">
                  <div class="input-group-side">
                    <input type="text" v-model="sizeFrom" class="range-input-side" @change="handleSizeChange" placeholder="Min">
                  </div>
                  <div class="input-group-side">
                    <input type="text" v-model="sizeTo" class="range-input-side" @change="handleSizeChange" placeholder="Max">
                  </div>
                </div>
                <div class="range-track">
                  <div class="range-progress" :style="sizeProgressStyle"></div>
                </div>
                <div class="range-slider">
                  <input type="range" min="0" max="10000" step="100" v-model="sizeFrom" class="slider" @input="handleSizeSliderChange" />
                  <input type="range" min="0" max="10000" step="100" v-model="sizeTo" class="slider" @input="handleSizeSliderChange" />
                </div>
              </div>
            </div>
          </div>
        </div>
       
      </div>

      <!-- Secondary Filters -->
      <div class="secondary-filters">
        <!-- Sale/Rent Tabs -->
         <div class="filter-section">
          <label class="filter-label">Type</label>
         <v-select
          v-model="selectedSaleRent"
          :options="saleRentOptions"
          label="label"
          :reduce="option => option.value"
          placeholder="All"
          class="custom-select sort-select unified-select unified-select-secondary"
          @update:modelValue="handleFilterChange"
        >
               <template #open-indicator="{ attributes }">
                    <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
                 </template>
         </v-select>
        </div>

        <!-- Status Tabs -->
        <!-- <div class="filter-section">
          <label class="filter-label">Status</label>
          <div class="status-tabs">
            <button
              v-for="status in statusOptions"
              :key="status"
              :class="['status-tab', { active: selectedStatus === status }]"
              @click="handleStatusChange(status)"
            >
              {{ status }}
            </button>
          </div>
        </div> -->

        <!-- Sort By -->
      
        
        <div class="form-group-inline form-group-location search-btn-group">
          <button class="btn btn-primary unified-search-btn" @click="applyFilters" :disabled="isLoadingAreas || isLoadingPropertyTypes">
            <i class="ri-search-line"></i>
            {{ (isLoadingAreas || isLoadingPropertyTypes) ? 'Loading...' : 'Search' }}
          </button>
        </div>

          <div class="filter-section">
          <label class="filter-label">Sort By</label>
         <v-select
            v-model="selectedSort"
            :options="sortOptions"
            label="label"
            :reduce="option => option.value"
            placeholder="Most Recent"
            class="custom-select sort-select unified-select"
            @update:modelValue="handleFilterChange"
            :modelValue="selectedSort"
          >
               <template #open-indicator="{ attributes }">
                <i v-bind="attributes" class="ri-arrow-down-s-line dropdown-icon"></i>
             </template>
         </v-select>
        </div>
      </div>

      <!-- Active Filters -->
     <!-- <div class="active-filters" v-if="hasActiveFilters">
        <div class="active-filters-header">
          <span>Active Filters:</span>
          <div class="filter-tags">
          <span class="filter-tag" v-if="selectedSaleRent && selectedSaleRent !== 'All'">
            {{ selectedSaleRent }}
            <i class="ri-close-line" @click="handleSaleRentChange('All')"></i>
          </span>
          <span class="filter-tag" v-if="selectedStatus && selectedStatus !== 'All'">
            {{ selectedStatus }}
            <i class="ri-close-line" @click="handleStatusChange('All')"></i>
          </span>
          <span class="filter-tag" v-if="selectedArea">
            {{ selectedArea.name }}
            <i class="ri-close-line" @click="selectedArea = null; handleFilterChange()"></i>
          </span>
          <span class="filter-tag" v-if="selectedPropertyType">
            {{ selectedPropertyType.name }}
            <i class="ri-close-line" @click="selectedPropertyType = null; handleFilterChange()"></i>
          </span>
          <span class="filter-tag" v-if="selectedBeds">
            {{ selectedBeds }} Beds
            <i class="ri-close-line" @click="selectedBeds = ''; handleFilterChange()"></i>
          </span>
          <span class="filter-tag" v-if="priceFrom > 0 || priceTo < 10000000">
            Price: {{ formatNumber(priceFrom) }} - {{ formatNumber(priceTo) }} AED
            <i class="ri-close-line" @click="resetPriceRange"></i>
          </span>
          <span class="filter-tag" v-if="sizeFrom > 0 || sizeTo < 10000">
            Size: {{ sizeFrom }} - {{ sizeTo }} sqft
            <i class="ri-close-line" @click="resetSizeRange"></i>
          </span>
        </div>
          <div class="clear-btn">
         <button class="clear-all" @click="resetFilters">Clear All</button>
        </div>
        </div>
      </div>-->
    </div>

    <!-- Overlay for dropdowns -->
    <div v-if="showPriceDropdown || showSizeDropdown" class="dropdown-overlay" @click="closeAllDropdowns"></div>
  </div>
</template>

<script>
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import { ref, onMounted, computed, getCurrentInstance, onUnmounted } from 'vue';

import api from '@/plugins/axios';

export default {
  name: "FixedSearchBar",
  components: { vSelect },
  setup() {
    const { emit } = getCurrentInstance();
    
    // Reactive data
    const areas = ref([]);
    const propertyTypes = ref([]);
      const projects = ref([]);
    const isLoadingAreas = ref(false);
    const isLoadingPropertyTypes = ref(false);
        const isLoadingAgents = ref(false);
 const isLoadingProjects = ref(false); 
        const agents = ref([]);

    const selectedSaleRent = ref("");
    const selectedStatus = ref("All");
    const selectedArea = ref(null);
    const selectedProject = ref(null); 
    const selectedCompletionStatus = ref(null); 
    const selectedPropertyType = ref(null);
    const selectedBeds = ref("");
    const selectedSort = ref("");
 const selectedAgent = ref(null); 
    const priceFrom = ref(0);
    const priceTo = ref(10000000);
    const sizeFrom = ref(0);
    const sizeTo = ref(10000);

    const showPriceDropdown = ref(false);
    const showSizeDropdown = ref(false);
const searchReferenceNumber = ref("");

    // Debounce timer
    const searchTimer = ref(null);

    // Static options
    const saleRentOptions = [
          { label: "All", value: "All" },
          { label: "Sale", value: "Sale" },
          { label: "Rent", value: "Rent" }
        ];
    const statusOptions = ["All", "Ready", "Offplan"];
    const bedsOptions = ["Studio", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10+"];
     const completionStatusOptions = [
      { label: "All", value: null },
      { label: "Completed", value: "Completed" },
      { label: "Under Construction", value: "Under Construction" }
    ];
    const sortOptions = [
          { label: "Hot Deal", value: "hot_deal" },
          { label: "Latest Listings", value: "created_at_desc" },
        //   { label: "Oldest Listings", value: "created_at_asc" },
          { label: "Price: Low to High", value: "price_asc" },
          { label: "Price: High to Low", value: "price_desc" },
      
      // { label: "Size: Small to Large", value: "size_asc" },
      // { label: "Size: Large to Small", value: "size_desc" }
    ];
    // Fetch areas from API
    const fetchAreas = async () => {
      try {
        isLoadingAreas.value = true;
        const response = await api.get("/listings/areas/?has_listings=true");
        
        const areasData = response.data.data || response.data;
        
        areas.value = areasData.map(area => ({
          id: area.id,
          name: area.area_parents_title || area.name || area.title
        }));
        
        console.log("✅ Areas loaded:", areas.value.length);
        
      } catch (error) {
        console.error("❌ Error fetching areas:", error.response || error);
      } finally {
        isLoadingAreas.value = false;
      }
    };
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
 const fetchAgents = async () => {
      try {
        isLoadingAgents.value = true;
        const response = await api.get("/users?agents=true");
        
        const agentsData = response.data.data || response.data;
        
        agents.value = agentsData.map(agent => ({
          id: agent.id,
          name: agent.name || agent.email
        }));
        
        console.log("✅ Agents loaded:", agents.value.length);
        
      } catch (error) {
        console.error("❌ Error fetching agents:", error.response || error);
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
      return selectedSaleRent.value !== "All" || 
             selectedStatus.value !== "All" || 
             selectedArea.value || selectedAgent.value || selectedProject.value || 
             selectedPropertyType.value || 
             selectedBeds.value || 
             priceFrom.value > 0 || 
             priceTo.value < 10000000 || 
             sizeFrom.value > 0 || 
             sizeTo.value < 10000 || searchReferenceNumber.value.trim() !== "" ||
             selectedCompletionStatus.value !== null;
    });

    // Convert filters to API format
    const convertFiltersToAPI = (filters) => {
      const apiFilters = {};

      // Sale/Rent Filter
      if (filters.saleRent && filters.saleRent !== 'All') {
        apiFilters.listing_status = filters.saleRent.toLowerCase();
      }
         if (filters.completionStatus && filters.completionStatus.value) {
        apiFilters.completion_status = filters.completionStatus.value;
      }
      // Status Filter
      if (filters.status && filters.status !== 'All') {
        apiFilters.completion_status = filters.status;
      }

      // Area Filter
      if (filters.area) {
        apiFilters.area_id = filters.area.id;
      }

      // Property Type Filter
      if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
   // Agent Filter
      if (filters.agent) {
        apiFilters.agent_id = filters.agent.id;
      }
      // Project Filter
      if (filters.project) {
        apiFilters.project = filters.project.id;
      }
      // Bedrooms Filter
      if (filters.beds) {
        if (filters.beds == 'Studio') {
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
      if (filters.referenceNumber && filters.referenceNumber.trim() !== "") {
        apiFilters.reference_number = filters.referenceNumber.trim();
      }

      // Sort
     if (filters.sort) {
        apiFilters.sort = filters.sort; 
      }

      return apiFilters;
    };

    // Debounced search function
    const performSearch = () => {
      // Clear existing timer
      if (searchTimer.value) {
        clearTimeout(searchTimer.value);
      }

      // Set new timer with 500ms delay
      searchTimer.value = setTimeout(() => {
        const filters = {
          saleRent: selectedSaleRent.value,
              completionStatus: selectedCompletionStatus.value,
          status: selectedStatus.value,
          area: selectedArea.value,
          propertyType: selectedPropertyType.value,
          agent: selectedAgent.value,
          project: selectedProject.value,
          beds: selectedBeds.value,
          priceFrom: priceFrom.value,
          priceTo: priceTo.value,
          sizeFrom: sizeFrom.value,
          sizeTo: sizeTo.value,
          sort: selectedSort.value,
           referenceNumber: searchReferenceNumber.value
        };
        
        console.log("🔍 Auto-search with filters:", filters);
        
        // Emit event to parent component
        emit('filters-changed', filters);
      }, 300);
    };

    // Handle filter changes
    const handleFilterChange = () => {
       
      performSearch();
    };

    // Handle sale/rent change
    const handleSaleRentChange = (type) => {
      selectedSaleRent.value = type;
      performSearch();
    };

    // Handle status change
    const handleStatusChange = (status) => {
      selectedStatus.value = status;
      performSearch();
    };

    // Handle price changes
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

    // Handle size changes
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

    // Manual search (for button click)
    const applyFilters = () => {
       if (!selectedSort.value) {
          selectedSort.value = "created_at_desc";
        }
      const filters = {
        saleRent: selectedSaleRent.value,
         completionStatus: selectedCompletionStatus.value,
        status: selectedStatus.value,
        area: selectedArea.value,
        propertyType: selectedPropertyType.value,
        agent: selectedAgent.value,
        project: selectedProject.value,
        beds: selectedBeds.value,
        priceFrom: priceFrom.value,
        priceTo: priceTo.value,
        sizeFrom: sizeFrom.value,
        sizeTo: sizeTo.value,
        sort: selectedSort.value,
         referenceNumber: searchReferenceNumber.value 
      };
      
      console.log(" Manual search with filters:", filters);
      emit('filters-changed', filters);
    };

    const resetFilters = () => {
      selectedSaleRent.value = "All";
      selectedStatus.value = "All";
        selectedCompletionStatus.value = null;
      selectedArea.value = null;
      selectedPropertyType.value = null;
        selectedAgent.value = null;
      selectedBeds.value = "";
      selectedSort.value = "created_at_desc";
      priceFrom.value = 0;
      priceTo.value = 10000000;
      sizeFrom.value = 0;
      sizeTo.value = 10000;
      performSearch(); // Trigger search after reset
        searchReferenceNumber.value = "";
    };

    const resetPriceRange = () => {
      priceFrom.value = 0;
      priceTo.value = 10000000;
      performSearch();
    };

    const resetSizeRange = () => {
      sizeFrom.value = 0;
      sizeTo.value = 10000;
      performSearch();
    };

    const togglePriceDropdown = () => {
      showPriceDropdown.value = !showPriceDropdown.value;
      if (showPriceDropdown.value) showSizeDropdown.value = false;
    };

    const toggleSizeDropdown = () => {
      showSizeDropdown.value = !showSizeDropdown.value;
      if (showSizeDropdown.value) showPriceDropdown.value = false;
    };

    const closeAllDropdowns = () => {
      showPriceDropdown.value = false;
      showSizeDropdown.value = false;
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

    // Lifecycle
    onMounted(() => {
      // Fetch data when component mounts
      fetchAreas();
      fetchPropertyTypes();
        fetchAgents();
        fetchProjects();

      // Close dropdowns when clicking outside
      document.addEventListener('click', (e) => {
        const searchContainer = document.querySelector('.search-container');
        if (searchContainer && !searchContainer.contains(e.target)) {
          closeAllDropdowns();
        }
      });
    });

    // Cleanup timer on unmount
    onUnmounted(() => {
      if (searchTimer.value) {
        clearTimeout(searchTimer.value);
      }
    });

    return {
      // Data
      areas,
      agents,
      projects,
      propertyTypes,
      isLoadingAreas,
      isLoadingPropertyTypes,
      isLoadingAgents,
      isLoadingProjects, 
      selectedProject,
      selectedSaleRent,
      selectedStatus,
       selectedCompletionStatus, 
      selectedArea,
      selectedPropertyType,
       selectedAgent,
      selectedBeds,
      selectedSort,
      priceFrom,
      priceTo,
      sizeFrom,
      sizeTo,
      showPriceDropdown,
      showSizeDropdown,
      
      // Static options
      saleRentOptions,
      statusOptions,
       completionStatusOptions,
      bedsOptions,
      sortOptions,
      
      // Computed
      priceProgressStyle,
      sizeProgressStyle,
      hasActiveFilters,
      
      
      // Methods
      applyFilters,
      resetFilters,
      resetPriceRange,
      resetSizeRange,
      togglePriceDropdown,
      toggleSizeDropdown,
      closeAllDropdowns,
      updatePriceFrom,
      updatePriceTo,
      updateSizeFrom,
      updateSizeTo,
      validatePriceFrom,
      validatePriceTo,
      validateSizeFrom,
      validateSizeTo,
      formatNumber,
      handleFilterChange,
      handleSaleRentChange,
      handleStatusChange,
      handlePriceChange,
      handlePriceSliderChange,
      handleSizeChange,
      handleSizeSliderChange,
      
      searchReferenceNumber,
         locationFirstLine,
      locationSecondLine,
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

:deep(.vs__deselect) {
  border: none !important;
  box-shadow: none !important;
}

.range-dropdown {
  position: relative;
  display: block;
  /*margin:0px 8px;*/
}

.range-dropdown-content {
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
  transform: translateY(0) !important;
}
</style>