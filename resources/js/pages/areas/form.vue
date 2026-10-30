<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Area' : 'Add Area'" 
            :breadcrumbs="[
                { name: 'Areas', path: '/areas' },
                { name: isEditMode ? 'Edit' : 'Add' }
            ]" 
        />

        <div class="card area-form">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Area' : 'Add Area' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <!-- Edit Mode - Show Current Path -->
                            <div v-if="isEditMode" class="mb-4">
                                <label class="form-label">Current Path</label>
                                <div class="area-path border rounded p-3">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span v-if="selectedCountry" class="badge bg-primary">
                                            <iconify-icon icon="lucide:globe" class="me-1"></iconify-icon>
                                            {{ selectedCountry.name }}
                                        </span>
                                        <iconify-icon v-if="selectedCity" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedCity" class="badge bg-success">
                                            <iconify-icon icon="lucide:building" class="me-1"></iconify-icon>
                                            {{ selectedCity.name }}
                                        </span>
                                        <iconify-icon v-if="selectedArea" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedArea" class="badge bg-info">
                                            <iconify-icon icon="lucide:map-pin" class="me-1"></iconify-icon>
                                            {{ selectedArea.name }}
                                        </span>
                                        <iconify-icon v-if="selectedCommunity" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedCommunity" class="badge bg-warning">
                                            <iconify-icon icon="lucide:users" class="me-1"></iconify-icon>
                                            {{ selectedCommunity.name }}
                                        </span>
                                        <iconify-icon v-if="selectedSubCommunity" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedSubCommunity" class="badge bg-secondary">
                                            <iconify-icon icon="lucide:user-plus" class="me-1"></iconify-icon>
                                            {{ selectedSubCommunity.name }}
                                        </span>
                                        <iconify-icon v-if="selectedCluster" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedCluster" class="badge bg-dark">
                                            <iconify-icon icon="lucide:group" class="me-1"></iconify-icon>
                                            {{ selectedCluster.name }}
                                        </span>
                                        <iconify-icon v-if="selectedBuilding" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="selectedBuilding" class="badge bg-danger">
                                            <iconify-icon icon="lucide:home" class="me-1"></iconify-icon>
                                            {{ selectedBuilding.name }}
                                        </span>
                                        <iconify-icon v-if="areaForm.name" icon="lucide:chevron-right" class="text-muted"></iconify-icon>
                                        <span v-if="areaForm.name" class="badge bg-primary">
                                            <iconify-icon icon="lucide:edit" class="me-1"></iconify-icon>
                                            {{ areaForm.name }} (Current)
                                        </span>
                                    </div>
                                </div>
                                <small class="text-muted">You are editing: <strong>{{ areaForm.name }}</strong> ({{ formatType(areaForm.type) }})</small>
                            </div>

                            <!-- Country Selection -->
                            <div class="mb-4">
                                <label class="form-label">Country</label>
                                <v-select
                                    v-model="selectedCountry"
                                    :options="countries"
                                    label="name"
                                    :reduce="(area) => area"
                                    placeholder="Select country (leave empty to create a new country)"
                                    :class="{'is-invalid': errors.country}"
                                    :disabled="loading"
                                    @update:modelValue="onCountryChange"
                                >
                                    <template #option="{ name }">
                                        <div>
                                            <iconify-icon icon="lucide:globe" class="text-primary me-2"></iconify-icon>
                                            {{ name }}
                                        </div>
                                    </template>
                                </v-select>
                            </div>

                            <!-- City Selection -->
                            <div class="mb-4" v-if="selectedCountry">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <v-select
                                    v-model="selectedCity"
                                    :options="cities"
                                    label="name"
                                    :reduce="(area) => area"
                                    placeholder="Select city"
                                    :class="{'is-invalid': errors.city}"
                                    :disabled="loading || loadingCities"
                                    @update:modelValue="onCityChange"
                                >
                                    <template #option="{ name }">
                                        <div>
                                            <iconify-icon icon="lucide:building" class="text-success me-2"></iconify-icon>
                                            {{ name }}
                                        </div>
                                    </template>
                                    <template #no-options>
                                        <div class="text-center text-muted py-2">
                                            {{ loadingCities ? 'Loading cities...' : 'No cities available' }}
                                        </div>
                                    </template>
                                </v-select>
                                <div class="invalid-feedback" v-if="errors.city">
                                    {{ errors.city[0] }}
                                </div>
                            </div>

                            <!-- Area Selection -->
                            <div class="mb-4" v-if="selectedCity">
                                <label class="form-label">Area</label>
                                <!--|| (isEditMode && !canEditArea)-->
                                <v-select
                                    v-model="selectedArea"
                                    :options="areas"
                                    label="name"
                                    :reduce="(area) => area"
                                    placeholder="Select area (optional)"
                                    :class="{'is-invalid': errors.area}"
                                    :disabled="loading || loadingAreas "
                                    @update:modelValue="onAreaChange"
                                >
                                    <template #option="{ name }">
                                        <div>
                                            <iconify-icon icon="lucide:map-pin" class="text-info me-2"></iconify-icon>
                                            {{ name }}
                                        </div>
                                    </template>
                                </v-select>
                            </div>

                            <!-- Community Selection -->
                            <div class="mb-4" v-if="selectedArea">
                                <label class="form-label">Community</label>
                                <v-select
                                    v-model="selectedCommunity"
                                    :options="communities"
                                    label="name"
                                    :reduce="(area) => area"
                                    placeholder="Select community (optional)"
                                    :class="{'is-invalid': errors.community}"
                                    :disabled="loading || loadingCommunities "
                                    @update:modelValue="onCommunityChange"
                                >
                                    <template #option="{ name }">
                                        <div>
                                            <iconify-icon icon="lucide:users" class="text-warning me-2"></iconify-icon>
                                            {{ name }}
                                        </div>
                                    </template>
                                </v-select>
                            </div>

                            <!-- Sub Community Selection -->
                            <div class="mb-4" v-if="selectedCommunity">
                                <label class="form-label">Sub Community</label>
                                <v-select
                                    v-model="selectedSubCommunity"
                                    :options="subCommunities"
                                    label="name"
                                    :reduce="(area) => area"
                                    placeholder="Select sub community (optional)"
                                    :class="{'is-invalid': errors.subCommunity}"
                                    :disabled="loading || loadingSubCommunities "
                                    @update:modelValue="onSubCommunityChange"
                                >
                                    <template #option="{ name }">
                                        <div>
                                            <iconify-icon icon="lucide:user-plus" class="text-secondary me-2"></iconify-icon>
                                            {{ name }}
                                        </div>
                                    </template>
                                </v-select>
                            </div>

                            <div class="mb-4" v-if="shouldShowTypeSelection">
                                <label class="form-label">Type to Create <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <!-- Cluster option -->
                                    <div class="form-check" v-if="typeOptions.includes('cluster')">
                                        <input 
                                            type="radio" 
                                            class="form-check-input" 
                                            id="typeCluster"
                                            value="cluster"
                                            v-model="selectedType"
                                        >
                                        <label class="form-check-label" for="typeCluster">
                                            <iconify-icon icon="lucide:group" class="text-dark me-1"></iconify-icon>
                                            Cluster
                                        </label>
                                    </div>
                                    
                                    <!-- Building option -->
                                    <div class="form-check" v-if="typeOptions.includes('building')">
                                        <input 
                                            type="radio" 
                                            class="form-check-input" 
                                            id="typeBuilding"
                                            value="building"
                                            v-model="selectedType"
                                        >
                                        <label class="form-check-label" for="typeBuilding">
                                            <iconify-icon icon="lucide:home" class="text-danger me-1"></iconify-icon>
                                            Building
                                        </label>
                                    </div>
                                    
                                    <!-- Phase option -->
                                    <div class="form-check" v-if="typeOptions.includes('phaces')">
                                        <input 
                                            type="radio" 
                                            class="form-check-input" 
                                            id="typePhase"
                                            value="phaces"
                                            v-model="selectedType"
                                        >
                                        <label class="form-check-label" for="typePhase">
                                            <iconify-icon icon="lucide:layers" class="text-purple me-1"></iconify-icon>
                                            Phase
                                        </label>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    {{ getTypeSelectionDescription }}
                                </small>
                            </div>

                            <!-- Name Field -->
                            <div class="mb-4">
                                <div class="form-label name-label-row d-flex align-items-center flex-wrap gap-3">
                                 <div>{{!isEditMode?'Add New':'update' }} {{ getFinalLevelLabel }} <span class="text-danger">*</span></div>
                                    <div class="form-check" v-if="!isEditMode && shouldShowProjectCheckbox">
                                        <input 
                                            type="checkbox" 
                                            class="form-check-input" 
                                            id="createAsProject"
                                            v-model="createAsProject"
                                        >
                                        <label class="form-check-label" for="createAsProject">
                                            <strong>Create as Project also</strong>
                                            <!--<br>-->
                                            <!--<small class="text-muted">-->
                                            <!--    This will create a new project with the same name in the selected location-->
                                            <!--</small>-->
                                        </label>
                                    </div>
                                    <button
                                        v-if="!isEditMode"
                                        type="button"
                                        class="btn add-name-btn ms-auto"
                                        :disabled="loading"
                                        @click="addNameRow()">
                                        <iconify-icon icon="lucide:plus"></iconify-icon>
                                        Add another
                                    </button>
                                </div>
                                <template v-if="isEditMode">
                                    <input
                                        type="text"
                                        class="form-control"
                                        v-model="areaForm.name"
                                        :class="{'is-invalid': errors.name}"
                                        :placeholder="`Enter ${getFinalLevelLabel.toLowerCase()} name`"
                                        autofocus>
                                    <div class="invalid-feedback" v-if="errors.name">
                                        {{ errors.name[0] }}
                                    </div>
                                </template>
                                <!-- Create mode: several names under the same parent in one submit -->
                                <template v-else>
                                    <div v-for="(row, index) in newNames" :key="row.key" class="mb-2">
                                        <div class="d-flex gap-2">
                                            <input
                                                type="text"
                                                class="form-control"
                                                v-model="row.name"
                                                :ref="el => { if (el) nameInputs[index] = el }"
                                                :class="{'is-invalid': row.error}"
                                                :placeholder="`Enter ${getFinalLevelLabel.toLowerCase()} name`"
                                                :disabled="loading"
                                                @keydown.enter.prevent="addNameRow(index)"
                                                autofocus>
                                            <button
                                                v-if="newNames.length > 1"
                                                type="button"
                                                class="btn btn-outline-danger"
                                                title="Remove"
                                                :disabled="loading"
                                                @click="removeNameRow(index)">
                                                <iconify-icon icon="lucide:x"></iconify-icon>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback" v-if="row.error">
                                            {{ row.error }}
                                        </div>
                                    </div>
                                </template>
                                <small class="text-muted d-block mt-1">
                                    {{ getFinalLevelDescription }}
                                </small>
                            </div>



                            <!-- Hidden parent_id field -->
                            <input type="hidden" v-model="areaForm.parent_id">

                            <!-- Action Buttons -->
                            <div class="d-flex gap-2 justify-content-end border-top pt-4 mt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()" :disabled="loading">
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading || !isFormValid">
                                    <span v-if="loading">
                                        <iconify-icon icon="lucide:loader-2" class="me-2 spin"></iconify-icon>
                                        {{ isEditMode ? 'Updating...' : 'Creating...' }}
                                    </span>
                                    <span v-else>
                                        <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                        {{ isEditMode ? 'Update' : (filledNames.length > 1 ? `Create ${filledNames.length}` : 'Create') }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';

export default {
    name: 'AreaForm',
    components: {
        Breadcrumb,
        vSelect
    },
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-area');
        const areaId = computed(() => route.params.id);

        // Refs
        const errors = ref({});
        const loading = ref(false);
        const createAsProject = ref(false);
        
        // Loading states
        const loadingCities = ref(false);
        const loadingAreas = ref(false);
        const loadingCommunities = ref(false);
        const loadingSubCommunities = ref(false);
        const loadingClusters = ref(false);
        const loadingBuildings = ref(false);

        // Selected values
        const selectedCountry = ref(null);
        const selectedCity = ref(null);
        const selectedArea = ref(null);
        const selectedCommunity = ref(null);
        const selectedSubCommunity = ref(null);
        const selectedCluster = ref(null);
        const selectedBuilding = ref(null);
        const selectedType = ref('country');

        // Lists
        const countries = ref([]);
        const cities = ref([]);
        const areas = ref([]);
        const communities = ref([]);
        const subCommunities = ref([]);
        const clusters = ref([]);
        const buildings = ref([]);

        // Area Form Data
        const areaForm = ref({
            name: "",
            type: "",
            parent_id: null,
            parent_name: null
        });

        // Create mode: one row per name, all saved under the same parent
        let nameRowKey = 0;
        const makeNameRow = (name = '') => ({ key: ++nameRowKey, name, error: '' });
        const newNames = ref([makeNameRow()]);
        const nameInputs = ref([]);
        const filledNames = computed(() => newNames.value.filter(row => row.name?.trim()));

        const addNameRow = (afterIndex = null) => {
            const index = afterIndex === null ? newNames.value.length : afterIndex + 1;
            newNames.value.splice(index, 0, makeNameRow());
            nextTick(() => nameInputs.value[index]?.focus());
        };

        const removeNameRow = (index) => {
            newNames.value.splice(index, 1);
            nameInputs.value = [];
        };

        // UAE ID (assuming it's 1, you might need to adjust this)
        const UAE_ID = 1;

        // Type hierarchy levels
        const typeLevels = {
            'country': 1,
            'city': 2,
            'area': 3,
            'community': 4,
            'sub_community': 5,
            'cluster': 6,
            'building': 7,
            'phaces': 8
        };

        // Computed: Current area type level
        const areaTypeLevel = computed(() => {
            return typeLevels[areaForm.value.type] || 0;
        });

        // Computed: Can edit country?
        const canEditCountry = computed(() => {
            return areaTypeLevel.value <= 1;
        });

        const canEditCity = computed(() => {
            return areaTypeLevel.value <= 2;
        });

        const canEditArea = computed(() => {
            return areaTypeLevel.value <= 3;
        });

        const canEditCommunity = computed(() => {
            return areaTypeLevel.value <= 4;
        });

        const canEditSubCommunity = computed(() => {
            return areaTypeLevel.value <= 5;
        });

        const canEditCluster = computed(() => {
            return areaTypeLevel.value <= 6;
        });

        const canEditBuilding = computed(() => {
            return areaTypeLevel.value <= 7;
        });

        const shouldShowTypeSelection = computed(() => {
            if (isEditMode.value) {
                return false;
            }
            
            if (selectedSubCommunity.value && !selectedCluster.value && !selectedBuilding.value) {
                return true; 
            }
            if (selectedCluster.value && !selectedBuilding.value) {
                return true; 
            }
            if (selectedBuilding.value) {
                return true; 
            }
            
            return false;
        });

        const typeOptions = computed(() => {
            const options = [];
            
            if (!isEditMode.value) {
                if (selectedSubCommunity.value && !selectedCluster.value && !selectedBuilding.value) {
                    options.push('cluster', 'building', 'phaces');
                } else if (selectedCluster.value && !selectedBuilding.value) {
                    options.push('building', 'phaces');
                } else if (selectedBuilding.value) {
                    options.push('phaces');
                }
            }
            
            return options;
        });

        const getTypeSelectionDescription = computed(() => {
            if (selectedSubCommunity.value && !selectedCluster.value && !selectedBuilding.value) {
                return 'Select what you want to create under this Sub Community';
            }
            if (selectedCluster.value && !selectedBuilding.value) {
                return 'Select what you want to create under this Cluster';
            }
            if (selectedBuilding.value) {
                return 'You can only create Phase under a Building';
            }
            return '';
        });

        // Computed: Should show project checkbox
        const shouldShowProjectCheckbox = computed(() => {
            // Don't show in edit mode
            if (isEditMode.value) return false;
            
            // Must have country and city selected
            // if (!selectedCountry.value || !selectedCity.value) return false;
            
            // Show for specific levels based on selections
            
            // Creating Area (when city is selected and no area)
            if (selectedCity.value && !selectedArea.value && !selectedCommunity.value && !selectedSubCommunity.value) {
                return true;
            }
            
            // Creating Community (when area is selected)
            if (selectedArea.value && !selectedCommunity.value && !selectedSubCommunity.value) {
                return true;
            }
            
            // Creating Sub Community (when community is selected)
            if (selectedCommunity.value && !selectedSubCommunity.value) {
                return true;
            }
            
            // Creating Cluster (when sub community is selected AND type is cluster)
            // if (selectedSubCommunity.value && selectedType.value === 'cluster' && !selectedCluster.value) {
            //     return true;
            // }
            
            return false;
        });

        // Computed: Get final level label
        // Computed: Get final level label
        const getFinalLevelLabel = computed(() => {
            if (isEditMode.value && areaForm.value.type) {
                return formatType(areaForm.value.type);
            }
            
            if (!isEditMode.value) {
                if (shouldShowTypeSelection.value && selectedType.value) {
                    return formatType(selectedType.value);
                }
                
                if (selectedBuilding.value) {
                    return 'Phase';
                }
                if (selectedCluster.value) {
                    return 'Building';
                }
                if (selectedSubCommunity.value) {
                    return 'Cluster';
                }
                if (selectedCommunity.value) {
                    return 'Sub Community';
                }
                if (selectedArea.value) {
                    return 'Community';
                }
                if (selectedCity.value) {
                    return 'Area';
                }
                if (selectedCountry.value) {
                    return 'City';
                }
            }
            
            return 'Country';
        });

        const getFinalLevelDescription = computed(() => {
            if (isEditMode.value) {
                return `Update the ${getFinalLevelLabel.value.toLowerCase()} name`;
            }
            return `Enter the ${getFinalLevelLabel.value.toLowerCase()} name you want to create`;
        });

        // Computed: Determine parent_id for submission
        const determineParentId = computed(() => {
            // In edit mode, preserve original parent_id unless changed
            if (isEditMode.value) {
                // Check if user changed any selection by comparing with original areaForm.parent_id
                const currentParent = getCurrentSelectedParent();
                
                // If current selection is different from original, use new parent
                if (currentParent && currentParent.id !== areaForm.value.parent_id) {
                    return currentParent.id;
                }
                
                // No changes, keep original
                return areaForm.value.parent_id;
            }
            
            // Create mode - determine from selections
            return getCurrentSelectedParent()?.id || null;
        });
        
        // Helper function to get current selected parent
        const getCurrentSelectedParent = () => {
            if (selectedBuilding.value) return selectedBuilding.value;
            if (selectedCluster.value) return selectedCluster.value;
            if (selectedSubCommunity.value) return selectedSubCommunity.value;
            if (selectedCommunity.value) return selectedCommunity.value;
            if (selectedArea.value) return selectedArea.value;
            if (selectedCity.value) return selectedCity.value;
            if (selectedCountry.value) return selectedCountry.value;
            // return default country id
            return 1;
        };

        // Computed: Form validity
        const isFormValid = computed(() => {
            if (isEditMode.value) return !!areaForm.value.name?.trim();
            if (!filledNames.value.length) return false;
            if (shouldShowTypeSelection.value && !selectedType.value) return false;
            return true;
        });

        // Determine type based on selections
        const determineType = computed(() => {
            if (isEditMode.value) {
                return areaForm.value.type;
            }
            
            if (shouldShowTypeSelection.value && selectedType.value) {
                return selectedType.value;
            }
            
            if (selectedBuilding.value) return 'phaces';
            if (selectedCluster.value) return 'building';
            if (selectedSubCommunity.value) return 'cluster';
            if (selectedCommunity.value) return 'sub_community';
            if (selectedArea.value) return 'community';
            if (selectedCity.value) return 'area';
            if (selectedCountry.value) return 'city';
    
            
            return 'country';
        });

        // Helper: Get icon for type
        const getTypeIcon = (type) => {
            switch(type) {
                case 'cluster': return 'lucide:group';
                case 'building': return 'lucide:home';
                case 'phaces': return 'lucide:layers';
                default: return 'lucide:map-pin';
            }
        };

        const getTypeClass = (type) => {
            switch(type) {
                case 'cluster': return 'text-dark';
                case 'building': return 'text-danger';
                case 'phaces': return 'text-purple';
                default: return 'text-info';
            }
        };

        // Helper: Format type string
        const formatType = (type) => {
            if (!type) return '';
            return type.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        };

       

        // Fetch countries
        const fetchCountries = async () => {
            try {
                const response = await api.get('/listings/areas', {
                    params: { type: 'country' }
                });
                countries.value = response.data.data || response.data || [];
                
               
                
            } catch (error) {
                console.error("Error fetching countries:", error);
                showNotification("Failed to load countries.", "error");
            }
        };
        const fetchCities = async () => {
            try {
                loadingCities.value = true;
                
                const response = await api.get('/listings/areas', {
                        params: { parent_id: 1 } 
                });
        
                cities.value = response.data.data || response.data || [];
                
                if (cities.value.length > 0 && !selectedCity.value) {
                    const selectedCity = cities.value.find(c => 
                        c.name.toLowerCase() === 'Abu Dhabi'
                     
                     
                    );
                    
                    if (selectedCity) {
                        selectedCity.value = selectedCity;
                        onCityChange(selectedCity);
                    }
                }
                
            } catch (error) {
                console.error("Error fetching cities:", error);
                showNotification("Failed to load cities.", "error");
            } finally {
                loadingCities.value = false;
            }
        };
      
        // Fetch children
        const fetchChildren = async (parentId, type) => {
            try {
                switch(type) {
                    case 'city': loadingCities.value = true; break;
                    case 'area': loadingAreas.value = true; break;
                    case 'community': loadingCommunities.value = true; break;
                    case 'sub_community': loadingSubCommunities.value = true; break;
                    case 'cluster': loadingClusters.value = true; break;
                    case 'building': loadingBuildings.value = true; break;
                }

                const response = await api.get('/listings/areas', {
                    params: { parent_id: parentId }
                });

                const children = response.data.data || response.data || [];

                switch(type) {
                    case 'city': cities.value = children; break;
                    case 'area': areas.value = children; break;
                    case 'community': communities.value = children; break;
                    case 'sub_community': subCommunities.value = children; break;
                    case 'cluster': clusters.value = children; break;
                    case 'building': buildings.value = children; break;
                }
            } catch (error) {
                console.error(`Error fetching ${type}s:`, error);
                showNotification(`Failed to load ${type}s.`, "error");
            } finally {
                switch(type) {
                    case 'city': loadingCities.value = false; break;
                    case 'area': loadingAreas.value = false; break;
                    case 'community': loadingCommunities.value = false; break;
                    case 'sub_community': loadingSubCommunities.value = false; break;
                    case 'cluster': loadingClusters.value = false; break;
                    case 'building': loadingBuildings.value = false; break;
                }
            }
        };

        // Event handlers
        const onCountryChange = async (country) => {
            selectedCountry.value = country;
            selectedCity.value = null;
            selectedArea.value = null;
            selectedCommunity.value = null;
            selectedSubCommunity.value = null;
            selectedCluster.value = null;
            selectedBuilding.value = null;
            selectedType.value = 'city';
            cities.value = [];
            areas.value = [];
            communities.value = [];
            subCommunities.value = [];
            clusters.value = [];
            buildings.value = [];
            
            if (country) {
                await fetchChildren(country.id, 'city');
            }
        };

        const onCityChange = async (city) => {
                selectedCity.value = city;
                selectedArea.value = null;
                selectedCommunity.value = null;
                selectedSubCommunity.value = null;
                selectedCluster.value = null;
                selectedBuilding.value = null;
                selectedType.value = 'area';
                areas.value = [];
                communities.value = [];
                subCommunities.value = [];
                clusters.value = [];
                buildings.value = [];
                
                if (city) {
                    await fetchChildren(city.id, 'area');
                }
            };

        const onAreaChange = async (area) => {
            selectedArea.value = area;
            selectedCommunity.value = null;
            selectedSubCommunity.value = null;
            selectedCluster.value = null;
            selectedBuilding.value = null;
            selectedType.value = 'community';
            communities.value = [];
            subCommunities.value = [];
            clusters.value = [];
            buildings.value = [];
            
            if (area) {
                await fetchChildren(area.id, 'community');
            }
        };

        const onCommunityChange = async (community) => {
            selectedCommunity.value = community;
            selectedSubCommunity.value = null;
            selectedCluster.value = null;
            selectedBuilding.value = null;
            selectedType.value = 'sub_community';
            subCommunities.value = [];
            clusters.value = [];
            buildings.value = [];
            
            if (community) {
                await fetchChildren(community.id, 'sub_community');
            }
        };

        const onSubCommunityChange = async (subCommunity) => {
            selectedSubCommunity.value = subCommunity;
            selectedCluster.value = null;
            selectedBuilding.value = null;
            selectedType.value = 'cluster';
            clusters.value = [];
            buildings.value = [];
            
            if (subCommunity) {
                await fetchChildren(subCommunity.id, 'cluster');
            }
        };

        const onClusterChange = async (cluster) => {
            selectedCluster.value = cluster;
            selectedBuilding.value = null;
            selectedType.value = 'building';
            buildings.value = [];
            
            if (cluster) {
                await fetchChildren(cluster.id, 'building');
            }
        };

        const onBuildingChange = (building) => {
            selectedBuilding.value = building;
            selectedType.value = 'phaces';
        };

        // تحديث selectedType تلقائياً عندما يتغير المستوى
        watch([selectedSubCommunity, selectedCluster, selectedBuilding], () => {
            if (!isEditMode.value) {
                if (selectedSubCommunity.value && !selectedCluster.value && !selectedBuilding.value) {
                    if (!selectedType.value || !['cluster', 'building', 'phaces'].includes(selectedType.value)) {
                        selectedType.value = 'cluster';
                    }
                } else if (selectedCluster.value && !selectedBuilding.value) {
                    if (!selectedType.value || !['building', 'phaces'].includes(selectedType.value)) {
                        selectedType.value = 'building';
                    }
                } else if (selectedBuilding.value) {
                    selectedType.value = 'phaces';
                }
            }
        });

        // Fetch area for editing
        const fetchArea = async () => {
            try {
                loading.value = true;
                
                const response = await api.get(`/listings/areas/${areaId.value}`);
                const areaData = response.data.data || response.data;
        
                if (!areaData) {
                    throw new Error('Area not found');
                }
        
                // Populate form
                areaForm.value = {
                    name: areaData.name || "",
                    type: areaData.type || "",
                    parent_id: areaData.parent_id || null,
                    parent_name: areaData.parent_name || null
                };
        
                // Set selectedType based on area type
                selectedType.value = areaData.type;
        
                // Load hierarchy based on parent_id
                if (areaData.parent_id) {
                    await loadParentHierarchy(areaData.parent_id, areaData.type);
                } else {
                    await fetchCountries();
                }
        
            } catch (error) {
                console.error("Error fetching area:", error);
                showNotification("Failed to load area.", "error");
                router.push('/areas');
            } finally {
                loading.value = false;
            }
        };
        
        // Load parent hierarchy
        const loadParentHierarchy = async (parentId, currentType = null) => {
            try {
                const response = await api.get(`/listings/areas/${parentId}`);
                const parent = response.data.data || response.data;
                
                if (!parent) return;
        
                if (parent.parent_id) {
                    await loadParentHierarchy(parent.parent_id);
                }
        
                switch(parent.type) {
                    case 'country':
                        selectedCountry.value = parent;
                        await fetchChildren(parent.id, 'city');
                        break;
                    case 'city':
                        selectedCity.value = parent;
                        await fetchChildren(parent.id, 'area');
                        break;
                    case 'area':
                        selectedArea.value = parent;
                        await fetchChildren(parent.id, 'community');
                        break;
                    case 'community':
                        selectedCommunity.value = parent;
                        await fetchChildren(parent.id, 'sub_community');
                        break;
                    case 'sub_community':
                        selectedSubCommunity.value = parent;
                        await fetchChildren(parent.id, 'cluster');
                        break;
                    case 'cluster':
                        selectedCluster.value = parent;
                        await fetchChildren(parent.id, 'building');
                        break;
                    case 'building':
                        selectedBuilding.value = parent;
                        break;
                }
        
                if (currentType === 'phaces' && parent.type === 'building') {
                    selectedBuilding.value = parent;
                }
            } catch (error) {
                console.error('Error loading parent hierarchy:', error);
            }
        };

        // Submit form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                if (!isEditMode.value) {
                    areaForm.value.type = determineType.value;
                    await createAreas();
                    return;
                }

                if (!areaForm.value.name?.trim()) {
                    showNotification("Please enter area name", "error");
                    loading.value = false;
                    return;
                }

                const submitData = {
                    name: areaForm.value.name.trim(),
                    type: areaForm.value.type,
                    parent_id: determineParentId.value,
                };

                // Only add create_project if it's true
                if (createAsProject.value) {
                    submitData.create_project = true;
                }

                if (submitData.type === 'country') {
                    submitData.parent_id = null;
                }

                let response;
                
                if (isEditMode.value) {
                    response = await api.put(`/listings/areas/${areaId.value}`, submitData);
                } else {
                    response = await api.post("/listings/areas", submitData);
                }

                let successMessage = isEditMode.value ? "Area updated successfully!" : "Area created successfully!";
                
                if (!isEditMode.value && createAsProject.value) {
                    successMessage = "Area and project created successfully! You can edit the project later from Projects section.";
                }
                
                showNotification(successMessage, "success");

                setTimeout(() => {
                    router.push('/areas');
                }, 1000);

            } catch (error) {
                console.error("Error saving area:", error);
                
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("Please check the form for errors.", "error");
                } else {
                    showNotification("Failed to save area.", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Create mode: save every filled name under the same parent/type. Rows that fail
        // stay in the form with their error; the rest are removed.
        const createAreas = async () => {
            const rows = filledNames.value;
            if (!rows.length) {
                showNotification("Please enter area name", "error");
                return;
            }

            const type = areaForm.value.type;
            const parentId = type === 'country' ? null : determineParentId.value;
            const failed = [];

            for (const row of rows) {
                row.error = '';
                const submitData = { name: row.name.trim(), type, parent_id: parentId };
                if (createAsProject.value) {
                    submitData.create_project = true;
                }
                try {
                    await api.post("/listings/areas", submitData);
                } catch (error) {
                    console.error(`Error saving area "${submitData.name}":`, error);
                    const fieldErrors = error.response?.data?.errors;
                    row.error = fieldErrors?.name?.[0]
                        || (fieldErrors && Object.values(fieldErrors)[0]?.[0])
                        || error.response?.data?.message
                        || 'Failed to save.';
                    failed.push(row);
                }
            }

            const createdCount = rows.length - failed.length;
            const label = formatType(type);

            if (!failed.length) {
                let successMessage = createdCount > 1
                    ? `${createdCount} ${label} items created successfully!`
                    : "Area created successfully!";
                if (createAsProject.value) {
                    successMessage += " Projects were created too; you can edit them from the Projects section.";
                }
                showNotification(successMessage, "success");
                setTimeout(() => {
                    router.push('/areas');
                }, 1000);
                return;
            }

            newNames.value = failed;
            nameInputs.value = [];
            showNotification(
                createdCount
                    ? `${createdCount} created, ${failed.length} failed. Fix the highlighted names and try again.`
                    : "Please check the form for errors.",
                "error"
            );
        };

        // Helper: Show notification
        const showNotification = (message, type = 'info') => {
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
            }
        };

       

        // Initialize
        onMounted(async () => {
            try {
                loading.value = true;
                await fetchCountries();
                if (isEditMode.value) {
                    await fetchArea();
                }
            } catch (error) {
                console.error('Error initializing component:', error);
            } finally {
                loading.value = false;
            }
        });

        return {
            isEditMode,
            loading,
            loadingCities,
            loadingAreas,
            loadingCommunities,
            loadingSubCommunities,
            loadingClusters,
            loadingBuildings,
            areaForm,
            newNames,
            nameInputs,
            filledNames,
            addNameRow,
            removeNameRow,
            errors,
            countries,
            cities,
            areas,
            communities,
            subCommunities,
            clusters,
            buildings,
            selectedCountry,
            selectedCity,
            selectedArea,
            selectedCommunity,
            selectedSubCommunity,
            selectedCluster,
            selectedBuilding,
            selectedType,
            areaTypeLevel,
            canEditCountry,
            canEditCity,
            canEditArea,
            canEditCommunity,
            canEditSubCommunity,
            canEditCluster,
            canEditBuilding,
            shouldShowTypeSelection,
            typeOptions,
            getTypeSelectionDescription,
            shouldShowProjectCheckbox,
            getFinalLevelLabel,
            getFinalLevelDescription,
            isFormValid,
            createAsProject,
            formatType,
            getTypeIcon,
            getTypeClass,
            onCountryChange,
            onCityChange,
            onAreaChange,
            onCommunityChange,
            onSubCommunityChange,
            onClusterChange,
            onBuildingChange,
            submitForm,
            getCurrentSelectedParent,
        };
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.invalid-feedback {
    display: block;
}

.v-select {
    --vs-border-radius: 6px;
}

.is-invalid {
    border-color: #dc3545;
}

.border-top {
    border-top: 1px solid #dee2e6 !important;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.text-muted {
    font-size: 0.875rem;
}

.text-purple {
    color: #6f42c1;
}

.form-check-input {
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
}

.alert-info {
    background-color: #cff4fc;
    border-color: #b6effb;
    color: #055160;
    border-radius: 0.375rem;
}

/* ============ Theme ============ */
.area-form {
    border: 1px solid var(--color-primary-border);
    border-radius: 14px;
    overflow: visible;
}

.area-form .card-header {
    background: linear-gradient(90deg, var(--color-primary-soft) 0%, #ffffff 72%);
    border-bottom: 1px solid var(--color-primary-border);
    border-radius: 14px 14px 0 0;
    padding: 14px 20px;
}

.area-form .card-title {
    color: var(--color-primary-ink);
    font-weight: 700;
}

.area-form .form-label {
    color: var(--color-primary-ink);
    font-weight: 600;
    font-size: 0.875rem;
}

.area-form .form-control {
    min-height: 42px;
    border-radius: 8px;
    border-color: var(--color-primary-border);
}

.area-form .form-control:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-ring);
}

