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
          :clearable="false"
          :close-on-select="false"
          :clear-search-on-select="false"
          :append-to-body="false"
          label="name"
          placeholder="City,Area,Community,Project or Building"
          class="custom-select listing-main-location"
          @update:modelValue="handleFilterChange"
        >
           <template #open-indicator="{ attributes }">
              <span v-bind="attributes">
                  <iconify-icon icon="lucide:chevron-down" class="vs__open-indicator-icon"></iconify-icon>
              </span>
          </template>
          <template #option="option">
            <div class="location-option" :class="{ selected: isAreaSelected(option) }">
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

      <div v-if="isMobileViewport" class="mobile-quick-chips">
        <button type="button" class="mobile-quick-chip" :class="{ active: selectedSaleRent === 'Sale' }" @click="toggleMobileQuickSale()">For Sale</button>
        <button type="button" class="mobile-quick-chip" :class="{ active: selectedCompletionStatus?.value === 'Completed' }" @click="toggleMobileQuickReady()">Ready</button>
        <button
          type="button"
          class="mobile-quick-chip mobile-quick-chip-action"
          @click="showMobileFilterSheet = true"
        >
          <i class="ri-equalizer-line"></i>
          <span>Filter</span>
          <span v-if="mobileActiveFilterCount > 0" class="mobile-filter-badge">{{ mobileActiveFilterCount }}</span>
        </button>
        <button
          type="button"
          class="mobile-quick-chip mobile-quick-chip-action"
          @click="openMobileSortSheet"
        >
          <i class="ri-sort-desc"></i>
          <span>Sort</span>
        </button>
      </div>

      <div class="listing-pill-row">
        <div class="listing-sale-rent-wrap">
          <button
            type="button"
            class="listing-pill-btn listing-sale-rent-btn"
            @click.stop="toggleSaleRentDropdown"
          >
            <span>{{ saleRentButtonLabel }}</span>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div v-if="showSaleRentDropdown" class="listing-sale-rent-popover" @click.stop>
            <div class="listing-pop-title-sm">Purpose</div>
            <div class="listing-tab-switch listing-tab-switch-purpose">
              <button
                type="button"
                class="listing-tab-btn"
                :class="{ active: selectedSaleRent === 'Sale' }"
                @click="selectSaleRentOption('Sale')"
              >
                Buy
              </button>
              <button
                type="button"
                class="listing-tab-btn"
                :class="{ active: selectedSaleRent === 'Rent' }"
                @click="selectSaleRentOption('Rent')"
              >
                Rent
              </button>
            </div>
            <div class="listing-pop-actions listing-pop-actions--dual">
              <button type="button" class="btn btn-outline-secondary" @click="resetSaleRentSelection">Reset</button>
              <button type="button" class="btn btn-primary" @click="applySaleRentSelection">Done</button>
            </div>
          </div>
        </div>

        <div class="listing-property-type-wrap">
          <button
            type="button"
            class="listing-pill-btn listing-property-type-btn"
            :disabled="isLoadingPropertyTypes"
            @click.stop="togglePropertyTypeDropdown"
          >
            <span>{{ propertyTypeButtonLabel }}</span>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div v-if="showPropertyTypeDropdown" class="listing-property-type-popover" @click.stop>
            <!--<div class="listing-pop-title-sm">Property Type</div>-->
            <!--<div class="listing-tab-switch">-->
            <!--  <button-->
            <!--    type="button"-->
            <!--    class="listing-tab-btn"-->
            <!--    :class="{ active: propertyTypeTab === 'residential' }"-->
            <!--    @click="propertyTypeTab = 'residential'"-->
            <!--  >-->
            <!--    Residential-->
            <!--  </button>-->
            <!--  <button-->
            <!--    type="button"-->
            <!--    class="listing-tab-btn"-->
            <!--    :class="{ active: propertyTypeTab === 'commercial' }"-->
            <!--    @click="propertyTypeTab = 'commercial'"-->
            <!--  >-->
            <!--    Commercial-->
            <!--  </button>-->
            <!--</div>-->
            <div class="listing-property-grid">
              <button
                v-for="type in visiblePropertyTypes"
                :key="type.id"
                type="button"
                class="listing-property-pill"
                :class="{ active: isPropertyTypeSelected(type) }"
                @click="togglePropertyTypeOption(type)"
              >
                {{ type.name }}
              </button>
            </div>
            <div class="listing-pop-actions listing-pop-actions--dual">
              <button type="button" class="btn btn-outline-secondary" @click="resetPropertyTypeSelection">Reset</button>
              <button type="button" class="btn btn-primary" @click="applyPropertyTypeSelection">Done</button>
            </div>
          </div>
        </div>

        <div class="listing-beds-wrap">
          <button type="button" class="listing-pill-btn" @click.stop="toggleBedsDropdown">
            <span>{{ bedsBathsButtonLabel }}</span>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div v-if="showBedsDropdown" class="listing-beds-popover" @click.stop>
            <div class="listing-pop-title-sm">Beds & Baths</div>
            <div class="listing-pop-label">Beds</div>
            <div class="listing-chip-grid">
              <button
                v-for="bed in bedsOptions"
                :key="bed"
                type="button"
                class="listing-chip-btn"
                :class="{ active: selectedBeds.includes(bed) }"
                @click="selectBedsOption(bed)"
              >
                {{ bed }}
              </button>
            </div>
            <div class="listing-pop-label mt-2">Baths</div>
              <div class="listing-chip-grid">
                <button
                  v-for="bath in bathsOptions"
                  :key="bath"
                  type="button"
                  class="listing-chip-btn"
                  :class="{ active: selectedBaths.includes(bath) }"
                  @click="selectBathOption(bath)"
                >
                  {{ bath }}
                </button>
              </div>
            <div class="listing-pop-actions listing-pop-actions--dual mt-3">
              <button type="button" class="btn btn-outline-secondary" @click="resetBedsBaths">Reset</button>
              <button type="button" class="btn btn-primary" @click="applyBedsBaths">Done</button>
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
                <label>Property Status</label>
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
              <div class=""   v-if="!isMyListingPage || (isMyListingPage && isTeamLeadManager)">
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
              <!-- ✅ Additional Features Section (جديدة وموضوعة داخل More Filters) -->
              <div class="listing-filter-section listing-pop-field--full">
                <label class="listing-pop-label">Features </label>
                <div class="listing-feature-grid">
                  <button
                    v-for="feature in listingFeatureOptions"
                    :key="feature.key"
                    type="button"
                    class="listing-feature-pill"
                    :class="{ active: isFeatureSelected(feature.key) }"
                    @click="toggleFeature(feature.key)"
                  >
                    <span class="listing-feature-label">{{ feature.label }}</span>
                  </button>
                </div>
              </div>
            </div>

            <div class="listing-pop-actions">
              <button type="button" class="btn btn-outline-secondary" @click="resetFilters">Reset</button>
              <button type="button" class="btn btn-primary" @click="showMoreFilters = false; applyFilters()">Done</button>
            </div>
          </div>
        </div>

        <div class="listing-sort-wrap">
          <button type="button" class="listing-icon-circle listing-sort-btn" title="Sort by" @click.stop="toggleSortDropdown">
            <i class="ri-sort-desc"></i>
          </button>
          <div v-if="showSortDropdown" class="listing-sort-popover" @click.stop>
            <div class="listing-pop-title-sm">Sort By</div>
            <button
              v-for="option in sortOptions"
              :key="`sort-${option.value}`"
              type="button"
              class="listing-sort-option"
              :class="{ active: sortDraft === option.value }"
              @click="selectSortOption(option.value)"
            >
              {{ option.label }}
            </button>
            <div class="listing-pop-actions listing-pop-actions--dual mt-2">
              <button type="button" class="btn btn-outline-secondary" @click="resetSortSelection">Reset</button>
              <button type="button" class="btn btn-primary" @click="applySortSelection">Done</button>
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
          <button 
            class="status-btn" 
            v-if="showStatusTabs" 
            :class="{ active: activeStatus === 'rented' }" 
            @click="emitStatusChange('rented')"
        >
            <i class="ri-home-gear-line"></i> Rented
        </button>
          <button class="status-btn" v-if="showStatusTabs" :class="{ active: activeStatus === 'draft' }" @click="emitStatusChange('draft')">
            <i class="fa fa-pencil-alt"></i> Draft
          </button>
          <!--<span class="status-sort-separator" v-if="showStatusTabs"></span>-->
        </div>
      </div>
    </div>

    <div v-if="showPriceDropdown || showMoreFilters || showBedsDropdown || showPropertyTypeDropdown || showSortDropdown || showSaleRentDropdown" class="dropdown-overlay" @click="closeAllDropdowns"></div>

    <div v-if="isMobileViewport && showMobileFilterSheet" class="mobile-filter-sheet-overlay" @click.self="showMobileFilterSheet = false">
      <div class="mobile-filter-sheet" @click.stop>
        <div class="mobile-filter-sheet-head">
          <button type="button" class="mobile-filter-clear" @click="resetFilters">Clear All</button>
          <button type="button" class="mobile-filter-close" @click="showMobileFilterSheet = false" aria-label="Close">
            <i class="ri-close-line"></i>
          </button>
        </div>

        <div class="mobile-filter-accordion">
          <details open>
            <summary>
              <span>Listing Type</span>
              <small>{{ mobileSaleRentLabel }}</small>
            </summary>
            <v-select
              v-model="selectedSaleRent"
              :options="typeOptions"
              label="label"
              :reduce="option => option.value"
              :searchable="false"
              :append-to-body="false"
              placeholder="All"
              class="custom-select listing-pop-select"
              @update:modelValue="handleFilterChange"
            />
          </details>

          <details open>
            <summary>
              <span>Property Type</span>
              <small>{{ mobilePropertyTypeLabel }}</small>
            </summary>
            <!--<div class="listing-tab-switch listing-tab-switch-mobile">-->
            <!--  <button type="button" class="listing-tab-btn" :class="{ active: propertyTypeTab === 'residential' }" @click="propertyTypeTab = 'residential'">Residential</button>-->
            <!--  <button type="button" class="listing-tab-btn" :class="{ active: propertyTypeTab === 'commercial' }" @click="propertyTypeTab = 'commercial'">Commercial</button>-->
            <!--</div>-->
            <div class="listing-property-grid listing-property-grid-mobile">
              <button
                v-for="type in visiblePropertyTypes"
                :key="'m-type-' + type.id"
                type="button"
                class="listing-property-pill"
                :class="{ active: isPropertyTypeSelected(type) }"
                @click="togglePropertyTypeOption(type)"
              >
                {{ type.name }}
              </button>
            </div>
          </details>

          <details>
            <summary>
              <span>Beds & Baths</span>
              <small>{{ mobileBedsBathsLabel }}</small>
            </summary>
            <div class="listing-pop-label">Bedrooms</div>
            <div class="listing-chip-grid">
              <button
                v-for="bed in bedsOptions"
                :key="'m-bed-' + bed"
                type="button"
                class="listing-chip-btn"
                :class="{ active: selectedBeds.includes(bed) }"
                @click="selectBedsOption(bed)"
              >{{ bed }}</button>
            </div>
            <div class="listing-pop-label mt-2">Bathrooms</div>
            <div class="listing-chip-grid">
              <button
                v-for="bath in bathsOptions"
                :key="'m-bath-' + bath"
                type="button"
                class="listing-chip-btn"
                :class="{ active: selectedBaths.includes(bath) }"
                @click="selectBathOption(bath)"
              >{{ bath }}</button>
            </div>
          </details>

          <details>
            <summary>
              <span>Price Range</span>
              <small>{{ mobilePriceLabel }}</small>
            </summary>
            <div class="listing-pop-grid">
              <div>
                <label>Minimum</label>
                <input type="text" :value="formatThousandsDisplay(priceFrom)" class="range-input-side" @input="onPriceFromInput" @blur="handlePriceChange" placeholder="0" />
              </div>
              <div>
                <label>Maximum</label>
                <input type="text" :value="formatThousandsDisplay(priceTo)" class="range-input-side" @input="onPriceToInput" @blur="handlePriceChange" placeholder="Any" />
              </div>
            </div>
          </details>

          <details>
            <summary>
              <span>Area (sqft)</span>
              <small>{{ mobileSizeLabel }}</small>
            </summary>
            <div class="listing-pop-grid">
              <div>
                <label>Minimum</label>
                <input type="text" :value="formatThousandsDisplay(sizeFrom)" class="range-input-side" @input="onSizeFromInput" @blur="handleSizeChange" placeholder="0" />
              </div>
              <div>
                <label>Maximum</label>
                <input type="text" :value="formatThousandsDisplay(sizeTo)" class="range-input-side" @input="onSizeToInput" @blur="handleSizeChange" placeholder="Any" />
              </div>
            </div>
          </details>

          <details v-if="!isMyListingPage">
            <summary>
              <span>Agent</span>
              <small>{{ mobileAgentLabel }}</small>
            </summary>
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
            />
          </details>

        </div>

        <div class="mobile-filter-sticky-actions">
          <button type="button" class="btn btn-primary w-100" @click="applyMobileFilters">Apply Filters</button>
        </div>
      </div>
    </div>

    <div v-if="isMobileViewport && showMobileSortSheet" class="mobile-filter-sheet-overlay" @click.self="showMobileSortSheet = false">
      <div class="mobile-sort-sheet" @click.stop>
        <div class="mobile-filter-sheet-head">
          <button type="button" class="mobile-filter-clear" @click="resetMobileSort">Reset</button>
          <button type="button" class="mobile-filter-close" @click="showMobileSortSheet = false" aria-label="Close">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="mobile-sort-list">
          <button
            v-for="option in sortOptions"
            :key="'m-sort-' + option.value"
            type="button"
            class="mobile-sort-option"
            :class="{ active: mobileSortDraft === option.value }"
            @click="mobileSortDraft = option.value"
          >
            {{ option.label }}
          </button>
        </div>
        <div class="mobile-filter-sticky-actions">
          <button type="button" class="btn btn-primary w-100" @click="applyMobileSort">Done</button>
        </div>
      </div>
    </div>
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
    const selectedSaleRent = ref("All");
    const selectedStatus = ref("All");
    const selectedArea = ref([]);
    const selectedPropertyType = ref(null);
    const selectedPropertyTypes = ref([]);
    const selectedAgent = ref(null);
    const selectedBeds = ref([]);
    const selectedSort = ref("");

    const priceFrom = ref(0);
    const priceTo = ref(10000000);
    const sizeFrom = ref(0);
    const sizeTo = ref(10000);

    const showPriceDropdown = ref(false);
    const showSizeDropdown = ref(false);
    const showMoreFilters = ref(false);
    const showBedsDropdown = ref(false);
    const showPropertyTypeDropdown = ref(false);
    const showSortDropdown = ref(false);
    const sortDraft = ref("created_at_desc");
    const showSaleRentDropdown = ref(false);
    const showMobileSortSheet = ref(false);
    const mobileSortDraft = ref("created_at_desc");
    const showMobileFilterSheet = ref(false);
    const isMobileViewport = ref(false);
    let resizeHandler = null;
