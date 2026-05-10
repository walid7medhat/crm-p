<template>
    <div class="dashboard-main-body">
        <Breadcrumb 
            :title="isEditMode ? 'Edit Owner' : 'Add New Owner'" 
            :breadcrumbs="[
                { name: 'Owners', path: '/owners' },
                { name: isEditMode ? 'Edit' : 'Add New' }
            ]" 
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ isEditMode ? 'Edit Owner' : 'Add New Owner' }}</h5>
                <button class="btn btn-outline-secondary" @click="$router.back()">
                    <iconify-icon icon="lucide:arrow-left" class="me-2"></iconify-icon>
                    Back
                </button>
            </div>
            
            <div class="card-body">
                <form @submit.prevent="submitForm">
                    <div class="row">
                        <!-- Form Fields -->
                        <div class="col-md-12">
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Salutation <span class="text-danger">*</span></label>
                                        <select v-model="ownerForm.salutation" class="form-select" 
                                                :class="{'is-invalid': errors.salutation}">
                                            <option value="">Select...</option>
                                            <option>Mr</option>
                                            <option>Mrs</option>
                                            <option>Ms</option>
                                            <option>Dr</option>
                                        </select>
                                        <div class="invalid-feedback" v-if="errors.salutation">
                                            {{ errors.salutation[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- First Name -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text"
                                               class="form-control"
                                               v-model="ownerForm.first_name"
                                               @input="onlyLetters('first_name')"
                                               :class="{'is-invalid': errors.first_name}"
                                               placeholder="Enter first name">
                                        <div class="invalid-feedback" v-if="errors.first_name">
                                            {{ errors.first_name[0] }}
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Last Name -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text"
                                               class="form-control"
                                               v-model="ownerForm.last_name"
                                               @input="onlyLetters('last_name')"
                                               :class="{'is-invalid': errors.last_name}"
                                               placeholder="Enter last name">
                                        <div class="invalid-feedback" v-if="errors.last_name">
                                            {{ errors.last_name[0] }}
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Email -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email </label>
                                        <input type="email"
                                               class="form-control"
                                               v-model="ownerForm.email"
                                               :class="{'is-invalid': errors.email}"
                                               placeholder="Enter email address">
                                        <div class="invalid-feedback" v-if="errors.email">
                                            {{ errors.email[0] }}
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Phone -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                                        <input type="text"
                                               class="form-control"
                                               v-model="ownerForm.phone_number"
                                               @input="onlyNumbers('phone_number')"
                                               :class="{'is-invalid': errors.phone_number}"
                                               placeholder="Enter phone number">
                                        <div class="invalid-feedback" v-if="errors.phone_number">
                                            {{ errors.phone_number[0] }}
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Whatsapp Number -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Whatsapp Number</label>
                                        <input type="text"
                                               class="form-control"
                                               v-model="ownerForm.whatsapp_number"
                                               @input="onlyNumbers('whatsapp_number')"
                                               :class="{'is-invalid': errors.whatsapp_number}"
                                               placeholder="Enter whatsapp number">
                                        <div class="invalid-feedback" v-if="errors.whatsapp_number">
                                            {{ errors.whatsapp_number[0] }}
                                        </div>
                                    </div>
                                </div>
                            
                                <!-- Second Phone -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Second Phone</label>
                                        <input type="text"
                                               class="form-control"
                                               v-model="ownerForm.second_phone_number"
                                               @input="onlyNumbers('second_phone_number')"
                                               :class="{'is-invalid': errors.second_phone_number}"
                                               placeholder="Enter second phone number">
                                        <div class="invalid-feedback" v-if="errors.second_phone_number">
                                            {{ errors.second_phone_number[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Nationality & Residency -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nationality</label>
                                        <v-select 
                                            v-model="ownerForm.nationality" 
                                            :options="nationalities" 
                                            placeholder="Select nationality"
                                            :class="{'is-invalid': errors.nationality}"
                                            @update:modelValue="handleNationalityChange"
                                        />
                                        <div class="invalid-feedback" v-if="errors.nationality">
                                            {{ errors.nationality[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Residency Status</label>
                                        <select 
                                            v-model="ownerForm.residency_status" 
                                            class="form-select" 
                                            :class="{'is-invalid': errors.residency_status}"
                                            :disabled="isUaeNationality(ownerForm.nationality)"
                                        >
                                            <option value="">Select...</option>
                                            <option value="resident">Resident</option>
                                            <option value="non_resident">Non Resident</option>
                                        </select>
                                        <div class="invalid-feedback" v-if="errors.residency_status">
                                            {{ errors.residency_status[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">{{ getLocationLabel() }} </label>
                                        <v-select
                                            v-model="ownerForm.location_id"
                                            :options="locations"
                                            label="name"
                                            :reduce="(loc) => loc.id"
                                            :placeholder="getLocationPlaceholder()"
                                            :disabled="!ownerForm.residency_status || locations.length === 0"
                                            :class="{'is-invalid': errors.location_id}"
                                        />
                                        <div class="invalid-feedback" v-if="errors.location_id">
                                            {{ errors.location_id[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Documents Section -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <h6 class="mb-3">Documents</h6>
                                    <div class="alert alert-info">
                                        <small>
                                            <iconify-icon icon="lucide:info" class="me-1"></iconify-icon>
                                            In edit mode, existing documents will be kept unless you upload new files.
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-3">
                                        <label class="form-label">ID Front</label>
                                        <input type="file" class="form-control" 
                                               @change="handleFileUpload($event, 'id_front')" 
                                               accept="image/*,.pdf">
                                        <div v-if="ownerForm.id_front" class="mt-1">
                                            <small class="text-success">File selected: {{ ownerForm.id_front.name }}</small>
                                        </div>
                                        <div v-if="existingFiles.id_front" class="mt-1">
                                            <small class="text-info">Existing file: {{ existingFiles.id_front }}</small>
                                        </div>
                                        <div v-if="errors.id_front" class="text-danger small mt-1">
                                            {{ errors.id_front[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-3">
                                        <label class="form-label">ID Back</label>
                                        <input type="file" class="form-control" 
                                               @change="handleFileUpload($event, 'id_back')" 
                                               accept="image/*,.pdf">
                                        <div v-if="ownerForm.id_back" class="mt-1">
                                            <small class="text-success">File selected: {{ ownerForm.id_back.name }}</small>
                                        </div>
                                        <div v-if="existingFiles.id_back" class="mt-1">
                                            <small class="text-info">Existing file: {{ existingFiles.id_back }}</small>
                                        </div>
                                        <div v-if="errors.id_back" class="text-danger small mt-1">
                                            {{ errors.id_back[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-3">
                                        <label class="form-label">Visa Copy</label>
                                        <input type="file" class="form-control" 
                                               @change="handleFileUpload($event, 'visa_copy')" 
                                               accept="image/*,.pdf">
                                        <div v-if="ownerForm.visa_copy" class="mt-1">
                                            <small class="text-success">File selected: {{ ownerForm.visa_copy.name }}</small>
                                        </div>
                                        <div v-if="existingFiles.visa_copy" class="mt-1">
                                            <small class="text-info">Existing file: {{ existingFiles.visa_copy }}</small>
                                        </div>
                                        <div v-if="errors.visa_copy" class="text-danger small mt-1">
                                            {{ errors.visa_copy[0] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-3">
                                        <label class="form-label">Passport Copy</label>
                                        <input type="file" class="form-control" 
                                               @change="handleFileUpload($event, 'passport_copy')" 
                                               accept="image/*,.pdf">
                                        <div v-if="ownerForm.passport_copy" class="mt-1">
                                            <small class="text-success">File selected: {{ ownerForm.passport_copy.name }}</small>
                                        </div>
                                        <div v-if="passportOcrLoading" class="mt-1">
                                            <small class="text-primary">{{ ocrStatusMessage }}</small>
                                        </div>
                                        <div v-if="existingFiles.passport_copy" class="mt-1">
                                            <small class="text-info">Existing file: {{ existingFiles.passport_copy }}</small>
                                        </div>
                                        <div v-if="errors.passport_copy" class="text-danger small mt-1">
                                            {{ errors.passport_copy[0] }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Documents -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Additional Documents</label>
                                        <input type="file" class="form-control" multiple
                                               accept=".pdf,.jpg,.jpeg,.png,.svg"
                                               @change="handleAdditionalDocumentsUpload">
                                        <div v-if="existingAdditionalDocuments && existingAdditionalDocuments.length" class="mt-2">
                                            <small class="text-muted d-block mb-1">Existing ({{ existingAdditionalDocuments.length }})</small>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a v-for="doc in existingAdditionalDocuments" :key="doc.id"
                                                   :href="doc.url" target="_blank" rel="noopener" class="badge bg-secondary text-decoration-none me-1">{{ doc.name }}</a>
                                            </div>
                                        </div>
                                        <div v-if="ownerForm.additionalDocuments && ownerForm.additionalDocuments.length" class="mt-2">
                                            <small class="text-muted d-block mb-1">New uploads</small>
                                            <div v-for="(item, idx) in ownerForm.additionalDocuments" :key="'new-' + idx" class="d-flex align-items-center justify-content-between small mb-1">
                                                <span class="text-truncate">{{ item.name || item.file?.name }}</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeAdditionalDocument(idx)">Remove</button>
                                            </div>
                                        </div>
                                        <small class="text-muted">PDF, JPG, PNG, SVG. Max 10MB per file.</small>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea v-model="ownerForm.notes" rows="4" class="form-control" 
                                                  placeholder="Additional notes..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                                <button type="button" class="btn btn-outline-secondary" @click="$router.back()">
                                    <iconify-icon icon="lucide:x" class="me-2"></iconify-icon>
                                    Cancel
                                </button>
                              
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <iconify-icon icon="lucide:save" class="me-2"></iconify-icon>
                                    <span v-if="loading">{{ isEditMode ? 'Updating...' : 'Creating...' }}</span>
                                    <span v-else>{{ isEditMode ? 'Update Owner' : 'Create Owner' }}</span>
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
import { ref, onMounted, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "@/plugins/axios";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";
import Breadcrumb from '@/components/breadcrumb/Breadcrumb.vue';
import userPlaceholder from '@/assets/images/avatar/avatar1.png';
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;

export default {
    name: 'OwnerForm',
    components: {
        Breadcrumb,
        vSelect
    },
    methods: {
    onlyLetters(field) {
    this.ownerForm[field] =
        this.ownerForm[field].replace(/[^a-zA-Z\u0600-\u06FF\s]/g, '')
                             .replace(/[0-9\u0660-\u0669]/g, ''); 
},

    onlyNumbers(field) {
        this.ownerForm[field] =
            this.ownerForm[field].replace(/[^0-9]/g, '');
    },
     onlyLettersSearch(search, loading) {
            if (/\d/.test(search)) {
                loading(false);
                return false;
            }
        }
    
},
    setup() {
        const route = useRoute();
        const router = useRouter();

        const isEditMode = computed(() => route.name === 'edit-owner');
        const ownerId = computed(() => route.params.id);

        // Refs
        const avatarInput = ref(null);
        const errors = ref({});
        const loading = ref(false);
        const locations = ref([]);
        const avatarPreview = ref(null);
        const passportOcrLoading = ref(false);
        const ocrStatusMessage = ref('Reading document MRZ...');
        const existingFiles = ref({
            id_front: null,
            id_back: null,
            visa_copy: null,
            passport_copy: null
        });
        const existingAdditionalDocuments = ref([]);

        // Owner Form Data
        const ownerForm = ref({
            salutation: "",
            first_name: "",
            last_name: "",
            email: "",
            phone_number: "",
            whatsapp_number: "",
            second_phone_number: "",
            nationality: "",
            residency_status: "",
            location_id: "",
            avatar: null,
            id_front: null,
            id_back: null,
            visa_copy: null,
            passport_copy: null,
            notes: "",
            additionalDocuments: [],
        });

      const nationalities = ref([
        "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria",
        "Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan",
        "Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi",
        "Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia",
        "Comoros","Congo (Congo-Brazzaville)","Costa Rica","Croatia","Cuba","Cyprus","Czechia","Denmark",
        "Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea",
        "Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana",
        "Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland",
        "India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan",
        "Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya",
        "Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta",
        "Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia",
        "Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand",
        "Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau",
        "Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar",
        "Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines",
        "Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles",
        "Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea",
        "South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan",
        "Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey",
        "Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States",
        "Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
        ]);

        /** Must match the entry in nationalities exactly so v-select binds correctly. Legacy API values may say "UAE". */
        const UAE_NATIONALITY_LABEL = 'United Arab Emirates';
        const isUaeNationality = (val) => val === 'UAE' || val === UAE_NATIONALITY_LABEL;

        // Handle avatar upload
        const handleAvatarUpload = (event) => {
            const file = event.target.files[0];
            if (file) {
                // Check file size (2MB limit for avatar)
                if (file.size > 2 * 1024 * 1024) {
                    showNotification("❌ Avatar image must be less than 2MB", "error");
                    event.target.value = '';
                    return;
                }

                // Check file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showNotification("❌ Please upload a valid image (JPG, PNG, GIF)", "error");
                    event.target.value = '';
                    return;
                }

                ownerForm.value.avatar = file;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    avatarPreview.value = e.target.result;
                };
                reader.readAsDataURL(file);

                showNotification("✅ Avatar uploaded successfully", "success");
            }
        };

        // Remove avatar
        const removeAvatar = () => {
            ownerForm.value.avatar = null;
            avatarPreview.value = null;
            if (avatarInput.value) {
                avatarInput.value.value = '';
            }
            showNotification("🗑️ Avatar removed", "info");
        };

        // Handle image error
        const handleImageError = (event) => {
            console.log('Image error, using placeholder');
            event.target.src = userPlaceholder;
        };

        // Handle nationality change
        const handleNationalityChange = (newNationality) => {
            if (isUaeNationality(newNationality)) {
                ownerForm.value.residency_status = 'resident';
                fetchLocations('resident');
            } else {
                ownerForm.value.residency_status = "";
                ownerForm.value.location_id = "";
                locations.value = [];
            }
        };

        // Get location label and placeholder
        const getLocationLabel = () => {
            if (isUaeNationality(ownerForm.value.nationality)) {
                return 'City';
            } else if (ownerForm.value.residency_status === 'resident') {
                return 'Emirate';
            } else if (ownerForm.value.residency_status === 'non_resident') {
                return 'Country';
            }
            return 'Location';
        };

        const getLocationPlaceholder = () => {
            if (isUaeNationality(ownerForm.value.nationality)) {
                return 'Select City';
            } else if (ownerForm.value.residency_status === 'resident') {
                return 'Select Emirate';
            } else if (ownerForm.value.residency_status === 'non_resident') {
                return 'Select Country';
            }
            return 'Select location';
        };

        // Fetch locations from API
        const fetchLocations = async (residencyStatus) => {
            try {
                const response = await api.get(
                    `/listings/owners/locations/available?residency_status=${residencyStatus}`
                );
                locations.value = response.data.data || response.data;
            } catch (error) {
                console.error("❌ Error fetching locations:", error);
                showNotification("❌ Failed to load locations.", "error");
            }
        };

        const MAX_ADDITIONAL_SIZE = 10 * 1024 * 1024;
        const ALLOWED_ADDITIONAL_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];

        const handleAdditionalDocumentsUpload = (event) => {
            const files = event.target.files ? Array.from(event.target.files) : [];
            if (!files.length) return;
            const list = ownerForm.value.additionalDocuments || [];
            for (const file of files) {
                if (file.size > MAX_ADDITIONAL_SIZE) {
                    showNotification(`File "${file.name}" exceeds 10MB`, "error");
                    continue;
                }
                if (!ALLOWED_ADDITIONAL_TYPES.includes(file.type)) {
                    showNotification(`File "${file.name}" has invalid type. Use PDF, JPG, PNG, SVG.`, "error");
                    continue;
                }
                list.push({ file, name: file.name });
            }
            ownerForm.value.additionalDocuments = list;
            event.target.value = '';
        };

        const MRZ_NATIONALITY_MAP = {
            ARE: 'United Arab Emirates',
            EGY: 'Egypt',
            SAU: 'Saudi Arabia',
            IND: 'India',
            PAK: 'Pakistan',
            BGD: 'Bangladesh',
            GBR: 'United Kingdom',
            USA: 'United States',
            CAN: 'Canada',
            FRA: 'France',
            DEU: 'Germany',
            ITA: 'Italy',
            ESP: 'Spain',
            TUR: 'Turkey',
            IRN: 'Iran',
            IRQ: 'Iraq',
            JOR: 'Jordan',
            LBN: 'Lebanon',
            SYR: 'Syria',
            YEM: 'Yemen',
            MAR: 'Morocco',
            DZA: 'Algeria',
            TUN: 'Tunisia',
            SDN: 'Sudan',
            SOM: 'Somalia',
            ETH: 'Ethiopia',
            KEN: 'Kenya',
            RUS: 'Russia',
            UKR: 'Ukraine',
            CHN: 'China',
            JPN: 'Japan',
            KOR: 'South Korea',
            PHL: 'Philippines',
            NPL: 'Nepal',
            LKA: 'Sri Lanka',
        };

        const mapNationalityCode = (code) => {
            const normalized = String(code || '').toUpperCase().trim();
            return MRZ_NATIONALITY_MAP[normalized] || normalized;
        };

        const splitNameFirstAndRemaining = (rawFullName) => {
            const tokens = String(rawFullName || '')
                .replace(/[^a-z\s]/gi, ' ')
                .trim()
                .split(/\s+/)
                .filter(Boolean);
            if (!tokens.length) return { firstName: '', lastName: '' };
            return {
                firstName: tokens[0] || '',
                lastName: tokens.slice(1).join(' '),
            };
        };

        const cleanMrzLine = (line) =>
            String(line || '')
                .toUpperCase()
                .replace(/[^A-Z0-9<]/g, '');

        const extractMrzLines = (ocrText) => {
            const candidates = String(ocrText || '')
                .split(/\r?\n/)
                .map(cleanMrzLine)
                .filter((line) => line.length >= 28);

            // Passport TD3: 2 lines, usually length 44 and first starts with P<
            for (let i = 0; i < candidates.length - 1; i += 1) {
                const l1 = candidates[i];
                const l2 = candidates[i + 1];
                if (l1.startsWith('P<') && l2.length >= 40) {
                    return [l1.slice(0, 44), l2.slice(0, 44)];
                }
            }
            // Fallback: choose best two long lines and infer first line prefix.
            const longLines = [...candidates]
                .sort((a, b) => b.length - a.length)
                .slice(0, 2)
                .map((line) => line.slice(0, 44));
            if (longLines.length === 2) {
                if (!longLines[0].startsWith('P<')) longLines[0] = `P<${longLines[0].slice(2)}`;
                return longLines;
            }
            return null;
        };

        const parsePassportMrz = (line1, line2) => {
            const namesRaw = line1.slice(5);
            const [surnameRaw = '', givenRaw = ''] = namesRaw.split('<<');
            const surname = surnameRaw.replace(/</g, ' ').trim();
            const givenNames = givenRaw.replace(/</g, ' ').trim();
            const fullName = [givenNames, surname].filter(Boolean).join(' ').trim();
            const { firstName, lastName } = splitNameFirstAndRemaining(fullName);
            const nationalityCode = line2.slice(10, 13);
            const passportNumber = line2.slice(0, 9).replace(/</g, '').trim();

            return {
                firstName,
                lastName,
                nationality: mapNationalityCode(nationalityCode),
                nationalityCode,
                passportNumber,
            };
        };

        const loadImageElement = (file) =>
            new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    resolve(img);
                };
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('Failed to load image'));
                };
                img.src = url;
            });

        const buildCanvasFromFile = async (file, scale = 2) => {
            const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');

            if (isPdf) {
                const data = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data }).promise;
                const page = await pdf.getPage(1);
                const viewport = page.getViewport({ scale });
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                if (!context) throw new Error('Canvas not supported');
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);
                await page.render({ canvasContext: context, viewport }).promise;
                return canvas;
            }

            const image = await loadImageElement(file);
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            if (!ctx) throw new Error('Canvas not supported');
            canvas.width = Math.floor(image.width * scale);
            canvas.height = Math.floor(image.height * scale);
            ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
            return canvas;
        };

        const runPassportMrzOcr = async (file) => {
            const buildCropCanvas = (startRatio) => {
                const source = sourceCanvas;
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                if (!ctx) throw new Error('Canvas not supported');

                const cropY = Math.floor(source.height * startRatio);
                const cropHeight = source.height - cropY;
                canvas.width = source.width;
                canvas.height = cropHeight;
                ctx.imageSmoothingEnabled = true;
                ctx.drawImage(source, 0, cropY, source.width, cropHeight, 0, 0, canvas.width, canvas.height);

                // Contrast boost + threshold to isolate MRZ glyphs.
                const frame = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = frame.data;
                for (let i = 0; i < data.length; i += 4) {
                    const gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    const val = gray > 140 ? 255 : 0;
                    data[i] = val;
                    data[i + 1] = val;
                    data[i + 2] = val;
                }
                ctx.putImageData(frame, 0, 0);
                return canvas;
            };

            const module = await import('tesseract.js');
            const recognize = module.recognize || module.default?.recognize;
            if (!recognize) throw new Error('Tesseract recognize not available');
            const sourceCanvas = await buildCanvasFromFile(file, 2);

            const cropRatios = [0.58, 0.5, 0.65];
            for (const ratio of cropRatios) {
                const canvas = buildCropCanvas(ratio);
                const result = await recognize(canvas, 'eng', {
                    logger: () => {},
                    tessedit_pageseg_mode: 6,
                    tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                });
                const text = result?.data?.text || '';
                const mrzLines = extractMrzLines(text);
                if (mrzLines) return parsePassportMrz(mrzLines[0], mrzLines[1]);
            }

            throw new Error('MRZ not detected');
        };

        const runIdOcr = async (file) => {
            const canvas = await buildCanvasFromFile(file, 1.8);

            const module = await import('tesseract.js');
            const recognize = module.recognize || module.default?.recognize;
            if (!recognize) throw new Error('Tesseract recognize not available');

            let text = '';
            try {
                const result = await recognize(canvas, 'eng+ara', {
                    logger: () => {},
                    tessedit_pageseg_mode: 6,
                });
                text = String(result?.data?.text || '');
            } catch {
                const result = await recognize(canvas, 'eng', {
                    logger: () => {},
                    tessedit_pageseg_mode: 6,
                });
                text = String(result?.data?.text || '');
            }
            const lines = text
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter(Boolean);

            const findValue = (regexList) => {
                for (const line of lines) {
                    for (const regex of regexList) {
                        const match = line.match(regex);
                        if (match?.[1]) return match[1].trim();
                    }
                }
                return '';
            };

            const fullName = findValue([
                /name\s*[:\-]\s*([a-z\s]+)/i,
                /full\s*name\s*[:\-]\s*([a-z\s]+)/i,
                /name\s+([a-z\s]{4,})$/i,
                /holder\s*[:\-]\s*([a-z\s]+)/i,
            ]);
            const nationalityRaw = findValue([
                /nationality\s*[:\-]\s*(.+)/i,
                /country\s*[:\-]\s*(.+)/i,
            ]);
            const nationalityText = nationalityRaw.replace(/[^a-zA-Z\s]/g, ' ').trim();

            const normalizedForUae = `${text}\n${fullName}\n${nationalityText}`.toLowerCase();
            const lettersFold = String(text || '')
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '');

            const isEmiratesIdCard =
                /\b(eid|emirates\s*id|emirates\s*identity|united\s*arab\s*emirates|uaen|uae\s*id|federal\s*authority|icao|ica0)\b/i.test(text)
                || /\b(emirati|emiratee?i|emarati)\b/i.test(normalizedForUae)
                || lettersFold.includes('UNITEDARABEMIRATES')
                || lettersFold.includes('EMIRATES')
                || lettersFold.includes('EMRATE')
                || lettersFold.includes('FMERATS')
                || lettersFold.includes('IDENTITYCARD')
                || /784[-\s]?\d{4}\s?\d{7}\s?\d/i.test(text)
                || /784\d{12,14}/i.test(lettersFold);

            const { firstName, lastName } = splitNameFirstAndRemaining(fullName);

            let nationalityFinal = nationalityText;
            if (isEmiratesIdCard || /\b(emirati|emarati|united\s*arab\s*emirates|\buae\b)\b/i.test(nationalityText)) {
                nationalityFinal = UAE_NATIONALITY_LABEL;
            }

            return {
                firstName,
                lastName,
                nationality: nationalityFinal,
                isUAE: nationalityFinal === UAE_NATIONALITY_LABEL || isUaeNationality(nationalityFinal),
            };
        };

        const applyMrzToOwnerForm = async (mrz) => {
            if (mrz.firstName) ownerForm.value.first_name = mrz.firstName;
            if (mrz.lastName) ownerForm.value.last_name = mrz.lastName;
            if (mrz.nationality) {
                ownerForm.value.nationality = mrz.nationality;
                await handleNationalityChange(mrz.nationality);
            }
            showNotification('✅ Passport MRZ parsed and fields auto-filled', 'success');
        };

        const applyIdDataToOwnerForm = async (data) => {
            let changed = false;
            if (data.firstName) {
                ownerForm.value.first_name = data.firstName;
                changed = true;
            }
            if (data.lastName) {
                ownerForm.value.last_name = data.lastName;
                changed = true;
            }
            if (data.isUAE || isUaeNationality(data.nationality)) {
                ownerForm.value.nationality = UAE_NATIONALITY_LABEL;
                ownerForm.value.residency_status = 'resident';
                await handleNationalityChange(UAE_NATIONALITY_LABEL);
                changed = true;
            } else if (data.nationality) {
                ownerForm.value.nationality = data.nationality;
                await handleNationalityChange(data.nationality);
                changed = true;
            }
            if (changed) {
                showNotification('✅ ID OCR parsed and fields auto-filled', 'success');
            } else {
                throw new Error('No useful fields extracted from ID');
            }
        };

        const removeAdditionalDocument = (index) => {
            ownerForm.value.additionalDocuments.splice(index, 1);
        };

        // Handle file upload
        const handleFileUpload = async (event, field) => {
            const file = event.target.files[0];
            if (file) {
                // Check file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    showNotification("❌ File size must be less than 5MB", "error");
                    event.target.value = '';
                    return;
                }
                ownerForm.value[field] = file;
                showNotification(`✅ ${field.replace('_', ' ')} uploaded successfully`, "success");

                const isLikelyImage = file.type.startsWith('image/')
                    || /\.(png|jpe?g|webp|bmp)$/i.test(file.name || '');
                const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
                const canRunOcr = isLikelyImage || isPdf;

                if (field === 'passport_copy' && canRunOcr) {
                    try {
                        passportOcrLoading.value = true;
                        ocrStatusMessage.value = 'Reading passport MRZ...';
                        const mrz = await runPassportMrzOcr(file);
                        await applyMrzToOwnerForm(mrz);
                    } catch (error) {
                        console.error('Passport MRZ OCR failed:', error);
                        showNotification('⚠️ Could not read passport MRZ. Please fill fields manually.', 'warning');
                    } finally {
                        passportOcrLoading.value = false;
                    }
                }

                if ((field === 'id_front' || field === 'id_back') && canRunOcr) {
                    try {
                        passportOcrLoading.value = true;
                        ocrStatusMessage.value = 'Reading ID and extracting data...';
                        const idData = await runIdOcr(file);
                        await applyIdDataToOwnerForm(idData);
                    } catch (error) {
                        console.error('ID OCR failed:', error);
                        showNotification('⚠️ Could not read ID fields. Please fill manually.', 'warning');
                    } finally {
                        passportOcrLoading.value = false;
                    }
                }
            }
        };

        // Fetch owner data for editing
        const fetchOwner = async () => {
            try {
                loading.value = true;
                console.log('Fetching owner data for ID:', ownerId.value);
                
                const response = await api.get(`/listings/owners/${ownerId.value}`);
                console.log('Owner API response:', response);
                
                const ownerData = response.data.data || response.data;
                console.log('Owner data:', ownerData);

                // Populate form with existing data
                Object.keys(ownerForm.value).forEach(key => {
                    if (ownerData[key] !== undefined && ownerData[key] !== null) {
                        ownerForm.value[key] = ownerData[key];
                    }
                });

                // Set avatar preview if exists
                if (ownerData.avatar_path) {
                    avatarPreview.value = ownerData.avatar_path;
                    console.log('Avatar URL set:', ownerData.avatar_path);
                }

                // Store existing file names for display
                if (ownerData.id_front) existingFiles.value.id_front = 'Uploaded';
                if (ownerData.id_back) existingFiles.value.id_back = 'Uploaded';
                if (ownerData.visa_copy) existingFiles.value.visa_copy = 'Uploaded';
                if (ownerData.passport_copy) existingFiles.value.passport_copy = 'Uploaded';
                existingAdditionalDocuments.value = ownerData.additional_documents || [];
                ownerForm.value.additionalDocuments = [];

                // If nationality is UAE, fetch locations
                if (isUaeNationality(ownerForm.value.nationality)) {
                    await fetchLocations('resident');
                } else if (ownerForm.value.residency_status) {
                    await fetchLocations(ownerForm.value.residency_status);
                }

                console.log('Form data after population:', ownerForm.value);
                showNotification("✅ Owner data loaded successfully", "success");

            } catch (error) {
                console.error("❌ Error fetching owner:", error);
                showNotification("❌ Failed to load owner data.", "error");
                router.push('/owners');
            } finally {
                loading.value = false;
            }
        };

        // Submit owner form
        const submitForm = async () => {
            try {
                loading.value = true;
                errors.value = {};

                // Basic validation
                if (!ownerForm.value.salutation || !ownerForm.value.first_name || !ownerForm.value.last_name) {
                    showNotification("❌ Please fill in all required fields", "error");
                    loading.value = false;
                    return;
                }

                const formData = new FormData();

                // Append form data (exclude arrays and files that we handle separately)
                const skipKeys = ['additionalDocuments'];
                Object.keys(ownerForm.value).forEach(key => {
                    if (skipKeys.includes(key)) return;
                    const value = ownerForm.value[key];
                    if (value instanceof File) {
                        formData.append(key, value);
                    } else if (value !== null && value !== "" && !Array.isArray(value)) {
                        formData.append(key, value);
                    }
                });

                // Append additional documents
                if (ownerForm.value.additionalDocuments && ownerForm.value.additionalDocuments.length > 0) {
                    ownerForm.value.additionalDocuments.forEach((item, index) => {
                        const file = item.file || item;
                        if (file instanceof File) {
                            formData.append(`additional_documents[${index}]`, file);
                        }
                    });
                }

                console.log('Submitting form data...');
                let response;
                
                if (isEditMode.value) {
                    console.log('Updating owner with ID:', ownerId.value);
                    response = await api.post(`/listings/owners/${ownerId.value}`, formData, {
                        headers: { 
                            "Content-Type": "multipart/form-data",
                            "X-HTTP-Method-Override": "PUT"
                        },
                    });
                } else {
                    console.log('Creating new owner');
                    response = await api.post("/listings/owners", formData, {
                        headers: { "Content-Type": "multipart/form-data" },
                    });
                }

                console.log('Submit response:', response);
                const successMessage = isEditMode.value ? "Owner updated successfully!" : "Owner created successfully!";
                showNotification(`✅ ${successMessage}`, "success");

                // Redirect to owners list
                setTimeout(() => {
                    router.push('/owners');
                }, 1500);

            } catch (error) {
                console.error("❌ Error saving owner:", error);
                
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                    showNotification("❌ Please check the form for errors.", "error");
                } else {
                    showNotification("❌ Failed to save owner. Please try again.", "error");
                }
            } finally {
                loading.value = false;
            }
        };

        // Helper function for notifications
        const showNotification = (message, type = 'info') => {
            // Use the global notification system
            if (window.$showNotification) {
                window.$showNotification(message, type);
            } else {
                console.log(`${type}: ${message}`);
            }
        };

        // Watch for residency status changes
        watch(() => ownerForm.value.residency_status, async (newStatus) => {
            if (isUaeNationality(ownerForm.value.nationality)) return;
            
            if (newStatus) {
                await fetchLocations(newStatus);
            } else {
                locations.value = [];
                ownerForm.value.location_id = "";
            }
        });

        // Initialize component
        onMounted(() => {
            console.log('Component mounted, isEditMode:', isEditMode.value);
            if (isEditMode.value) {
                fetchOwner();
            }
        });

        return {
            isEditMode,
            loading,
            avatarPreview,
            userPlaceholder,
            ownerForm,
            errors,
            nationalities,
            locations,
            existingFiles,
            avatarInput,
            handleAvatarUpload,
            removeAvatar,
            handleImageError,
            handleNationalityChange,
            getLocationLabel,
            getLocationPlaceholder,
            handleFileUpload,
            passportOcrLoading,
            ocrStatusMessage,
            existingAdditionalDocuments,
            handleAdditionalDocumentsUpload,
            removeAdditionalDocument,
            submitForm,
            isUaeNationality,
        };
    }
};
</script>

<style scoped>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.form-text {
    font-size: 0.875rem;
}

.invalid-feedback {
    display: block;
}

.text-danger {
    font-size: 0.875rem;
}

.v-select {
    --vs-border-radius: 6px;
}

.is-invalid {
    border-color: #dc3545;
}

hr {
    border-top: 1px solid #dee2e6;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}
</style>