.area-form .form-control.is-invalid {
    border-color: #dc3545;
}

/* v-select */
.area-form .v-select {
    --vs-border-radius: 8px;
    --vs-border-color: var(--color-primary-border);
    --vs-dropdown-option--active-bg: var(--color-primary);
    --vs-dropdown-option--active-color: #ffffff;
    --vs-selected-color: var(--color-primary-ink);
    --vs-actions-padding: 4px 8px 0 4px;
}

.area-form :deep(.vs__dropdown-toggle) {
    min-height: 42px;
    background: #ffffff;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.area-form :deep(.vs--open .vs__dropdown-toggle) {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-ring);
}

.area-form :deep(.vs__open-indicator),
.area-form :deep(.vs__clear) {
    fill: var(--color-primary);
}

.area-form :deep(.vs__dropdown-menu) {
    margin-top: 4px;
    padding: 6px;
    border: 1px solid var(--color-primary-border);
    border-radius: 10px;
    box-shadow: 0 10px 28px rgba(var(--color-primary-rgb), 0.16);
}

.area-form :deep(.vs__dropdown-option) {
    padding: 8px 10px;
    border-radius: 6px;
    color: var(--color-primary-ink);
}

/* icon + text on one row (the Iconify script swaps <iconify-icon> for an <svg>) */
.area-form :deep(.vs__dropdown-option > div) {
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.3;
}