const searchReferenceNumber = ref("");
const showFeaturesDropdown = ref(false);
const selectedFeatures = ref({});
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
    const bedsOptions = ["Studio", "1", "2", "3", "4", "5", "6", "7", "8+"];
    const selectedBaths = ref([]);
const bathsOptions = ["1", "2", "3", "4", "5", "6+"];
    const propertyTypeTab = ref("residential");
     const completionStatusOptions = [
      { label: "All", value: null },
      { label: "Completed", value: "Completed" },
      { label: "Under Construction", value: "Under Construction" }
    ];
    const listingFeatureOptions = [
      { key: 'maid', label: 'Maid Room' },
      { key: 'storage', label: 'Storage Room' },
      { key: 'study', label: 'Study Room' },
      { key: 'store', label: 'Store Room' },
      { key: 'laundry', label: 'Laundry Room' },
      { key: 'driver', label: 'Driver Room' },
    ];
const typeOptions = [
  { label: "All", value: "All" },
  { label: "Sale", value: "Sale" },
  { label: "Rent", value: "Rent" }
];

const sortOptions = [
  { label: "Hot Deal", value: "hot_deal" },
  { label: "Most Recent", value: "created_at_desc" },
  { label: "Price: Low to High", value: "price_asc" },
  { label: "Price: High to Low", value: "price_desc" }
];

    /** Status row: dropdown for price/date only (Hot Deal stays a separate button). */
    const quickSortSelectOptions = [
      { label: "Most Recent", value: "created_at_desc" },
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
    const response = await api.get("/listings/areas?has_listings=true");
    
    const areasData = response.data.data || response.data;
    
    const mappedAreas = areasData.map(area => ({
      id: area.id,
      name: area.area_parents_title || area.name || area.title,
      subtitle: area.region || area.city || area.country || 'UAE'
    }));
    
    areas.value = mappedAreas;
    allAreas.value = mappedAreas; //
    
    console.log("✅ Areas loaded:", areas.value.length);
    
  } catch (error) {
    console.error("❌ Error fetching areas:", error.response || error);
  } finally {
    isLoadingAreas.value = false;
  }
};

    const inferPropertyCategory = (name) => {
      const value = String(name || "").toLowerCase();
      const commercialKeywords = ["office", "shop", "retail", "warehouse", "labour", "hotel", "factory", "showroom", "commercial"];
      return commercialKeywords.some((kw) => value.includes(kw)) ? "commercial" : "residential";
    };

    // Fetch property types from API
    const fetchPropertyTypes = async () => {
      try {
        isLoadingPropertyTypes.value = true;
        const response = await api.get("/listings/property-types/?non_root_only=1");
        
        const propertyTypesData = response.data.data || response.data;
        
        propertyTypes.value = propertyTypesData.map(type => {
          const name = type.name || type.type_name || type.title;
          return {
            id: type.id,
            name,
            category: inferPropertyCategory(name)
          };
        });
        
        console.log("✅ Property types loaded:", propertyTypes.value.length);
        
      } catch (error) {
        console.error("❌ Error fetching property types:", error.response || error);
      } finally {
        isLoadingPropertyTypes.value = false;
      }
    };
    const propertyTypesByTab = computed(() => {
      return {
        residential: propertyTypes.value.filter((item) => item.category !== "commercial"),
        commercial: propertyTypes.value.filter((item) => item.category === "commercial"),
      };
    });

    const visiblePropertyTypes = computed(() => {
      return propertyTypesByTab.value[propertyTypeTab.value] || [];
    });

    const isPropertyTypeSelected = (type) => {
      return selectedPropertyTypes.value.some((item) => Number(item.id) === Number(type.id));
    };

    const togglePropertyTypeOption = (type) => {
      const idx = selectedPropertyTypes.value.findIndex((item) => Number(item.id) === Number(type.id));
      if (idx >= 0) {
        selectedPropertyTypes.value.splice(idx, 1);
      } else {
        selectedPropertyTypes.value.push(type);
      }
      selectedPropertyType.value = selectedPropertyTypes.value[0] || null;
    };

    const propertyTypeButtonLabel = computed(() => {
      if (!selectedPropertyTypes.value.length) return "Property Type";
      if (selectedPropertyTypes.value.length === 1) return selectedPropertyTypes.value[0].name;
      return `${selectedPropertyTypes.value.length} Property Types`;
    });

    const bedsBathsButtonLabel = computed(() => {
      const bedsCount = selectedBeds.value.length;
      const bathsCount = selectedBaths.value.length;
      if (!bedsCount && !bathsCount) return "Beds & Baths";
      if (bedsCount && bathsCount) return `${bedsCount} Beds, ${bathsCount} Baths`;
      if (bedsCount) return `${bedsCount} Beds`;
      return `${bathsCount} Baths`;
    });

    const quickSortLabel = computed(() => {
      const row = quickSortSelectOptions.find((item) => item.value === selectedSort.value);
      return row?.label || "Most Recent";
    });
    const mobileSaleRentLabel = computed(() => {
      if (!selectedSaleRent.value || selectedSaleRent.value === "All") return "Any";
      return selectedSaleRent.value;
    });
    const mobilePropertyTypeLabel = computed(() => {
      const count = selectedPropertyTypes.value.length;
      if (!count) return "Any";
      return count === 1 ? selectedPropertyTypes.value[0]?.name || "1 selected" : `${count} selected`;
    });
    const mobileBedsBathsLabel = computed(() => {
      const b = selectedBeds.value.length;
      const ba = selectedBaths.value.length;
      if (!b && !ba) return "Any";
      if (b && ba) return `${b} beds, ${ba} baths`;
      return b ? `${b} beds` : `${ba} baths`;
    });
    const mobilePriceLabel = computed(() => {
      if ((priceFrom.value || 0) <= 0 && (priceTo.value || 10000000) >= 10000000) return "Any";
      return `${formatThousandsDisplay(priceFrom.value) || 0} - ${formatThousandsDisplay(priceTo.value) || "Any"}`;
    });
    const mobileSizeLabel = computed(() => {
      if ((sizeFrom.value || 0) <= 0 && (sizeTo.value || 10000) >= 10000) return "Any";
      return `${formatThousandsDisplay(sizeFrom.value) || 0} - ${formatThousandsDisplay(sizeTo.value) || "Any"}`;
    });
    const mobileAgentLabel = computed(() => selectedAgent.value?.name || "Any");
    const mobileSortLabel = computed(() => {
      const row = sortOptions.find((item) => item.value === selectedSort.value);
      return row?.label || "Most Recent";
    });

    const saleRentButtonLabel = computed(() => {
      if (selectedSaleRent.value === "Sale") return "Buy";
      if (selectedSaleRent.value === "Rent") return "Rent";
      return "Purpose";
    });

    const toggleSaleRentDropdown = () => {
      showSaleRentDropdown.value = !showSaleRentDropdown.value;
      if (showSaleRentDropdown.value) {
        showSortDropdown.value = false;
        showPropertyTypeDropdown.value = false;
        showBedsDropdown.value = false;
        showPriceDropdown.value = false;
        showMoreFilters.value = false;
      }
    };

    const selectSaleRentOption = (value) => {
      selectedSaleRent.value = value;
    };

    const resetSaleRentSelection = () => {
      selectedSaleRent.value = "All";
      handleFilterChange();
    };

    const applySaleRentSelection = () => {
      showSaleRentDropdown.value = false;
      handleFilterChange();
    };

    const toggleSortDropdown = () => {
      showSortDropdown.value = !showSortDropdown.value;
      if (showSortDropdown.value) {
        sortDraft.value = selectedSort.value || "created_at_desc";
        showSaleRentDropdown.value = false;
        showPropertyTypeDropdown.value = false;
        showBedsDropdown.value = false;
        showPriceDropdown.value = false;
        showMoreFilters.value = false;
      }
    };

    const selectSortOption = (value) => {
      sortDraft.value = value;
    };

    const resetSortSelection = () => {
      sortDraft.value = "created_at_desc";
    };

    const applySortSelection = () => {
      selectedSort.value = sortDraft.value || "created_at_desc";
      showSortDropdown.value = false;
      handleFilterChange();
    };
    const openMobileSortSheet = () => {
      mobileSortDraft.value = selectedSort.value || "created_at_desc";
      showMobileSortSheet.value = true;
      showMobileFilterSheet.value = false;
    };
    const resetMobileSort = () => {
      mobileSortDraft.value = "created_at_desc";
    };
    const applyMobileSort = () => {
      selectedSort.value = mobileSortDraft.value || "created_at_desc";
      showMobileSortSheet.value = false;
      handleFilterChange();
    };

    const togglePropertyTypeDropdown = () => {
      showPropertyTypeDropdown.value = !showPropertyTypeDropdown.value;
      if (showPropertyTypeDropdown.value) {
        showSaleRentDropdown.value = false;
        showSortDropdown.value = false;
        showBedsDropdown.value = false;
        showPriceDropdown.value = false;
        showMoreFilters.value = false;
      }
    };

    const applyPropertyTypeSelection = () => {
      showPropertyTypeDropdown.value = false;
      handleFilterChange();
    };

    const resetPropertyTypeSelection = () => {
      selectedPropertyTypes.value = [];
      selectedPropertyType.value = null;
      handleFilterChange();
    };

    const selectBedsOption = (bed) => {
      const idx = selectedBeds.value.indexOf(bed);
      if (idx >= 0) {
        selectedBeds.value.splice(idx, 1);
      } else {
        selectedBeds.value.push(bed);
      }
    };

    const selectBathOption = (bath) => {
      const idx = selectedBaths.value.indexOf(bath);
      if (idx >= 0) {
        selectedBaths.value.splice(idx, 1);
      } else {
        selectedBaths.value.push(bath);
      }
    };

    const applyBedsBaths = () => {
      showBedsDropdown.value = false;
      handleFilterChange();
    };

    const resetBedsBaths = () => {
      selectedBeds.value = [];
      selectedBaths.value = [];
      handleFilterChange();
    };
const getUserFromStorage = () => {
    try {
        const userData = localStorage.getItem('user')
        return userData ? JSON.parse(userData) : null
    } catch (error) {
        console.error('Error getting user from storage:', error)
        return null
    }
}

const user = ref(getUserFromStorage())

// Check if user is admin or super_admin (same pattern as header/index.vue)
const isTeamLeadManager = computed(() => {
    if (!user.value) return false
    
    const isAdminUser = user.value.roles?.includes('manager') || 
                       user.value.roles?.includes('team_lead')
    
    return isAdminUser
})
const featuresButtonLabel = computed(() => {
  const selectedCount = Object.values(selectedFeatures.value).filter(Boolean).length;
  if (selectedCount === 0) return 'Features';
  if (selectedCount === 1) {
    const activeKey = Object.keys(selectedFeatures.value).find(key => selectedFeatures.value[key] === true);
    const feature = listingFeatureOptions.find(f => f.key === activeKey);
    return feature ? feature.label : '1 Feature';
  }
  return `${selectedCount} Features`;
});


    const fetchAgents = async () => {
      try {
        isLoadingAgents.value = true;
        
          let response; 
        if (isTeamLeadManager && isMyListingPage.value) {
          response = await api.get("/listings/agents/?listings=true");
        } else {
          response = await api.get("/listings/agents");
        }
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
          const hasSelectedFeatures = Object.values(selectedFeatures.value).some(value => value === true);

      return selectedSaleRent.value !== "All" || 
             selectedStatus.value !== "All" || 
             hasSelectedAreas || 
             selectedPropertyTypes.value.length > 0 || 
             selectedBeds.value.length > 0 ||
             selectedBaths.value.length > 0 || 
             priceFrom.value > 0 || 
             priceTo.value < 10000000 || 
             sizeFrom.value > 0 || 
             sizeTo.value < 10000 || searchReferenceNumber.value.trim() !== ""
             ||
             selectedCompletionStatus.value !== null  ||
             hasSelectedFeatures;  
    });
    const mobileActiveFilterCount = computed(() => {
      let n = 0;
      if (selectedSaleRent.value && selectedSaleRent.value !== "All") n++;
      if (selectedPropertyTypes.value.length) n++;
      if (selectedBeds.value.length) n++;
      if (selectedBaths.value.length) n++;
      if (priceFrom.value > 0 || priceTo.value < 10000000) n++;
      if (selectedCompletionStatus.value !== null) n++;
      if (selectedSort.value && selectedSort.value !== "created_at_desc") n++;
      return n;
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
      if (Array.isArray(filters.propertyTypes) && filters.propertyTypes.length) {
        apiFilters.property_type_ids = filters.propertyTypes.map((item) => item?.id).filter(Boolean);
        apiFilters.property_type_id = apiFilters.property_type_ids[0];
      } else if (filters.propertyType) {
        apiFilters.property_type_id = filters.propertyType.id;
      }
      if (filters.agent) {
        apiFilters.agent_id = filters.agent.id;
      }

      // Bedrooms Filter
      const bedList = Array.isArray(filters.bedsList) ? filters.bedsList : (filters.beds ? [filters.beds] : []);
      if (bedList.length) {
        const firstBed = bedList[0];
        if (firstBed === 'Studio') {
          apiFilters.number_of_bedrooms = 'Studio';
        } else {
          apiFilters.number_of_bedrooms = parseInt(firstBed);
        }
        apiFilters.number_of_bedrooms_in = bedList;
      }
      const bathList = Array.isArray(filters.bathsList) ? filters.bathsList : (filters.baths ? [filters.baths] : []);
      if (bathList.length) {
        apiFilters.number_of_bathrooms = parseInt(bathList[0]);
        apiFilters.number_of_bathrooms_in = bathList;
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
        if (filters.selectedFeatures && Object.keys(filters.selectedFeatures).length > 0) {
            const activeFeatures = Object.keys(filters.selectedFeatures).filter(key => filters.selectedFeatures[key] === true);
            if (activeFeatures.length > 0) {
              apiFilters.additional_features = activeFeatures;
            }
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
          propertyType: selectedPropertyTypes.value[0] || null,
          propertyTypes: selectedPropertyTypes.value,
          agent: selectedAgent.value,
          beds: selectedBeds.value[0] || "",
          bedsList: selectedBeds.value,
          priceFrom: priceFrom.value,
          priceTo: priceTo.value,
          sizeFrom: sizeFrom.value,
          sizeTo: sizeTo.value,
          sort: selectedSort.value,
           referenceNumber: searchReferenceNumber.value,
           baths: selectedBaths.value[0] || "",
           bathsList: selectedBaths.value,
            selectedFeatures: selectedFeatures.value,
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
    const toggleMobileQuickSale = () => {
      selectedSaleRent.value = selectedSaleRent.value === 'Sale' ? 'All' : 'Sale';
      handleFilterChange();
    };
    const toggleMobileQuickReady = () => {
      selectedCompletionStatus.value =
        selectedCompletionStatus.value?.value === 'Completed' ? null : completionStatusOptions[1];
      handleFilterChange();
    };
    const toggleMobileQuickHotDeal = () => {
      selectedSort.value = selectedSort.value === 'hot_deal' ? 'created_at_desc' : 'hot_deal';
      handleFilterChange();
    };
    const toggleHotDeal = () => {
      if (selectedSort.value === 'hot_deal') {
        selectedSort.value = 'created_at_desc';
      } else {
        selectedSort.value = 'hot_deal';
      }
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
    
    
    
    
    const toggleFeaturesDropdown = () => {
          showFeaturesDropdown.value = !showFeaturesDropdown.value;
          // إغلاق القوائم الأخرى عند الفتح (اختياري)
          if (showFeaturesDropdown.value) {
            showSaleRentDropdown.value = false;
            showPropertyTypeDropdown.value = false;
            showBedsDropdown.value = false;
            showPriceDropdown.value = false;
            showSortDropdown.value = false;
          }
        };
        
        const isFeatureSelected = (key) => {
          return !!selectedFeatures.value[key];
        };
        
        const toggleFeature = (key) => {
          selectedFeatures.value = {
            ...selectedFeatures.value,
            [key]: !selectedFeatures.value[key]
          };
        };
        
        const resetFeaturesSelection = () => {
          selectedFeatures.value = {};
          // لا نريد إرسال الفلتر مباشرة، ينتظر المستخدم الضغط على "Done"
        };
        
        const applyFeaturesSelection = () => {
          showFeaturesDropdown.value = false;
          handleFilterChange(); // استدعاء الدالة الموجودة بالفعل لتحديث البحث
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
        propertyType: selectedPropertyTypes.value[0] || null,
        propertyTypes: selectedPropertyTypes.value,
        agent: selectedAgent.value,
        beds: selectedBeds.value[0] || "",
        bedsList: selectedBeds.value,
        baths: selectedBaths.value[0] || "",
        bathsList: selectedBaths.value,
        priceFrom: priceFrom.value,
        priceTo: priceTo.value,
        sizeFrom: sizeFrom.value,
        sizeTo: sizeTo.value,
        sort: selectedSort.value,
         referenceNumber: searchReferenceNumber.value ,
          selectedFeatures: selectedFeatures.value,
           
      };
      
      console.log("🔍 Manual search with filters:", filters);
      emit('filters-changed', filters);
    };
    const applyMobileFilters = () => {
      applyFilters();
      showMobileFilterSheet.value = false;
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
      const sourceList = Array.isArray(filters.propertyTypes) && filters.propertyTypes.length
        ? filters.propertyTypes
        : (filters.propertyType ? [filters.propertyType] : []);
      if (sourceList.length && propertyTypes.value.length) {
        const picked = sourceList
          .map((f) => propertyTypes.value.find((p) => Number(p.id) === Number(f?.id)))
          .filter(Boolean);
        selectedPropertyTypes.value = picked;
        selectedPropertyType.value = picked[0] || null;
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
         if (filters.selectedFeatures) {
              selectedFeatures.value = { ...filters.selectedFeatures };
            } else {
              selectedFeatures.value = {};
            }
            selectedArea.value = areasWithNames;
        selectedProject.value = filters.project || null;
        selectedPropertyTypes.value = Array.isArray(filters.propertyTypes)
          ? filters.propertyTypes
          : (filters.propertyType ? [filters.propertyType] : []);
        selectedPropertyType.value = selectedPropertyTypes.value[0] || null;
        selectedAgent.value = filters.agent || null;
        selectedBeds.value = Array.isArray(filters.bedsList) ? filters.bedsList : (filters.beds ? [filters.beds] : []);
        selectedBaths.value = Array.isArray(filters.bathsList) ? filters.bathsList : (filters.baths ? [filters.baths] : []);
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
      selectedPropertyTypes.value = [];
      selectedAgent.value = null;
      selectedBeds.value = [];
      selectedBaths.value = [];
      selectedSort.value = "created_at_desc";
      priceFrom.value = 0;
      priceTo.value = 5000000;
      sizeFrom.value = 0;
      sizeTo.value = 5000;
      performSearch(); 
      searchReferenceNumber.value = "";
       selectedFeatures.value = {};
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
        showSaleRentDropdown.value = false;
        showMoreFilters.value = false;
        showSizeDropdown.value = false;
        showBedsDropdown.value = false;
        showPropertyTypeDropdown.value = false;
        showSortDropdown.value = false;
      }
    };

    const toggleBedsDropdown = () => {
      showBedsDropdown.value = !showBedsDropdown.value;
      if (showBedsDropdown.value) {
        showSaleRentDropdown.value = false;
        showPriceDropdown.value = false;
        showMoreFilters.value = false;
        showSizeDropdown.value = false;
        showPropertyTypeDropdown.value = false;
        showSortDropdown.value = false;
      }
    };

    const toggleSizeDropdown = () => {
      showSizeDropdown.value = !showSizeDropdown.value;
    };

    const closeAllDropdowns = () => {
      showPriceDropdown.value = false;
      showSizeDropdown.value = false;
      showMoreFilters.value = false;
      showBedsDropdown.value = false;
      showPropertyTypeDropdown.value = false;
      showSortDropdown.value = false;
      showSaleRentDropdown.value = false;
    };

    const toggleMoreFilters = () => {
      showMoreFilters.value = !showMoreFilters.value;
      if (showMoreFilters.value) {
        showSaleRentDropdown.value = false;
        showPriceDropdown.value = false;
        showSizeDropdown.value = false;
        showBedsDropdown.value = false;
        showPropertyTypeDropdown.value = false;
        showSortDropdown.value = false;
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

    const isAreaSelected = (option) => {
      const selectedAreas = Array.isArray(selectedArea.value) ? selectedArea.value : [];
      return selectedAreas.some((item) => Number(item?.id) === Number(option?.id));
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
      resizeHandler = () => { isMobileViewport.value = window.innerWidth <= 768; };
      resizeHandler();
      window.addEventListener('resize', resizeHandler);
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
      if (resizeHandler) window.removeEventListener('resize', resizeHandler);
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
      selectedPropertyTypes,
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
      showPropertyTypeDropdown,
      showSortDropdown,
      sortDraft,
      showSaleRentDropdown,
      showMobileSortSheet,
      mobileSortDraft,
      showMobileFilterSheet,
      isMobileViewport,
      propertyTypeTab,
       listingFeatureOptions,     
     showFeaturesDropdown,      
      selectedFeatures, 
      
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
      quickSortLabel,
      mobileSaleRentLabel,
      mobilePropertyTypeLabel,
      mobileBedsBathsLabel,
      mobilePriceLabel,
      mobileSizeLabel,
      mobileAgentLabel,
      mobileSortLabel,
      saleRentButtonLabel,
      isMyListingPage,
      isTeamLeadManager,
      visiblePropertyTypes,
      propertyTypeButtonLabel,
      bedsBathsButtonLabel,
      // Computed
      priceProgressStyle,
      sizeProgressStyle,
      hasActiveFilters,
      mobileActiveFilterCount,
      dynamicHeadline,
      formattedResultCount,
      featuresButtonLabel,
      // Methods
      applyFilters,
      applyMobileFilters,
      resetFilters,
      resetPriceRange,
      resetSizeRange,
      togglePriceDropdown,
      toggleBedsDropdown,
      togglePropertyTypeDropdown,
      toggleSortDropdown,
      selectSortOption,
      resetSortSelection,
      applySortSelection,
      openMobileSortSheet,
      resetMobileSort,
      applyMobileSort,
      toggleSaleRentDropdown,
      toggleSizeDropdown,
      toggleMoreFilters,
      closeAllDropdowns,
      isPropertyTypeSelected,
      togglePropertyTypeOption,
      applyPropertyTypeSelection,
      resetPropertyTypeSelection,
      selectSaleRentOption,
      applySaleRentSelection,
      resetSaleRentSelection,
      selectBedsOption,
      selectBathOption,
      applyBedsBaths,
      resetBedsBaths,
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
      toggleHotDeal,
      toggleMobileQuickSale,
      toggleMobileQuickReady,
      toggleMobileQuickHotDeal,
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
      isAreaSelected,
      searchReferenceNumber,
      locationFirstLine,
      locationSecondLine,
      isFirstSelectedArea,
      isSecondSelectedArea,
      remainingSelectedAreasCount,
      toggleFeaturesDropdown,    
      isFeatureSelected,         
      toggleFeature,             
      resetFeaturesSelection,    
      applyFeaturesSelection,
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
  appearance: none;
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

/* Match Bayut-like selected location chip style */
:deep(.listing-main-location .vs__selected-options) {
  display: flex !important;
  align-items: center !important;
  gap: 8px !important;
  flex-wrap: nowrap !important;
  overflow-x: auto !important;
  scrollbar-width: none !important;
}

:deep(.listing-main-location .vs__selected-options::-webkit-scrollbar) {
  display: none !important;
}

:deep(.listing-main-location .vs__search) {
  min-width: 180px !important;
  padding-left: 2px !important;
}

/* Location input: never show default clear X (chip remove stays available). */
:deep(.listing-main-location .vs__clear) {
  display: none !important;
}

.location-chip {
  display: inline-flex !important;
  align-items: center !important;
  gap: 8px !important;
  min-height: 30px !important;
  border: 1.5px solid #7a70c3 !important;
  border-radius: 999px !important;
  background: #f9f8ff !important;
  color: #4c438c !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 0 10px 0 12px !important;
  margin: 0 !important;
  line-height: 1 !important;
  white-space: nowrap !important;
  box-shadow: none !important;
}

.location-chip-close {
  width: 16px !important;
  height: 16px !important;
  margin: 0 !important;
  border-radius: 50% !important;
  background: transparent !important;
}

.location-chip-close::before {
  content: "✕" !important;
  font-size: 11px !important;
  font-weight: 400 !important;
  color: #6b639f !important;
  font-family: Arial, sans-serif !important;
}

.location-chip-close:hover {
  background: #efeaff !important;
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
    border-color: #faa300;
    color: #b45309;
  background: #fff7ed;
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
/*.listing-status-row .status-btn.hot-deal-btn {*/
/*  border: 1px solid #fdba74;*/
/*  background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);*/
/*  color: #9a3412;*/
/*  font-weight: 700;*/
/*  letter-spacing: 0.02em;*/
/*}*/

/*.listing-status-row .status-btn.hot-deal-btn i {*/
/*  font-size: 14px;*/
/*  color: #ea580c;*/
/*}*/

.listing-status-row .status-btn.hot-deal-btn.active {
  border: 1px solid #fdba74;
  background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
  color: #9a3412;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.listing-status-row .status-btn.hot-deal-btn.active i {
  color: #ea580c;
  font-size: 14px
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
  background: #fff7ed !important;
  color: #b45309 !important;
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

/* Bayut-like compact controls for desktop filters */
.listing-pill-row,
.listing-pill-row button,
.listing-pill-row :deep(.vs__selected),
.listing-pill-row :deep(.vs__search),
.listing-pill-row :deep(.vs__placeholder) {
  font-family: "Inter", "Segoe UI", Tahoma, Arial, sans-serif !important;
}

.listing-pill-row :deep(.vs__selected),
.listing-pill-row :deep(.vs__search),
.listing-pill-row :deep(.vs__placeholder),
.listing-pill-btn {
  font-size: 13px !important;
  font-weight: 500 !important;
  color: #1f2937 !important;
}

.listing-sale-rent-wrap,
.listing-property-type-wrap,
.listing-sort-wrap {
  position: relative;
}

.listing-sale-rent-btn,
.listing-property-type-btn {
  min-width: 132px;
  max-width: 148px;
  justify-content: space-between;
}

.listing-sale-rent-btn span,
.listing-property-type-btn span,
.listing-pill-btn span {
  display: inline-flex;
  align-items: center;
  line-height: 1;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.listing-pop-title-sm {
  font-size: 13px;
  color: #111827;
  font-weight: 700;
  margin-bottom: 10px;
}

.listing-property-type-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  /*width: min(460px, calc(100vw - 20px));*/
  width:690px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 12px;
  z-index: 1300;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
    max-width: min(680px, calc(100vw - 24px));
}

.listing-sale-rent-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: min(420px, calc(100vw - 20px));
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 12px;
  z-index: 1300;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
}

.listing-tab-switch-purpose {
  padding: 4px;
      gap: 15px;
}

.listing-tab-switch-purpose .listing-tab-btn {
  border: none;
  border-radius: 8px;
  color: #1f2937;
  font-size: 16px;
  padding:  8px;
    border: 1px solid #dbe2ee;
    text-align:center;
}

.listing-tab-switch-purpose .listing-tab-btn.active {
  border-bottom: none;
  background: #fff4e6;
  color: #d97706;
}

.listing-tab-switch {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 10px;
}

.listing-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 8px 6px;
  color: #6b7280;
  font-size: 13px;
  font-weight: 600;
}

.listing-tab-btn.active {
  color: #d97706;
  border-bottom-color: #faa300;
}

.listing-property-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
     gap: 10px 5px;
}

.listing-property-pill {
  min-height: 42px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #4b5563;
  font-size: 11px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1;
  padding: 0 5px;
}

.listing-property-pill.active {
  border-color: #faa300;
  color: #b45309;
  background: #fff7ed;
}

.listing-pop-actions--dual .btn {
  min-height: 40px !important;
  border-radius: 12px !important;
  font-size: 13px !important;
  font-weight: 600 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  text-align: center !important;
  line-height: 1 !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.listing-beds-popover {
  width: min(580px, calc(100vw - 20px));
}

.listing-chip-grid {
  grid-template-columns: repeat(5, minmax(0, 1fr));
}

.listing-chip-btn {
  min-height: 42px;
  font-size: 13px;
  font-weight: 500;
}

.listing-sort-btn i {
  font-size: 16px;
}

.listing-sort-popover {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 230px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 10px;
  z-index: 1300;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
}

.listing-sort-option {
  width: 100%;
  text-align: left;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 12px;
  color: #374151;
  margin-bottom: 8px;
}

.listing-sort-option:last-child {
  margin-bottom: 0;
}

.listing-sort-option.active {
  border-color: #faa300;
  background: #fff7ed;
  color: #b45309;
}

.status-sort-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #dbe2ee;
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 11px;
  color: #4b5563;
  background: #fff;
}

.location-option.selected {
  background: #fff7ed;
  border-radius: 10px;
}

.location-option.selected .location-option-name {
  color: #b45309;
  font-weight: 600;
}

.listing-tab-switch-mobile {
  margin-top: 8px;
}

.listing-property-grid-mobile {
  margin-top: 10px;
}

/* Mobile-first simplified search/filter UX */
@media (max-width: 768px) {
  .listing-headline {
    display: none;
  }

  .listing-main-search {
    position: relative;
    width: 100%;
  }

  .listing-main-search .listing-main-location {
    width: 100%;
    padding-right: 10px;
  }

  .mobile-filter-trigger {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .mobile-sort-trigger {
    position: absolute;
    right: 48px;
    top: 50%;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .mobile-filter-badge {
    position: static;
    min-width: 16px;
    height: 16px;
    border-radius: 999px;
    background: #f59e0b;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
  }

  .listing-pill-row,
  .listing-status-row,
  .listing-notify-btn,
  .unified-search-btn {
    display: none !important;
  }

  .mobile-quick-chips {
    margin-top: 8px;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .mobile-quick-chips::-webkit-scrollbar { display: none; }

  .mobile-quick-chip {
    border: 1px solid #dbe3ef;
    background: #fff;
    color: #334155;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
  }

  .mobile-quick-chip-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .mobile-quick-chip.active {
    border-color: #f59e0b;
    color: #b45309;
    background: #fff7ed;
  }

  .mobile-filter-sheet-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 2000;
    display: flex;
    align-items: flex-end;
    justify-content: center;
  }
  .mobile-filter-sheet {
    width: 100%;
    max-height: 91dvh;
    overflow: auto;
    background: #fff;
    border-radius: 18px 18px 0 0;
    padding: 10px 10px calc(60px + env(safe-area-inset-bottom, 0px));
    position: relative;
  }
  .mobile-sort-sheet {
    width: 100%;
    max-height: 62dvh;
    overflow: auto;
    background: #fff;
    border-radius: 18px 18px 0 0;
    padding: 10px 10px calc(60px + env(safe-area-inset-bottom, 0px));
    position: relative;
  }
  .mobile-sort-list {
    display: grid;
    gap: 8px;
    margin-top: 4px;
  }
  .mobile-sort-option {
    width: 100%;
    border: 1px solid #e6eaf1;
    background: #fff;
    color: #0f172a;
    border-radius: 10px;
    min-height: 34px;
    text-align: left;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 500;
  }
  .mobile-sort-option.active {
    border-color: #faa300;
    color: #b45309;
    background: #fff7ed;
  }
  .mobile-filter-sheet-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 0 2px;
  }
  .mobile-filter-clear {
    border: none;
    background: transparent;
    color: #334155;
    font-weight: 500;
    font-size: 12px;
    padding: 4px 0;
  }
  .mobile-filter-close {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }
  .mobile-filter-close i {
    font-size: 14px;
    line-height: 1;
  }
  .mobile-filter-accordion details {
    border: 1px solid #e7ebf2;
    border-radius: 12px;
    padding: 8px 9px;
    margin-bottom: 7px;
    background: #fbfcff;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
  }
  .mobile-filter-accordion summary {
    font-size: 11.5px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 6px;
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    line-height: 1.2;
  }
  .mobile-filter-accordion summary small {
    font-size: 10px;
    font-weight: 500;
    color: #6b7280;
    max-width: 50%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .mobile-filter-accordion summary::-webkit-details-marker { display: none; }
  .mobile-filter-sheet .listing-pop-label {
    font-size: 9px !important;
    font-weight: 500;
  }
  .mobile-filter-sheet .listing-chip-btn {
    font-size: 10px !important;
    min-height: 26px;
    padding: 4px 8px;
    border-radius: 999px;
  }
  .mobile-filter-sheet :deep(.vs__open-indicator-icon) {
    font-size: 9px !important;
    font-weight: 400 !important;
    color: #66666680 !important;
  }
  .mobile-filter-sheet :deep(.vs__open-indicator) {
    transform: scale(0.7);
    transform-origin: center;
    fill: #66666680 !important;
  }
  .mobile-filter-sheet :deep(.vs__open-indicator svg) {
    fill: #66666680 !important;
  }
  .mobile-filter-sheet :deep(.vs__dropdown-toggle) {
    min-height: 32px;
    border-radius: 10px !important;
    border-color: #e4e8f0 !important;
  }
  .mobile-filter-sheet :deep(.vs__selected),
  .mobile-filter-sheet :deep(.vs__search),
  .mobile-filter-sheet :deep(.vs__search::placeholder) {
    font-size: 11px !important;
    font-weight: 400 !important;
  }
  .mobile-filter-sheet :deep(.vs__selected-options) {
    padding-left: 8px;
  }
  .mobile-filter-sheet :deep(.vs__search) {
    padding-left: 6px !important;
  }
  .mobile-filter-sheet :deep(.vs__dropdown-menu) {
    z-index: 4005 !important;
    font-size: 11px !important;
  }
  .mobile-filter-sheet :deep(.vs__dropdown-option) {
    font-size: 11px !important;
    font-weight: 400 !important;
    padding-top: 6px !important;
    padding-bottom: 6px !important;
  }
  .mobile-filter-sheet .listing-pop-grid label {
    font-size: 10px !important;
    font-weight: 500;
  }
  .mobile-filter-sheet .listing-pop-grid .range-input-side {
    font-size: 11px !important;
    min-height: 31px;
    border-radius: 9px !important;
  }
  .mobile-filter-sheet .listing-pop-grid .range-input-side::placeholder {
    font-size: 10px !important;
  }
  .mobile-filter-sheet :deep(.vs--open) {
    position: relative;
    z-index: 4006;
  }
  .mobile-status-chip-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
  }
  .mobile-filter-sticky-actions {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 4010;
    background: #fff;
    border-top: 1px solid #eef2f7;
    padding: 7px 10px calc(8px + env(safe-area-inset-bottom, 0px));
  }
  .mobile-filter-sticky-actions .btn {
    min-height: 34px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 10px;
    padding-top: 4px;
    padding-bottom: 4px;
  }

  .mobile-filter-sheet .listing-property-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }
  .mobile-filter-sheet .listing-property-pill {
    min-height: 34px;
    font-size: 11px;
    padding: 0 8px;
  }
  .mobile-filter-sheet .listing-chip-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 7px;
  }
}

/* Final override: selected location chip exactly like reference */
:deep(.listing-main-location .vs__selected-options) {
  display: flex !important;
  align-items: center !important;
  gap: 8px !important;
  flex-wrap: nowrap !important;
}

.listing-main-location .location-chip {
  border: 1.5px solid #faa300 !important;
  background: #fff7ed !important;
  color: #b45309 !important;
  border-radius: 999px !important;
  min-height: 31px !important;
  padding: 0 10px 0 12px !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  line-height: 1 !important;
}

.listing-main-location .location-chip-close {
  width: 16px !important;
  height: 16px !important;
  margin-left: 2px !important;
  border-radius: 50% !important;
  background: transparent !important;
}

.listing-main-location .location-chip-close::before {
  content: "✕" !important;
  font-size: 11px !important;
  font-family: Arial, sans-serif !important;
  font-weight: 400 !important;
  color: #c16c09 !important;
}

/* Location dropdown row: remove inner bordered card/box. */
:deep(.listing-main-location .vs__dropdown-option .location-option) {
  border: none !important;
  border-radius: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  padding: 4px 0 !important;
}

:deep(.listing-main-location .vs__dropdown-option--highlight .location-option) {
  background: transparent !important;
}

.listing-main-location .location-option.selected {
  border-radius: 0 !important;
  background: transparent !important;
}
/* Additional Features Styles - Matching the existing design */
.listing-features-wrap {
  position: relative;
}

.listing-features-btn {
  min-width: 132px;
  max-width: 148px;
  justify-content: space-between;
}

.listing-features-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: min(580px, calc(100vw - 20px));
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px;
  z-index: 1300;
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.16);
}

.listing-feature-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.listing-feature-pill {
  min-height: 42px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #4b5563;
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  line-height: 1;
  padding: 0 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.listing-feature-pill.active {
  border-color: #faa300;
  color: #b45309;
  background: #fff7ed;
}

.listing-feature-pill:hover {
  border-color: #faa300;
  background: #fef9e8;
}

@media (max-width: 768px) {
  .listing-feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  
  .listing-feature-pill {
    min-height: 34px;
    font-size: 11px;
    padding: 0 10px;
  }
  
  .listing-features-popover {
    width: calc(100vw - 30px);
    padding: 12px;
  }
}
</style>