.area-form :deep(.vs__dropdown-option > div > iconify-icon),
.area-form :deep(.vs__dropdown-option > div > svg) {
    display: inline-flex;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    padding: 6px;
    margin: 0 !important;
    border-radius: 8px;
    background: var(--color-primary-soft);
    color: var(--color-primary) !important;
    box-sizing: border-box;
}

.area-form :deep(.vs__dropdown-option--highlight > div > iconify-icon),
.area-form :deep(.vs__dropdown-option--highlight > div > svg) {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff !important;
}

.area-form :deep(.vs__dropdown-option--selected) {
    background: var(--color-primary-soft);
    font-weight: 600;
}

.area-form :deep(.vs--disabled .vs__dropdown-toggle) {
    background: var(--color-primary-soft);
}

/* radios / checkbox */
.area-form .form-check-input:checked {
    background-color: var(--color-primary);
    border-color: var(--color-primary);
}

.area-form .form-check-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-ring);
}

.area-form .form-check-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.area-form .form-check-label :deep(iconify-icon),
.area-form .form-check-label :deep(svg) {
    display: inline-flex;
    width: 16px;
    height: 16px;
    margin: 0 !important;
    color: var(--color-primary) !important;
}

/* buttons: icon and label on one row */
.area-form .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.area-form .btn > span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.area-form .btn :deep(iconify-icon),
.area-form .btn :deep(svg) {
    display: inline-flex;
    width: 16px;
    height: 16px;
    margin: 0 !important;
}

/* name field header: title · project checkbox · Add another */
.name-label-row {
    margin-bottom: 10px;
}

.name-label-row .form-check {
    margin: 0;
}

.area-form .add-name-btn {
    padding: 5px 12px;
    border-radius: 999px;
    border: 1px dashed var(--color-primary);
    background: var(--color-primary-soft);
    color: var(--color-primary);
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.2;
}

.area-form .add-name-btn:hover:not(:disabled) {
    background: var(--color-primary);
    border-style: solid;
    color: #ffffff;
}

/* remove-name button */
.area-form .btn-outline-danger {
    flex: 0 0 42px;
    width: 42px;
    padding: 0;
    border-radius: 8px;
    border-color: var(--color-primary-border);
    color: #b4a3bd;
    background: #ffffff;
}

.area-form .btn-outline-danger:hover:not(:disabled) {
    border-color: #dc3545;
    background: #fdecee;
    color: #dc3545;
}

/* current path (edit mode) */
.area-path {
    background: var(--color-primary-soft);
    border-color: var(--color-primary-border) !important;
}

.area-path .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff !important;
    color: var(--color-primary) !important;
    border: 1px solid var(--color-primary-border);
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1;
    padding: 7px 12px;
}

.area-path .badge :deep(iconify-icon),
.area-path .badge :deep(svg) {
    display: inline-flex;
    width: 14px;
    height: 14px;
    margin: 0 !important;
    color: currentColor !important;
}

/* chevrons between chips (badge icons above override the colour) */
.area-path > div :deep(iconify-icon),
.area-path > div :deep(svg) {
    display: inline-flex;
    width: 14px;
    height: 14px;
    color: var(--color-primary-muted) !important;
}

.area-path .badge:last-child {
    background: var(--gradient-primary) !important;
    color: #ffffff !important;
    border-color: transparent;
}

/* buttons */
.area-form .btn-primary {
    background: var(--gradient-primary) !important;
    border-color: transparent !important;
    color: #ffffff !important;
}

.area-form .btn-primary:disabled {
    opacity: 0.55;
}

.area-form .btn-outline-primary {
    color: var(--color-primary);
    border-color: var(--color-primary);
    background: #ffffff;
}

.area-form .btn-outline-primary:hover:not(:disabled) {
    background: var(--color-primary);
    color: #ffffff;
}
</style>